<?php
namespace Deployer;

/**
 * Recipe to prepare a self-extracting deployment file for download via package manager on host-side.
 * 
 */

use Symfony\Component\Console\Input\InputOption;

require 'vendor/axenox/deployer/Recipes/Config.php';
require 'vendor/axenox/deployer/Recipes/Build.php';
require 'vendor/axenox/deployer/Recipes/SelfDeployment.php';

option('build', null, InputOption::VALUE_OPTIONAL, 'test option.');

task('Updater:publish', function() {
    $filename = get('self_extractor_filename');
    $filePath = get('builds_archives_path') . DIRECTORY_SEPARATOR . $filename;
    $phpPath = get('php_path');
    
    echo (<<<cli

Deployment file "$filePath" is now read to be download via OTA self-update from the host.

Please perform self-update on target host - e.g. via CLI command "vendor/bin/action axenox.PackageManager:SelfUpdate".

Alternatively, the deployment file can be uploaded manually:

1) Open the host's command line as administrator (IMPORTANT - otherwise you will get symlink-errors!)
2) Run the command "vendor/bin/action axenox.PackageManager:SelfUpdate --download-only=true" on host.
3) Run the command "$phpPath -d memory_limit=2G path/to/$filename"

cli);
        
});

task('LocalBldUpdaterPull', [
    'config:setup_deploy_config',
    'build:find',
    'self_deployment:create',
    'Updater:publish',
    'self_deployment:show_rollback_instructions_for_cli'
]);
