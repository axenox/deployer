<?php
namespace axenox\Deployer\Facades;

use axenox\PackageManager\Common\Updater\SelfUpdateInstaller;
use exface\Core\CommonLogic\Tasks\CliTask;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade;
use exface\Core\Facades\AbstractHttpFacade\Middleware\AuthenticationMiddleware;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Factories\ActionFactory;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\Response;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\ComparatorDataType;
use axenox\Deployer\Actions\Deploy;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\Exceptions\FileNotFoundError;
use exface\Core\DataTypes\SortingDirectionsDataType;
use exface\Core\Exceptions\Facades\HttpBadRequestError;
use exface\Core\Exceptions\DataSheets\DataNotFoundError;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * Handles over-the-air (OTA) updates
 * 
 * Routes: 
 * 
 * - `GET api/deployer/ota/<project_alias>/<host_uid>` - download an update if there is one awaiting self-update
 * - `GET api/deployer/ota/<project_alias>/<host_uid>?redeploy=true` - download an update and redeploy if none awaiting
 * - `POST api/deployer/ota/<project_alias>/<host_uid>` - upload log lines (incremental)
 * - `POST api/deployer/ota/<project_alias>/<host_uid>?final=true` - mark the log line as final
 * - `POST api/deployer/ota/<project_alias>/<host_uid>?error=true` - mark the log line explicitly as error
 * 
 * @author Andrej Kabachnik
 *
 */
class DeployerFacade extends AbstractHttpFacade
{
    private const HEADER_DEPLOYMENT_UID = 'X-Exface-Deployment-Uid';

    private const STATUS_DOWNLOADED = 65;

