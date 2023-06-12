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
use axenox\Deployer\DataConnectors\DeployerSshConnector;
use exface\Core\Factories\DataConnectionFactory;
use axenox\Deployer\Actions\Traits\BuildProjectTrait;
use Symfony\Component\Process\Process;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\Facades\HttpFileServerFacade;
use exface\Core\Interfaces\Tasks\ResultMessageStreamInterface;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Actions\ActionRuntimeError;
use exface\Core\DataTypes\JsonDataType;

/**
 * Deploys a build to a specific host.
 * 
 * This action requires an instance of a build and an instance of a host as parameters.
 * The action may either be called via the PowerUI frontend or via CLI-command.
 * The parameters for the CLI-call are the names of the host and build, which have to be existing instances of
 * `axenox.Deployer.host` and `axenox.Deployer.build` objects.
 * This action will then extract the data, required for the deployment, from the given objects, create a working directory
 * and call the actual command for the deployment process. 
 * Upon finishing the deployment process, the action will delete every temporary file / directories created.
 * 
 * ## Commandline Usage:
 * 
 * ```
 * action axenox.Deployer:Deploy [BuildName] [HostName]
 * ```
 * 
 * For example:
 * ```
 * action axenox.Deployer:Deploy 1.1.2+20191108145134 sdrexf2
 * ```
 * 
 * 
 * @author Andrej Kabachnik
 *
 */
