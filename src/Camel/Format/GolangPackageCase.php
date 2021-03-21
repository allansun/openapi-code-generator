<?php


namespace OpenAPI\CodeGenerator\Camel\Format;


use Camel\Format\FormatInterface;

class GolangPackageCase implements FormatInterface
{
    public function join(array $words): string
    {
        // Ensure words are lowercase
        $words = array_map('strtolower', $words);

        return implode('.', $words);
    }

    public function split($word): array
    {
        return explode('.', $word);
    }

}