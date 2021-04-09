<?php


namespace OpenAPI\CodeGenerator\Code\V3;


use OpenAPI\Schema\V3\OpenAPI;

interface APIInterface
{
    public function __construct(string $classname, OpenAPI $openAPI);
}