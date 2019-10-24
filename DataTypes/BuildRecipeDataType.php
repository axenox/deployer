<?php
namespace axenox\Deployer\DataTypes;

use exface\Core\CommonLogic\DataTypes\EnumStaticDataTypeTrait;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use exface\Core\DataTypes\StringDataType;

/**
 * Enumeration built-in build recipies.
 *
 * @method BuildRecipeDataType COMPOSER_INSTALL(\exface\Core\CommonLogic\Workbench $workbench)
 * @method BuildRecipeDataType CLONE_LOCAL(\exface\Core\CommonLogic\Workbench $workbench)
 * // TODO add other @method
 *
 * @author Andrej Kabachnik
 *
 */
class BuildRecipeDataType extends StringDataType implements EnumDataTypeInterface
{
    use EnumStaticDataTypeTrait;
    
    const COMPOSER_INSTALL = "ComposerInstall";
    const CLONE_LOCAL = "CloneLocal";
    const CUSTOM_BUILD = "CustomBuild";
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::getLabels()
     */
    public function getLabels()
    {
        return [
            self::COMPOSER_INSTALL => 'Build via Composer',
            self::CLONE_LOCAL => 'Clone current installation',
            self::CUSTOM_BUILD => 'Custom build recipe'
        ];
    }
}