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
class Build extends AbstractActionDeferred implements iCanBeCalledFromCLI, iCreateData
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
            $deployPhp = $this->createDeployPhp($buildRecipe, $buildFolder);

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

    protected function getBuildRecipeFile(TaskInterface $task): string
    {
        $recipe = $this->getProjectData($task, 'build_recipe');
        $recipeFile = '';
        $recipiesBasePath = Filemanager::FOLDER_NAME_VENDOR . DIRECTORY_SEPARATOR . $this->getApp()->getDirectory() . DIRECTORY_SEPARATOR . 'Recipes' . DIRECTORY_SEPARATOR;

        switch ($recipe) {
            case BuildRecipeDataType::COMPOSER_INSTALL:
                // TODO füllen von $recipeFile
                break;
            case BuildRecipeDataType::CLONE_LOCAL:
                $recipeFile = $recipiesBasePath . 'CreateBuild.php';
                break;
        }

        return $recipeFile;
    }
    
    protected function createDeployPhp(string $recipePath, string $buildFolder) : string
    {
        $content = <<<PHP

<?php
namespace Deployer;

require 'vendor/autoload.php'; // Or move it to deployer and automatically detect

\$application = 'Power UI';
\$version = '0.1-beta';
\$project = $buildFolder;

// === Host ===
\$stage = 'test';
\$keep_releases = 6;
\$host_short = 'powerui';
\$host_ssh_config = __DIR__ . '\\hosts\\' . \$host_short . '\\ssh_config';

// === Path definitions ===
\$basic_deploy_path =  'C:\\wamp\\www\\powerui';
\$relative_deploy_path = 'powerui';
\$builds_archives_path = __DIR__ . '\\builds';

require '{$recipePath}';

PHP;
        
        $content_php = fopen($buildFolder . DIRECTORY_SEPARATOR . 'deploy.php', 'w');
        fwrite($content_php, $content);
        fclose($content_php);
        
        
        // TODO Datei speichern unter deployer\[project-alias]\deploy.php
        // return /* TODO pfad */;
        return $buildFolder . DIRECTORY_SEPARATOR . 'deploy.php';
    }
    
    protected function createBuildFolder(TaskInterface $task) : string
    {
     //   $connection = $this->getSshConnection($task);
        
     //   $hostName = $connection->getHostName();
     //   $customOptions = $connection->getSshConfig();
     //   $privateKey = $connection->getSshPrivateKey();
        
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
            		builds -> leerer ordner
            		deploy.php
         */
        
        // ACHTUNG: id_rsa muss nur für PHP-user lesbar sein!
        
        return $fm->getPathToBaseFolder()
        . DIRECTORY_SEPARATOR . 'deployer'
            . DIRECTORY_SEPARATOR . $this->getProjectData($task, 'alias') ;
        //'deployer\sfc_koenig';
    }
    
    protected function getBuildsFolderName() : string
    {
        return 'builds';
    }
    
    protected function getDefaultSshConfig(string $pathToHostFolder, string $hostName, string $user, string $port) : array
    {
        return [
            /*
            HostName 10.57.2.40 // Kommt aus Dataconnection
            User SFCKOENIG\ITSaltBI // Kommt aus DataConnection
            port 22 // Kommt aus DataConnection
            PreferredAuthentications publickey
            StrictHostKeyChecking no
            IdentityFile C:\wamp\www\sfckoenig\exface\deployer\sfc\hosts\powerui\id_rsa
            UserKnownHostsFile C:\wamp\www\sfckoenig\exface\deployer\sfc\hosts\powerui\known_hosts
            */
        ];
    }
    
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
                'data_connection'
            ]);
            $ds->addFilterFromString('UID', $hostUid, ComparatorDataType::EQUALS);
            $ds->dataRead();
            $this->hostData = $ds;
        }
        return $this->hostData->getCellValue($option, 0);
    }
    
    protected function getSshConnection(TaskInterface $task) : DeployerSshConnector
    {
        $connectionUid = $this->getHostData($task, 'data_connection');
        return DataConnectionFactory::createFromModel($this->getWorkbench(), $connectionUid);
    }    
    
    protected function getProjectData(TaskInterface $task, string $projectAttributeAlias): string
    {
        if ($this->projectData === null) {
            $inputData = $this->getInputDataSheet($task);
            if ($col = $inputData->getColumns()->get('project')) {
                $projectUid = $col->getCellValue(0);
            } else {
                throw new ActionInputMissingError($this, 'TODO: not project!');
            }

            $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'axenox.Deployer.project');
            $ds->getColumns()->addMultiple([
                'alias',
                'build_recipe',
                'build_recipe_custom_path',
                'default_composer_json',
                'default_composer_auth_json',
                'default_config',
                'deployment_recipe',
                'deployment_receipe_custom_path',
                'name',
                'project_group'
            ]);
            $ds->addFilterFromString('UID', $projectUid, ComparatorDataType::EQUALS);
            $ds->dataRead();
            $this->projectData = $ds;
        }
        return $this->projectData->getCellValue($projectAttributeAlias, 0);
    }

    /**
     * Generates buildname from buildversion and current time
     * @param TaskInterface $task
     * @return string
     */
    protected function generateBuildName(TaskInterface $task) : string
    {
        $inputData = $this->getInputDataSheet($task);
        if ($col = $inputData->getColumns()->get('version')) {
            $version = $col->getCellValue(0);
        } else {
            throw new ActionInputMissingError($this, 'TODO: no version');
        }

        $timestamp = date('YmdHis');
        
        return $version . '+' . $timestamp;

    }
   
    
    /**
     *
     * {@inheritdoc}
     * @see iCanBeCalledFromCLI::getCliArguments()
     */
    public function getCliArguments(): array
    {
        return [
            (new ServiceParameter($this))->setName('project')->setDescription('Alias of the project to build'),
            (new ServiceParameter($this))->setName('version')->setDescription('Version number - e.g. 1.0.12 or 2.0-beta. Use sematic versioning!')
        ];
    }

    /**
     *
     * {@inheritdoc}
     * @see iCanBeCalledFromCLI::getCliOptions()
     */
    public function getCliOptions(): array
    {
        return [];
    }
    
    protected function cleanupFiles()
    {
        // TODO alles löschen außer deployer\[project_alias]\builds
    }
}