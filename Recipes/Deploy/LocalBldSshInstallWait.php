<?php
namespace Deployer;

use Symfony\Component\Console\Input\InputOption;

require 'vendor/axenox/deployer/Recipes/Config.php';
require 'vendor/axenox/deployer/Recipes/Build.php';
require 'vendor/axenox/deployer/Recipes/RemoteWindows.php';
require 'vendor/axenox/deployer/Recipes/SelfExtractor.php';
require 'vendor/axenox/deployer/Recipes/Deploy.php';
require 'vendor/axenox/deployer/Recipes/Install.php';
require 'vendor/axenox/deployer/Recipes/Network.php';

option('build', null, InputOption::VALUE_OPTIONAL, 'test option.');

task('LocalBldSshInstallWait', [
    'config:setup_deploy_config',
    'build:find',
    'remote_windows:use_native_symlinks',
    'deploy:prepare',
    'self_extractor:create',
    'self_extractor:upload',
    'self_extractor:extract',
    'deploy:fix_permissions',
    'deploy:copy_directories',
    'network:wait',
    'deploy:shared',
    'deploy:create_symlinks',
    'network:wait',
    'deploy:create_shared_links',
    'network:wait',
    'install:install_current_packages',
    'install:uninstall_unused_packages',
    'self_extractor:delete_remote_file',
    'self_extractor:delete_local_file',
    'deploy:show_release_names',
    'deploy:success'
]);