class Deploy extends AbstractActionDeferred implements iCanBeCalledFromCLI, iCreateData
{
    use BuildProjectTrait;
    
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
        $this->setInputObjectAlias('axenox.Deployer.deployment');
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractActionDeferred::performImmediately()
     */
    protected function performImmediately(TaskInterface $task, DataTransactionInterface $transaction, ResultMessageStreamInterface $result) : array
    {
        // $buildData based on object axenox.Deployer.deployment
        try {
            $deployData = $this->getInputDataSheet($task);
        } catch (ActionInputMissingError $e) {
            $deployData = DataSheetFactory::createFromObject($this->getInputObjectExpected());
            $deployData->addRow([
                'build' => $this->getBuildData($task, 'name'),
                'host' => $this->getHostData($task, 'name')
            ]);
        }
        return [$task, $deployData];
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractActionDeferred::performDeferred()
     */
    protected function performDeferred(TaskInterface $task = null, DataSheetInterface $deployData = null) : \Generator
    {
        if ($task === null) {
            throw new InvalidArgumentException('Missing argument $task in deferred action call!');
        }
        
        if ($deployData === null) {
            throw new InvalidArgumentException('Missing argument $deployData in deferred action call!');
        }
        
        $seconds = time();
        
        // Create deploy entry and mark it as "in progress"
        $deployData->setCellValue('status', 0, 50);
        $deployData->setCellValue('host', 0, $this->getHostData($task, 'uid'));
        $deployData->setCellValue('build', 0, $this->getBuildData($task, 'uid'));
        $deployData->setCellValue('started_on', 0, date(DateTimeDataType::DATETIME_FORMAT_INTERNAL));
        $deployData->setCellValue('deploy_recipe_file', 0, $this->getDeployRecipeFile($task));
        // Do not use the transaction to force force creating a separate one for this operation.
        $deployData->dataCreate(false);
        
        try {
            $buildName = $this->getBuildData($task, 'name');
            $hostName = $this->getHostData($task, 'name');
            
            // run the deployer task via CLI
            if (getcwd() !== $this->getWorkbench()->filemanager()->getPathToBaseFolder()) {
                chdir($this->getWorkbench()->filemanager()->getPathToBaseFolder());
            }
            
            //create directories
            $projectFolder = $this->getProjectFolderRelativePath($task);
            $this->createDeployerProjectFolder($task);
            
            //build the command used for the actual deployment
            $deployTask = $this->createDeployerTask($task); // testbuild\deploy.php LocalBldSshSelfExtractor --build=1.0.1...tar.gz
            $cmd .= 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . "dep {$deployTask}";
            $environmentVars = $this->getCmdEnvirontmentVars();
            
            $log = '';
            
            //execute deploy command
            $process = Process::fromShellCommandline($cmd, null, $environmentVars, null, $this->getTimeout());
            $process->start();
            foreach ($process as $msg) {
                // Live output
                yield $this->escapeCliMessage($this->replaceFilePathsWithHyperlinks($msg));
                // Save to log
                $log .= $msg;
                $deployData->setCellValue('log', 0, $log);
                $deployData->dataUpdate(false);
            }
            
            if ($process->isSuccessful() === false) {
                $deployData->setCellValue('status', 0, 90); // failed
                $msg = '✘ FAILED deploying build ' . $buildName . ' on ' . $hostName . '.';
            } else {
                if (strpos($deployTask, 'LocalBldUpdaterPull') !== false) {
                    $deployData->setCellValue('status', 0, 60); // published
                    $seconds = time() - $seconds;
                    $msg = '✔ SUCCEEDED publishing build ' . $buildName . ' for download by ' . $hostName . ' in ' . $seconds . ' seconds.';
                } else {
                    $deployData->setCellValue('status', 0, 99); // completed
                    $seconds = time() - $seconds;
                    $msg = '✔ SUCCEEDED deploying build ' . $buildName . ' on ' . $hostName . ' in ' . $seconds . ' seconds.';
                }
            }
            yield $msg;
            $log .= $msg;
            
            $deployData->setCellValue('completed_on', 0, date(DateTimeDataType::DATETIME_FORMAT_INTERNAL));
            $deployData->setCellValue('log', 0, $log);
            
            // Update deployment entry's state and save log to data source
            $deployData->dataUpdate(false);
            
            $this->cleanupFiles($projectFolder);
        } catch (\Throwable $e) {
            $log .= PHP_EOL . '✘ ERRROR: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();
            $deployData->setCellValue('log', 0, $log);
            $deployData->setCellValue('status', 0, 90); // failed
            $this->getWorkbench()->getLogger()->logException($e);
            $deployData->dataUpdate(false);
        }
    }
    
    /**
     * Replaces C:\... with http://... links for files within the project folder
     * 
     * @param string $msg
     * @return string
     */
    protected function replaceFilePathsWithHyperlinks(string $msg) : string
    {
        $urlMatches = [];
        if (preg_match_all('/' . preg_quote($this->getBasePath(), '/') . '[^ "]*/', $msg, $urlMatches) !== false) {
            foreach ($urlMatches[0] as $urlPath) {
                $url = HttpFileServerFacade::buildUrlToDownloadFile($this->getWorkbench(), $urlPath, false);
                $msg = str_replace($urlPath, $url, $msg);
            }
        }
        return $msg;
    }
    
    protected function escapeCliMessage(string $msg) : string
    {
        // TODO handle strange empty spaces in composer output
        return str_replace(["\r", "\n"], PHP_EOL, $msg);
    }
     
    /**
     * This function takes an task, and an attribute as parameter, and returns the data of
     * the tasks project data, stored under the attribute passed as a parameter.
     *
     * @param TaskInterface $task
     * @param string $projectAttributeAlias
     * @throws ActionInputMissingError
     * @return string
     */
    protected function getProjectData(TaskInterface $task, string $projectAttributeAlias) : string
    {
        if ($this->projectData === null) {
            $projectUid = $this->getBuildData($task, 'project');
            
            $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'axenox.Deployer.project');
            $ds->getColumns()->addMultiple([
                'alias',
                'build_recipe',
                'build_recipe_custom_path',
                'default_composer_json',
                'default_composer_auth_json',
                'deployment_recipe',
                'deployment_recipe_custom_path',
                'name',
                'project_group'
            ]);
            $ds->getFilters()->addConditionFromString('uid', $projectUid, ComparatorDataType::EQUALS);
            $ds->dataRead();
            $this->projectData = $ds;
        }
        return $this->projectData->getCellValue($projectAttributeAlias, 0);
    }

