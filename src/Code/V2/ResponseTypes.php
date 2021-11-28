<?php

namespace OpenAPI\CodeGenerator\Code\V2;

use Laminas\Code\Generator\AbstractMemberGenerator;
use Laminas\Code\Generator\ClassGenerator;
use OpenAPI\CodeGenerator\Code\AbstractClassGenerator;
use OpenAPI\CodeGenerator\Code\APIOperations;
use OpenAPI\CodeGenerator\Config;
use OpenAPI\CodeGenerator\Utility;
use OpenAPI\Runtime\ResponseTypes as RuntimeResponseTypes;
use OpenAPI\Schema\V2\Operation;
use OpenAPI\Schema\V2\Paths;
use OpenAPI\Schema\V2\Response;

class ResponseTypes extends AbstractClassGenerator
{
    private static $responseTypes = [];

    /**
     * @var Paths
     */
    private $spec;

    public function __construct(Paths $spec)
    {
        parent::__construct([]);

        $this->spec = $spec;
    }

    public function prepare(): void
    {
        $this->setNamespace($this->getRootNamespace());

        $this->ClassGenerator = new ClassGenerator();
        $this->ClassGenerator
            ->setNamespaceName($this->getRootNamespace())
            ->setName('ResponseTypes')
            ->addUse(RuntimeResponseTypes::class, 'AbstractResponseTypes')
            ->setExtendedClass('AbstractResponseTypes');

        $this->setClass($this->ClassGenerator);

        $this->initFilename();
    }

    public function write(): AbstractClassGenerator
    {
        foreach ($this->spec->getPatternedFields() as $PathItemObject) {
            foreach (APIOperations::OPERATIONS as $operation) {
                $OperationObject = $PathItemObject->$operation;
                if ($OperationObject instanceof Operation) {
                    $this->parseReseponseTypes($OperationObject);
                }
            }
        }

        $this->ClassGenerator->addProperty('types',
            self::$responseTypes,
            [AbstractMemberGenerator::FLAG_PUBLIC, AbstractMemberGenerator::FLAG_STATIC]);

        parent::write();

        return $this;
    }

    public function parseReseponseTypes(Operation $Operation)
    {
        $config         = Config::getInstance();
        $modelNamespace = $config->getOption(Config::OPTION_NAMESPACE_ROOT) .
                          '\\' .
                          $config->getOption(Config::OPTION_NAMESPACE_MODEL) .
                          '\\';

        if (!isset($Operation->responses)) {
            return;
        }

        foreach ($Operation->responses->getPatternedFields() as $statusCode => $Response) {
            /** @var Response $Response */
            if (!empty($Response->schema)) {
                if ($Response->schema->_ref) {
                    $responseType = $modelNamespace . Utility::convertV2RefToClass($Response->schema->_ref);
                } elseif ('array' == $Response->schema->type && !empty($Response->schema->items['$ref'])) {
                    $responseType =
                        $modelNamespace . Utility::convertV2RefToClass($Response->schema->items['$ref']) . '[]';
                }
                if (!empty($responseType)) {
                    self::$responseTypes[$Operation->operationId]["{$statusCode}."] = $responseType;
                }
            }
        }
    }
}