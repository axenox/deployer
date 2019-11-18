<?php 
namespace axenox\Deployer\Actions\Traits;

use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Exceptions\Actions\ActionInputMissingError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\Tasks\TaskInterface;


trait BuildProjectTrait{
    
    /**
     *
     * @return string
     */
    protected function getBasePath() : string
    {
        return $this->getWorkbench()->filemanager()->getPathToBaseFolder() . DIRECTORY_SEPARATOR;
    }
    
    /**
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
     *
     * @return string
     */
    protected function getFolderNameForBaseConfig() : string
    {
        return 'base-config';
    }
    
    /**
     * @return int
     */
    protected function getTimeout() : int
    {
        return $this->timeout;
    }
    
    /**
     * Timeout for the Deploy/Build command.
     *
     * @uxon-property timeout
     * @uxon-type integer
     * @uxon-default 600
     *
     * @param int $seconds
     */
    public function setTimeout(int $seconds)
    {
        $this->timeout = $seconds;
        return $this;
    }
    
}