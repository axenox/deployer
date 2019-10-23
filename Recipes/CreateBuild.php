<?php
namespace Deployer;


task('generate_release_name', function(){
	$current_date = new \DateTime( 'now' , new \DateTimeZone(get('time_zone')) );
	$current_date = $current_date->format('YmdHis');
	$releaseName = get('customer_specific_version') . '+build' . $current_date;
	$archivName = $releaseName . '.tar.gz';
	set('release_name', $releaseName );
	set('archiv_name', $archivName);
}); // name of folder in releases


task('create_release_archiv', function () {
    $builds_path = get('builds_archives_path');
    if (!is_dir($builds_path)) {
        mkdir($builds_path);
    }
    $builds_path_relative = strstr($builds_path , 'deployer');
    set('builds_archives_relative_path', $builds_path_relative);
	runLocally('tar -czf {{builds_archives_relative_path}}\{{archiv_name}} {{source_files}}');
});

task('create_build', [
	'generate_release_name',
	'create_release_archiv',
]); 
