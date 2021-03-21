<?php

namespace OpenAPI\CodeGenerator\Code\V2;

use Laminas\Code\Generator\ClassGenerator;
use Laminas\Code\Generator\DocBlock\Tag\VarTag;
use Laminas\Code\Generator\DocBlockGenerator;
use Laminas\Code\Generator\PropertyGenerator;
use OpenAPI\CodeGenerator\Code\AbstractClassGenerator;
use OpenAPI\CodeGenerator\Utility;
use OpenAPI\Schema\V2\DataTypes;
use OpenAPI\Schema\V2\Schema;

class Model extends AbstractClassGenerator
{
    protected $rootNamespace = 'Kubernetes\\Model\\';

    /**
     * @var Schema
     */
    protected $Schema;

    /**
     * Model constructor.
     *
     * @param string       $classname
     * @param Schema $Schema
     *
     * @throws \Exception
     */
    public function __construct(string $classname, Schema $Schema)
    {
        parent::__construct([]);

        [$objectNamespace, $objectClassname] = Utility::parseClassInfo(Utility::convertV2RefToClass($classname));
        $this->setNamespace($this->rootNamespace . $objectNamespace);

        $this->Schema = $Schema;

        $this->ClassGenerator = new ClassGenerator();
        $this->ClassGenerator
            ->setNamespaceName($this->namespace)
            ->setName($objectClassname)
            ->addProperties($this->parseProperties())
            ->addUse('\KubernetesRuntime\AbstractModel')
            ->setExtendedClass('AbstractModel');

        $this->setClass($this->ClassGenerator);


        if ($Schema->description) {
            $Schema->description = $this->parseDescription($Schema->description);

            $DocBlockGenerator = new DocBlockGenerator($Schema->description);
            $this->checkAndAddDeprecatedTag($Schema->description, $DocBlockGenerator);

            $this->ClassGenerator->setDocBlock($DocBlockGenerator);
        }

        if ($Schema->type && 'object' != $Schema->type) {
            $this->ClassGenerator->addProperty('isRawObject', true, PropertyGenerator::FLAG_PROTECTED);
        }

        # Patch object should be dealt specially
        if('Patch' == $objectClassname){
            $this->ClassGenerator->setExtendedClass('\KubernetesRuntime\AbstractPatchModel');
        }

        $this->initFilename();
    }

    /**
     * @return PropertyGenerator[]
     *
     * @throws \Exception
     */
    function parseProperties()
    {
        $properties = [];

        foreach ((array)$this->Schema->properties as $key => $property) {

            if (false !== strpos($key, '$')) {
                $key = str_replace('$', '_', $key);
            }

            $PropertyGenerator = new PropertyGenerator($key);
            $PropertyGenerator->setFlags(PropertyGenerator::FLAG_PUBLIC);

            $property['description'] = array_key_exists('description', $property)
                ? $this->parseDescription($property['description']) : '';

            $DocBlockGenerator = new DocBlockGenerator($property['description']);

            // Setup correct phpdocumentation @var tag
            if (array_key_exists('$ref', $property)) {
                $DocBlockGenerator->setTag(new VarTag(null, $this->parseDataType($property['$ref'], false)));
            }

            if (array_key_exists('type', $property)) {
                switch ($property['type']) {
                    case 'array':
                        $Tag = new VarTag(null, array_map(function ($item) {
                            return $this->parseDataType($item, true);
                        }, $property['items']));
                        break;
                    default:
                        $property['type'] = $this->parseDataType($property['type'], false);

                        $Tag = new VarTag(null, $property['type']);
                        break;
                }
                $DocBlockGenerator->setTag($Tag);
            }

            // Mark property as DEPRECATED when we detect the keyword in description
            if (in_array('description', $property)) {
                $this->checkAndAddDeprecatedTag($property['description'], $DocBlockGenerator);
            }

            // Parse value for special kubernetes keywords such as kind and apiversion
            $groupVersionKind = $this->Schema->getPatternedField(KubernetesExtentions::GROUP_VERSION_KIND);
            if ('kind' == $key &&
                is_array($groupVersionKind) &&
                array_key_exists(KubernetesExtentions::KIND, $groupVersionKind[0])) {
                $PropertyGenerator->setDefaultValue($groupVersionKind[0][KubernetesExtentions::KIND]);
            }
            if ('apiVersion' == $key) {
                $apiVersion = '';
                if (is_array($groupVersionKind) &&
                    array_key_exists(KubernetesExtentions::GROUP, $groupVersionKind[0]) &&
                    '' != $groupVersionKind[0][KubernetesExtentions::GROUP]
                ) {
                    $apiVersion .= $groupVersionKind[0][KubernetesExtentions::GROUP] . "/";
                }

                if (is_array($groupVersionKind) &&
                    array_key_exists(KubernetesExtentions::VERSION, $groupVersionKind[0]) &&
                    $groupVersionKind[0][KubernetesExtentions::VERSION]
                ) {
                    $apiVersion .= $groupVersionKind[0][KubernetesExtentions::VERSION];
                }
                if ('' != $apiVersion) {
                    $PropertyGenerator->setDefaultValue($apiVersion);
                }
            }
            $PropertyGenerator->setDocBlock($DocBlockGenerator);
            $properties[] = $PropertyGenerator;
        }

        return $properties;
    }

    /**
     * @param string $dataType
     * @param bool   $isArray
     *
     * @return string
     * @throws \Exception
     */
    protected function parseDataType(
        string $dataType,
        $isArray = false
    ): string {
        if (0 === strpos($dataType, '#')) {
            $dataType = '\\' . $this->rootNamespace . Utility::convertV2RefToClass($dataType);

            // If referenced datatype is within the same namespace, we don't need to import full namespace
            if (1 === strpos($dataType, $this->getNamespace())) {
                $dataType = str_replace('\\' . $this->getNamespace() . '\\', '', $dataType);
            }
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