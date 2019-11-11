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
use Symfony\Component\Process\Process;
use exface\Core\Interfaces\Exceptions\ActionExceptionInterface;
use axenox\Deployer\Actions\Traits\BuildProjectTrait;

/**
 * Creates a build from the passed data.
 *
 * @author Andrej Kabachnik
 *        
 */
class Build extends AbstractActionDeferred implements iCanBeCalledFromCLI, iCreateData
{
    
    use Traits\BuildProjectTrait;
    
    private $projectData = null;
    
    private $timeout = 600;

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
        // $buildData based on object axenox.Deployer.build
        try {
            $buildData = $this->getInputDataSheet($task);
        } catch (ActionInputMissingError $e) {
            $buildData = DataSheetFactory::createFromObject($this->getInputObjectExpected());
            $buildData->addRow([
                'version' => $this->getVersion($task),
                'project' => $this->getProjectData($task, 'UID'),
                'comment' => $this->getComment($task),
                'notes' => $this->getNotes($task)
            ]);
        }
        $result = new ResultMessageStream($task);

        $generator = function () use ($task, $buildData, $result, $transaction) {

            $buildName = $this->generateBuildName($task);
            
            $log = '';
            
            $msg = 'Building ' . $buildName . '...' . PHP_EOL;
            yield $msg;
            $log .= $msg;
    
            // Create build entry and mark it as "in progress"
            $buildData->setCellValue('status', 0, 50);
            $buildData->setCellValue('name', 0, $buildName);
            $buildData->dataCreate(false, $transaction);

            // Prepare project folder and deployer task file
            $projectFolder = $this->prepareDeployerProjectFolder($task);
            $buildTask = $this->prepareDeployerTask($task, $projectFolder, $buildName);

            // run the deployer task via CLI
            if (getcwd() !== $this->getWorkbench()->filemanager()->getPathToBaseFolder()) {
                chdir($this->getWorkbench()->filemanager()->getPathToBaseFolder());
            }
            $cmd .= 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . "dep {$buildTask}";

            $seconds = time();
            
            $process = Process::fromShellCommandline($cmd, null, null, null, $this->getTimeout());
            $process->start();
            foreach ($process as $msg) {
                // Live output
                yield $msg;
                // Save to log
                $log .= $msg;
            }
            
            if ($process->isSuccessful() === false) {
                $buildData->setCellValue('status', 0, 90); // failed                
                $msg = 'Building of ' . $buildName . ' failed.'; 
            } else {
                $buildData->setCellValue('status', 0, 99); // completed                
                $seconds = time() - $seconds;
                $msg = 'Build ' . $buildName . ' completed in ' . $seconds . ' seconds.';
            }

            $buildComment = $this->getComment($task);
            $buildData->setCellValue('comment', 0, $buildComment);
            
            $buildNotes = $this->getNotes($task);
            $buildData->setCellValue('notes', 0, $buildNotes);
            
            // Delete temporary files
            $this->cleanupFiles($projectFolder);
            
            // Send success/failure message
            yield $msg;
            $log .= $msg;
           
            $buildData->setCellValue('log', 0, $log); 
            
            // Update build entry's state and save log to data source 
            $buildData->dataUpdate(false, $transaction);
            
            // IMPORTANT: Trigger regular action post-processing as required by AbstractActionDeferred.
            $this->performAfterDeferred($result, $transaction);
        };

