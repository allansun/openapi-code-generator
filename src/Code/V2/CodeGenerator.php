<?php


namespace OpenAPI\CodeGenerator\Code\V2;

use OpenAPI\CodeGenerator\Code\APIOperations;
use OpenAPI\CodeGenerator\Code\CodeGeneratorInterface;
use OpenAPI\CodeGenerator\Config;
use OpenAPI\CodeGenerator\Logger;
use OpenAPI\Schema\V2 as Schema;

class CodeGenerator implements CodeGeneratorInterface
{
    /**
     * @var Config
     */
    protected $config;
    /**
     * @var Schema\Swagger
     */
    private $spec;

    public function __construct($spec)
    {
        $this->spec   = $spec;
        $this->config = Config::getInstance();
    }


    public function generateApis()
    {
        /** @var Api[] $classGenerators */
        $classGenerators = [];

        $class = $this->config->getOption(Config::OPTION_API_GENERATOR_CLASS);
        $class = Config::DEFAULT == $class ? API::class : $class;

        foreach ($this->spec->paths->getPatternedFields() as $path => $pathItem) {
            foreach (APIOperations::OPERATIONS as $operationMethod) {
                $operation = $pathItem->$operationMethod;
                if ($operation instanceof Schema\Operation) {
                    // We assume first tag should be the Model this API operates on,this is mandatory.
                    // TODO: If this is not the case, a customized parsing method should be used.
                    $classname = $operation->tags[0];
                    $classname = $classname ?? 'API';
                    if (array_key_exists($classname, $classGenerators)) {
                        $classGenerator = $classGenerators[$classname];
                    } else {
                        $classGenerator = new $class($classname, $this->spec);
                        $classGenerator->prepare();
                        $classGenerators[$classname] = $classGenerator;
                    }
                    $classGenerator->parseMethod($operation, $path, $operationMethod);
                }
            }
        }

        foreach ($classGenerators as $classGenerator) {
            $classGenerator->write();
            Logger::getInstance()->debug($classGenerator->getFilename());
        }
    }

    public function generateModels()
    {
        $class = $this->config->getOption(Config::OPTION_MODEL_GENERATOR_CLASS);
        $class = Config::DEFAULT == $class ? Model::class : $class;

        foreach ($this->spec->definitions->getPatternedFields() as $name => $Schema) {
            //Filter out JsonLD (maybe we should implement this in the future?)
            if (false !== strpos($name, '.jsonld')) {
                continue;
            }
            $classGenerator = new $class($name, $Schema);
            $classGenerator->prepare();
            $classGenerator->write();
            Logger::getInstance()->debug($classGenerator->getFilename());
        }
    }


    public function generateResponseTypes()
    {
        $class = $this->config->getOption(Config::OPTION_MODEL_GENERATOR_CLASS);
        $class = Config::DEFAULT == $class ? ResponseTypes::class : $class;

        $classGenerator = new $class($this->spec->paths);
        $classGenerator->prepare();
        $classGenerator->write();
        Logger::getInstance()->debug($classGenerator->getFilename());
    }
}