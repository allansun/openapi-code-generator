<?php


namespace OpenAPI\CodeGenerator;


use Camel\CaseTransformer;
use Camel\CaseTransformerInterface;
use OpenAPI\CodeGenerator\Camel\Format\ApiPlatformGeneratedCase;
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
            self::$caseTransformer = new CaseTransformer(new ApiPlatformGeneratedCase(), new PhpPackageCase());
        }

        if (preg_match('/^[\d]{3}.*/', $definition)) {
            // If the definition name starts with 3 digits, we assume it is a HTTP status code
            // Because PHP dose not allow class name to start with numbers, we prefix a wording in front
            $definition = 'Status' . $definition;
        } elseif (preg_match('/^[\d]+.*/', $definition)) {
            // For other unkown circumstances should a definition begins with number,
            // we just add 'Model' to the beginning
            $definition = 'Model' . $definition;
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