        $result->setMessageStreamGenerator($generator);
        return $result;
    }
    
    /**
     * function for getting a value out of the projects data
     *
     * @param TaskInterface $task
     * @param string $projectAttributeAlias
     * @throws ActionInputMissingError
     * @return string
     */
    protected function getProjectData(TaskInterface $task, string $projectAttributeAlias): string
    {
        if ($this->projectData === null) {
            if ($task->hasParameter('project')) {
                $projectAlias = $task->getParameter('project');
            } else {
                $inputData = $this->getInputDataSheet($task);
                if ($col = $inputData->getColumns()->get('project')) {
                    $projectUid = $col->getCellValue(0);
                }
            }
            
            if (! $projectUid && $projectAlias === null) {
                throw new ActionInputMissingError($this, 'Cannot create build: missing project reference!', '784EI40');
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
            $ds->addFilterFromString('alias', $projectAlias, ComparatorDataType::EQUALS);
            $ds->dataRead();
            $this->projectData = $ds;
        }
        return $this->projectData->getCellValue($projectAttributeAlias, 0);
    }
    
    /**
     * Returns the path to the deployer recipe file relative to the base installation folder.
     * 
     * E.g. for built-in recipes it's "vendor/axenox/deployer/Recipes..."
     * 
     * @param TaskInterface $task
     * @return string
     */
    protected function getBuildRecipeFile(TaskInterface $task): string
    {
        $recipe = $this->getProjectData($task, 'build_recipe');
        
        switch ($recipe) {
            case BuildRecipeDataType::CUSTOM_BUILD:
                return $this->getProjectData($task, 'build_recipe_custom_path');
            default:
                $recipiesBasePath = Filemanager::FOLDER_NAME_VENDOR . DIRECTORY_SEPARATOR . $this->getApp()->getDirectory() . DIRECTORY_SEPARATOR . 'Recipes' . DIRECTORY_SEPARATOR . 'Build' . DIRECTORY_SEPARATOR;
                return $recipiesBasePath . $recipe . '.php';
        }
    }
    
    /**
     * Generates a deployer task file and returns the CLI parameters to run it
     * 
     * @param TaskInterface $task
     * @param string $buildFolder
     * @param string $buildName
     * @return string
     */
    protected function prepareDeployerTask(TaskInterface $task, string $buildFolder, string $buildName) : string
    {
        $slash = DIRECTORY_SEPARATOR;
        $builds_archives_path = $slash . $this->getFolderNameForBuilds();
        $base_config_path = $slash . $this->getFolderNameForBaseConfig();

        // Get deployer recipe file path
        $recipePath = $this->getBuildRecipeFile($task);
        $deployerTaskName = basename($recipePath, '.php');
        
        $content = <<<PHP
<?php
namespace Deployer;

require 'vendor/autoload.php';
require 'vendor/deployer/deployer/recipe/common.php';

set('release_name', '{$buildName}');

// === Path definitions ===
set('builds_archives_path', __DIR__ . '{$builds_archives_path}');
set('base_config_path', __DIR__ . '{$base_config_path}');

require '{$recipePath}';

PHP;
        // Save to file
        $content_php = fopen($this->getWorkbench()->filemanager()->getPathToBaseFolder() . $slash . $buildFolder . $slash . 'build.php', 'w');
        fwrite($content_php, $content);
        fclose($content_php);
        
        // Return the deployer CLI parameters to run the task
        return "-f={$buildFolder}{$slash}build.php {$deployerTaskName}";
    }
    
    /**
     * Prepares the folder structure needed to run the deployer command and 
     * returns it's path relative to installation root.
     * 
     * ```
     * project_folder
     * - builds
     * - base-config
     * 
     * ```
     * 
     * @param TaskInterface $task
     * @return string
     */
    protected function prepareDeployerProjectFolder(TaskInterface $task) : string
    {
        $projectFolder = $this->getProjectFolderRelativePath($task);
        $basePath = $this->getWorkbench()->filemanager()->getPathToBaseFolder() . DIRECTORY_SEPARATOR;
        
        // Make sure, folder project/builds exists
        $buildsFolderPath = $projectFolder 
            . DIRECTORY_SEPARATOR . $this->getFolderNameForBuilds();
        Filemanager::pathConstruct($basePath . $buildsFolderPath);
        
        
        $baseConfigFolderPath = $projectFolder
            . DIRECTORY_SEPARATOR . $this->getFolderNameForBaseConfig();   
        Filemanager::pathConstruct($basePath . $baseConfigFolderPath);
        
        return $projectFolder;
    }

    /**
     * Generates buildname from buildversion and current time
     * @param TaskInterface $task
     * @return string
     */
    protected function generateBuildName(TaskInterface $task) : string
    {
        $timestamp = date('YmdHis');
        
        return $this->getVersion($task) . '+' . $timestamp;

    } 
    
    /**
     * gets version from task
     * 
     * @param TaskInterface $task
     * @throws ActionInputMissingError
     * @return string
     */
    protected function getVersion(TaskInterface $task) : string 
    {
        if ($task->hasParameter('version')) {
            $version = $task->getParameter('version');
        } else {
            $inputData = $this->getInputDataSheet($task);
            if ($col = $inputData->getColumns()->get('version')) {
                $version = $col->getCellValue(0);
            }
        }
        
        if ($version === null) {
            throw new ActionInputMissingError($this, 'Cannot create build: No version number provided!', '784EENG');
        } else if ($version === '') {
            throw new ActionInputMissingError($this, 'Cannot create build: Invalid/empty version number provided!', '784EENG');
        }
        
        return $version;        
    }
  
    /**
     * gets comment from task
     * 
     * @param TaskInterface $task
     * @return string
     */
    protected function getComment(TaskInterface $task) : string
    {
        $comment = '';
        if ($task->hasParameter('comment')) {
            $comment = $task->getParameter('comment');
        } else {
            try {
                $inputData = $this->getInputDataSheet($task);
                if ($col = $inputData->getColumns()->get('comment')) {
                    $comment = $col->getCellValue(0);
                }
            } catch (ActionInputMissingError $e) {
                $comment = '';
            }
        }     
        return $comment;
    }
    
    /**
     * gets notes from task
     * 
     * @param TaskInterface $task
     * @return string
     */
    protected function getNotes(TaskInterface $task) : string
    {
        $notes = '';
        if ($task->hasParameter('notes')) {
            $notes = $task->getParameter('notes');
        } else {
            try {
                $inputData = $this->getInputDataSheet($task);
                if ($col = $inputData->getColumns()->get('notes')) {
                    $notes = $col->getCellValue(0);
                }
            } catch (ActionInputMissingError $e) {
                $notes = '';
            }
        }
        return $notes;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see iCanBeCalledFromCLI::getCliArguments()
     */
    public function getCliArguments(): array
    {
        return [
            (new ServiceParameter($this))
                ->setName('project')
                ->setDescription('Alias of the project to build')
                ->setRequired(true),
            (new ServiceParameter($this))
                ->setName('version')
                ->setDescription('Version number - e.g. 1.0.12 or 2.0-beta. Use sematic versioning!')
                ->setRequired(true)
        ];
    }

    /**
     *
     * {@inheritdoc}
     * @see iCanBeCalledFromCLI::getCliOptions()
     */
    public function getCliOptions(): array
    {
        return [
            (new ServiceParameter($this))
                ->setName('comment')
                ->setDescription('Comment to give a short description about the build.'),
            (new ServiceParameter($this))
                ->setName('notes')
                ->setDescription('You can save a note to the build to give further information.')
        ];
    }
    
    /**
     * deletes all directories and files created in the building process, except the actual build (-directory)
     * 
     * @param string $src
     * @param bool $calledRecursive
     */
    protected function cleanupFiles(string $projectFolder)
    {
        $stagedFiles = [
            $projectFolder . DIRECTORY_SEPARATOR . 'build.php'
        ];
        
        $stagedDirectories = [
            $projectFolder . DIRECTORY_SEPARATOR . $this->getFolderNameForBaseConfig()
        ];   
        
        //delete files first
        foreach($stagedFiles as $file){
            unlink($file);
        }
        
        //delete directories last
        foreach($stagedDirectories as $dir){
            rmdir($dir);
        }
   
    }
    
    protected function getTimeout() : int
    {
        return $this->timeout;
    }
    
    /**
     * Timeout for the build command.
     * 
     * @uxon-property timeout
     * @uxon-type integer
     * @uxon-default 600
     * 
     * @param int $seconds
     * @return Build
     */
    public function setTimeout(int $seconds) : Build
    {
        $this->timeout = $seconds;
        return $this;
    }

}