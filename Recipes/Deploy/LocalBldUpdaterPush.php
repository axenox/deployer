<?php
namespace Deployer;

/**
 * Recipe to create a self extracting php file and upload it to the axenox.PackageManager.UpdaterFacade.
 * 
 * This is very similar to `LocalBldUsbSelfExtractor`, but the deployment file is uploaded via HTTP instead
 * of printing instruction for the user to transfer it manually to the host.
 * 
 */

use Symfony\Component\Console\Input\InputOption;
use GuzzleHttp;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Utils;

require 'vendor/axenox/deployer/Recipes/Config.php';
require 'vendor/axenox/deployer/Recipes/Build.php';
require 'vendor/axenox/deployer/Recipes/SelfDeployment.php';

option('build', null, InputOption::VALUE_OPTIONAL, 'test option.');

task('updater:upload', function() {
    $filename = get('self_extractor_filename');
    $filePath = get('builds_archives_path') . DIRECTORY_SEPARATOR . $filename;
    $connectionConfig = get('connection_config');
    $phpPath = get('php_path');
    
    $defaults = array();
    $defaults['verify'] = false;
    
    // Authentication
    if ($connectionConfig['user']) {
        $defaults ['auth'] = array(
            $connectionConfig['user'],
            $connectionConfig['password']
        );
    }
    
    $httpClient = new Client($defaults);
    $url = $connectionConfig['url'];
    
    try {
        echo ("Uploading $filename to $url" . PHP_EOL);
        
        $response = $httpClient->request('POST', $url, [
            'multipart' => [
                [
                    'name'     => $filename,
                    'filename' => $filename,
                    'contents' => Utils::tryFopen($filePath, 'r')
                ]
            ]
        ]);
        
        echo $response->getBody()->__toString();
        
    } catch (\Throwable $e) {
        echo (<<<cli
        
✘ FAILED uploading to host: {$e->getMessage()}

Please transfer the self-extractor PHP file to the server manually and execute it there:

1) Copy/Download "$filePath"
2) Upload it to anywhere on the host
3) Open the host's command line as administrator (IMPORTANT - otherwise you will get symlink-errors!)
4) Run the command "$phpPath -d memory_limit=2G path/to/$filename"

cli);
    }
});

task('LocalBldUpdaterPush', [
    'config:setup_deploy_config',
    'build:find',
    'self_deployment:create',
    'updater:upload'
]);
