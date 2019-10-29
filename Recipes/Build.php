<?php

namespace Deployer;

use Symfony\Component\Console\Input\InputArgument;
use Deployer\Exception\ConfigurationException;

task('build:find', function () {
    if (get('release_name') == '') {
        if (input()->getOption('build') != null) {
            $releaseName = input()->getOption('build');
            $archivName = $releaseName . '.tar.gz';
            set('release_name', $releaseName );
            set('archiv_name', $archivName);
        } else {
            $directory = get('builds_archives_path');
            $files = scandir($directory, SCANDIR_SORT_DESCENDING);
            $newest_file = $files[0];
            $archivName = $newest_file;
            $stringLength = strlen($archivName);
            $releaseName = substr($archivName,0,($stringLength-7));
            set('release_name', $releaseName );
            set('archiv_name', $archivName);
        }
    }
    set('release_path' , 'releases/{{release_name}}');    
});

task('build:generate_release_name', function(){
    try {
        $releaseName = get('release_name');
    } catch (ConfigurationException $e) {
        $releaseName = null;
    }
    echo('Name: ' . $releaseName);
    if ($releaseName ==='' || $releaseName === null) {
        $currentDate = new \DateTime('now' , new \DateTimeZone(get('time_zone')));
        $currentDate = $currentDate->format('YmdHis');
        $releaseName = get('customer_specific_version') . '+build' . $currentDate;
        set('release_name', $releaseName );
    }
    $archivName = $releaseName . '.tar.gz';
    set('archiv_name', $archivName);
});
    
task('build:create_from_local', function() {
    $buildsPath = get('builds_archives_path');
    if (!is_dir($buildsPath)) {
        mkdir($buildsPath);
    }
    $buildsPathRelative = strstr($buildsPath , 'deployer');
    set('builds_archives_relative_path', $buildsPathRelative);
    try {
        $baseConfigPath = get('base_config_path');
    } catch (ConfigurationException $e) {
        $baseConfigPath = null;
    }
    if ($baseConfigPath !=='' && $baseConfigPath !== null) {
        if (!is_dir($baseConfigPath)) {
            mkdir($baseConfigPath);
        }
        $directory_name = substr($baseConfigPath, strrpos($baseConfigPath, '\\') + 1);
        runLocally('tar -czf {{builds_archives_relative_path}}\{{archiv_name}} {{source_files}} -C {{base_config_path}}\.. ' . $directory_name);
    } else {
        runLocally('tar -czf {{builds_archives_relative_path}}\{{archiv_name}} {{source_files}}');        
    }
});

//TODO
task('build:create_from_composer', function() {
    
});