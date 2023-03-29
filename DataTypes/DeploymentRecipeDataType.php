<?php
namespace axenox\Deployer\DataTypes;

use exface\Core\CommonLogic\DataTypes\EnumStaticDataTypeTrait;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use exface\Core\DataTypes\StringDataType;

/**
 * Enumeration for built-in deployment recipies.
 *
 * @method DeploymentRecipeDataType LOCAL_BLD_SSH_SELF_EXTRACTOR(\exface\Core\CommonLogic\Workbench $workbench)
 * @method DeploymentRecipeDataType LOCAL_BLD_USB_SELF_EXTRACTOR(\exface\Core\CommonLogic\Workbench $workbench)
 * // TODO add other @method
 *
 * @author Andrej Kabachnik
 *
 */
class DeploymentRecipeDataType extends StringDataType implements EnumDataTypeInterface
{
    use EnumStaticDataTypeTrait;
    
    const LOCAL_BLD_SSH_SELF_EXTRACTOR = "LocalBldSshSelfExtractor";
    const LOCAL_BLD_USB_SELF_EXTRACTOR = "LocalBldUsbSelfExtractor";
    const LOCAL_BLD_SSH_INSTALL = "LocalBldSshInstall";
    const LOCAL_BLD_SSH_INSTALL_WAIT = "LocalBldSshInstallWait";
    const LOCAL_BLD_AZURE_APP_SERVICE_MANUAL_INSTALL = "LocalBldAzureAppServiceManualInstall";
    const LOCAL_BLD_UPDATER_FACADE = 'LocalBldUpdaterUpload';
    const CUSTOM_DEPLOY = "CustomDeploy";
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::getLabels()
     */
    public function getLabels()
    {
        return [
            self::LOCAL_BLD_UPDATER_FACADE => 'Self-extractor + Updater upload',
            self::LOCAL_BLD_SSH_SELF_EXTRACTOR => 'Self-extractor + SSH upload',
            self::LOCAL_BLD_USB_SELF_EXTRACTOR => 'Self-extractor + manual transfer',
            self::LOCAL_BLD_AZURE_APP_SERVICE_MANUAL_INSTALL => 'Self-extractor + manual upload to Microsoft Azure',
            self::LOCAL_BLD_SSH_INSTALL => 'Build-ZIP + SSH install',
            self::LOCAL_BLD_SSH_INSTALL_WAIT => 'Build-ZIP + SSH install + wait fix',
            self::CUSTOM_DEPLOY => 'Custom Deployment Recipe'
        ];
    }
}
