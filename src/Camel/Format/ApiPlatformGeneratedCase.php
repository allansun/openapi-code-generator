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
        $words = explode('-', $word);
        $model = $words[0];

        if (!array_key_exists(1, $words)) {
            return [$model];
        }

        $serializationGroup  = $words[1];
        $serializationGroup  = str_replace('_', '|', $serializationGroup);
        $serializationGroup  = str_replace('.', '|', $serializationGroup);
        $serializationGroups = explode('|', $serializationGroup);
        $serializationGroups = array_map(fn($word) => ucfirst($word), $serializationGroups);

        return [$model, implode('', $serializationGroups)];
    }
}
