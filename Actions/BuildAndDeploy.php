<?php

namespace axenox\Deployer\Actions;

use axenox\Deployer\Actions\Traits\BuildProjectTrait;
use exface\Core\CommonLogic\AbstractActionDeferred;
use exface\Core\CommonLogic\Actions\ServiceParameter;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Exceptions\Actions\ActionInputError;
use exface\Core\Exceptions\Actions\ActionInputMissingError;
use exface\Core\Exceptions\Actions\ActionRuntimeError;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Factories\ActionFactory;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\TaskFactory;
use exface\Core\Interfaces\Actions\iCanBeCalledFromCLI;
use exface\Core\Interfaces\Actions\iCreateData;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\ResultMessageStreamInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;

/**
 * Builds a project variant and deploys the resulting build to one or more hosts.
 * 
 * The action forwards the given build parameters to `axenox.Deployer.Build`, waits until the deferred
 * build stream has finished, checks that the created build completed successfully and then calls
 * `axenox.Deployer.Deploy` with the created build name and the requested host parameter.
 * 
 * @author Sergej Riel
 */
class BuildAndDeploy extends AbstractActionDeferred implements iCanBeCalledFromCLI, iCreateData
{
    use BuildProjectTrait;

    private $projectData = null;

    /**
     * {@inheritdoc}
     * @see \exface\Core\CommonLogic\AbstractAction::init()
     */
    protected function init()
    {
        parent::init();
        $this->setInputRowsMin(0);
        $this->setInputRowsMax(1);
    }

    /**
     * Takes a snapshot of the original task for the deferred orchestration.
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractActionDeferred::performImmediately()
     */
    protected function performImmediately(TaskInterface $task, DataTransactionInterface $transaction, ResultMessageStreamInterface $result) : array
    {
        return [$task->copy()];
    }

    /**
     * Runs the build action first and deploys the created build after a successful build.
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractActionDeferred::performDeferred()
     */
    protected function performDeferred(TaskInterface $task = null) : \Generator
    {
        if ($task === null) {
            throw new InvalidArgumentException('Missing argument $task in deferred action call!');
        }
        
        yield PHP_EOL . '========== Starting build ==========' . PHP_EOL;
        $buildTask = $this->createSubTask(Build::class, $task->getParameters());
        $buildResult = $this->handleSubAction(Build::class, $buildTask); 
        foreach ($this->streamResult($buildResult) as $msg) {
            yield $msg;
        }

        $buildName = $this->getResultReturn($buildResult);
        
        if (! is_string($buildName) || $buildName === '') {
            throw new ActionRuntimeError($this, 'Cannot deploy build: the build action did not return the created build name!');
        }
        
        $this->validateBuildWasSuccessful($buildName);
        
        yield PHP_EOL . '========== Starting deployment for build "' . $buildName . '" ==========' . PHP_EOL;
        $deployTask = $this->createSubTask(Deploy::class, [
            'build' => $buildName,
            'host' => $this->getHostParameter($task)
        ]);
        $deployResult = $this->handleSubAction(Deploy::class, $deployTask);
        foreach ($this->streamResult($deployResult) as $msg) {
            yield $msg;
        }
    }

    /**
     * Creates a clean task for a nested action and copies the given parameters to it.
     * 
     * @param string $actionClass
     * @param array $parameters
     * @return TaskInterface
     */
    protected function createSubTask(string $actionClass, array $parameters) : TaskInterface
    {
        $subTask = TaskFactory::createEmpty($this->getWorkbench());
        $subTask->setActionSelector($actionClass);
        foreach ($parameters as $name => $value) {
            $subTask->setParameter($name, $value);
        }
        
        return $subTask;
    }

    /**
     * Instantiates and handles a nested action.
     * 
     * @param string $actionClass
     * @param TaskInterface $task
     * @return ResultInterface
     */
    protected function handleSubAction(string $actionClass, TaskInterface $task) : ResultInterface
    {
        $action = ActionFactory::createFromString($this->getWorkbench(), $actionClass);
        if (method_exists($action, 'setTimeout')) {
            $action->setTimeout($this->getTimeout());
        }
        return $action->handle($task);
    }

