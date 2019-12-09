<?php
namespace Deployer;

$path_script_createphparchive = 'vendor\\axenox\\deployer\\Recipes\\SelfExtractingPHP\\SelfExtractingArchive.php';
set('path_script_createphparchive', $path_script_createphparchive);
set('self_extractor_extension', '.phx');

/**
 * create self extracting php file
 */
task('self_extractor:create', function () {
    $buildFileAbs = get('builds_archives_path') . DIRECTORY_SEPARATOR . get('archiv_name');
    if (false === file_exists($buildFileAbs)) {
        throw new \RuntimeException('Build file "' . $buildFileAbs . '" not found!');
    }
    runLocally('copy /b "{{path_script_createphparchive}}" + "{{builds_archives_path}}\{{archiv_name}}" "{{builds_archives_path}}\{{release_name}}{{self_extractor_extension}}"');
});

/**
 * upload self extracting php file to remote host
 */
task('self_extractor:upload', function () {
    runLocally('cat {{builds_archives_path}}\{{release_name}}{{self_extractor_extension}} | ssh -F {{host_ssh_config}} "{{host_short}}" "(cd {{deploy_path}}/{{release_path}}; cat > {{release_name}}.php)"');
});

/**
 * extract self extracting php file on remote host
 */
task('self_extractor:extract', function() {
    run('cd {{basic_deploy_path_cygwin}}/{{relative_deploy_path}}/{{release_path}} && {{php_path}} -d memory_limit=500M {{release_name}}.php');
});

/**
 * delete self extracting php file on remote host
 */
task('self_extractor:delete_remote_file', function() {
    run('cd {{basic_deploy_path_cygwin}}/{{relative_deploy_path}}/{{release_path}} && rm -f {{release_name}}.php');
});

/**
 * delete self extracting php file on local machine
 */
task('self_extractor:delete_local_file', function() {
    runLocally('rm -f {{builds_archives_path}}\{{release_name}}.php');
});
                    