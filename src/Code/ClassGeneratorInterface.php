<?php


namespace OpenAPI\CodeGenerator\Code;


interface ClassGeneratorInterface
{
    /**
     * All classes should use prepare() to parse according specs and create according class generators
     */
    public function prepare():void;
}