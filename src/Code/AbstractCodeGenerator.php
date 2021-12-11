<?php

namespace OpenAPI\CodeGenerator\Code;

use OpenAPI\CodeGenerator\Code\AbstractAPI as AbstractAPIGenerator;
use OpenAPI\CodeGenerator\Code\APIInterface as APIInterfaceGenerator;
use OpenAPI\CodeGenerator\Config;
use OpenAPI\CodeGenerator\Logger;
use OpenAPI\Schema\V2\Swagger;
use OpenAPI\Schema\V3\OpenAPI;

abstract class AbstractCodeGenerator implements CodeGeneratorInterface
{
    /**
     * @var Config
     */
    protected Config $config;

    /**
     * CodeGeneratorInterface constructor.
     *
     * @param  OpenAPI|Swagger  $spec
     */
    public abstract function __construct(OpenAPI|Swagger $spec);

    public abstract function generateApis();

    public abstract function generateModels();

    public abstract function generateResponseTypes();

    public function generateCommonFiles()
    {
        $class = $this->config->getOption(Config::OPTION_RESPONSE_HANDLER_STACK_GENERATOR_CLASS);
        $class = Config::DEFAULT == $class ? ResponseHandlerStack::class : $class;

        $classGenerator = new $class();
        $classGenerator->prepare();
        $classGenerator->write();
        Logger::getInstance()->debug($classGenerator->getFilename());

        $APIInterfaceGenerator = new APIInterfaceGenerator();
        $APIInterfaceGenerator->prepare();
        $APIInterfaceGenerator->write();
        Logger::getInstance()->debug($APIInterfaceGenerator->getFilename());

        $AbstractAPIGenerator = new AbstractAPIGenerator();
        $AbstractAPIGenerator->prepare();
        $AbstractAPIGenerator->write();
        Logger::getInstance()->debug($AbstractAPIGenerator->getFilename());

        $HttpClientInterfaceGenerator = new HttpClientInterface();
        $HttpClientInterfaceGenerator->prepare();
        $HttpClientInterfaceGenerator->write();
        Logger::getInstance()->debug($HttpClientInterfaceGenerator->getFilename());
    }
}
