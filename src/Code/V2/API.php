<?php

namespace OpenAPI\CodeGenerator\Code\V2;


use Camel\CaseTransformer;
use Camel\Format\CamelCase;
use Camel\Format\SnakeCase;
use Laminas\Code\Generator\AbstractMemberGenerator;
use Laminas\Code\Generator\ClassGenerator;
use Laminas\Code\Generator\DocBlock\Tag\ParamTag;
use Laminas\Code\Generator\DocBlock\Tag\ReturnTag;
use Laminas\Code\Generator\DocBlockGenerator;
use Laminas\Code\Generator\MethodGenerator;
use Laminas\Code\Generator\ParameterGenerator;
use OpenAPI\CodeGenerator\Code\AbstractClassGenerator;
use OpenAPI\CodeGenerator\Config;
use OpenAPI\CodeGenerator\Logger;
use OpenAPI\CodeGenerator\Utility;
use OpenAPI\Runtime\UnexpectedResponse;
use OpenAPI\Schema\V2\Header;
use OpenAPI\Schema\V2\Operation;
use OpenAPI\Schema\V2\Parameter;
use OpenAPI\Schema\V2\Response;
use OpenAPI\Schema\V2\Schema;
use OpenAPI\Schema\V2\Swagger;

class API extends AbstractClassGenerator implements APIInterface
{
    /**
     * @var Swagger
     */
    private Swagger $swagger;

    /**
     * @var string
     */
    private string $classname;

    public function __construct(string $classname, Swagger $swagger)
    {
        parent::__construct([]);

        $this->swagger   = $swagger;
        $this->classname = $classname;
    }

