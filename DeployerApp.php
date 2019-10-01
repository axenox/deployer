<?php
namespace axenox\Deployer;

use exface\Core\Interfaces\InstallerInterface;
use exface\Core\CommonLogic\AppInstallers\MySqlDatabaseInstaller;
use exface\Core\CommonLogic\Model\App;
use exface\Core\Exceptions\Model\MetaObjectNotFoundError;
use exface\Core\Factories\DataSourceFactory;

class DeployerApp extends App
{
    public function getInstaller(InstallerInterface $injected_installer = null)
    {
        $installer = parent::getInstaller($injected_installer);       
        $sqlInstaller = new MySqlDatabaseInstaller($this->getSelector());
        $sqlInstaller
            ->setFoldersWithMigrations(['InitDB','Migrations'])
            ->setMigrationsTableName($sqlInstaller->getMigrationsTableName() . '_deployer')
            ->setDataConnection($this->getWorkbench()->model()->getModelLoader()->getDataConnection());
        $installer->addInstaller($sqlInstaller);
        return $installer;
    }
}
?>