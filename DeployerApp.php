<?php
namespace axenox\Deployer;

use exface\Core\Interfaces\InstallerInterface;
use exface\Core\CommonLogic\AppInstallers\MySqlDatabaseInstaller;
use exface\Core\CommonLogic\Model\App;

class DeployerApp extends App
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\App::getInstaller()
     */
    public function getInstaller(InstallerInterface $injected_installer = null)
    {
        $installer = parent::getInstaller($injected_installer);       
        
        $sqlInstaller = new MySqlDatabaseInstaller($this->getSelector());
        $sqlInstaller
            ->setFoldersWithMigrations(['InitDB','Migrations'])
            ->setDataSourceSelector('0x11eab5facf6370bab5fa0205857feb80')
            // Change the migrations table name to allow keeping deployer tables in the
            // core schema!
            ->setMigrationsTableName($sqlInstaller->getMigrationsTableName() . '_deployer');
        $installer->addInstaller($sqlInstaller);
        return $installer;
    }
}
?>