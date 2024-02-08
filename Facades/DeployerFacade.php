<?php
namespace axenox\Deployer\Facades;

use exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade;
use exface\Core\Facades\AbstractHttpFacade\Middleware\AuthenticationMiddleware;
use exface\Core\DataTypes\StringDataType;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\Response;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\ComparatorDataType;
use axenox\Deployer\Actions\Deploy;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\Exceptions\FileNotFoundError;
use function GuzzleHttp\Psr7\stream_for;
use exface\Core\DataTypes\SortingDirectionsDataType;
use exface\Core\Exceptions\Facades\HttpBadRequestError;
use exface\Core\Exceptions\DataSheets\DataNotFoundError;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * Handles over-the-air (OTA) updates
 * 
 * Routes: 
 * 
 * - GET api/deployer/ota/<project_alias>/<host_uid>
 * - POST api/deployer/ota/<project_alias>/<host_uid>
 * 
 * @author andrej.kabachnik
 *
 */
class DeployerFacade extends AbstractHttpFacade
{
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
                        return $this->createResponseForOTA($projectAlias, $hostName);
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
        $ds = $this->createDeploymentSheet($projectAlias, $hostName);
        $ds->getColumns()->addMultiple([
            'log'
        ]);
        $ds->dataRead();
                
        if ($ds->isEmpty()) {
            throw new DataNotFoundError($ds, 'Host "' . $hostName . '" not found in project "' . $projectAlias . '"');
        }
        
        $log = $request->getBody()->__toString() ?? '';
        if (mb_stripos($log, 'error:') !== false) {
            $status = 90;
        } else {
            $status = 99;
        }
        
        $ds->setCellValue('log', 0, $ds->getCellValue('log', 0) . PHP_EOL . PHP_EOL . $log);
        $ds->setCellValue('status', 0, $status);
        $ds->dataUpdate();
        
        return new Response(200, $this->buildHeadersCommon());
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
    protected function createResponseForOTA(string $projectAlias, string $hostName) : ResponseInterface
    {
        $ds = $this->createDeploymentSheet($projectAlias, $hostName);
        $ds->getColumns()->addMultiple([
            'build__name',
            'host__name'
        ]);
        $ds->dataRead();
        
        if ($ds->isEmpty()) {
            throw new DataNotFoundError($ds, 'Host "' . $hostName . '" not found in project "' . $projectAlias . '"');
        }
        
        $filename = $ds->getCellValue('build__name', 0) . '_' . Deploy::getHostAlias($ds->getCellValue('host__name', 0)) . '.phx';
        $path = $this->getWorkbench()->getInstallationPath() 
        . DIRECTORY_SEPARATOR . FilePathDataType::normalize($this->getApp()->getConfig()->getOption('PROJECTS_FOLDER_RELATIVE_TO_BASE'))
        . DIRECTORY_SEPARATOR . $projectAlias
        . DIRECTORY_SEPARATOR . 'builds'
        . DIRECTORY_SEPARATOR;
        
        if (! file_exists($path . $filename)) {
            throw new FileNotFoundError('Deployment file ' . $path . $filename . ' not found!');
        }
        
        $headers = array_merge($this->buildHeadersCommon(), [
            'Expires' => 0,
            'Cache-Control', 'must-revalidate, post-check=0, pre-check=0',
            'Pragma' => 'public',
            'Content-Disposition' => 'attachment; filename=' . $filename,
            'Content-Type' => 'application/x-httpd-php'
        ]);
        
        $resource = fopen($path . $filename, 'r');
        $stream = stream_for($resource);
        
        return new Response(200, $headers, $stream);
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
        $ds->getFilters()->addConditionFromString('host', $hostName);
        $ds->getFilters()->addConditionFromString('status', 60);
        
        $ds->getSorters()->addFromString('started_on', SortingDirectionsDataType::DESC);
        $ds->setRowsLimit(1);;
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