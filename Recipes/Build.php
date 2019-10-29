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
    $current_date = new \DateTime('now' , new \DateTimeZone(get('time_zone')));
    $current_date = $current_date->format('YmdHis');
    $releaseName = get('customer_specific_version') . '+build' . $current_date;
    $archivName = $releaseName . '.tar.gz';
    set('release_name', $releaseName );
    set('archiv_name', $archivName);
});
    
task('build:create_from_local', function() {
    $builds_path = get('builds_archives_path');
    if (!is_dir($builds_path)) {
        mkdir($builds_path);
    }
    $builds_path_relative = strstr($builds_path , 'deployer');
    set('builds_archives_relative_path', $builds_path_relative);
    try {
        $base_config_path = get('base_config_path');
    } catch (ConfigurationException $e) {
        $base_config_path = null;
    }
    if ($base_config_path !=='' && $base_config_path !== null) {
        if (!is_dir($base_config_path)) {
            mkdir($base_config_path);
        }
        $directory_name = substr($base_config_path, strrpos($base_config_path, '\\') + 1);
        runLocally('tar -czf {{builds_archives_relative_path}}\{{archiv_name}} {{source_files}} -C {{base_config_path}}\.. ' . $directory_name);
    } else {
        runLocally('tar -czf {{builds_archives_relative_path}}\{{archiv_name}} {{source_files}}');        
    }
});

//TODO
task('build:create_from_composer', function() {
    
});