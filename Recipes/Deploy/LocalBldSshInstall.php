<?php
namespace Deployer;

/**
 * This file contains the recipe to create a self extracting php file and deploy it via
 * SSH commands.
 * To do so there needs to be a deploy.php file located in the same directory as the
 * builds directory and the hosts directory for that deployment. The structure for that
 * deploy.php file is given below.
 * To create the deplyoment php file run the folowing command in the console from the PowerUI
 * exface directory the command:
 *
 * vendor\bin\dep -f={filepath} LocalBldSshInstall --build {build}
 *
 * The {filepath} parameter is the path to the deploy.php file.
 * The {build} paramter is the name of the build you want to deploy, without any file endings
 * like ".tar.gz"
 *
 * Structure for deploy.php:
 
 <?php
 namespace Deployer;
 
 ini_set('memory_limit', '-1'); // deployment may exceed 128MB internal memory limit
 
 require 'vendor/autoload.php'; // Or move it to deployer and automatically detect
 
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

require 'vendor/deployer/deployer/recipe/deploy/shared.php';
require 'vendor/axenox/deployer/Recipes/Config.php';
require 'vendor/axenox/deployer/Recipes/Build.php';
require 'vendor/axenox/deployer/Recipes/RemoteWindows.php';
require 'vendor/axenox/deployer/Recipes/SelfExtractor.php';
require 'vendor/axenox/deployer/Recipes/Deploy.php';
require 'vendor/axenox/deployer/Recipes/Install.php';

option('build', null, InputOption::VALUE_REQUIRED, 'Build name to deploy');

task('LocalBldSshInstall', [
    'config:setup_deploy_config',
    'build:find',
    'remote_windows:use_native_symlinks',
    'deploy:prepare',
    'self_extractor:create',
    'self_extractor:upload',
    'self_extractor:extract',
    'deploy:fix_permissions',
    'deploy:copy_directories',   
    'deploy:shared',
    'install:uninstall_old_packages',
    'deploy:create_symlinks',
    'deploy:create_shared_links',
    'install:install_current_packages',
    'deploy:cleanup_old_releases',
    'self_extractor:delete_remote_file',
    'self_extractor:delete_local_file',
    'deploy:show_release_names',
    'deploy:success'
]);