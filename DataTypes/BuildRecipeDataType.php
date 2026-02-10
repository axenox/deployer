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
    const COMPOSER_INSTALL_WITH_ASSET_FIX = "ComposerInstallAssetFix";
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
            self::COMPOSER_INSTALL_WITH_ASSET_FIX => 'Build via Composer + asset FIX',
            self::CLONE_LOCAL => 'Clone current installation',
            self::CUSTOM_BUILD => 'Custom build recipe'
        ];
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::getValueHints()
     */
    public function getValueHints() : array
    {
        return [
            self::COMPOSER_INSTALL => 'Runs a composer install on the composer.json from the build variant',
            self::COMPOSER_INSTALL_WITH_ASSET_FIX => 'Forces asset-packagist to use its latest version cache as a workaround for different CDN problems',
            self::CLONE_LOCAL => 'Copies the current installation instead of running composer',
            self::CUSTOM_BUILD => 'Runs the deployer recipe file provided in the project config'
        ];
    }
}