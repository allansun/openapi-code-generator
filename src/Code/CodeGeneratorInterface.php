<?php


namespace OpenAPI\CodeGenerator\Code;


use OpenAPI\Schema\V2\Swagger;
use OpenAPI\Schema\V3\OpenAPI;

interface CodeGeneratorInterface
{
    /**
     * CodeGeneratorInterface constructor.
     *
     * @param  OpenAPI|Swagger  $spec
     */
    public function __construct($spec);

    public function generateApis();

    public function generateModels();

    public function generateResponseTypes();

    public function generateCommonFiles();
}