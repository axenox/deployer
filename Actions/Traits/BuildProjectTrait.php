<?php 
namespace axenox\Deployer\Actions\Traits;

use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Exceptions\Actions\ActionInputMissingError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\Tasks\TaskInterface;


trait BuildProjectTrait{
    
    private $timeout = 900;
    
    /**
     * Returns the absolute path to the basefolder, ending with a DIRECTORY_SEPERATOR.
     * 
     * Example:
     * ```
     *  C:\wamp\www\exface\exface\
     * ```
     * 
     * @return string
     */
    protected function getBasePath() : string
    {
        return $this->getWorkbench()->filemanager()->getPathToBaseFolder() . DIRECTORY_SEPARATOR;
    }
    
    /**
     * Returns the path to the project folder, relative to the basefolder.
     * 
     * Example:
     * ```
     *  deployer\exampleHost
     * ```
     * 
     * @param TaskInterface $task
     * @return string
     */
    protected function getProjectFolderRelativePath(TaskInterface $task) : string
    {
        return 'deployer' . DIRECTORY_SEPARATOR . $this->getProjectData($task, 'alias');
    }
    
    /**
     *
     * @return string
     */
    protected function getFolderNameForBuilds() : string
    {
        return 'builds';
    }
    
    /**
     * @return int
     */
    protected function getTimeout() : int
    {
        return $this->timeout;
    }
    
    /**
     * Timeout for the Deploy/Build command in seconds.
     *
     * @uxon-property timeout
     * @uxon-type integer
     * @uxon-default 900
     *
     * @param int $seconds
     */
    public function setTimeout(int $seconds)
    {
        $this->timeout = $seconds;
        return $this;
    }
    
    protected abstract function getProjectData(TaskInterface $task, string $projectAttributeAlias) : string;
}