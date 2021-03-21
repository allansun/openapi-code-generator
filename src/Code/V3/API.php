<?php

namespace OpenAPI\CodeGenerator\Code\V3;


use Camel\CaseTransformer;
use Camel\Format\CamelCase;
use Camel\Format\SnakeCase;
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
use OpenAPI\Schema\V3\MediaType;
use OpenAPI\Schema\V3\Operation;
use OpenAPI\Schema\V3\Parameter;
use OpenAPI\Schema\V3\PathItem;
use OpenAPI\Schema\V3\Response;

class API extends AbstractClassGenerator implements APIInterface
{
    const PARAMETER_IN_PATH = 'path';
    const PARAMETER_IN_QUERY = 'query';
    const PARAMETER_IN_BODY = 'body';
    const PARAMETER_IN_HEADER = 'header';
    const PARAMETER_IN_COOKIE = 'cookie';

    private PathItem $spec;
    private string $classname;

    public function __construct(string $classname, PathItem $spec)
    {
        parent::__construct([]);

        $this->spec      = $spec;
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


        $parameters = $this->parseParameters($Operation);

        $MethodGenerator   = new MethodGenerator($apiAction);
        $DocBlockGenerator = new DocBlockGenerator($Operation->description);

        $MethodGenerator->setFlags(MethodGenerator::FLAG_PUBLIC);
        $MethodGenerator->setBody($this->generateMethodBody($Operation, $path, $operation, $parameters));

        /** @var Parameter[] $methodParameters */
        $methodParameters = $parameters[self::PARAMETER_IN_PATH];
        /** @var Parameter[] $queryParameters */
        $queryParameters = $parameters[self::PARAMETER_IN_QUERY];
        /** @var MediaType $bodyParameters */
        $bodyParameters = $parameters[self::PARAMETER_IN_BODY];
        $tags           = [];

        // Set method parameters
        if (0 < count($methodParameters)) {
            foreach ($methodParameters as $Parameter) {
                $ParameterGenerator = new ParameterGenerator($Parameter->name, $Parameter->schema->type,
                    $Parameter->schema->default);
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
        if ($bodyParameters
            && property_exists($bodyParameters, 'schema')
            && $bodyParameters->schema->getPatternedField('_ref')) {
            $ParameterGenerator =
                new ParameterGenerator('Model',
                    Config::getInstance()->getModelNamespace() .
                    Utility::convertV3RefToClass($bodyParameters->schema->getPatternedField('_ref')
                    ),
                    $bodyParameters->schema->default
                );
            $MethodGenerator->setParameter($ParameterGenerator);

            $tags[] = new ParamTag(
                $ParameterGenerator->getName(),
                (!$ParameterGenerator->getType() || in_array($ParameterGenerator->getType(), self::$internalPhpTypes))
                    ? $ParameterGenerator->getType()
                    : $this->getUseAlias(Config::getInstance()->getModelNamespace() .
                                         Utility::convertV3RefToClass($bodyParameters->schema->getPatternedField('_ref'))),
                $Operation->requestBody->description
            );
        }

        // Set query parameters
        if (0 < count($queryParameters)) {
            $queryOptionsDescription = 'options:' . PHP_EOL;
            foreach ($queryParameters as $ParameterGenerator) {
                /** @var Parameter $ParameterGenerator */
                $queryOptionsDescription .= "'" . $ParameterGenerator->name . "'" . "\t" .
                                            $ParameterGenerator->schema->type .
                                            "\t" . $ParameterGenerator->description .
                                            PHP_EOL;
            }
            $tags[] = new ParamTag('queries', ['array'], $queryOptionsDescription);
            $MethodGenerator->setParameter(new ParameterGenerator('queries', 'array', []));
        }

        // Set responses
        $responseTypes = [];
        foreach ($Operation->responses->getPatternedFields() as $responseStatus => $Response) {
            /** @var Response $Response */
            foreach ((array)$Response->content as $contentType => $content) {
                if ('application/json' != $contentType) {
                    continue;
                }
                /** @var MediaType $content */
                if ($content->schema && $content->schema->getPatternedField('_ref')) {
                    $responseTypes[$content->schema->getPatternedField('_ref')] =
                        $this->getUseAlias(Config::getInstance()->getModelNamespace() .
                                           Utility::convertV3RefToClass($content->schema->getPatternedField('_ref')));
                } elseif ($content->schema &&
                          'array' == $content->schema->type &&
                          $content->schema->items->getPatternedField('_ref')
                ) {
                    $responseTypes[$content->schema->items->getPatternedField('_ref')] =
                        $this->getUseAlias(Config::getInstance()->getModelNamespace() .
                                           Utility::convertV3RefToClass($content->schema->items->getPatternedField('_ref'))
                        ) . '[]';
                } else {
                    $responseTypes[$content->schema->type] = $content->schema->type;
                }
            }
        }

        $tags[] = new ReturnTag(implode('|', $responseTypes));

        $DocBlockGenerator->setTags($tags);
        $MethodGenerator->setDocBlock($DocBlockGenerator);
        $this->ClassGenerator->addMethodFromGenerator($MethodGenerator);
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
    ) {
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

        $parameters[self::PARAMETER_IN_PATH] = $this->sortMethodParameters($parameters[self::PARAMETER_IN_PATH]);

        if (isset($operation->requestBody) && property_exists($operation->requestBody, 'content')) {
            foreach ((array)$operation->requestBody->content as $type => $content) {
                if ('application/json' != $type) {
                    continue;
                }
                $parameters[self::PARAMETER_IN_BODY] = $content;
            }
        }

        return $parameters;
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

    protected function generateMethodBody(
        Operation $Operation,
        string $path,
        string $operation,
        array $parameters
    ):
    string {
        $body               = '';
        $queryParameterBody = "\t[" . PHP_EOL;


        foreach ($parameters[self::PARAMETER_IN_BODY] as $Parameter) {
            if ($Parameter && property_exists($Parameter, 'schema')) {
                $queryParameterBody .= "\t\t'json' => \$Model->getArrayCopy()," . PHP_EOL;
            }
        }

        foreach ($parameters[self::PARAMETER_IN_PATH] as $Parameter) {
            /** @var Parameter $Parameter */
            $path = str_replace('{' . $Parameter->name . '}', '{$' . $Parameter->name . '}', $path);
        }


        if (0 < count($parameters[self::PARAMETER_IN_QUERY])) {
            $queryParameterBody .= "\t\t'query' => \$queries," . PHP_EOL;
        }
        $queryParameterBody .= "\t]" . PHP_EOL;

        $body .= "return \$this->client->request('{$Operation->operationId}','{$operation}',\"{$path}\"," . PHP_EOL;
        $body .= $queryParameterBody;
        $body .= ");" . PHP_EOL;

        return $body;
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
            ->addUse(Config::getInstance()->getOption(Config::OPTION_API_BASE_CLASS), 'AbstractAPI')
            ->setExtendedClass('AbstractAPI');

        $this->setClass($this->ClassGenerator);

        $this->initFilename();
    }

}