    /**
     * Streams a nested action result without buffering deferred output.
     * 
     * @param ResultInterface $result
     * @return \Generator
     */
    protected function streamResult(ResultInterface $result) : \Generator
    {
        if ($result instanceof ResultMessageStreamInterface) {
            yield from $result->getMessageStreamGenerator();
            return;
        }
        
        if ($result->isEmpty() === false) {
            yield $result->getMessage();
        }
    }

    /**
     * Returns the return value of a nested stream result.
     * 
     * @param ResultInterface $result
     * @return mixed
     */
    protected function getResultReturn(ResultInterface $result) : mixed
    {
        if (method_exists($result, 'getReturn')) {
            return $result->getReturn();
        }
        
        return null;
    }

    /**
     * Verifies that the build created by the nested build action exists and completed successfully.
     * 
     * @param string $buildName
     * @throws ActionRuntimeError
     * @return void
     */
    protected function validateBuildWasSuccessful(string $buildName) : void
    {
        $buildData = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'axenox.Deployer.build');
        $buildData->getColumns()->addMultiple([
            'uid',
            'name',
            'status'
        ]);
        $buildData->getFilters()->addConditionFromString('name', $buildName, ComparatorDataType::EQUALS);
        $buildData->dataRead(1);
        
        if ($buildData->isEmpty()) {
            throw new ActionRuntimeError($this, 'Cannot deploy build "' . $buildName . '": build record was not found after building!');
        }
        
        if ((int) $buildData->getCellValue('status', 0) !== 99) {
            throw new ActionRuntimeError($this, 'Cannot deploy build "' . $buildName . '": build did not finish successfully!');
        }
    }

    /**
     * Returns the host parameter for the nested deploy action.
     * 
     * @param TaskInterface $task
     * @throws ActionInputMissingError
     * @return string
     */
    protected function getHostParameter(TaskInterface $task) : string
    {
        if (! $task->hasParameter('host') || $task->getParameter('host') === '') {
            throw new ActionInputMissingError($this, 'Cannot deploy build: missing host reference!', '78810KV');
        }
        
        return $task->getParameter('host');
    }

    /**
     * Returns project data required by shared deployer project helpers.
     * 
     * @param TaskInterface $task
     * @param string $projectAttributeAlias
     * @throws ActionInputMissingError
     * @return string
     */
    protected function getProjectData(TaskInterface $task, string $projectAttributeAlias) : string
    {
        if ($this->projectData === null) {
            $projectAlias = null;
            $projectUid = null;
            $projectData = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'axenox.Deployer.project');
            $projectData->getColumns()->addMultiple([
                'uid',
                'alias'
            ]);
            
            if ($task->hasParameter('project')) {
                $projectAlias = $task->getParameter('project');
                $projectData->getFilters()->addConditionFromString('alias', $projectAlias, ComparatorDataType::EQUALS);
            }
            
            if (! $projectUid && $projectAlias === null) {
                throw new ActionInputMissingError($this, 'Cannot build and deploy: missing project reference!', '784EI40');
            }
            
            $projectData->dataRead(1);
            if ($projectData->isEmpty()) {
                throw new ActionInputError($this, "Project with alias/UID '" . ($projectAlias ?? $projectUid) . "' not found!", '784EI40');
            }
            $this->projectData = $projectData;
        }
        return $this->projectData->getCellValue($projectAttributeAlias, 0);
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Actions\iCanBeCalledFromCLI::getCliArguments()
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
                ->setRequired(true),
            (new ServiceParameter($this))
                ->setName('host')
                ->setDescription('Identifier of the host to deploy on')
                ->setRequired(true)
        ];
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Actions\iCanBeCalledFromCLI::getCliOptions()
     */
    public function getCliOptions(): array
    {
        return [
            (new ServiceParameter($this))
                ->setName('comment')
                ->setDescription('Comment to give a short description about the build.'),
            (new ServiceParameter($this))
                ->setName('notes')
                ->setDescription('You can save a note to the build to give further information.'),
            (new ServiceParameter($this))
                ->setName('php')
                ->setDescription('Custom PHP version to be used (must be registered in axenox.Deployer.config.json!)')
        ];
    }
}