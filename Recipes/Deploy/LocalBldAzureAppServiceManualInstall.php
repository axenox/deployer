<?php
namespace Deployer;

/**
 * This file contains the recipe to create a self extracting and upload it to Microsoft Azure.
 * 
 * This is very similar to LocalBldUsbSelfExtractor, but yields a Azure-specific instructions at the end.
 * 
 */

use Symfony\Component\Console\Input\InputOption;

require 'vendor/axenox/deployer/Recipes/Config.php';
require 'vendor/axenox/deployer/Recipes/Build.php';
require 'vendor/axenox/deployer/Recipes/SelfDeployment.php';

option('build', null, InputOption::VALUE_OPTIONAL, 'test option.');

/**
 * show link to created local self deployment php file
 */
task('azure:upload', function() {
    $filename = get('self_extractor_filename');
    $filePath = get('builds_archives_path') . DIRECTORY_SEPARATOR . $filename;
    $phpPath = get('php_path');
    $text = <<<cli
    
ⓘ Please follow these steps to upload the build to your Azure App Service and install it there:

1) Copy/Download "$filePath" to your downloads folder (`C:\Users\%USERNAME%\Downloads`)
2) Open the command line on your PC and copy/paste the following command 
cd c:\Users\%USERNAME%\Downloads && powershell Compress-Archive "$filename" $filename.zip && curl -v -X POST -u {Username}:{Password} https://{AppServiceName}.scm.azurewebsites.net/api/zipdeploy -T azure.zip && del $filename.zip
    Replace placehodlers as follows: 
        - {Username} and {Password} are the credntials of an Azure FTP(S) user. The {Username} must NOT include any domain like `{AppServiceName}\`!
        - {AppServiceName} is the name of the service (i.e. the "xxx" in https://xxx.azurewebsites.net)
3) Wait untill the upload finishes and something like `< HTTP/1.1 200 OK` is shown - this can take 10-15 minutes for 100MB
5) Log in to Azure Portal (https://portal.azure.com), open your App Service, navigate to `Development Tools > SSH` and press `Go`
6) Log in again to open the web SSH promt
7) Run the command "$phpPath -d memory_limit=2G path/to/$filename"

cli;
    echo ($text);
});

task('LocalBldAzureAppServiceManualInstall', [
    'config:setup_deploy_config',
    'build:find',
    'self_deployment:create',
    'azure:upload'
]);
