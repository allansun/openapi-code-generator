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
    protected ClassGenerator $ClassGenerator;
    protected ?string $rootSourceFileDirectory = null;

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

    protected function getNamespaceDirectory(): string
    {
        return str_replace('\\', DIRECTORY_SEPARATOR,
                ltrim($this->getNamespace(), Config::getInstance()->getOption(Config::OPTION_NAMESPACE_ROOT))) .
               DIRECTORY_SEPARATOR;
    }

    abstract public function prepare(): void;

    public function write(): self
    {
        $FileSystem = new Filesystem();
        $FileSystem->mkdir($this->getRootSourceFileDirectory() . $this->getNamespaceDirectory(), 0755);

        parent::write();

        return $this;
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
        if (preg_match('/deprecated/', $description)) {
            $DocBlockGenerator->setTag(new GenericTag('deprecated'));
        }
    }

    protected function getUseAlias(string $fullClassName): string
    {
//        if (0 !== strpos($fullClassName, '\\')) {
//            $fullClassName = '\\' . $fullClassName;
//        }

        $classInfo = explode('\\', $fullClassName);
        $className = array_pop($classInfo);

        if ($className == $this->ClassGenerator->getName()) {
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