    /**
     * @param  Operation  $Operation
     * @param  string     $path
     * @param  string     $operation
     */
    public function parseMethod(
        Operation $Operation,
        string $path,
        string $operation
    ) {
        $apiAction = $this->parseApiAction($Operation, $this->classname);

        Logger::getInstance()->debug($this->classname . ' : ' . $apiAction . ' : ' . $Operation->operationId);

        $config     = Config::getInstance();
        $parameters = $this->parseParameters($Operation);

        $MethodGenerator   = new MethodGenerator($apiAction);
        $DocBlockGenerator = new DocBlockGenerator($Operation->description);
        $DocBlockGenerator->setWordWrap(Config::getInstance()->getOption(Config::OPTION_FORMATTING_WORD_WRAP));

        $MethodGenerator->setFlags(AbstractMemberGenerator::FLAG_PUBLIC);
        $MethodGenerator->setBody($this->generateMethodBody($Operation, $path, $operation, $parameters));

        /** @var Parameter[] $methodParameters */
        $methodParameters = $parameters[self::PARAMETER_IN_PATH];
        /** @var Parameter[] $queryParameters */
        $queryParameters = $parameters[self::PARAMETER_IN_QUERY];
        /** @var Parameter $bodyParameter */
        $bodyParameter = $parameters[self::PARAMETER_IN_BODY];
        /** @var Header[] $headerParameters */
        $headerParameters = $parameters[self::PARAMETER_IN_HEADER];
        $tags             = [];

        // Set method parameters
        if (0 < count($methodParameters)) {
            foreach ($methodParameters as $Parameter) {
                $ParameterGenerator = new ParameterGenerator($Parameter->name, $Parameter?->schema?->type,
                    $Parameter?->schema?->default);
                $MethodGenerator->setParameter($ParameterGenerator);

                $tags[] = new ParamTag($ParameterGenerator->getName(),
                    (!$ParameterGenerator->getType() ||
                     in_array($ParameterGenerator->getType(), self::$internalPhpTypes))
                        ? $ParameterGenerator->getType()
                        : $this->getUseAlias($ParameterGenerator->getType()),
                    $Parameter->description
                );
            }
        }

        // Set request body parameter
        if ($bodyParameter && !empty($bodyParameter->schema->_ref)) {
            $ParameterGenerator =
                new ParameterGenerator('Model',
                    Config::getInstance()->getModelNamespace() .
                    Utility::convertV2RefToClass($bodyParameter->schema->_ref
                    ),
                    $bodyParameter->schema->default
                );
            $MethodGenerator->setParameter($ParameterGenerator);

            $tags[] = new ParamTag(
                $ParameterGenerator->getName(),
                (!$ParameterGenerator->getType() || in_array($ParameterGenerator->getType(), self::$internalPhpTypes))
                    ? $ParameterGenerator->getType()
                    : $this->getUseAlias(Config::getInstance()->getModelNamespace() .
                                         Utility::convertV2RefToClass($bodyParameter->schema->_ref)),
                $Operation->summary . ' ' . $Operation->description
            );
        }

        // Set query parameters
        if (0 < count($queryParameters)) {
            $queryOptionsDescription = 'options:' . PHP_EOL;
            foreach ($queryParameters as $ParameterGenerator) {
                /** @var Parameter $ParameterGenerator */
                $queryOptionsDescription .= "'" . $ParameterGenerator->name . "'" . "\t" .
                                            $ParameterGenerator?->schema?->type .
                                            "\t" . $ParameterGenerator->description .
                                            PHP_EOL;
            }
            $tags[] = new ParamTag('queries', ['array'], $queryOptionsDescription);
            $MethodGenerator->setParameter(new ParameterGenerator('queries', 'array', []));
        }

        // Set header parameters
        if (0 < count($headerParameters)) {
            $headerOptionsDescription = 'options:' . PHP_EOL;
            foreach ($headerParameters as $headerParameter) {
                /** @var Parameter $headerParameter */
                $headerOptionsDescription .= "'" . $headerParameter->name . "'" . "\t" .
                                             $headerParameter?->schema?->type .
                                             "\t" . $headerParameter->description .
                                             PHP_EOL;
            }
            $tags[] = new ParamTag('headers', ['array'], $headerOptionsDescription);
            $MethodGenerator->setParameter(new ParameterGenerator('headers', 'array', []));
        }

        // Set responses
        $responseTypes = [];
        if (isset($Operation->responses)) {
            foreach ($Operation->responses->getPatternedFields() as $Response) {
                /** @var Response $Response */
                /** @var Schema $content */
                if ($Response->schema && !empty($Response->schema->_ref)) {
                    $responseTypes[$Response->schema->_ref] =
                        $this->getUseAlias(Config::getInstance()->getModelNamespace() .
                                           Utility::convertV2RefToClass($Response->schema->_ref));
                } elseif ($Response->schema &&
                          'array' == $Response->schema->type &&
                          !empty($Response->schema->items['_ref'])
                ) {
                    $responseTypes[$Response->schema->items['_ref']] =
                        $this->getUseAlias(Config::getInstance()->getModelNamespace() .
                                           Utility::convertV2RefToClass($Response->schema->items['_ref'])
                        ) . '[]';
                } elseif (!empty($Response->schema->type)) {
                    $responseTypes[$Response->schema->type] = $Response->schema->type;
                }
            }
        }

        if ('GET' == strtoupper($operation)) {
            if ($config->getOption(Config::OPTION_API_ALLOW_404_RESPONSE)) {
                $responseTypes['404'] = 'null';
            }
        }
        if ($config->getOption(Config::OPTION_API_ALLOW_ERROR_RESPONSE)) {
            $responseTypes['api-response-error'] = $this->getUseAlias(UnexpectedResponse::class);
        }

        if (0 < count($responseTypes)) {
            $tags[] = new ReturnTag(implode('|', $responseTypes));
        } else {
            $tags[] = new ReturnTag('mixed');
        }

        $DocBlockGenerator->setTags($tags);
        $MethodGenerator->setDocBlock($DocBlockGenerator);
        $this->ClassGenerator->addMethodFromGenerator($MethodGenerator);
    }

    public function prepare(): void
    {
        $config = Config::getInstance();
        $this->setNamespace(rtrim($this->getRootNamespace() . '\\' .
                                  $config->getOption(Config::OPTION_NAMESPACE_API)));

        $this->ClassGenerator = new ClassGenerator();
        $this->ClassGenerator
            ->setNamespaceName($this->namespace)
            ->setName(Utility::filterSpecialWord($this->classname))
            ->setExtendedClass('AbstractAPI');

        $this->setClass($this->ClassGenerator);

        $this->initFilename();
    }