    /**
     * This function takes an task, and an attribute as parameter, and returns the data of
     * the tasks host data, stored under the attribute passed as a parameter.
     *
     * @param TaskInterface $task
     * @param string $option
     * @throws ActionInputMissingError
     * @return string
     */
    protected function getHostData(TaskInterface $task, string $option) : string
    {
        if ($this->hostData === null) {
            if ($task->hasParameter('host')) {
                $hostName = $task->getParameter('host');
            } else {
                $inputData = $this->getInputDataSheet($task);
                if ($col = $inputData->getColumns()->get('host')) {
                    $hostUid = $col->getCellValue(0);
                }
            }
            
            if (! $hostUid && $hostName === null) {
                throw new ActionInputMissingError($this, 'Cannot deploy build: missing host reference!', '78810KV');
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
                'stage',
                'deploy_config'
            ]);
            $ds->getFilters()->addConditionFromString('uid', $hostUid, ComparatorDataType::EQUALS);
            $ds->getFilters()->addConditionFromString('name', $hostName, ComparatorDataType::EQUALS);
            $ds->dataRead();
            $this->hostData = $ds;
        }
        return $this->hostData->getCellValue($option, 0);
    }
    
    /**
     * This function takes an task, and an attribute as parameter, and returns the data of
     * the tasks build data, stored under the attribute passed as a parameter.
     *
     * @param TaskInterface $task
     * @param string $projectAttributeAlias
     * @return string
     */
    protected function getBuildData(TaskInterface $task, string $projectAttributeAlias) : string
    {
        if ($this->buildData === null) {
            if ($task->hasParameter('build')) {
                $buildName = $task->getParameter('build');
            } else {
                $inputData = $this->getInputDataSheet($task);
                if ($col = $inputData->getColumns()->get('build')) {
                    $buildUid = $col->getCellValue(0);
                }
            }
            
            if (! $buildUid && $buildName === null) {
                throw new ActionInputMissingError($this, 'Cannot deploy build: missing build reference!', '7880ZT2');
            }
            
            $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'axenox.Deployer.build');
            $ds->getColumns()->addMultiple([
                'build_datatime',
                'build_recipe_path',
                'comment',
                'composer_auth_json',
                'composer_json',
                'log',
                'name',
                'notes',
                'project',
                'status',
                'version'
            ]);
            $ds->getFilters()->addConditionFromString('uid', $buildUid, ComparatorDataType::EQUALS);
            $ds->getFilters()->addConditionFromString('name', $buildName, ComparatorDataType::EQUALS);
            $ds->dataRead();
            $this->buildData = $ds;
        }
        return $this->buildData->getCellValue($projectAttributeAlias, 0);
    }
 
    /**
     *
     * @param TaskInterface $task
     * @return DeployerSshConnector
     */
    protected function getSshConnection(TaskInterface $task) : ?DeployerSshConnector
    {
        $connectionUid = $this->getHostData($task, 'data_connection');
        if (! $connectionUid) {
            return null;
        } else {
            return DataConnectionFactory::createFromModel($this->getWorkbench(), $connectionUid);
        }
    }
 
   /**
    * This function generates the contents of the `deploy.php` file, which is required for the depolyment process.
    * It returns the path to the `deploy.php`, relative to the working directory (`.../exface/exface`).
    *
    * @param TaskInterface $task
    * @param string $basepath
    * @param string $buildFolder
    * @param string $hostName
    * @param string $sshConfigFilePath
    * @return string
    */
    protected function createDeployPhp(TaskInterface $task, string $basepath, string $buildFolder, string $hostName, string $sshConfigFilePath = null) : string
    {
        $stage = $this->getHostData($task, 'stage');
        $absoluteSshConfigFilePath = $sshConfigFilePath !== null ? $basepath . $sshConfigFilePath : '';
        $basicDeployPath = $this->getHostData($task, 'path_abs_to_api');
        $buildsArchivesPath = $basepath . $buildFolder . DIRECTORY_SEPARATOR . $this->getFolderNameForBuilds();
        $phpPath = $this->getHostData($task, 'php_cli');
        $recipePath = $this->getDeployRecipeFile($task);
        $relativeDeployPath = $this->getHostData($task, 'path_rel_to_releases');
        $deployConfigJson = $this->getHostData($task, 'deploy_config') ?? '{}';
        try {
            $deployConfig = JsonDataType::decodeJson($deployConfigJson);
        } catch (\Throwable $e) {
            throw new ActionRuntimeError($this, 'Cannot parse deployment configuration: not a valid JSON!');
        }
        if (($deployConfig['default_app_config'] ?? null) && ($deployConfig['default_app_config']['System.config.json'] ?? null)) {
            if ($deployConfig['default_app_config']['System.config.json']['SERVER.INSTALLATION_NAME'] ?? null === null) {
                $deployFolderName = FilePathDataType::findFileName($basicDeployPath, false);
                $deployConfig['default_app_config']['System.config.json']['SERVER.INSTALLATION_NAME'] = $deployFolderName;
            }
        }
        $deployConfigPHP = var_export($deployConfig, true);
        
        $connection = $this->getSshConnection($task);
        if ($connection && ! $connection instanceof DeployerSshConnector) {
            $connectionConfigPHP = var_export($connection->exportUxonObject(), true);
        } else {
            $connectionConfigPHP = '[]';
        }
        
        $content = <<<PHP
<?php
namespace Deployer;

ini_set('memory_limit', '-1'); // deployment may exceed 128MB internal memory limit

require 'vendor/autoload.php'; // Or move it to deployer and automatically detect
require 'vendor/deployer/deployer/recipe/common.php';

// === Host ===
set('stage', '{$stage}');
set('host_ssh_config', '{$absoluteSshConfigFilePath}');
set('host_short', '{$hostName}');
host('{$hostName}');

// === Path definitions ===
set('basic_deploy_path', '{$basicDeployPath}');
set('relative_deploy_path', '{$relativeDeployPath}');
set('builds_archives_path', '{$buildsArchivesPath}');
set('php_path', '{$phpPath}');
set('deploy_config', $deployConfigPHP);
set('connection_config', $connectionConfigPHP);

require '{$recipePath}';

PHP;
        
        $content_php = fopen($buildFolder . DIRECTORY_SEPARATOR . 'deploy.php', 'w');
        fwrite($content_php, $content);
        fclose($content_php);
        
        return $buildFolder . DIRECTORY_SEPARATOR . 'deploy.php';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iCanBeCalledFromCLI::getCliArguments()
     */
    public function getCliArguments() : array
    {
        return [
            (new ServiceParameter($this))
            ->setName('build')
            ->setDescription('Name of the build to deploy')
            ->setRequired(true),
            (new ServiceParameter($this))
            ->setName('host')
            ->setDescription('Identifier of the host to deploy on')
            ->setRequired(true)
        ];
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iCanBeCalledFromCLI::getCliOptions()
     */
    public function getCliOptions() : array
    {
        return [];
    }

    /**
     * Prepares the folder structure needed to run the deployer command.
     *
     * @param TaskInterface $task
     * @return string
     */
    protected function createDeployerProjectFolder(TaskInterface $task) : string
    {
        $connection = $this->getSshConnection($task);
        
        $basePath = $this->getBasePath();
        
        //extract the data required for the SSH-connection
        if ($connection !== null) {
            $privateKey = $connection->getSshPrivateKey();
            $hostAlias = $connection->getAlias();
            
            //create /hosts/alias directory
            $hostAliasFolderPath = $this->createHostFolderPath($task, $hostAlias);
            
            // ACHTUNG: id_rsa darf nur für PHP-user lesbar sein!
            $privateKeyFilePath = $this->createPrivateKeyFile($hostAliasFolderPath, $privateKey);
            $this->createPrivateKeyFileSetPermissions($privateKeyFilePath);
            
            //create known_hosts file
            $knownHostsFilePath = $this->createKnownHostsFile($hostAliasFolderPath);
            
            //get ssh-config
            $sshConfigFilePath = $this->createSshConfig($basePath, $hostAlias, $privateKeyFilePath, $knownHostsFilePath, $hostAliasFolderPath, $connection);
        } else {
            // If no connection exists, generate the host alias from the host name.
            $sshConfigFilePath = null;
            $hostName = $this->getHostData($task, 'name');
            $hostAlias = self::getHostAlias($hostName);
        }
        
        $projectFolderPath = $this->getProjectFolderRelativePath($task);
        $this->createDeployPhp($task, $basePath, $projectFolderPath, $hostAlias, $sshConfigFilePath);
        
        return $basePath . $projectFolderPath;
    }
    
    public static function getHostAlias(string $hostName) : string
    {
        $hostAlias = str_replace(' ', '_', $hostName);
        $hostAlias = preg_replace('/[^a-z0-9_]/i', '', $hostAlias);
        return $hostAlias;
    }
    
    /**
     *
     * @return string
     */
    protected function getFolderNameForHosts() : string
    {
        return 'hosts';
    }
    
    /**
     * 
     * @return string
     */
    protected function getFileNamePrivateKey() : string
    {
        return 'id_rsa';
    }
    
    /**
     * 
     * @return string
     */
    protected function getFileNameKnownHosts() : string
    {
        return 'known_hosts';
    }
    
    /**
     * 
     * @return string
     */
    protected function getFileNameSshConfig() : string
    {
        return 'ssh_config';
    }
   
    /**
     * Creates the folder structure of the directories needed for deployment. 
     * 
     * e.g. data\deployer\testBuild\hosts\hostAlias
     * 
     * @param Taskinterface $task
     * @param string $hostAlias
     * @return string
     */
    protected function createHostFolderPath(Taskinterface $task, string $hostAlias) : string
    {
        $projectFolder = $this->getProjectFolderRelativePath($task);
        
        $hostsFolderPath = $projectFolder
        . DIRECTORY_SEPARATOR . $this->getFolderNameForHosts();
        
        $hostAliasFolderPath = $hostsFolderPath
        . DIRECTORY_SEPARATOR . $hostAlias;
        
        Filemanager::pathConstruct($hostAliasFolderPath);
        
        return $hostAliasFolderPath;
    }
    
    /**
     * Creates and fills the file for the privatekey of the SSH connection. Returns the path to the file.
     * @param string $hostAliasFolderPath
     * @param string $privateKey
     * @return string
     */
    protected function createPrivateKeyFile(string $hostAliasFolderPath, string $privateKey) :string
    {
   
        $privateKeyFileDirectory = $hostAliasFolderPath . DIRECTORY_SEPARATOR . $this->getFileNamePrivateKey();
        
        $privateKeyFile = fopen($privateKeyFileDirectory, 'w');
        fwrite($privateKeyFile, $privateKey);
        fclose($privateKeyFile);

        return $privateKeyFileDirectory;
    }
    
    /**
     * This function sets the right permissions for the file containing the private ssh-key.
     * If the parameters are not set right, the ssh-call of the deployment process might not work.
     * The permissions are set, so that only the user that calles this action and system administrators have the rights 
     * to read, write and modify the file.
     * The actual functions to set the permissions are depending on the operating system the deployer is executed on.
     * 
     * @param string $privateKeyFileDirectory
     * @param string $hostOperatingSystem
     */
    protected function createPrivateKeyFileSetPermissions(string $privateKeyFileDirectory)
    {
        $hostOperatingSystem = PHP_OS;
        
        switch ($hostOperatingSystem){
            // running on windows:
            case (strtoupper(substr($hostOperatingSystem, 0, 3)) === 'WIN') :

                $user = $this->getCurrentWinCliUsername();
                
                $commandList = [
                    'icacls ' . $privateKeyFileDirectory . ' /c /t /inheritance:d',
                    'icacls ' . $privateKeyFileDirectory . ' /c /t /remove Administrator "Authenticated Users" BUILTIN Everyone System Users',
                    'icacls ' . $privateKeyFileDirectory . ' /c /t /grant "'. $user .'":F',
                    'icacls ' . $privateKeyFileDirectory
                ];
                
                //execute all commands set in $commandList
                foreach($commandList as $cmd){
                    $process = Process::fromShellCommandline($cmd);
                    $process->mustRun();
                }
                break;
                
            // if there is no case for the used OS, just assume its linux
            default:
                chmod($privateKeyFileDirectory, 0600);
                break;
        }
        
        return;
    }
       
    /**
     * This function creates the known-hosts file which is needed for an ssh-connection.
     * The file is created empty, date will be filled automatically when an ssh-connection is established.
     * 
     * @param string $hostAliasFolderPath
     * @return string
     */
    protected function createKnownHostsFile(string $hostAliasFolderPath) : string
    {
        $knownHostsFileDirectory = $hostAliasFolderPath . DIRECTORY_SEPARATOR . $this->getFileNameKnownHosts();
        $knownHostsFile = fopen($knownHostsFileDirectory, 'w');
        fwrite($knownHostsFile, '');
        fclose($knownHostsFile);
        
        return $knownHostsFileDirectory;
    }
    
    /**
     * Creates the file including the SSH-configuration from an array including the parameters. 
     * 
     * @param string $hostAliasFolderPath
     * @param array $sshConfig
     * @return string
     */
    protected function createSshConfigFile( string $hostAliasFolderPath, array $sshConfig) : string
    {
        $sshConfigFileDirectory = $hostAliasFolderPath . DIRECTORY_SEPARATOR . $this->getFileNameSshConfig();
        
        $sshConfigString = $this->stringifySshConfigArray($sshConfig);

        $sshConfigFile = fopen($sshConfigFileDirectory, 'w');
        fwrite($sshConfigFile, $sshConfigString);
        fclose($sshConfigFile);
        
        return $sshConfigFileDirectory;
    }
    
    /**
     * This function creates the content for the ssh-connection file, returning them in form of an array.
     * The settings array is created by merging the default ssh options with the custom ones in the 
     *
     * @param string $basePath
     * @param string $host
     * @param string $privateKeyFilePath
     * @param string $knownHostsFilePath
     * @param string $hostAliasFolderPath
     * @param DeployerSshConnector|NULL $connection
     * @return string
     */
    protected function createSshConfig(string $basePath, string $host, string $privateKeyFilePath, string $knownHostsFilePath, string $hostAliasFolderPath, DeployerSshConnector $connection = null) : string
    {
        if ($connection === null) {
            return $this->createSshConfigFile($hostAliasFolderPath, []);
        }
        
        $hostName = $connection->getHostName();
        $user = $connection->getUser();
        $port = $connection->getPort();
        $customOptions = $connection->getSshConfig();
        
        $defaultSshConfig = [
             'Host' => $host,
             'HostName' => $hostName, // 10.57.2.40 // Kommt aus Dataconnection
             'User' => $user, //SFCKOENIG\ITSaltBI // Kommt aus DataConnection
             'port' => $port, //22 // Kommt aus DataConnection
             'PreferredAuthentications' => 'publickey',
             'StrictHostKeyChecking' => 'no',
             'IdentityFile' => $basePath . $privateKeyFilePath, //C:\wamp\www\sfckoenig\exface\deployer\sfc\hosts\powerui\id_rsa
             'UserKnownHostsFile' => $basePath . $knownHostsFilePath //C:\wamp\www\sfckoenig\exface\deployer\sfc\hosts\powerui\known_hosts
        ];
        
        //if there are no specific ssh options given, use the default ones from the project data.
        $sshConfig = array_merge($defaultSshConfig, $customOptions);
                
        //save the options to a file
        return $this->createSshConfigFile($hostAliasFolderPath, $sshConfig);
    }
    
    /**
     * Creates a stringified version of the ssh-config data, which the deployer can read.
     * 
     * @param array $sshConfig
     * @return string
     */
    protected function stringifySshConfigArray(array $sshConfig) : string
    {
        $sshConfigKeys = array_keys($sshConfig);
        $sshConfigValues = array_values($sshConfig);
        
        $sshConfigString = '';
        
        for($entry = 0; $entry < sizeof($sshConfig); $entry++){
            $sshConfigString .= $sshConfigKeys[$entry]
            . ' '
                . $sshConfigValues[$entry]
                . "\n";
        }
        
        return $sshConfigString;
    }
    
    /**
     * This function gets the recipe file required for the deployment. 
     * The project contains data describing which recipe is to use. 
     * 
     * @param TaskInterface $task
     * @return string
     */
    protected function getDeployRecipeFile(TaskInterface $task) : string
    {
        $recipe = $this->getProjectData($task, 'deployment_recipe');
        
        switch ($recipe) {
            case BuildRecipeDataType::CUSTOM_BUILD:
                return $this->getProjectData($task, 'deployment_recipe_custom_path');
            default:
                $recipiesBasePath = Filemanager::FOLDER_NAME_VENDOR . DIRECTORY_SEPARATOR . $this->getApp()->getDirectory() . DIRECTORY_SEPARATOR . 'Recipes' . DIRECTORY_SEPARATOR . 'Deploy' . DIRECTORY_SEPARATOR;
                return $recipiesBasePath . $recipe . '.php';
        }
    }
    
    /**
     * This function returns the cli-command, executing the deployment process.
     * 
     * Example: `- f=testbuild\deploy.php LocalBldSshSelfExtractor --build=1.0.1...tar.gz`
     * 
     * @param TaskInterface $task
     * @param string $baseFolder
     * @param DataSheetInterface $deployData
     * @return string
     */
    protected function createDeployerTask(TaskInterface $task) : string
    {
        $cmd = " -f=" . $this->getProjectFolderRelativePath($task) . DIRECTORY_SEPARATOR . 'deploy.php';
        
        // Get deployer recipe file path
        $recipePath = $this->getDeployRecipeFile($task);
        $deployerTaskName = basename($recipePath, '.php');
        
        $cmd .= ' ' . $deployerTaskName;

        $cmd .= ' --build=' . $this->getBuildData($task, 'name');
        
        return $cmd;
    }
    
    /**
     * Returns an array of environment variables which may be required for commands executed via symfony.
     * 
     * @return array
     */
    protected function getCmdEnvirontmentVars() : array
    {
        return ['ProgramData' => 'C:\\ProgramData'];
    }
    
    /**
     * Deletes every temporary file created in the deployment-process
     * @param string $projectFolder
     * @param string $hostAliasFolderPath
     */
    protected function cleanupFiles(string $projectFolder) : Deploy
    {
        $stagedFiles = [
            $projectFolder . DIRECTORY_SEPARATOR . 'deploy.php'
        ];
        
        $stagedDirectories = [
            $projectFolder . DIRECTORY_SEPARATOR . 'hosts',
            $projectFolder . DIRECTORY_SEPARATOR . $this->getFolderNameForHosts()
        ];
        
        //delete files first
        foreach($stagedFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        
        //delete directories last
        foreach($stagedDirectories as $dir) {
            if (file_exists($dir)) {
                Filemanager::deleteDir($dir);
            }
        } 
        return $this;
    }
    
    /**
     * Uses the windows commandline for getting the username which the server uses on the commandline.
     * 
     * @return string
     */
    protected function getCurrentWinCliUsername() : string
    {
        $process = Process::fromShellCommandline('whoami');
        $process->start();
        foreach ($process as $msg) {
            $user = $msg;
        }
        // replace CRLF
        $user = trim(preg_replace('/\s\s+/', ' ', $user));
        return $user;
    }
}