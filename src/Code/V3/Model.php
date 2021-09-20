<?php

namespace OpenAPI\CodeGenerator\Code\V3;

use Exception;
use Laminas\Code\Generator\AbstractMemberGenerator;
use Laminas\Code\Generator\ClassGenerator;
use Laminas\Code\Generator\DocBlock\Tag\VarTag;
use Laminas\Code\Generator\DocBlockGenerator;
use Laminas\Code\Generator\PropertyGenerator;
use OpenAPI\CodeGenerator\Code\AbstractClassGenerator;
use OpenAPI\CodeGenerator\Config;
use OpenAPI\CodeGenerator\Utility;
use OpenAPI\Schema\DataTypes;
use OpenAPI\Schema\V3\Schema;

class Model extends AbstractClassGenerator implements ModelInterface
{
    /**
     * @var Schema
     */
    protected $Schema;

    /**
     * @var string
     */
    private $classname;

    /**
     * Model constructor.
     *
     * @param  string  $classname
     * @param  Schema  $Schema
     *
     * @throws Exception
     */
    public function __construct(string $classname, Schema $Schema)
    {
        parent::__construct([]);
        $this->classname = Utility::convertV3RefToClass($classname);
        $this->Schema    = $Schema;
    }

    public function prepare(): void
    {
        $config = Config::getInstance();
        [$objectNamespace, $objectClassname] = Utility::parseClassInfo($this->classname);
        $this->setNamespace(rtrim($this->getRootNamespace() . '\\' .
                                  $config->getOption(Config::OPTION_NAMESPACE_MODEL) . '\\' .
                                  $objectNamespace, '\\'));

        $this->ClassGenerator = new ClassGenerator();
        $this->ClassGenerator
            ->setNamespaceName($this->namespace)
            ->setName($objectClassname)
            ->addProperties($this->parseProperties())
            ->addUse(Config::getInstance()->getOption(Config::OPTION_MODEL_BASE_CLASS), 'AbstractModel')
            ->setExtendedClass('AbstractModel');

        $this->setClass($this->ClassGenerator);


        if ($this->Schema->description) {
            $this->Schema->description = $this->parseDescription($this->Schema->description);

            $DocBlockGenerator = new DocBlockGenerator($this->Schema->description);
            $this->checkAndAddDeprecatedTag($this->Schema->description, $DocBlockGenerator);

            $this->ClassGenerator->setDocBlock($DocBlockGenerator);
        }

        if ($this->Schema->type && 'object' != $this->Schema->type) {
            $this->ClassGenerator->addProperty('isRawObject', true, AbstractMemberGenerator::FLAG_PROTECTED);
        }

        $this->initFilename();
    }


    /**
     * @return PropertyGenerator[]
     *
     * @throws Exception
     */
    function parseProperties(): array
    {
        if (!isset($this->Schema->properties)) {
            return [];
        }
        $properties = [];

        foreach ($this->Schema->properties->getPatternedFields() as $key => $property) {
            $property = (array)$property;

            if (false !== strpos($key, '$')) {
                $key = str_replace('$', '_', $key);
            }

            $PropertyGenerator = new PropertyGenerator($key);
            $PropertyGenerator->setFlags(AbstractMemberGenerator::FLAG_PUBLIC);

            $property['description'] = $property['description'] ?? '';

            $DocBlockGenerator = new DocBlockGenerator($property['description']);

            // Setup correct phpdocumentation @var tag
            if (array_key_exists('$ref', $property)) {
                $DocBlockGenerator->setTag(new VarTag(null, $this->parseDataType($property['$ref'])));
            }

            if (array_key_exists('type', $property)) {
                $types = [];
                switch ($property['type']) {
                    case 'array':
                        $types = array_map(function ($key, $item) {
                            if (in_array($key, ['$ref', 'type'])) {
                                return $this->parseDataType($item, true);
                            }
                        }, array_keys($property['items']), array_values($property['items']));
                        break;
                    default:
                        $types[] = $this->parseDataType($property['type']);
                        break;
                }
                if (array_key_exists('nullable', $property) && true == $property['nullable']) {
                    $types[] = 'null';
                }
                $DocBlockGenerator->setTag(new VarTag(null, $types));
            }

            if (array_key_exists('default', $property)) {
                $PropertyGenerator->setDefaultValue($property['default']);
            }

            if (array_key_exists('nullable', $property) && true == $property['nullable']) {
                $PropertyGenerator->setDefaultValue(null);
            }

            // Mark property as DEPRECATED when we detect the keyword in description
            if (in_array('description', $property)) {
                $this->checkAndAddDeprecatedTag($property['description'], $DocBlockGenerator);
            }


            $PropertyGenerator->setDocBlock($DocBlockGenerator);
            $properties[$key] = $PropertyGenerator;
        }

        return $properties;
    }

    /**
     * @param  string  $dataType
     * @param  bool    $isArray
     *
     * @return string
     * @throws Exception
     */
    protected function parseDataType(
        string $dataType,
        bool $isArray = false
    ): string {
        if (0 === strpos($dataType, '#')) {
            $dataType = '\\' . Config::getInstance()->getModelNamespace() . Utility::convertV3RefToClass($dataType);
        } else {
            if (array_key_exists($dataType, DataTypes::DATATYPES)) {
                $dataType = DataTypes::getPHPDataType($dataType);
            }
        }

        if ($isArray) {
            $dataType .= '[]';
        }

        return $dataType;
    }


}