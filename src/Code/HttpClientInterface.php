<?php

namespace OpenAPI\CodeGenerator\Code;

use Laminas\Code\Generator\InterfaceGenerator;
use OpenAPI\CodeGenerator\Config;
use Psr\Http\Client\ClientInterface;

class HttpClientInterface extends AbstractClassGenerator
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
            ->setName('HttpClientInterface')
            ->addUse(ClientInterface::class, 'BaseClass')
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