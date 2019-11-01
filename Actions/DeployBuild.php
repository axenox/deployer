<?php
namespace axenox\Deployer\Actions;

use exface\Core\CommonLogic\AbstractActionDeferred;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Actions\iCanBeCalledFromCLI;
use exface\Core\CommonLogic\Tasks\ResultMessageStream;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use axenox\Deployer\DataTypes\BuildRecipeDataType;
use exface\Core\CommonLogic\Actions\ServiceParameter;
use exface\Core\Interfaces\Actions\iCreateData;
use exface\Core\Exceptions\Actions\ActionInputMissingError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\CommonLogic\Filemanager;
use axenox\Deployer\DeployerSshConnector\DeployerSshConnector;
use exface\Core\Factories\DataConnectionFactory;

/**
 * Creates a build from the passed data.
 *
 * @author Andrej Kabachnik
 *
 */
class DeployBuild extends AbstractActionDeferred implements iCanBeCalledFromCLI, iCreateData
{
    
    private $projectData = null;
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\CommonLogic\AbstractAction::init()
     */
    protected function init()
    {
        parent::init();
        $this->setInputRowsMin(1);
        $this->setInputRowsMax(1);
        $this->setInputObjectAlias('axenox.Deployer.build');
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction): ResultInterface
    {
        $buildData = $this->getInputDataSheet($task);
        $result = new ResultMessageStream($task);
        
        $generator = function () use ($task, $buildData, $result, $transaction) {
            
            // TODO generate build name
            $buildName = $this->generateBuildName($task);
            // e.g. '0.1-beta+20191024115900';
            
            yield 'Building ' . $buildName;
            
            $buildData->setCellValue('status', 0, 50);
            $buildData->setCellValue('name', 0, $buildName);
            $buildData->dataCreate(false, $transaction);
            
            $buildRecipe = $this->getBuildRecipeFile($task);
            
            $buildFolder = $this->createBuildFolder($task);
            $deployPhp = $this->createDeployPhp($buildRecipe);
            
            // TODO run the deployer recipe for building and see if it is successfull!
            // Use symfony process?
            // c:\wamp\www\sfckoenig\exface> vendor\bin\dep -f=deployer\sfc\deploy_prod.php deploy
            // Beispiel - s. WebConsoleFacade ab Zeile 124
            $log = '';
            $seconds = 0;
            foreach ($process as $msg) {
                // Live output
                yield $msg;
                // Save to log
                $log .= $msg;
            }
            
            if ($success === false) {
                $buildData->setCellValue('status', 0, 90); // failed
            } else {
                $buildData->setCellValue('status', 0, 99); // completed
            }
            // TODO Save Log to $buildData
            
            // Update build with actual build results
            $buildData->dataUpdate(false, $transaction);
            
            $this->cleanupFiles();
            
            yield 'Build ' . $buildName . ' completed in ' . $seconds . ' seconds';
            
            // IMPORTANT: Trigger regular action post-processing as required by AbstractActionDeferred.
            $this->performAfterDeferred($result, $transaction);
        };
        
        $result->setMessageStreamGenerator($generator);
        return $result;
    }
    
    protected function createBuildFolder(TaskInterface $task) : string
    {
        $connection = $this->getSshConnection($task);
        
        $privateKey = $connection->getSshPrivateKey();
        $hostName = $connection->getHostName();
        $customOptions = $connection->getSshConfig();
        
        $fm = $this->getWorkbench()->filemanager();
        $buildsFolderPath = $fm->getPathToBaseFolder()
        . DIRECTORY_SEPARATOR . 'deployer'
            . DIRECTORY_SEPARATOR . $this->getProjectData($task, 'alias')
            . DIRECTORY_SEPARATOR . $this->getBuildsFolderName();
            Filemanager::pathConstruct($buildsFolderPath);
            
            
            /*
             * TODO
             *
             * deployer
             [project_alias]
             hosts
             host_name
             ssh_config -> Daten aus der DataConnection
             known_hosts -> leer
             id_rsa -> private key aus DataConnection
             builds -> leerer ordner
             */
            
            // ACHTUNG: id_rsa muss nur für PHP-user lesbar sein!
            
            return 'deployer\sfc_koenig';
    }
    
    
    /**
     * function for getting a value out of the hosts data
     *
     * @param TaskInterface $task
     * @param string $option
     * @throws ActionInputMissingError
     * @return string
     */
    protected function getHostData(TaskInterface $task, string $option) : string
    {
        if ($this->hostData === null) {
            $inputData = $this->getInputDataSheet($task);
            if ($col = $inputData->getColumns()->get('host')) {
                $hostUid = $col->getCellValue(0);
            } else {
                throw new ActionInputMissingError($this, 'TODO: not host!');
            }
            
            $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'axenox.Deployer.host');
            $ds->getColumns()->addMultiple([
                'data_connection',
                'last_build_deployed',
                'name',
                'operating_system',
                'path_abs_to_api',
                'path_rel_to_releases',
                'php_cli',
                'project',
                'stage'
            ]);
            $ds->addFilterFromString('UID', $hostUid, ComparatorDataType::EQUALS);
            $ds->dataRead();
            $this->hostData = $ds;
        }
        return $this->hostData->getCellValue($option, 0);
    }
      
    
    /**
     *
     * @param TaskInterface $task
     * @return DeployerSshConnector
     */
    protected function getSshConnection(TaskInterface $task) : DeployerSshConnector
    {
        $connectionUid = $this->getHostData($task, 'data_connection');
        return DataConnectionFactory::createFromModel($this->getWorkbench(), $connectionUid);
    }    
    
    
    /**
     * generates deploy data and creates deploy.php file
     *
     * @param TaskInterface $task
     * @param string $recipePath
     * @param string $buildFolder
     * @return string
     */
    protected function createDeployPhp(TaskInterface $task, string $recipePath, string $buildFolder) : string
    {
        
        $stage = "test"; // $this->getHostData($task, 'stage');
        $name = "testbuild"; //$this->getHostData($task, 'name');
        
        
        $content = <<<PHP
        
<?php
namespace Deployer;

ini_set('memory_limit', '-1'); // deployment may exceed 128MB internal memory limit

require 'vendor/autoload.php'; // Or move it to deployer and automatically detect
require 'vendor/deployer/deployer/recipe/common.php';

// === Host ===
set('stage', '{$stage}');
\$host_short = '{$name}';
set('host_short', \$name);
\$host_ssh_config = __DIR__ . '\\hosts\\' . \$host_short . '\\ssh_config';
set('host_ssh_config', \host_ssh_config);

// === Path definitions ===
set('basic_deploy_path', 'C:\\wamp\\www\\powerui');
set('relative_deploy_path', 'powerui');
\$builds_archives_path = __DIR__ . '\\' . '{$this->getBuildsFolderName()}';
set('builds_archives_path', \$builds_archives_path);

require '{$recipePath}';

PHP;
        
        $content_php = fopen($buildFolder . DIRECTORY_SEPARATOR . 'deploy.php', 'w');
        fwrite($content_php, $content);
        fclose($content_php);
        
        
        
        return $buildFolder . DIRECTORY_SEPARATOR . 'deploy.php';
    }
    
    
    
    public function getCliArguments(): array
    {}

    public function getCliOptions(): array
    {}

}