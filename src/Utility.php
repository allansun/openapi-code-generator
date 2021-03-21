<?php


namespace OpenAPI\CodeGenerator;


use Camel\CaseTransformer;
use Camel\CaseTransformerInterface;
use OpenAPI\CodeGenerator\Camel\Format\GolangPackageCase;
use OpenAPI\CodeGenerator\Camel\Format\PhpPackageCase;

final class Utility
{
    static private ?CaseTransformerInterface $caseTransformer = null;

    public static function setCaseTransformer(CaseTransformerInterface $caseTransformer): void
    {
        self::$caseTransformer = $caseTransformer;
    }

    static function convertV2RefToClass(string $ref): string
    {
        $ref = str_replace('#/definitions/', '', $ref);

        return self::convertDefinitionToClass($ref);

    }

    static function convertDefinitionToClass(string $definition): string
    {
        if (!self::$caseTransformer) {
            self::$caseTransformer = new CaseTransformer(new GolangPackageCase(), new PhpPackageCase());
        }

        return self::$caseTransformer->transform($definition);
    }

    static function convertV3RefToClass(string $ref): string
    {
        $ref = str_replace('#/components/schemas/', '', $ref);

        return self::convertDefinitionToClass($ref);

    }

    static function parseClassInfo(string $fullClass): array
    {
        $classInfo = explode('\\', $fullClass);
        $className = array_pop($classInfo);

        return [implode('\\', $classInfo), $className];
    }

    static function filterSpecialWord(string $word): string
    {
        // Convert '-' to '_' to follow PHP language naming convention
        $word = str_replace('-', '_', $word);

        // Change 'Namespace' package to 'TheNamespace', because 'namespace' is a PHP reservced keyword
        $word = 'namespace' == strtolower($word) ? 'The' . ucfirst($word) : ucfirst($word);

        return $word;
    }

}