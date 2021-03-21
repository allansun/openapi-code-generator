<?php


namespace OpenAPI\CodeGenerator\Code\V3;


use OpenAPI\Schema\V3\PathItem;

interface APIInterface
{
    public function __construct(string $classname, PathItem $spec);
}