<?php


namespace OpenAPI\CodeGenerator\Code\V3;


use OpenAPI\Schema\V3\Paths;

interface ResponseTypesInterface
{
    public function __construct(Paths $spec);
}