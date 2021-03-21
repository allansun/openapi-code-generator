<?php

namespace OpenAPI\CodeGenerator;

use Exception;
use OpenAPI\CodeGenerator\Code\CodeGeneratorInterface;
use OpenAPI\Runtime\AbstractAPI;
use OpenAPI\Runtime\AbstractModel;
use OpenAPI\Runtime\ResponseTypes;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Config
{
    public const DEFAULT = 'DEFAULT';

    public const OPTION_ROOT_SOURCE_DIR = 'ROOT_SOURCE_DIR';

    public const OPTION_NAMESPACE_ROOT = 'ROOT_NAMESPACE';
    public const OPTION_NAMESPACE_API = 'API_NAMESPACE';
    public const OPTION_NAMESPACE_MODEL = 'MODEL_NAMESPACE';

    public const OPTION_API_BASE_CLASS = 'API_BASE_CLASS';
    public const OPTION_API_GENERATOR_CLASS = 'API_GENERATOR_CLASS';

    public const OPTION_MODEL_BASE_CLASS = 'MODEL_BASE_CLASS';
    public const OPTION_MODEL_GENERATOR_CLASS = 'MODEL_GENERATOR_CLASS';

    public const OPTION_RESPONSE_TYPES_BASE_CLASS = 'RESPONSE_TYPES_BASE_CLASS';
    public const OPTION_RESPONSE_TYPES_GENERATOR_CLASS = 'RESPONSE_TYPES_GENERATOR_CLASS';

    public const OPTION_CODE_GENERATOR_CLASS = 'CODE_GENERATOR_CLASS';

    private static ?Config $instance = null;
    private array $options;

    public function __construct(array $options = [])
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);

        $this->options = $resolver->resolve($options);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            self::OPTION_ROOT_SOURCE_DIR => __DIR__ . '/../generated/',

            self::OPTION_NAMESPACE_ROOT  => 'App',
            self::OPTION_NAMESPACE_API   => 'Api',
            self::OPTION_NAMESPACE_MODEL => 'Model',

            self::OPTION_API_BASE_CLASS      => AbstractAPI::class,
            self::OPTION_API_GENERATOR_CLASS => self::DEFAULT,

            self::OPTION_MODEL_BASE_CLASS      => AbstractModel::class,
            self::OPTION_MODEL_GENERATOR_CLASS => self::DEFAULT,

            self::OPTION_RESPONSE_TYPES_BASE_CLASS      => ResponseTypes::class,
            self::OPTION_RESPONSE_TYPES_GENERATOR_CLASS => self::DEFAULT,

            self::OPTION_CODE_GENERATOR_CLASS => null,
        ])->setAllowedValues(self::OPTION_CODE_GENERATOR_CLASS, function ($values) {
            return null == $values || ($values instanceof CodeGeneratorInterface);
        });
    }

    public static function getInstance($options = []): Config
    {
        if (!self::$instance) {
            self::$instance = new self($options);
        }

        return self::$instance;
    }

    public function getAPINamespace(): string
    {
        return $this->getOption(self::OPTION_NAMESPACE_ROOT) . '\\' . $this->getOption(self::OPTION_NAMESPACE_API) .
               '\\';
    }

    public function getOption($option)
    {
        if (!key_exists($option, $this->options)) {
            throw new Exception(sprintf('Unknown option [%s]', $option));
        }

        return $this->options[$option];
    }

    public function getModelNamespace(): string
    {
        return $this->getOption(self::OPTION_NAMESPACE_ROOT) . '\\' . $this->getOption(self::OPTION_NAMESPACE_MODEL) .
               '\\';
    }

    public function getResponseTypesNamespace(): string
    {
        return $this->getOption(self::OPTION_NAMESPACE_ROOT) . '\\';
    }
}