    protected function parseApiAction(Operation $Operation, string $apiKind): string
    {
        $apiAction = $Operation->operationId;

        $Transformer = new CaseTransformer(new SnakeCase(), new CamelCase());
        $tag         = ucfirst($Transformer->transform($Operation->tags[0]));

        $apiAction = str_replace($tag, '', $apiAction);
        $apiAction = str_replace($apiKind, '', $apiAction);
        $apiAction = str_replace('Namespaced', '', $apiAction);

        if ($this->ClassGenerator->hasMethod($apiAction)) {
            $apiAction .= $tag;
        }

        return $apiAction;
    }

    /**
     * @param  Operation  $operation
     *
     * @return array[]
     */
    protected function parseParameters(
        Operation $operation
    ): array {
        $parameters = [
            self::PARAMETER_IN_PATH   => [],
            self::PARAMETER_IN_BODY   => [],
            self::PARAMETER_IN_QUERY  => [],
            self::PARAMETER_IN_HEADER => [],
            self::PARAMETER_IN_COOKIE => [],
        ];

        foreach ((array)$operation->parameters as $parameter) {
            if (array_key_exists($parameter->in, $parameters)) {
                $parameters[$parameter->in][] = $parameter;
            }
        }

        // There should only be one BODY parameter
        if (!empty($parameters[self::PARAMETER_IN_BODY])) {
            $parameters[self::PARAMETER_IN_BODY] = $parameters[self::PARAMETER_IN_BODY][0];
        }

        $parameters[self::PARAMETER_IN_PATH] = $this->sortMethodParameters($parameters[self::PARAMETER_IN_PATH]);

        return $parameters;
    }

    protected function generateMethodBody(
        Operation $Operation,
        string $path,
        string $operation,
        array $parameters
    ):
    string {
        $body             = '';
        $requestHasBody   = false;
        $requestHasQuery  = false;
        $requestHasHeader = false;

        foreach ($parameters[self::PARAMETER_IN_PATH] as $Parameter) {
            /** @var Parameter $Parameter */
            $path = str_replace('{' . $Parameter->name . '}', '$' . $Parameter->name, $path);
        }

        if ($parameters[self::PARAMETER_IN_BODY] &&
            $parameters[self::PARAMETER_IN_BODY]?->schema?->getPatternedField('_ref')) {
            $requestHasBody = true;
        }

        if (0 < count($parameters[self::PARAMETER_IN_QUERY])) {
            $requestHasQuery = true;
        }

        if (0 < count($parameters[self::PARAMETER_IN_HEADER])) {
            $requestHasHeader = true;
        }

        // Force $path to be relative url to prevent URI (not URL) being forced to absolute path
        $path = Utility::getRelativeUrl($path);

        $body .= "return \$this->request(" . PHP_EOL .
                 "'$Operation->operationId'," . PHP_EOL .
                 "'" . strtoupper($operation) . "'," . PHP_EOL .
                 "\"$path\"," . PHP_EOL .
                 ($requestHasBody ? "\$Model->getArrayCopy()" : 'null') . ',' . PHP_EOL .
                 ($requestHasQuery ? "\$queries" : '[]') . ',' . PHP_EOL .
                 ($requestHasHeader ? "\$headers" : '[]') . PHP_EOL .
                 ');' . PHP_EOL;

        return $body;
    }

    /**
     * Sort method parameters to enforce the order of ($namepsace, $name)
     *
     * @param  Parameter[]  $parameters
     *
     * @return Parameter[]
     */
    private function sortMethodParameters(array $parameters): array
    {
        $sortedParameters = [];
        $sortKeyOrder     = ['namespace', 'name'];
        //Find items by expected name and put them into $sortedParameters in order
        foreach ($sortKeyOrder as $sortKey) {
            foreach ($parameters as $key => $Parameter) {
                if ($sortKey == $Parameter->name) {
                    $sortedParameters[] = $Parameter;
                    unset($parameters[$key]);
                }
            }
        }

        //Put remaining items into $sortedParameters
        foreach ($parameters as $Parameter) {
            $sortedParameters[] = $Parameter;
        }

        return $sortedParameters;
    }

}
