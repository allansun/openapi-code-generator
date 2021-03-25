<?php


namespace OpenAPI\CodeGenerator\Code\V2;


use OpenAPI\CodeGenerator\Code\AbstractClassGenerator;
use OpenAPI\CodeGenerator\Utility;
use OpenAPI\Schema\V2\Operation;
use OpenAPI\Schema\V2\Response;
use Zend\Code\Generator\ClassGenerator;

class ResponseTypes extends AbstractClassGenerator
{
    protected $rootNamespace = 'Kubernetes';

    public $responseTypes = [];

    public function __construct()
    {
        parent::__construct([]);

        $this->setNamespace($this->rootNamespace);


        $this->ClassGenerator = new ClassGenerator();
        $this->ClassGenerator
            ->setNamespaceName($this->rootNamespace)
            ->setName('ResponseTypes');

        $this->setClass($this->ClassGenerator);

        $this->initFilename();
    }

    public function parseReseponseTypes(Operation $Operation)
    {
        foreach ((array)$Operation->responses->getPatternedFields() as $statusCode => $Response) {
            /** @var Response $Response */
            if ($Response->schema && $Response->schema->_ref) {
                $this->responseTypes[$Operation->operationId]["{$statusCode}."] =
                    '\\Kubernetes\\Model\\' . Utility::convertV2RefToClass($Response->schema->_ref);
            }
        }

    }

    public function write()
    {
        $this->ClassGenerator->addConstant('TYPES', $this->responseTypes);

        return parent::write();
    }


}