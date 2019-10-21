<?php
namespace Deployer;

require 'vendor/deployer/deployer/recipe/common.php';

ini_set('memory_limit', '-1'); // deployment may exceed 128MB internal memory limit

$source_path = 'vendor';
$source_files = 'vendor composer.json composer.lock composer.phar';
$config_dir = 'config';
$modx_config_file = 'exface.ModxCmsConnector.config.json';
$releaseName = '';

// === semantic versioning parameters  ===
$time_zone = 'Europe/Berlin';
set('time_zone', $time_zone);
set('customer_specific_version', $version);
set('use_atomic_symlink', false); // atomic symlinks are not functional on several Windows systems
set('use_relative_symlink', true);

// === path definitions ===
set('basic_deploy_path', $basic_deploy_path);
set('relative_deploy_path', $relative_deploy_path);
set('basic_deploy_path_cygwin', '/cygdrive/' . str_replace(['\\', ':'], ['/', ''], $basic_deploy_path));
set('host_short', $host_short);
set('host_ssh_config', $host_ssh_config);
set('builds_archives_path', $builds_archives_path);
set('release_name', $releaseName);

// === name of modx config file
set('modx_config_file', $modx_config_file);

// === Deployer specific parameters ===
$composer_options = 'install --verbose --prefer-dist --no_progress --no-interaction --optimize-autoloader';
set('source_path', $source_path);
set('source_files', $source_files);
set('config_dir', $config_dir);
set('git_tty', false); 
set('shared_files', []);
set('shared_dirs', ['backup', 'cache', 'export', 'UserData', 'logs']);
set('copy_dirs', ['config']);
set('keep_releases', $keep_releases);
set('allow_anonymous_stats', false);

// === connection parameters
$host_deploy_path = str_replace('\\', '/', $basic_deploy_path) . '/' . $relative_deploy_path;
set('stage', $stage );
set('application', $application);
set('host_upload_dir', 'deployer\\' . $project . '\\hosts\\' . $host_short . '\\initial');
set('host_deploy_path', $host_deploy_path);
set('ssh_multiplexing', false);
host($host_short)
    ->set('composer_options', $composer_options)
    ->set('deploy_path', $host_deploy_path)
    ->configFile($host_ssh_config)
    ->forwardAgent(false);
	//->addSshFlag('-vvvv');
	
	