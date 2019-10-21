<?php
namespace Deployer;

$path_script_createphparchive = 'C:\\wamp\\www\\exface\\exface\\vendor\\axenox\\deployer\\Recipes\\CreatePHPArchive.php';
set('path_script_createphparchive', $path_script_createphparchive);
task('generate_release_name', function(){
	$current_date = new \DateTime( 'now' , new \DateTimeZone(get('time_zone')) );
	$current_date = $current_date->format('YmdHis');
	$releaseName = get('customer_specific_version') . '+build' . $current_date;
	$archivName = $releaseName . '.tar.gz';
	set('release_name', $releaseName );
	set('archiv_name', $archivName);
}); // name of folder in releases


task('create_release_archiv', function () {
	runLocally('tar -czf {{builds_archives_path}}\{{archiv_name}} {{source_files}}');
});

task('create_self_extracting_php', function () {
    runLocally('copy /b "{{path_script_createphparchive}}" + "{{builds_archives_path}}\{{archiv_name}}" "{{builds_archives_path}}\{{release_name}}.php"');
});

task('create_build', [
	'generate_release_name',
	'create_release_archiv',
    'create_self_extracting_php',
]); 
