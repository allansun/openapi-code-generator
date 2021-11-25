<?php

namespace OpenAPI\CodeGenerator\Camel\Format;

use Camel\Format\FormatInterface;

class ApiPlatformGeneratedCase implements FormatInterface
{
    public function join(array $words): string
    {

        return implode('-', $words);
    }

    public function split($word): array
    {
        $serializationGroup  = str_replace('-', '|', $word);
        $serializationGroup  = str_replace('_', '|', $serializationGroup);
        $serializationGroup  = str_replace('.', '|', $serializationGroup);
        $serializationGroups = explode('|', $serializationGroup);

        return array_map(function ($word) {
            return ucfirst($word);
        }, $serializationGroups);
    }
}
