<?php

namespace OpenAPI\CodeGenerator\Code;

use Laminas\Code\Generator\AbstractMemberGenerator;
use Laminas\Code\Generator\ClassGenerator;
use Laminas\Code\Generator\PropertyValueGenerator;
use Laminas\Code\Generator\ValueGenerator;
use OpenAPI\CodeGenerator\Config;
use OpenAPI\Runtime\AbstractAPI as BaseClass;

class AbstractAPI extends AbstractClassGenerator
{
    private static $responseTypes = [];

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
            ->addUse($this->getRootNamespace() . '\\JsonResponseHandlerStack', 'ResponseHandlerStack')
            ->addProperty('responseHandlerStack',
                new PropertyValueGenerator(
                    'ResponseHandlerStack::class',
                    ValueGenerator::TYPE_CONSTANT
                ),
                [AbstractMemberGenerator::FLAG_PROTECTED, AbstractMemberGenerator::FLAG_STATIC]);

        $this->setClass($this->ClassGenerator);

        $this->initFilename();

    }

    public function write(): AbstractClassGenerator
    {
        parent::write();

        return $this;
    }
}