    private const STATUS_RUNNING_PHX = 70;

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::getUrlRouteDefault()
     */
    public function getUrlRouteDefault(): string
    {
        return 'api/deployer';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::createResponse()
     */
    protected function createResponse(ServerRequestInterface $request) : ResponseInterface
    {
        $uri = $request->getUri();
        $path = ltrim(StringDataType::substringAfter($uri->getPath(), $this->getUrlRouteDefault()), "/");
        list($route, $innerPath) = explode('/', $path, 2);
        
        switch (mb_strtolower($route)) {
            case 'ota': 
                list($projectAlias, $hostName) = explode('/', urldecode($innerPath), 2);
                switch ($request->getMethod()) {
                    case 'GET': 
                        return $this->createResponseForOTA($projectAlias, $hostName, BooleanDataType::cast($request->getQueryParams()['redeploy'] ?? false));
                    case 'POST':
                        return $this->createResponseForLog($projectAlias, $hostName, $request);
                }
                break;
                
        }
        
        $e = new HttpBadRequestError($request, 'Cannot match route ' . $route);
        $this->getWorkbench()->getLogger()->logException($e);
        return $this->createResponseFromError($e, $request);
    }
    
    /**
     * 
     * @param string $projectAlias
     * @param string $hostName
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    protected function createResponseForLog(string $projectAlias, string $hostName, ServerRequestInterface $request) : ResponseInterface
    {
        $params = $request->getQueryParams();
        $deploySheet = $this->createDeploymentPublishedSheet($projectAlias, $hostName);
        $deploymentUid = $params['deployment_uid'] ?? null;
        if ($deploymentUid !== null) {
            $deploySheet->getFilters()->addConditionFromString('uid', $deploymentUid, ComparatorDataType::EQUALS);
        }
        $deploySheet->getColumns()->addMultiple([
            'log',
            'status',
            'build__name',
            'host__name'
        ]);
        $deploySheet->dataRead();
                
        if ($deploySheet->isEmpty()) {
            throw new DataNotFoundError($deploySheet, 'Host "' . $hostName . '" not found in project "' . $projectAlias . '"');
        }

        
        $log = $deploySheet->getCellValue('log', 0);
        $logReceived = $request->getBody()->__toString() ?? '';

        $logSheet = $deploySheet->extractSystemColumns();
        $log .= $logReceived === '.' ? $logReceived : PHP_EOL . $logReceived;
        $logSheet->setCellValue('log', 0, $log);

        $status = $params['status'] ?? null;
        if ($status !== null) {
            $status = (int) $status;
        }
        $isError = 
            array_key_exists('error', $params)  // Received data was explicitly marked as error by the remote
            || $this->isCliError($logReceived)
        ;
        // The log message is final if it is marked as such or there is no URL param at ALL (to be backwards compatible
        // with older installations, that will not use the URL parameter) 
        switch (true) {
            case array_key_exists('final', $params);
                $isFinal = $params['final'];
                break;
            case preg_match('/^Finished self-update successfully!/', $logReceived):
            case preg_match('/^FAILED self-update!/', $logReceived):
                $isFinal = true;
                break;
            default: 
                $isFinal = false;
                break;
        }
        
        if ($isFinal) {
            if ($status === null) {
                if ($isError) {
                    $status = 90;
                } else {
                    $status = 99;
                }
            }
            $logSheet->setCellValue('completed_on', 0, DateTimeDataType::now());
        }

        if ($status !== null) {
            $logSheet->setCellValue('status', 0, $status);
        }

        $logSheet->dataUpdate();
        
        if (
            $deploymentUid !== null
            && in_array($status, [self::STATUS_DOWNLOADED, self::STATUS_RUNNING_PHX], true)
            && $this->isLatestPublishedDeployment($projectAlias, $hostName, $deploymentUid)
        ) {
            $this->deleteDeploymentFile(
                $projectAlias,
                $deploySheet->getCellValue('build__name', 0),
                $deploySheet->getCellValue('host__name', 0)
            );
        }

        return new Response(200, $this->buildHeadersCommon());
    }
    
    protected function isCliError(string $cliOutput): bool {
        return mb_strpos($cliOutput, 'ERROR') !== false
            || mb_strpos($cliOutput, 'FAILED') !== false
            || mb_stripos($cliOutput, SelfUpdateInstaller::MESSAGE_INSTALLATION_FAILED)
            || mb_stripos($cliOutput, 'PHP Fatal error') // CLI errors - e.g. `PHP Fatal error:  Composer detected issues in your platform: ...`
        ;
    }
    
    /**
     * Looks for a pending deployment (in status 60) and returns the corresponding file for download
     * 
     * FIXME save the filename in the deployment data, so it does not need to be calculated
     * here and the recipies are free to use any filename they like
     * FIXME give hosts aliases too. UIDs for hosts are not really comfortable
     * 
     * @param string $projectAlias
     * @param string $hostName
     * @throws FileNotFoundError
     * @return ResponseInterface
     */
    protected function createResponseForOTA(string $projectAlias, string $hostName, bool $redeploy = false) : ResponseInterface
    {
        $lockPath = FilePathDataType::join([
            $this->getWorkbench()->filemanager()->getPathToCacheFolder(),
            'deployer-ota-' . hash('sha256', $projectAlias . "\0" . $hostName) . '.lock'
        ]);
        $lock = @fopen($lockPath, 'c');
        if ($lock === false) {
            throw new RuntimeException('Cannot create OTA download lock "' . $lockPath . '"');
        }
        if (flock($lock, LOCK_EX | LOCK_NB) === false) {
            fclose($lock);
            return new Response(304, $this->buildHeadersCommon(), 'No updates found for project "' . $projectAlias . '": another update request is active');
        }

        try {
            return $this->createResponseForOTALocked($projectAlias, $hostName, $redeploy);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Selects and reserves an OTA deployment while holding the host-specific download lock.
     *
     * @param string $projectAlias
     * @param string $hostName
     * @param bool $redeploy
     * @return ResponseInterface
     */
    protected function createResponseForOTALocked(string $projectAlias, string $hostName, bool $redeploy) : ResponseInterface
    {
        $headers = $this->buildHeadersCommon();
        $activeDeployment = $this->createDeploymentActiveSheet($projectAlias, $hostName);
        $activeDeployment->dataRead();
        if (! $activeDeployment->isEmpty()) {
            return new Response(304, $headers, 'No updates found for project "' . $projectAlias . '": another deployment is active');
        }

        $ds = $this->createDeploymentDownloadableSheet($projectAlias, $hostName);
        $ds->getColumns()->addMultiple([
            'build__name',
            'host__name'
        ]);
        $ds->dataRead();
        
        if ($ds->isEmpty()) {
            if ($redeploy === true) {
                try {
                    $this->redeploy($projectAlias, $hostName);
                    $ds = $this->createDeploymentDownloadableSheet($projectAlias, $hostName);
                    $ds->getColumns()->addMultiple([
                        'build__name',
                        'host__name'
                    ]);
                    $ds->dataRead();
                } catch (\Throwable $e) {
                    return new Response(500, $headers, 'OTA redeployment failed: ' . $e->getMessage());
                }
            } else {
                return new Response(304, $headers, 'No updates found for project "' . $projectAlias . '"');
            }
        }
        
        $filePath = $this->getDeploymentFilePath(
            $projectAlias,
            $ds->getCellValue('build__name', 0),
            $ds->getCellValue('host__name', 0)
        );
        $filename = basename($filePath);
        
        if (! file_exists($filePath)) {
            throw new FileNotFoundError('Deployment file ' . $filePath . ' not found!');
        }

        $downloadMaxExecutionTime = (int) $this->getApp()->getConfig()->getOption('OTA_DOWNLOAD_MAX_EXECUTION_TIME');
        $currentMaxExecutionTime = (int) ini_get('max_execution_time');
        if ($currentMaxExecutionTime > 0 && $currentMaxExecutionTime < $downloadMaxExecutionTime) {
            set_time_limit($downloadMaxExecutionTime);
        }
        
        $headers = array_merge($headers, [
            'Expires' => 0,
            'Cache-Control', 'must-revalidate, post-check=0, pre-check=0',
            'Pragma' => 'public',
            'Content-Disposition' => 'attachment; filename=' . $filename,
            'Content-Type' => 'application/x-httpd-php',
            self::HEADER_DEPLOYMENT_UID => $ds->getCellValue('uid', 0)
        ]);
        
        $resource = fopen($filePath, 'r');
        $stream = Utils::streamFor($resource);
        
        $statusSheet = $ds->extractSystemColumns();
        $statusSheet->setCellValue('status', 0, 62);
        $statusSheet->dataUpdate();
        
        return new Response(200, $headers, $stream);
    }

    /**
     * Returns the absolute path of the self-deployment file for a deployment.
     *
     * @param string $projectAlias
     * @param string $buildName
     * @param string $hostName
     * @return string
     */
    protected function getDeploymentFilePath(string $projectAlias, string $buildName, string $hostName) : string
    {
        $filename = $buildName . '_' . Deploy::getHostAlias($hostName) . '.phx';
        if (
            in_array($projectAlias, ['', '.', '..'], true)
            || preg_match('/[\/\\\\\x00]/', $projectAlias)
            || preg_match('/[\/\\\\\x00]/', $filename)
        ) {
            throw new RuntimeException('Cannot determine deployment file path from invalid project, build or host name');
        }

        return FilePathDataType::join([
            $this->getWorkbench()->getInstallationPath(),
            $this->getApp()->getConfig()->getOption('PROJECTS_FOLDER_RELATIVE_TO_BASE'),
            $projectAlias,
            'builds',
            $filename
        ]);
    }

    /**
     * Deletes a self-deployment file after the target host confirms that it has been downloaded.
     *
     * @param string $projectAlias
     * @param string $buildName
     * @param string $hostName
     * @return void
     */
    protected function deleteDeploymentFile(string $projectAlias, string $buildName, string $hostName) : void
    {
        $filePath = $this->getDeploymentFilePath($projectAlias, $buildName, $hostName);
        if (! file_exists($filePath)) {
            return;
        }

        if (@unlink($filePath) === false) {
            $this->getWorkbench()->getLogger()->logException(
                new RuntimeException('Downloaded deployment file "' . $filePath . '" could not be deleted')
            );
        }
    }

    /**
     * @param string $projectAlias
     * @param string $hostName
     * @return void
     */
    protected function redeploy(string $projectAlias, string $hostName)
    {
        $lastCompletedSheet = $this->createDeploymentCompletedSheet($projectAlias, $hostName);
        $lastCompletedSheet->getColumns()->addMultiple([
            'build__name'
        ]);
        if ($lastCompletedSheet->isEmpty()) {
            throw new RuntimeException('Cannot redeploy to host "' . $hostName . '": no previous successfully deployments found');
        }
        $buildName =  $lastCompletedSheet->getCellValue('build__name', 0);
        $task = new CliTask(
            $this->getWorkbench(), 
            'deploy', 
            [
                'build' => $buildName,
                'host' => $hostName
            ]
        );
        $deployAction = ActionFactory::createFromString($this->getWorkbench(), Deploy::class);
        $result = $deployAction->handle($task);
        $output = $result->getMessage();
        if ($this->isCliError($output)) {
            throw new RuntimeException('Redeployment failed: ' . $output);
        }
    }
    
    /**
     * 
     * @param string $projectAlias
     * @param string $hostName
     * @return DataSheetInterface
     */
    protected function createDeploymentSheet(string $projectAlias, string $hostName) : DataSheetInterface
    {
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'axenox.Deployer.deployment');
        $ds->getColumns()->addFromSystemAttributes();
        
        $ds->getFilters()->addConditionFromString('host__project__alias', $projectAlias, ComparatorDataType::EQUALS);
        $ds->getFilters()->addConditionFromString('host', $hostName, comparatorDataType::EQUALS);
        
        $ds->getSorters()->addFromString('started_on', SortingDirectionsDataType::DESC);
        $ds->setRowsLimit(1);;
        return $ds;
    }

    /**
     * @param string $projectAlias
     * @param string $hostName
     * @return DataSheetInterface
     */
    protected function createDeploymentPublishedSheet(string $projectAlias, string $hostName) : DataSheetInterface
    {
        $ds = $this->createDeploymentSheet($projectAlias, $hostName);
        $ds->getFilters()->addConditionFromString('status', 60, ComparatorDataType::GREATER_THAN_OR_EQUALS);
        $ds->getFilters()->addConditionFromString('status', 90, ComparatorDataType::LESS_THAN);
        return $ds;
    }

    /**
     * @param string $projectAlias
     * @param string $hostName
     * @return DataSheetInterface
     */
    protected function createDeploymentDownloadableSheet(string $projectAlias, string $hostName) : DataSheetInterface
    {
        $ds = $this->createDeploymentSheet($projectAlias, $hostName);
        $ds->getFilters()->addConditionFromString('status', 60, ComparatorDataType::EQUALS);
        return $ds;
    }

    /**
     * @param string $projectAlias
     * @param string $hostName
     * @return DataSheetInterface
     */
    protected function createDeploymentActiveSheet(string $projectAlias, string $hostName) : DataSheetInterface
    {
        $ds = $this->createDeploymentSheet($projectAlias, $hostName);
        $ds->getFilters()->addConditionFromString('status', 62, ComparatorDataType::GREATER_THAN_OR_EQUALS);
        $ds->getFilters()->addConditionFromString('status', 80, ComparatorDataType::LESS_THAN);
        return $ds;
    }

    /**
     * Returns whether the deployment is still the latest published deployment for the host.
     *
     * @param string $projectAlias
     * @param string $hostName
     * @param string $deploymentUid
     * @return bool
     */
    protected function isLatestPublishedDeployment(string $projectAlias, string $hostName, string $deploymentUid) : bool
    {
        $latestDeployment = $this->createDeploymentPublishedSheet($projectAlias, $hostName);
        $latestDeployment->dataRead();

        return ! $latestDeployment->isEmpty()
            && $latestDeployment->getCellValue('uid', 0) === $deploymentUid;
    }

    /**
     * @param string $projectAlias
     * @param string $hostName
     * @return DataSheetInterface
     */
    protected function createDeploymentCompletedSheet(string $projectAlias, string $hostName) : DataSheetInterface
    {
        $ds = $this->createDeploymentSheet($projectAlias, $hostName);
        $ds->getFilters()->addConditionFromString('status', 99, ComparatorDataType::EQUALS);
        return $ds;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::getMiddleware()
     */
    protected function getMiddleware() : array
    {
        $middleware = parent::getMiddleware();
        $middleware[] = new AuthenticationMiddleware(
            $this,
            [
                [AuthenticationMiddleware::class, 'extractBasicHttpAuthToken']
            ]
        );
        
        return $middleware;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::buildHeadersCommon()
     */
    protected function buildHeadersCommon() : array
    {
        // TODO add more headers
        return parent::buildHeadersCommon();
    }
}