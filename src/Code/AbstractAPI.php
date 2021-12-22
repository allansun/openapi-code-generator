<?php

namespace OpenAPI\CodeGenerator\Code;

use Laminas\Code\Generator\AbstractMemberGenerator;
use Laminas\Code\Generator\ClassGenerator;
use Laminas\Code\Generator\ParameterGenerator;
use Laminas\Code\Generator\PropertyGenerator;
use Laminas\Code\Generator\PropertyValueGenerator;
use Laminas\Code\Generator\TypeGenerator;
use Laminas\Code\Generator\ValueGenerator;
use OpenAPI\CodeGenerator\Config;
use OpenAPI\Runtime\AbstractAPI as BaseClass;
use OpenAPI\Runtime\ResponseHandlerStack\ResponseHandlerStackInterface;

class AbstractAPI extends AbstractClassGenerator
{

    public function __construct()
    {
        parent::__construct([]);
    }

    public function prepare(): void
    {
        $config = Config::getInstance();
        $this->setNamespace(rtrim($this->getRootNamespace() . '\\' .
                                  $config->getOption(Config::OPTION_NAMESPACE_API)));

        $this->ClassGenerator = new ClassGenerator();
        $this->ClassGenerator
            ->setNamespaceName($this->namespace)
            ->setName('AbstractAPI')
            ->addUse(BaseClass::class, 'BaseClass')
            ->setExtendedClass('BaseClass')
            ->setImplementedInterfaces(['APIInterface'])
            ->addUse($this->getRootNamespace() . '\\ResponseHandlerStack')
            ->addUse($this->namespace . '\\HttpClientInterface')
            ->addUse(ResponseHandlerStackInterface::class)
            ->addPropertyFromGenerator(new PropertyGenerator('responseHandlerStack', null,
                [
                    AbstractMemberGenerator::FLAG_PROTECTED,
                    AbstractMemberGenerator::FLAG_STATIC,
                ],
                $config->getOption(Config::OPTION_TARGET_PHP_VERION) >= 8.0 ?
                    TypeGenerator::fromTypeString('ResponseHandlerStackInterface|string') :
                    null
            ));

        $this->setClass($this->ClassGenerator);

        $this->ClassGenerator->addMethod(
            '__construct',
            [
                new ParameterGenerator('client', '?' . $this->namespace . '\\HttpClientInterface',
                    new ValueGenerator('null', ValueGenerator::TYPE_CONSTANT))
            ],
            [AbstractMemberGenerator::FLAG_PUBLIC],
            '$this->responseHandlerStack = ResponseHandlerStack::class;' .
            'parent::__construct($client);'
        );

        $this->initFilename();

    }

    public function write(): AbstractClassGenerator
    {
        parent::write();

        return $this;
    }
}