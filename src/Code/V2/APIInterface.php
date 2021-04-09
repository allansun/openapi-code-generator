<?php


namespace OpenAPI\CodeGenerator\Code\V2;


use OpenAPI\Schema\V2\Swagger;

interface APIInterface
{
    public function __construct(string $classname, Swagger $swagger);
}