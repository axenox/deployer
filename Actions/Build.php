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
use exface\Core\Interfaces\Events\TaskEventInterface;
use exface\Core\Exceptions\Actions\ActionInputInvalidObjectError;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Tasks\ResultMessageStreamInterface;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Exceptions\Actions\ActionInputError;

/**
 * Creates a new build for a project from one of the build variants configured for it.
 * 
 * ## Parameters
 * 
 * This action takes the following parameters
 * 
 * - project alias
 * - build number for the new build (see below)
 * - name of the build variant to use
 * - build comment - optional - a short text describing the build
 * - notes - optional - a (longer) text for side-notes
 * 
 * The parameters can either be passed via command line or with input data (as values for the
 * respective meta attributes of the build object).
 * 
 * The action creates a build archive named after a comination of the verison number and current time seperated 
 * by a '+' character: `[version]+yyyymmddhhmmss`, e.g. `1.0-beta+20191108145134`. The achrive-file is saved 
 * within the deployers data folder in `[config:PROJECTS_FOLDER][hostname]/[buildfolder]/[buildname].tar.gz`. 
 * 
 * After completing the building process, you can deploy the build to a host of your choice, using the action 
 * `axenox.Deployer.Deploy` or the corresponding the CLI command `axenox.Deployer:Deploy`. 
 * 
 * ## Build numbers
 * 
 * The build number can be anything you like, but it is recommended to use [semantic versioning](https://semver.org/) 
 * to make build number well readable and ensure proper sorting by build number.
 * 
 * Examples:
 * 
 * - `1.0`
 * - `1.0.01`
 * - `1.1-beta01`
 * 
 * Remember, that a build name derived from the current time will be added automatically!
 * 
 * ## Commandline Usage:
 * 
 * ```
 * vendor\bin\action axenox.Deployer:Build [project alias] [version] [variant name] <--comment some text> <--notes some text>
 * 
 * ```
 * 
 * For example:
 * ```
 * action axenox.Deployer:Build testProject 1.0-beta "test build"
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 *        
 */
class Build extends AbstractActionDeferred implements iCanBeCalledFromCLI, iCreateData
{
    use BuildProjectTrait;
    
