<?php
namespace Deployer;

$path_script_createphparchive = 'vendor\\axenox\\deployer\\Recipes\\SelfExtractingPHP\\SelfExtractingArchive.php';
set('path_script_createphparchive', $path_script_createphparchive);
set('self_extractor_extension', '.phx');

/**
 * create self extracting php file
 */
task('self_extractor:create', function () {
    set('self_extractor_filename', get('release_name') . '_' . get('host_short') . get('self_extractor_extension'));
    $buildFileAbs = get('builds_archives_path') . DIRECTORY_SEPARATOR . get('archiv_name');
    if (false === file_exists($buildFileAbs)) {
        throw new \RuntimeException('Build file "' . $buildFileAbs . '" not found!');
    }
    runLocally('copy /b "{{path_script_createphparchive}}" + "{{builds_archives_path}}\{{archiv_name}}" "{{builds_archives_path}}\{{self_extractor_filename}}"');
});

/**
 * upload self extracting php file to remote host
 */
task('self_extractor:upload', function () {
    runLocally('cat {{builds_archives_path}}\{{self_extractor_filename}} | ssh -F {{host_ssh_config}} "{{host_short}}" "(cd {{deploy_path}}/{{release_path}}; cat > {{self_extractor_filename}})"');
});

/**
 * extract self extracting php file on remote host
 */
task('self_extractor:extract', function() {
    run('cd {{basic_deploy_path_cygwin}}/{{relative_deploy_path}}/{{release_path}} && {{php_path}} -d memory_limit=500M {{self_extractor_filename}}');
});

/**
 * delete self extracting php file on remote host
 */
task('self_extractor:delete_remote_file', function() {
    run('cd {{basic_deploy_path_cygwin}}/{{relative_deploy_path}}/{{release_path}} && rm -f {{self_extractor_filename}}');
});

/**
 * delete self extracting php file on local machine
 */
task('self_extractor:delete_local_file', function() {
    if (substr(php_uname(), 0, 7) == "Windows"){
        runLocally('del /f /q "{{builds_archives_path}}' . DIRECTORY_SEPARATOR . '{{self_extractor_filename}}"');
    } else {
        runLocally('rm -f {{builds_archives_path}}/{{self_extractor_filename}}');
    }
});
                    