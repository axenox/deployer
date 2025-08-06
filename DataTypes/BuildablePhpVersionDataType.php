<?php
namespace axenox\Deployer\DataTypes;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\StringEnumDataType;
use exface\Core\Exceptions\Actions\ActionInputError;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Selectors\DataTypeSelectorInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Enumeration PHP versions available for builds.
 *
 * @author Andrej Kabachnik
 *
 */
class BuildablePhpVersionDataType extends StringEnumDataType
{
    /**
     * @see exface\Core\CommonLogic\DataTypes\AbstractDataType::__construct())
     */
    public function __construct(DataTypeSelectorInterface $selector, $value = null, UxonObject $configuration = null)
    {
        parent::__construct($selector, $value, $configuration);
        
        $versions = $this::getAvailableVersions($this->getWorkbench());
        $values = array_combine($versions, $versions);
        $this->setValues($values);
        $this->setValues($values);
        $this->setShowValues(false);
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
     * @param WorkbenchInterface $workbench
     * @return string[]
     */
    public static function getAvailableVersions(WorkbenchInterface $workbench) : array
    {
        $versions = [static::getRuntimeVersion()];
        $paths = $workbench->getApp('axenox.Deployer')->getConfig()->getOption('PHP_VERSION_PATHS')->toArray();
        $versions = array_merge($versions, array_keys($paths));
        $versions = array_unique($versions);
        return $versions;
    }

    /**
     * @return string
     */
    public static function getRuntimeVersion() : string
    {
        return phpversion();
    }

    /**
     * @param string $version
     * @param WorkbenchInterface $workbench
     * @return string
     */
    public static function getExecutable(string $version, WorkbenchInterface $workbench) : string
    {
        if ($version === static::getRuntimeVersion()) {
            return 'php';
        }
        $phpPathsUxon = $workbench->getApp('axenox.Deployer')->getConfig()->getOption('PHP_VERSION_PATHS');
        $phpPath = $phpPathsUxon->getProperty($version);
        if ($phpPath === null) {
            throw new RuntimeException('Cannot find PHP version "' . $version . '" in axenox.Deployer.config.json');
        }
        return $phpPath;
    }
}