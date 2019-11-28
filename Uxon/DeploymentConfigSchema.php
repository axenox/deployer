<?php
namespace axenox\Deployer\Uxon;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\UxonSchemaInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Uxon\UxonSchema;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\DataTypes\SortingDirectionsDataType;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\ComparatorDataType;

/**
 * UXON-schema class for actions.
 * 
 * @see UxonSchema for general information.
 * 
 * @author Andrej Kabachnik
 *
 */
class DeploymentConfigSchema implements UxonSchemaInterface
{
    private $parentSchema = null;
    private $workbench = null;
    
    /**
     *
     * @param WorkbenchInterface $workbench
     */
    public function __construct(WorkbenchInterface $workbench, UxonSchema $parentSchema = null)
    {
        $this->parentSchema = $parentSchema;
        $this->workbench = $workbench;
    }
    
    public static function getSchemaName() : string
    {
        return 'Deployment Config';
    }
    
    public function getValidValues(UxonObject $uxon, array $path, string $search = null, string $rootPrototypeClass = null, MetaObjectInterface $rootObject = null): array
    {
        return [];
    }

    public function getParentSchema(): UxonSchemaInterface
    {
        return $this->parentSchema ?? new UxonSchema($this->getWorkbench(), $this);
    }

    public function getPrototypeClass(UxonObject $uxon, array $path, string $rootPrototypeClass = null): string
    {
        return '\\' . __CLASS__;
    }

    public function getPropertiesTemplates(string $prototypeClass): array
    {
        return [
            'local_vendors' => '[""]',
            'default_app_config' => '{"app.Alias.config.json": {"": ""}}'
        ];
    }

    public function getPropertyValueRecursive(UxonObject $uxon, array $path, string $propertyName, string $rootValue = '')
    {
        return null;
    }

    public function getProperties(string $prototypeClass): array
    {
        return [
            'local_vendors',
            'default_app_config'
        ];
    }

    public function hasParentSchema()
    {
        return $this->parentSchema !== null;
    }

    public function getWorkbench()
    {
        return $this->workbench;
    }

    public function getPropertyTypes(string $prototypeClass, string $property): array
    {
        return [];
    }

    public function getMetaObject(UxonObject $uxon, array $path, MetaObjectInterface $rootObject = null): MetaObjectInterface
    {
        return $rootObject;
    }
    
    public function getPresets(UxonObject $uxon, array $path, string $rootPrototypeClass = null) : array
    {
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.UXON_PRESET');
        $ds->getColumns()->addMultiple(['UID','NAME', 'PROTOTYPE__LABEL', 'DESCRIPTION', 'PROTOTYPE', 'UXON' , 'WRAP_PATH', 'WRAP_FLAG']);
        $ds->addFilterFromString('UXON_SCHEMA', '\\' . __CLASS__, ComparatorDataType::EQUALS);
        $ds->getSorters()
        ->addFromString('PROTOTYPE', SortingDirectionsDataType::ASC)
        ->addFromString('NAME', SortingDirectionsDataType::ASC);
        $ds->dataRead();
        
        return $ds->getRows();
    }

}