    private $projectData = null;
    private $buildVariantData = null;

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
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractActionDeferred::performImmediately()
     */
    protected function performImmediately(TaskInterface $task, DataTransactionInterface $transaction, ResultMessageStreamInterface $result) : array
    {
        // $buildData based on object axenox.Deployer.build
        try {
            $buildData = $this->getInputDataSheet($task);
        } catch (ActionInputMissingError $e) {
            $buildData = DataSheetFactory::createFromObject($this->getInputObjectExpected());
            $buildData->addRow([
                'version' => $this->getVersion($task),
                'project' => $this->getProjectData($task, 'uid'),
                'comment' => $this->getComment($task),
                'notes' => $this->getNotes($task),
                'build_variant' => $this->getBuildVariantData($task, 'uid'),
                'composer_json' => $this->getBuildVariantData($task, 'composer_json') ?? '{}',
                'composer_auth_json' => $this->getBuildVariantData($task, 'composer_auth_json') ?? '{}'
            ]);
        }
        
        return [$task, $buildData];
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractActionDeferred::performDeferred()
     */
    protected function performDeferred(TaskInterface $task = null, DataSheetInterface $buildData = null) : \Generator
    {
        if ($task === null) {
            throw new InvalidArgumentException('Missing argument $task in deferred action call!');
        }
        
        if ($buildData === null) {
            throw new InvalidArgumentException('Missing argument $buildData in deferred action call!');
        }
        
        $buildName = $this->generateBuildName($task);
        
        $log = '';
        
        $msg = 'Building ' . $buildName . '...' . PHP_EOL;
        yield $msg;
        $log .= $msg;
        
        // Create build entry and mark it as "in progress"
        $buildData->setCellValue('status', 0, 50);
        $buildData->setCellValue('name', 0, $buildName);
        // Do not use the transaction to force force creating a separate one for this operation.
        $buildData->dataCreate(false);
        
        try {
            // Prepare project folder and deployer task file
            $projectFolder = $this->prepareDeployerProjectFolder($task);
            $buildTask = $this->prepareDeployerTask($task, $projectFolder, $buildName);
            
            $composerJson = $this->createComposerJson($task, $projectFolder);
            $buildData->setCellValue('composer_json', 0, $composerJson);
            
            $composerAuthJson = $this->createComposerAuthJson($task, $projectFolder);
            $buildData->setCellValue('composer_auth_json', 0, $composerAuthJson);
            
            $buildData->setCellValue('comment', 0, $this->getComment($task));
            $buildData->setCellValue('notes', 0, $this->getNotes($task));
            
            // run the deployer task via CLI
            if (getcwd() !== $this->getBasePath()) {
                chdir($this->getBasePath());
            }
            $cmd .= 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . "dep {$buildTask}";
            
            $seconds = time();
            
            $environmentVars = $this->getCmdEnvironmentVars($projectFolder);
            
            $process = Process::fromShellCommandline($cmd, null, $environmentVars, null, $this->getTimeout());
            $process->start();
            foreach ($process as $msg) {
                // Live output
                yield $msg;
                // Save to log
                $log .= $msg;
                // Save current log to DB
                $buildData->setCellValue('log', 0, $log);
                $buildData->dataUpdate(false);
            }
            
            if ($process->isSuccessful() === false) {
                $buildData->setCellValue('status', 0, 90); // failed
                $msg = '✘ FAILED building ' . $buildName . '.';
            } else {
                $buildData->setCellValue('status', 0, 99); // completed
                $seconds = time() - $seconds;
                $msg = '✔ SUCCEEDED building ' . $buildName . ' in ' . $seconds . ' seconds.';
            }
            $buildData->dataUpdate(false);
            
            $composerLockPath = $this->getBasePath() . $projectFolder . DIRECTORY_SEPARATOR . 'composer.lock';
            if (file_exists($composerLockPath)) {
                $buildData->setCellValue('composer_lock', 0, file_get_contents($composerLockPath));
            }
            
            // Delete temporary files
            $this->cleanupFiles($projectFolder);
            
            // Send success/failure message
            yield $msg;
            $log .= $msg;
            
            $buildData->setCellValue('log', 0, $log);
            
            // Update build entry's state and save log to data source
            $buildData->dataUpdate(false);
        } catch (\Throwable $e) {
            $log .= PHP_EOL . '✘ ERRROR: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();
            $buildData->setCellValue('log', 0, $log);
            $buildData->setCellValue('status', 0, 90); // failed
            $this->getWorkbench()->getLogger()->logException($e);
            $buildData->dataUpdate(false);
        }
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
                'build_recipe_custom_path'
            ]);
            $ds->getFilters()->addConditionFromString('uid', $projectUid, ComparatorDataType::EQUALS);
            $ds->getFilters()->addConditionFromString('alias', $projectAlias, ComparatorDataType::EQUALS);
            $ds->dataRead();
            $this->projectData = $ds;
        }
        return $this->projectData->getCellValue($projectAttributeAlias, 0);
    }
    
    /**
     * This function takes an task, and an attribute as parameter, and returns the data of
     * the tasks build variant data, stored under the attribute passed as a parameter.
     * 
     * @param TaskInterface $task
     * @param string $buildVariantAttributeAlias
     * @throws ActionInputMissingError
     * @return mixed
     */
    protected function getBuildVariantData(TaskInterface $task, string $buildVariantAttributeAlias)
    {
        if ($this->buildVariantData === null) {
            if ($task->hasParameter('variant')) {
                $buildVariant = $task->getParameter('variant');
            } else {
                $inputData = $this->getInputDataSheet($task);
                if ($col = $inputData->getColumns()->get('build_variant')) {
                    $buildVariant = $col->getCellValue(0);
                }
            }
            
            if ($buildVariant === null) {
                throw new ActionInputMissingError($this, 'Cannot create build: No Build Variant provided!', '7FGBG9F');
            } elseif ($buildVariant === '') {
                throw new ActionInputMissingError($this, 'Cannot create build: Invalid/empty Build Variant provided!', '7FGBG9F');
            }
            
            $projectUid = $this->getProjectData($task, 'uid');
            $projectAlias = $this->getProjectData($task, 'alias');
            
            $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'axenox.Deployer.build_variant');
            $ds->getColumns()->addMultiple([
                'uid',
                'project',
                'name',
                'composer_json',
                'composer_auth_json'
            ]);            
            $filterNameOrUID = ConditionGroupFactory::createForDataSheet($ds, EXF_LOGICAL_OR);
            $filterNameOrUID->addConditionFromString('uid', $buildVariant, ComparatorDataType::EQUALS);
            $filterNameOrUID->addConditionFromString('name', $buildVariant, ComparatorDataType::EQUALS);
            $ds->getFilters()
                ->addConditionFromString('project', $projectUid, ComparatorDataType::EQUALS)
                ->addNestedGroup($filterNameOrUID);
            $ds->dataRead();
            if ($ds->isEmpty()) {
                throw new ActionInputError($this, "There is no build variant with alias or UID '{$buildVariant}' associated with the project '{$projectAlias}'", '7FGBUMO');
            }
            $this->buildVariantData = $ds;
        }
        return $this->buildVariantData->getCellValue($buildVariantAttributeAlias, 0);
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
     * Generates a deployer task file named `build.php` and returns the CLI arguments to run the actual build command.
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
     * returns it's path relative to workbench installation root.
     * 
     * ```
     * project_folder
     * - builds
     * 
     * ```
     * 
     * @param TaskInterface $task
     * @return string
     */
    protected function prepareDeployerProjectFolder(TaskInterface $task) : string
    {
        $projectFolder = $this->getProjectFolderRelativePath($task);
        $basePath = $this->getBasePath();
        
        // Make sure, folder project/builds exists
        $buildsFolderPath = $projectFolder 
            . DIRECTORY_SEPARATOR . $this->getFolderNameForBuilds();
        Filemanager::pathConstruct($basePath . $buildsFolderPath);
        
        // copy current composer.phar to project folder, so it can be used for composer commands.
        if (file_exists($this->getBasePath() . 'composer.phar')) {
            $this->getWorkbench()->filemanager()->copyFile($this->getBasePath() . 'composer.phar', $this->getBasePath() . $projectFolder . DIRECTORY_SEPARATOR . 'composer.phar');
        }
        
        return $projectFolder;
    }

    /**
     * Generates buildname from buildversion and current time. 
     * The returned buildname is the concatinaton of the versionnumber and a timestamp of the current time,
     * formatted as `YYYYMMDDhhmmss`, seperated by a `+` character. 
     * Example: `1.0-beta+201911210859`
     * 
     * @param TaskInterface $task
     * @return string
     */
    protected function generateBuildName(TaskInterface $task) : string
    {
        $timestamp = date('YmdHis');
        
        return $this->getVersion($task) . '+' . $timestamp;

    } 
    
    /**
     * This function gets the version for the current build, passed as an parameter for this action.
     * Because of this Parameter being required for the build action, this function throws an `ActionInputMissingError` 
     * if the version is missing or invalid.
     * Any non-empty string is valid as parameter for the version.
     * The version number is returned as a string. 
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
     * This function gets the comment, possibly passed as an argument to the build action, and returns them as a string.
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
     * This function gets the notes, possibly passed as an argument to the build action, and returns them as a string.
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
     * This function retruns the valid `composer.json` for the current build action.
     * The function achives this by getting the default `composer.json` from the project data of the current task,
     * and an custom one, which may be passed as an argument for that specific build action.
     * If there is a custom json passed for `composer.json` the function returns it, else it will return the default one. 
     * 
     * @param TaskInterface $task
     * @return string
     */
    protected function getComposerJson(TaskInterface $task) : string
    {
        $defaultComposerJson = $this->getProjectData($task, 'default_composer_json');
        
        if ($task->hasParameter('composer_json')) {
            $customComposerJson = $task->getParameter('composer_json');
        } else {
            try {
                $inputData = $this->getInputDataSheet($task);
                if ($col = $inputData->getColumns()->get('composer_json')) {
                    $customComposerJson = $col->getCellValue(0);
                }
            } catch (ActionInputMissingError $e) {
                $customComposerJson = null;
            }
        }

        return $this->getRelevantObject($defaultComposerJson, $customComposerJson);       
    }
    
    /**
     * This function takes a stringifed json and saves it to the `composer.json` in the current projects working directory.
     * 
     * @param TaskInterface $task
     * @param string $projectFolder
     * @return string
     */
    protected function createComposerJson(TaskInterface $task, string $projectFolder) : string
    {
        $content = $this->getBuildVariantData($task, 'composer_json');
        file_put_contents($this->getBasePath() . $projectFolder . DIRECTORY_SEPARATOR . 'composer.json', $content);
        return $content;
    }
    
    /**
     * This function retruns the valid `auth.json` for the current build action.
     * The function achives this by getting the default `auth.json` from the project data of the current task,
     * and an custom one, which may be passed as an argument for that specific build action.
     * If there is a custom json passed for `auth.json` the function returns it, else it will return the default one. 
     * 
     * @param TaskInterface $task
     * @return string
     */
    protected function getComposerAuthJson(TaskInterface $task) : string
    {
        $defaultComposerAuthJson = $this->getProjectData($task, 'default_composer_auth_json');
        
        if ($task->hasParameter('composer_auth_json')) {
            $customComposerAuthJson = $task->getParameter('composer_auth_json');
        } else {
            try {
                $inputData = $this->getInputDataSheet($task);
                if ($col = $inputData->getColumns()->get('composer_auth_json')) {
                    $customComposerAuthJson = $col->getCellValue(0);
                }
            } catch (ActionInputMissingError $e) {
                $customComposerAuthJson = null;
            }
        }
        
        return $this->getRelevantObject($defaultComposerAuthJson, $customComposerAuthJson);
    }
    
    /**
     * 
     * @param TaskInterface $task
     * @param string $projectFolder
     * @return string
     */
    protected function createComposerAuthJson(TaskInterface $task, string $projectFolder) : string
    {
        $content = $this->getBuildVariantData($task, 'composer_auth_json');
        file_put_contents($this->getBasePath() . $projectFolder . DIRECTORY_SEPARATOR . 'auth.json', $content);
        return $content;
    }
    
    /**
     * This function returns takes two strings as parameters, one as default and one as option. If the option is null, it uses the default one.
     * If both strings are null, it returns an empty string. Else it returns the optional string. 
     * 
     * @param string $default
     * @param string $optional
     * @return string
     */
    protected function getRelevantObject(string $default, string $optional) : string
    {
        switch (true){
            case ($optional === null):
                $result = $default;
                break;
            case ($optional === null && $default === null):
                return '';
            default:
                $result = $optional;
                break;
        }
        $result = json_decode($result, true);
        return json_encode($result);
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
                ->setRequired(true),
            (new ServiceParameter($this))
                ->setName('variant')
                ->setDescription('Build variant name or UID')
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
     * @param string $projectFolder
     */
    protected function cleanupFiles(string $projectFolder)
    {
        $baseAbsPath = $this->getBasePath();
        $stagedFiles = [
            $projectFolder . DIRECTORY_SEPARATOR . 'build.php',
            $projectFolder . DIRECTORY_SEPARATOR . 'composer.json',
            $projectFolder . DIRECTORY_SEPARATOR . 'composer.lock',
            $projectFolder . DIRECTORY_SEPARATOR . 'auth.json'
        ];
        
        $stagedDirectories = [
            // .composer folder contains the composer cache. Perhaps it's not bad too keep it...
            // $baseAbsPath . $projectFolder . DIRECTORY_SEPARATOR . '.composer'
        ];   
        
        //delete files first
        foreach($stagedFiles as $file){
            if (file_exists($file)){
                unlink($file);
            }
        }
        
        //delete directories last
        foreach($stagedDirectories as $dir){
            Filemanager::deleteDir($dir);
        }
   
    }
    
    /**
     * Returns the environment variables needed for the cli to work with symfony.
     * 
     * @param string $projectFolder
     * @return array
     */
    protected function getCmdEnvironmentVars(string $projectFolder) : array
    {
        $composerHomePath = $this->getComposerHomePath($projectFolder);
        return [
            'COMPOSER_HOME' => $composerHomePath,
        ];
    }
    
    /**
     * Returns absolute path to .composer file in the projects working directory.
     * 
     * Example: `C:\wamp\www\exface\exface\deployer\testHost\.composer`
     * 
     * @param string $projectFolder
     * @return string
     */
    protected function getComposerHomePath(string $projectFolder) : string
    {
        return $this->getBasePath() . $projectFolder . DIRECTORY_SEPARATOR . '.composer';
    }

}