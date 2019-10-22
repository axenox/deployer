<?php
namespace Deployer;

require 'vendor/axenox/deployer/Recipes/Config.php';
require 'vendor/axenox/deployer/Recipes/CreateBuild.php';
require 'vendor/axenox/deployer/Recipes/DeployBuild.php';
use Symfony\Component\Console\Input\InputOption;

option('build', null, InputOption::VALUE_OPTIONAL, 'test option.');
 
task('deploy', [
	'create_build',
	'deploy_build_with_php_deployment',
]);

task('deploy_archiv', [
    'create_build',
    'deploy_build_with_archiv',
]);

