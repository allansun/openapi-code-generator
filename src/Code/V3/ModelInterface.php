<?php


namespace OpenAPI\CodeGenerator\Code\V3;


use OpenAPI\Schema\V3\Schema;

interface ModelInterface
{
    public function __construct(string $className, Schema $spec);
}