<?php
namespace Deployer;

use Symfony\Component\Console\Input\InputOption;

require 'vendor/axenox/deployer/Recipes/Config.php';
require 'vendor/axenox/deployer/Recipes/Build.php';
require 'vendor/axenox/deployer/Recipes/SelfDeployment.php';

option('build', null, InputOption::VALUE_OPTIONAL, 'test option.');

task('LocalBldSshSelfExtractor', [
    'config:setup_deploy_config',
    'build:find',
    'self_deployment:create',
    'self_deployment:upload',
    'self_deployment:run',
    'self_deployment:delete_local_file',
    'deploy:success'
]);
