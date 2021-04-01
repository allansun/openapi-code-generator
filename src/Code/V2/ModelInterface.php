<?php


namespace OpenAPI\CodeGenerator\Code\V2;


use OpenAPI\Schema\V2\Schema;

interface ModelInterface
{
    public function __construct(string $className, Schema $spec);
}