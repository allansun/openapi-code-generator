<?php

namespace OpenAPI\CodeGenerator\Code\V2;


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
use OpenAPI\Schema\V2\Operation;
use OpenAPI\Schema\V2\Parameter;
use OpenAPI\Schema\V2\PathItem;
use OpenAPI\Schema\V2\Response;
use OpenAPI\Schema\V2\Schema;

class API extends AbstractClassGenerator implements APIInterface
{
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
        /** @var Parameter $bodyParameter */
        $bodyParameter = $parameters[self::PARAMETER_IN_BODY];
        $tags          = [];

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

        if (0 < count($responseTypes)) {
            $tags[] = new ReturnTag(implode('|', $responseTypes));
        } else {
            $tags[] = new ReturnTag('mixed');
        }

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


        if (!empty($parameters[self::PARAMETER_IN_BODY])) {
            $Parameter = $parameters[self::PARAMETER_IN_BODY];
            /** @var Parameter $Parameter */
            if (!empty($Parameter->schema->_ref)) {
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