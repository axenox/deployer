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
    
    
    
}