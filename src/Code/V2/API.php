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
use OpenAPI\CodeGenerator\Utility;
use OpenAPI\Schema\V2\Operation;
use OpenAPI\Schema\V2\Parameter;
use OpenAPI\Schema\V2\Response;

class API extends AbstractClassGenerator
{
    const PARAMETER_IN_PATH = 'path';
    const PARAMETER_IN_QUERY = 'query';
    const PARAMETER_IN_BODY = 'body';
    protected $rootNamespace = 'Kubernetes\\API';

    public function __construct(string $classname)
    {
        parent::__construct([]);

        $this->setNamespace($this->rootNamespace);


        $this->ClassGenerator = new ClassGenerator();
        $this->ClassGenerator
            ->setNamespaceName($this->rootNamespace)
            ->setName(Utility::filterSpecialWord($classname))
            ->addUse('\KubernetesRuntime\AbstractAPI')
            ->setExtendedClass('AbstractAPI');

        $this->setClass($this->ClassGenerator);

        $this->initFilename();
    }

    /**
     * @param  Operation    $Operation
     * @param  string             $path
     * @param  string             $operation
     * @param  Parameter[]  $pathItemParameters
     */
    public function parseMethod(
        Operation $Operation,
        string $path,
        string $operation,
        array $pathItemParameters =
        []
    ) {
        $apiKind   = $Operation->getPatternedFields()
                     [KubernetesExtentions::GROUP_VERSION_KIND][KubernetesExtentions::KIND];
        $apiAction = $this->parseApiAction($Operation, $apiKind);

        echo $apiKind . ' : ' . $apiAction . ' : ' . $Operation->operationId . PHP_EOL;

        $parameters = $this->parseParameters($Operation, $pathItemParameters);

        $MethodGenerator   = new MethodGenerator($apiAction);
        $DocBlockGenerator = new DocBlockGenerator($Operation->description);

        $MethodGenerator->setFlags(MethodGenerator::FLAG_PUBLIC);
        $MethodGenerator->setBody($this->generateMethodBody($Operation, $path, $operation, $parameters));

        /** @var Parameter[] $methodParameters */
        $methodParameters = $parameters[self::PARAMETER_IN_PATH];
        /** @var Parameter[] $payloadParameters */
        $payloadParameters = $parameters[self::PARAMETER_IN_BODY];
        /** @var Parameter[] $queryParameters */
        $queryParameters = $parameters[self::PARAMETER_IN_QUERY];
        $tags            = [];


        if (0 < count($methodParameters)) {
            foreach ($methodParameters as $Parameter) {
                $ParameterGenerator = new ParameterGenerator($Parameter->name, $Parameter->type, $Parameter->default);
                if ('namespace' == $Parameter->name) {
                    $ParameterGenerator->setPosition(1);
                }
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

        if (0 < count($payloadParameters)) {
            foreach ($payloadParameters as $Parameter) {
                if ($Parameter->schema) {
                    $ParameterGenerator =
                        new ParameterGenerator('Model',
                            '\\Kubernetes\\Model\\' . Utility::convertV2RefToClass($Parameter->schema->_ref)
                            , $Parameter->type, $Parameter->default
                        );
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
        }

        if (0 < count($queryParameters)) {
            $queryOptionsDescription = 'options:' . PHP_EOL;
            foreach ($queryParameters as $ParameterGenerator) {
                /** @var Parameter $ParameterGenerator */
                $queryOptionsDescription .= "'" . $ParameterGenerator->name . "'" . "\t" . $ParameterGenerator->type .
                                            PHP_EOL . $ParameterGenerator->description .
                                            PHP_EOL;
            }
            $tags[] = new ParamTag('queries', ['array'], $queryOptionsDescription);
            $MethodGenerator->setParameter(new ParameterGenerator('queries', 'array', []));
        }


        $responseTypes = [];
        foreach ($Operation->responses->getPatternedFields() as $Response) {
            /** @var Response $Response */
            if ($Response->schema) {
                if ($Response->schema->_ref) {
                    $responseTypes[$Response->schema->_ref] =
                        $this->getUseAlias('\\Kubernetes\\Model\\' .
                                           Utility::convertV2RefToClass($Response->schema->_ref));
                } else {
                    $responseTypes[$Response->schema->type] = $Response->schema->type;
                }
            }
        }

        $tags[] = new ReturnTag(implode('|', $responseTypes) . '|mixed');

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
     * @param  Operation    $Operation
     * @param  Parameter[]  $pathItemParameters
     *
     * @return array[]
     */
    protected function parseParameters(
        Operation $Operation,
        array $pathItemParameters = []
    ) {
        $parameters = [
            self::PARAMETER_IN_PATH  => [],
            self::PARAMETER_IN_BODY  => [],
            self::PARAMETER_IN_QUERY => [],
        ];

        foreach ((array)$Operation->parameters as $Parameter) {
            $this->parseParameter($parameters, $Parameter);
        }

        foreach ((array)$pathItemParameters as $Parameter) {
            $this->parseParameter($parameters, $Parameter);
        }

        $parameters[self::PARAMETER_IN_PATH] = $this->sortMethodParameters($parameters[self::PARAMETER_IN_PATH]);
        return $parameters;
    }

    private function parseParameter(&$parameters, $Parameter)
    {
        switch ($Parameter->in) {
            case self::PARAMETER_IN_QUERY:
                $parameters[self::PARAMETER_IN_QUERY][] = $Parameter;
                break;

            case self::PARAMETER_IN_BODY:
                $parameters[self::PARAMETER_IN_BODY][] = $Parameter;
                break;

            case self::PARAMETER_IN_PATH:
                $parameters[self::PARAMETER_IN_PATH][] = $Parameter;
                break;
        }
    }

    protected function generateMethodBody(
        Operation $Operation,
        string $path,
        string $operation,
        array $parameters
    ):
    string {
        $body               = '';
        $queryParameterBody = "\t\t[" . PHP_EOL;

        foreach ($parameters[self::PARAMETER_IN_BODY] as $Parameter) {
            if ($Parameter->schema) {
                $queryParameterBody .= "\t\t\t'json' => \$Model->getArrayCopy()," . PHP_EOL;
            }
        }

        foreach ($parameters[self::PARAMETER_IN_PATH] as $Parameter) {
            /** @var Parameter $Parameter */
            $path = str_replace('{' . $Parameter->name . '}', '{$' . $Parameter->name . '}', $path);
        }


        if (0 < count($parameters[self::PARAMETER_IN_QUERY])) {
            $queryParameterBody .= "\t\t\t'query' => \$queries," . PHP_EOL;
        }
        $queryParameterBody .= "\t\t]" . PHP_EOL;

        $body .= 'return $this->parseResponse(' . PHP_EOL;
        $body .= "\t" . '$this->client->request(\'' . $operation . '\',' . PHP_EOL;
        $body .= "\t\t" . "\"{$path}\"," . PHP_EOL;
        $body .= $queryParameterBody;
        $body .= "\t)," . PHP_EOL;
        $body .= "\t'{$Operation->operationId}'" . PHP_EOL;
        $body .= ');';

        return $body;
    }

    /**
     * Sort method parameters to enforce the order of ($namepsace, $name)
     *
     * @param  Parameter[]  $parameters
     *
     * @return Parameter[]
     */
    private function sortMethodParameters(array $parameters){
        $sortedParameters=[];
        $sortKeyOrder=['namespace','name'];
        //Find items by expected name and put them into $sortedParameters in order
        foreach($sortKeyOrder as $sortKey) {
            foreach ($parameters as $key => $Parameter) {
                if($sortKey == $Parameter->name){
                    $sortedParameters[] = $Parameter;
                    unset($parameters[$key]);
                }
            }
        }

        //Put remaining items into $sortedParameters
        foreach($parameters as $Parameter){
            $sortedParameters[] = $Parameter;
        }
        return $sortedParameters;
    }

}