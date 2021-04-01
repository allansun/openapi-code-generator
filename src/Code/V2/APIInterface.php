<?php


namespace OpenAPI\CodeGenerator\Code\V2;


use OpenAPI\Schema\V2\PathItem;

interface APIInterface
{
    public function __construct(string $classname, PathItem $spec);
}