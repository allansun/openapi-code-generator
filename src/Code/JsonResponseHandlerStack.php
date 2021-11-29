<?php

namespace OpenAPI\CodeGenerator\Code;

use Laminas\Code\Generator\AbstractMemberGenerator;
use Laminas\Code\Generator\ClassGenerator;
use OpenAPI\Runtime\ResponseHandlerStack\JsonResponseHandlerStack as BaseClass;

class JsonResponseHandlerStack extends AbstractClassGenerator
{
    private static $responseTypes = [];

    public function __construct()
    {
        parent::__construct([]);
    }

    public function prepare(): void
    {
        $this->setNamespace($this->getRootNamespace());

        $this->ClassGenerator = new ClassGenerator();
        $this->ClassGenerator
            ->setNamespaceName($this->getRootNamespace())
            ->setName('JsonResponseHandlerStack')
            ->addUse(BaseClass::class, 'BaseClass')
            ->setExtendedClass('BaseClass');

        $this->setClass($this->ClassGenerator);

        $this->initFilename();

    }

    public function write(): AbstractClassGenerator
    {
        $this->ClassGenerator->addMethod('__construct', [], [AbstractMemberGenerator::FLAG_PUBLIC],
            'parent::__construct(new ResponseTypes());'
        );

        parent::write();

        return $this;
    }

}