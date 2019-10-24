<?php
namespace axenox\Deployer\Actions;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Actions\iCanBeCalledFromCLI;
use exface\Core\CommonLogic\Tasks\ResultMessageStream;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use axenox\Deployer\DataTypes\BuildRecipeDataType;
use exface\Core\CommonLogic\Actions\ServiceParameter;

/**
 * Creates a build from the passed data.
 * 
 * @author Andrej Kabachnik
 *
 */
class Build extends AbstractAction implements iCanBeCalledFromCLI
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::init()
     */
    protected function init()
    {
        parent::init;
        $this->setInputRowsMin(1);
        $this->setInputRowsMax(1);
        $this->setInputObjectAlias('axenox.Deployer.build');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction): ResultInterface
    {
        $buildData = $this->getInputDataSheet($task);
        $result = new ResultMessageStream($task);
        
        $generator = function() use ($buildData, $result, $transaction) {
            
            // TODO generate build name
            $buildName = '0.1-beta+20191024115900';
            
            yield 'Building ' . $buildName;
            
            $buildData->setCellValue('state', 0, 50);
            $buildData->dataCreate(false, $transaction);
            
            $buildRecipe = $this->getBuildRecipeFile($buildData);
            
            // TODO run the deployer recipe for building and see if it is successfull! 
            // Use symfony process? Then the $output generation should like liek this:
            $log = '';
            $seconds = 0;
            foreach ($output as $msg) {
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
            
            yield 'Build ' . $buildName . ' completed in ' . $seconds . ' seconds';
            
            // IMPORTANT: Trigger regular action post-processing as required by AbstractActionDeferred.
            $this->performAfterDeferred($result, $transaction);
        };
        
        $result->setMessageStreamGenerator($generator);
        return $result;
    }
    
    protected function getBuildRecipeFile(DataSheetInterface $inputData) : string
    {
        $recipe = '';
        $recipeFile = '';
        if ($col = $inputData->getColumns()->get('project__build_recipe')) {
            $recipe = $col->getCellValue(0);
        }
        
        switch ($recipe) {
            case BuildRecipeDataType::COMPOSER_INSTALL:
                // TODO füllen von $recipeFile
        }
        
        return $recipeFile;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see iCanBeCalledFromCLI::getCliArguments()
     */
    public function getCliArguments() : array
    {
        return [
            (new ServiceParameter($this))
                ->setName('project')
                ->setDescription('Alias of the project to build'),
            (new ServiceParameter($this))
                ->setName('version')
                ->setDescription('Version number - e.g. 1.0.12 or 2.0-beta. Use sematic versioning!')
        ];
    }
    
    /**
     *
     * {@inheritdoc}
     * @see iCanBeCalledFromCLI::getCliOptions()
     */
    public function getCliOptions() : array
    {
        return [];
    }
}