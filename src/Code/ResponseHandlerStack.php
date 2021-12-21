<?php

namespace OpenAPI\CodeGenerator\Code;

use Laminas\Code\Generator\AbstractMemberGenerator;
use Laminas\Code\Generator\ClassGenerator;
use OpenAPI\CodeGenerator\Config;
use OpenAPI\Runtime\ResponseHandler\Allow404ResponseStatusHandler;
use OpenAPI\Runtime\ResponseHandler\GenericResponseHandler;
use OpenAPI\Runtime\ResponseHandler\JsonResponseHandler;
use OpenAPI\Runtime\ResponseHandler\UnexpectedResponseHandler;
use OpenAPI\Runtime\ResponseHandlerStack\ResponseHandlerStack as BaseClass;

class ResponseHandlerStack extends AbstractClassGenerator
{
    public function prepare(): void
    {
        $this->setNamespace($this->getRootNamespace());

        $this->ClassGenerator = new ClassGenerator();
        $this->ClassGenerator
            ->setNamespaceName($this->getRootNamespace())
            ->setName('ResponseHandlerStack')
            ->addUse(BaseClass::class, 'BaseClass')
            ->setExtendedClass('BaseClass');
        $this->setClass($this->ClassGenerator);

        $this->initFilename();

    }

    public function write(): ClassGeneratorInterface
    {
        $config = Config::getInstance();
        $body   = '';

        $this->ClassGenerator->addUse(GenericResponseHandler::class);
        $body .= <<<EOF
\$handlers[] = new GenericResponseHandler();
EOF;

        $this->ClassGenerator->addUse(JsonResponseHandler::class);
        $body .= <<<EOF
\$jsonResponsHandler = new JsonResponseHandler();
\$jsonResponsHandler->setResponseTypes(new ResponseTypes());
\$handlers[] = \$jsonResponsHandler;
EOF;

        if ($config->getOption(Config::OPTION_API_ALLOW_ERROR_RESPONSE)) {
            $this->ClassGenerator->addUse(UnexpectedResponseHandler::class);
            $body .= <<<EOF
\$handlers[] = new UnexpectedResponseHandler();
EOF;
        }

        if ($config->getOption(Config::OPTION_API_ALLOW_404_RESPONSE)) {
            $this->ClassGenerator->addUse(Allow404ResponseStatusHandler::class);
            $body .= <<<EOF
\$handlers[] = new Allow404ResponseStatusHandler();
EOF;
        }

        $body .= 'parent::__construct($handlers);';

        $this->ClassGenerator->addMethod('__construct', [], [AbstractMemberGenerator::FLAG_PUBLIC], $body);
        parent::write();

        return $this;
    }

}