<?php
namespace Deployer;

use Deployer\Exception\ConfigurationException;

set('git_tty', false);
set('allow_anonymous_stats', false);
set('ssh_multiplexing', false);

task('config:setup_build_config', function() {
    $source_files = 'vendor composer.json composer.lock composer.phar';
    set('source_files', $source_files);
    
    // === semantic versioning parameters  ===
    $time_zone = 'Europe/Berlin';
    set('time_zone', $time_zone);
});

task('config:setup_deploy_config', function () {
    $configDir = 'config';
    
    // === semantic versioning parameters  ===
    $timeZone = 'Europe/Berlin';
    set('time_zone', $timeZone);
    set('use_atomic_symlink', false); // atomic symlinks are not functional on several Windows systems
    set('use_relative_symlink', true);
    
    //parameters set in deploy.php
    $basicDeployPath = get('basic_deploy_path');
    $relativeDeployPath = get('relative_deploy_path');
    try {
        $hostShort = get('host_short');
        $hostSshConfig = get('host_ssh_config');

    } catch (ConfigurationException $e) {
        $hostShort = null;
        $hostSshConfig = null;
    }
    
    // === path definitions ===
    set('basic_deploy_path_cygwin', '/cygdrive/' . str_replace(['\\', ':'], ['/', ''], $basicDeployPath));
    try {
        $releaseName = get('release_name');
    } catch (ConfigurationException $e) {
        $releaseName = '';
    }
    set('release_name', $releaseName);
    
    try {
        $phpPath = get('php_path');
    } catch (ConfigurationException $e) {
        $phpPath = 'php';
    }
    set('php_path', $phpPath);
    try {
        $localVendors = get('local_vendors');
    } catch (ConfigurationException $e) {
        $localVendors = [];
    }
    set('local_vendors', $localVendors);
    
    // === Deployer specific parameters ===
    set('config_dir', $configDir);
    set('shared_files', []);
    set('shared_dirs', ['backup', 'cache', 'export', 'UserData', 'logs']);
    set('copy_dirs', ['config']);    
    try {
        $keepReleases = get('keep_releases');
    } catch (ConfigurationException $e) {
        $keepReleases = 4;
    }
    set('keep_releases', $keepReleases);
    
    
    // === connection parameters
    $hostDeployPath = str_replace('\\', '/', $basicDeployPath) . '/' . $relativeDeployPath;
    set('host_deploy_path', $hostDeployPath);
    if ($hostShort !== null) {
        $hostShort = get('host_short');
        $hostSshConfig = get('host_ssh_config');
        $host = host($hostShort);
        $host
            ->set('deploy_path', $hostDeployPath)
            ->configFile($hostSshConfig)
            ->forwardAgent(false);
            //->addSshFlag('-vvvv');
    }
});
	