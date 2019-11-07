<?php
namespace Deployer;

$pathScriptCreatephpdeployment = 'vendor\\axenox\\deployer\\Recipes\\SelfExtractingPHP\\SelfExtractingDeployment.php';
set('path_script_createphpdeployment', $pathScriptCreatephpdeployment);

task('self_deployment:create', function () {
    //copy deployment script and replace placeholders
    $temp_php = get('builds_archives_path') . DIRECTORY_SEPARATOR . get('release_name') . '.php';
    set('temp_php', $temp_php);
    copy(get('path_script_createphpdeployment'), $temp_php);
    $str=file_get_contents($temp_php);
    $replaceBasicDeployPath = get('basic_deploy_path');
    $replaceRelativeDeployPath = get('relative_deploy_path');
    $replaceSharedDirs = "['" . implode("', '", get('shared_dirs')) . "']";
    $replaceCopyDirs = "['" . implode("', '", get('copy_dirs')) . "']";
    $replacePhpPath = get('php_path');
    $replaceKeepReleases = get('keep_releases');
    $str=str_replace('[#basic#]', $replaceBasicDeployPath, $str);
    $str=str_replace('[#relative#]', $replaceRelativeDeployPath, $str);
    $str=str_replace('[#shared#]', $replaceSharedDirs, $str);
    $str=str_replace('[#copy#]', $replaceCopyDirs, $str);
    $str=str_replace('[#php#]', $replacePhpPath, $str);
    $str=str_replace('[#releases#]', $replaceKeepReleases, $str);
    file_put_contents($temp_php, $str);
    
    runLocally('copy /b "{{temp_php}}" + "{{builds_archives_path}}\{{archiv_name}}" "{{builds_archives_path}}\{{release_name}}.php"');
});

task('self_deployment:upload', function () {
    runLocally('cat {{builds_archives_path}}\{{release_name}}.php | ssh -F {{host_ssh_config}} {{host_short}} "(cd {{basic_deploy_path_cygwin}}; cat > {{release_name}}.php)"');
});
    
task('self_deployment:run', function () {
    $composerOutput = run('cd {{basic_deploy_path_cygwin}} && {{php_path}} -d memory_limit=400M {{release_name}}.php');;
    write($composerOutput);
    writeln('');
});

task('self_deployment:delete_local_file', function() {
    runLocally('del /f {{builds_archives_path}}\{{release_name}}.php');
}); 

task('self_deployment:show_link', function() {
    echo(get('builds_archives_path') . DIRECTORY_SEPARATOR . get('release_name') . ".php\n");
    //runLocally('echo {{builds_archives_path}}\{{release_name}}.php');    
});