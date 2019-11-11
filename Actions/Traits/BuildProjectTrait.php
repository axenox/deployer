<?php 
namespace axenox\Deployer\Actions\Traits;

use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Exceptions\Actions\ActionInputMissingError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\Tasks\TaskInterface;


trait BuildProjectTrait{
    
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
    
    
    
}