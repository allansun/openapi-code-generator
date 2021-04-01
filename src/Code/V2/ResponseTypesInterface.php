<?php


namespace OpenAPI\CodeGenerator\Code\V2;


use OpenAPI\Schema\V2\Paths;

interface ResponseTypesInterface
{
    public function __construct(Paths $spec);
}