<?php
namespace Deployer;

use Symfony\Component\Console\Input\InputOption;

require 'vendor/axenox/deployer/Recipes/Config.php';
require 'vendor/axenox/deployer/Recipes/Build.php';
require 'vendor/axenox/deployer/Recipes/RemoteWindows.php';
require 'vendor/axenox/deployer/Recipes/SelfExtractor.php';
require 'vendor/axenox/deployer/Recipes/Deploy.php';
require 'vendor/axenox/deployer/Recipes/Install.php';

option('build', null, InputOption::VALUE_OPTIONAL, 'test option.');

task('LocalBldSshInstall', [
    'build:find',
    'remote_windows:use_native_symlinks',
    'deploy:prepare',
    'deploy:release',
    'self_extractor:create',
    'self_extractor:upload',
    'self_extractor:extract',
    'deploy:fix_permissions',
    'deploy:copy_directories',
    'deploy:symlink',
    'deploy:create_exface_symlink',
    'deploy:create_shared_links',
    'install:install_current_packages',//post_install',
    'install:uninstall_unused_packages',
    'deploy:show_release_names',
    'self_extractor:delete_remote_file',
    'self_extractor:delete_local_file',
    'deploy:success'    
]);