<?php
namespace axenox\Deployer\DataTypes;

use exface\Core\DataTypes\StringEnumDataType;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;

/**
 * Enumeration PHP versions available for builds.
 *
 * @author Andrej Kabachnik
 *
 */
class BuildablePhpVersionDataType extends StringEnumDataType
{
    /**
     *
     * {@inheritDoc}
     * @see exface\Core\CommonLogic\DataTypes\EnumDynamicDataTypeTrait::getValues()
     */
    public function getValues()
    {
        $versions = [phpversion()];
        $paths = $this->getWorkbench()->getApp('axenox.Deployer')->getConfig()->getOption('PHP_VERSION_PATHS')->toArray();
        $versions = array_merge($versions, array_keys($paths));
        return array_combine($versions, $versions);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see exface\Core\CommonLogic\DataTypes\EnumDynamicDataTypeTrait::getLabels()
     */
    public function getLabels()
    {
        return array_combine($this->getValues(), $this->getValues());
    }

    /**
     *
     * {@inheritDoc}
     * @see exface\Core\CommonLogic\DataTypes\EnumDynamicDataTypeTrait::setShowValues()
     */
    public function setShowValues(bool $trueOrFalse) : EnumDataTypeInterface
    {
        return false;
    }
}