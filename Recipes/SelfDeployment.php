<?php
namespace Deployer;

$pathScriptCreatephpdeployment = 'vendor\\axenox\\deployer\\Recipes\\SelfExtractingPHP\\SelfExtractingDeployment.php';
set('path_script_createphpdeployment', $pathScriptCreatephpdeployment);
set('self_extractor_extension', '.phx');

/**
 * copy deployment script and replace placeholders
 */
task('self_deployment:create', function () {
    $temp_php = get('builds_archives_path') . DIRECTORY_SEPARATOR . get('release_name') . get('self_extractor_extension');
    set('temp_php', $temp_php);
    copy(get('path_script_createphpdeployment'), $temp_php);
    $str=file_get_contents($temp_php);
    $replaceBasicDeployPath = get('basic_deploy_path');
    $replaceRelativeDeployPath = get('relative_deploy_path');
    $replaceSharedDirs = "['" . implode("', '", get('shared_dirs')) . "']";
    $replaceCopyDirs = "['" . implode("', '", get('copy_dirs')) . "']";
    $replaceLocalVendors = is_array(get('local_vendors')) === true ? "['" . implode("', '", get('local_vendors')) . "']" : "[]";
    $replacePhpPath = get('php_path');
    $replaceKeepReleases = get('keep_releases');
    $str=str_replace('[#basic#]', $replaceBasicDeployPath, $str);
    $str=str_replace('[#relative#]', $replaceRelativeDeployPath, $str);
    $str=str_replace('[#shared#]', $replaceSharedDirs, $str);
    $str=str_replace('[#copy#]', $replaceCopyDirs, $str);
    $str=str_replace('[#localvendors#]', $replaceLocalVendors, $str);
    $str=str_replace('[#php#]', $replacePhpPath, $str);
    $str=str_replace('[#releases#]', $replaceKeepReleases, $str);
    file_put_contents($temp_php, $str);
    
    runLocally('copy /b "{{temp_php}}" + "{{builds_archives_path}}\{{archiv_name}}" "{{builds_archives_path}}\{{release_name}}{{self_extractor_extension}}"');
});

/**
 * upload self deployment php file to remote host
 */
task('self_deployment:upload', function () {
    runLocally('cat {{builds_archives_path}}\{{release_name}}{{self_extractor_extension}} | ssh -F "{{host_ssh_config}}" "{{host_short}}" "(cd {{basic_deploy_path_cygwin}}; cat > {{release_name}}{{self_extractor_extension}})"', ['timeout' => 900]);
});
    
/**
 * run self deployment php file on remote host
 */
task('self_deployment:run', function () {
    $composerOutput = run('cd {{basic_deploy_path_cygwin}} && {{php_path}} -d memory_limit=500M {{release_name}}{{self_extractor_extension}}', ['timeout' => null]);;
    write($composerOutput);
    writeln('');
});

/**
 * delete self deplyoment php file on local machine
 */
task('self_deployment:delete_local_file', function() {
    runLocally('del /f {{builds_archives_path}}\{{release_name}}{{self_extractor_extension}}');
}); 

/**
 * show link to created local self deployment php file
 */
task('self_deployment:show_link', function() {
    $filename = get('release_name') . get('self_extractor_extension');
    $phpPath = get('php_path');
    $text = <<<cli
Please transfer the self-extractor PHP file to the server manually and execute it there:

1) Download "$filename" in the "Build Files" tab or copy it from the builds folder  
2) Upload it to anywhere on the host
3) Run "$phpPath path/to/$filename" on the host's command line 


cli;
    echo ($text);
});