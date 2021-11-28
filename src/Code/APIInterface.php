<?php

namespace OpenAPI\CodeGenerator\Code;

use Laminas\Code\Generator\InterfaceGenerator;
use OpenAPI\CodeGenerator\Config;
use OpenAPI\Runtime\APIInterface as BaseClass;

class APIInterface extends AbstractClassGenerator
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

        $this->ClassGenerator = new InterfaceGenerator();
        $this->ClassGenerator
            ->setNamespaceName($this->namespace)
            ->setName('APIInterface')
            ->addUse(BaseClass::class, 'BaseClass')
            ->setImplementedInterfaces(['BaseClass']);

        $this->setClass($this->ClassGenerator);

        $this->initFilename();

    }

    public function write(): AbstractClassGenerator
    {
        parent::write();

        return $this;
    }
}