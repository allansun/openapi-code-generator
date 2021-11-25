<?php


namespace OpenAPI\CodeGenerator\Code\V3;


use Laminas\Code\Generator\AbstractMemberGenerator;
use Laminas\Code\Generator\ClassGenerator;
use Laminas\Code\Generator\ValueGenerator;
use OpenAPI\CodeGenerator\Code\AbstractClassGenerator;
use OpenAPI\CodeGenerator\Code\APIOperations;
use OpenAPI\CodeGenerator\Config;
use OpenAPI\CodeGenerator\Utility;
use OpenAPI\Runtime\ResponseTypes as RuntimeResponseTypes;
use OpenAPI\Schema\V3\MediaType;
use OpenAPI\Schema\V3\Operation;
use OpenAPI\Schema\V3\Paths;
use OpenAPI\Schema\V3\Response;

class ResponseTypes extends AbstractClassGenerator implements ResponseTypesInterface
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

        foreach ($this->spec->getPatternedFields() as $PathItemObject) {
            foreach (APIOperations::OPERATIONS as $operation) {
                $OperationObject = $PathItemObject->$operation;
                if ($OperationObject instanceof Operation) {
                    $this->parseReseponseTypes($OperationObject);
                }
            }
        }
    }

    public function write(): AbstractClassGenerator
    {
        $this->ClassGenerator->addProperty('types',
            $this::$responseTypes,
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
            if ($Response->content && array_key_exists('application/json', $Response->content)) {
                /** @var MediaType $jsonResponse */
                $jsonResponse = $Response->content['application/json'];
                $responseType = null;
                if ($jsonResponse->schema->getPatternedField('_ref')) {
                    $responseType =
                        $modelNamespace .
                        Utility::convertV3RefToClass($jsonResponse->schema->getPatternedField('_ref'));
                } elseif ('array' == $jsonResponse->schema->type) {
                    $responseType =
                        $modelNamespace .
                        Utility::convertV3RefToClass($jsonResponse->schema->items->getPatternedField('_ref'))
                        . '[]';

                }
                self::$responseTypes[$Operation->operationId]["$statusCode."] = $responseType;
            }
        }

    }


}