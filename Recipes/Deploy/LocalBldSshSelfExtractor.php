<?php
namespace Deployer;

/**
 * This file contains the recipe to deploy a local deployment archive to a remote host.
 * The Deployment is done via a self extracting and self deploying php file.
 * To do so there needs to be a deploy.php file located in the same directory as the
 * builds directory and the hosts directory for that deployment. The structure for that
 * deploy.php file is given below. To start the deployment run the following command in
 * the console from the PowerUI exface directory:
 * 
 * vendor\bin\dep -f={filepath} LocalBldSshSelfExtractor --build {build}
 * 
 * The {filepath} is the path to the deploy.php file.
 * The {build} paramter is the name of the build you want to deploy, without any file endings
 * like ".tar.gz"
 * 
 * Structure for deploy.php:

    <?php
    namespace Deployer;
    
    ini_set('memory_limit', '-1'); // deployment may exceed 128MB internal memory limit
    
    require 'vendor/autoload.php'; // Or move it to deployer and automatically detect    
    
    // === Host ===
    //host name used in ssh_config
    $hostShort = 'sdrexf2';
    set('host_short', $hostShort);
    host($hostShort);
    
    //path to ssh_config file
    $hostSshConfig = __DIR__ . '\\hosts\\' . $hostShort . '\\ssh_config';
    set('host_ssh_config', $hostSshConfig);
    
    // === Path definitions ===
    //basic deploy bath, root path for CMS
    set('basic_deploy_path', 'C:\\wamp\\www\\powerui-int');
    
    //directory in root directory where releases, shared directories etc. will be stored
    set('relative_deploy_path', 'powerui');
    
    //path to local directory where build that should be deployed is stored
    $buildsArchivesPath = __DIR__ . '\\builds';
    set('builds_archives_path', $buildsArchivesPath);
    
    //path for php executable on remote machine, default is 'php'
    set('php_path', 'php');
    
    // set number how many releases should be kept, -1 to keep all releases, default is 4
    set('keep_releases', 6);
    
    require 'vendor/axenox/deployer/Recipes/Deploy/LocalBldSshSelfExtractor.php';
    
 * The tasks are named by the file they are written in, means the task
 * 'config:setup_deploy_config' is located in the 'Config.php' file.
 */

use Symfony\Component\Console\Input\InputOption;

require 'vendor/axenox/deployer/Recipes/Config.php';
require 'vendor/axenox/deployer/Recipes/Build.php';
require 'vendor/axenox/deployer/Recipes/Deploy.php';
require 'vendor/axenox/deployer/Recipes/SelfDeployment.php';

option('build', null, InputOption::VALUE_OPTIONAL, 'test option.');

task('LocalBldSshSelfExtractor', [
    'config:setup_deploy_config',
    'build:find',
    'self_deployment:create',
    'self_deployment:upload',
    'self_deployment:run',
    'self_deployment:delete_local_file',
    'self_deployment:delete_remote_file',
    'deploy:show_release_names',
    'deploy:success'
]);
