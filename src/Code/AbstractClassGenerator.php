<?php


namespace OpenAPI\CodeGenerator\Code;


use Laminas\Code\Generator\ClassGenerator;
use Laminas\Code\Generator\DocBlock\Tag\GenericTag;
use Laminas\Code\Generator\DocBlockGenerator;
use Laminas\Code\Generator\FileGenerator;
use OpenAPI\CodeGenerator\Config;
use Symfony\Component\Filesystem\Filesystem;

abstract class AbstractClassGenerator extends FileGenerator implements ClassGeneratorInterface
{
    const PARAMETER_IN_PATH = 'path';
    const PARAMETER_IN_QUERY = 'query';
    const PARAMETER_IN_BODY = 'body';
    const PARAMETER_IN_HEADER = 'header';
    const PARAMETER_IN_COOKIE = 'cookie';

    protected static array $internalPhpTypes = [
        'void',
        'int',
        'float',
        'string',
        'bool',
        'array',
        'callable',
        'iterable',
        'object'
    ];

    /**
     * @var ClassGenerator
     */
    protected ClassGenerator $ClassGenerator;

    /**
     * @var null|string
     */
    protected ?string $rootSourceFileDirectory = null;

    abstract public function prepare(): void;

    public function initFilename(): string
    {
        if (!$this->filename) {
            $this->setFilename($this->getRootSourceFileDirectory() .
                               $this->getNamespaceDirectory() .
                               $this->ClassGenerator->getName() .
                               '.php');
        }

        return $this->filename;
    }

    /**
     * @return string
     */
    public function getRootSourceFileDirectory(): string
    {
        if (!$this->rootSourceFileDirectory) {
            $config = Config::getInstance();
            $this->setRootSourceFileDirectory(
                realpath($config->getOption(Config::OPTION_ROOT_SOURCE_DIR)) .
                DIRECTORY_SEPARATOR);
        }

        return $this->rootSourceFileDirectory;
    }

    /**
     * @param  string  $rootSourceFileDirectory
     *
     * @return self
     */
    public function setRootSourceFileDirectory(string $rootSourceFileDirectory): self
    {
        $this->rootSourceFileDirectory = $rootSourceFileDirectory;

        return $this;
    }

    public function write(): ClassGeneratorInterface
    {
        $FileSystem = new Filesystem();
        $FileSystem->mkdir($this->getRootSourceFileDirectory() . $this->getNamespaceDirectory(), 0755);

        parent::write();

        return $this;
    }

    protected function getNamespaceDirectory(): string
    {
        return str_replace('\\', DIRECTORY_SEPARATOR,
                str_replace(Config::getInstance()->getOption(Config::OPTION_NAMESPACE_ROOT), '', $this->getNamespace())
               ) .
               DIRECTORY_SEPARATOR;
    }

    protected function getRootNamespace()
    {
        return Config::getInstance()->getOption(Config::OPTION_NAMESPACE_ROOT);
    }

    protected function parseDescription(string $description): string
    {
        return preg_replace('/\*\//', '*\\/', $description);
    }

    protected function checkAndAddDeprecatedTag($description, DocBlockGenerator $DocBlockGenerator): void
    {
        if (str_contains($description, 'deprecated')) {
            $DocBlockGenerator->setTag(new GenericTag('deprecated'));
        }
    }

    protected function getUseAlias(string $fullClassName): string
    {
        $classInfo = explode('\\', $fullClassName);
        $className = array_pop($classInfo);

        if (strtolower($className) == strtolower($this->ClassGenerator->getName())) {
            $className = $className . 'Model';
        }

        $classGeneratorUses = $this->ClassGenerator->getUses();
        $uses               = [];

        foreach ($classGeneratorUses as $use) {
            $useInfo = explode(' as ', $use);

            if (!isset($useInfo[1])) {
                $useInfo[1] = $useInfo[0];
            }

            $uses[$useInfo[0]] = $useInfo[1];
        }

        if ($this->ClassGenerator->hasUse($fullClassName)) {
            return $uses[$fullClassName];
        }

        while (in_array($className, $uses)) {
            $className .= array_pop($classInfo);
        }

        $this->ClassGenerator->addUse($fullClassName, $className);

        return $className;
    }
}