<?php
namespace Deployer;

use Symfony\Component\Console\Input\InputOption;

require 'vendor/axenox/deployer/Recipes/Config.php';
require 'vendor/axenox/deployer/Recipes/Build.php';
require 'vendor/axenox/deployer/Recipes/SelfDeployment.php';

option('build', null, InputOption::VALUE_OPTIONAL, 'test option.');

task('LocalBldUsbSelfExtractor', [
    'build:find',
    'self_deployment:create',
    'self_deployment:show_link'
]);
