<?php
namespace Deployer;

$path_script_createphpdeployment = 'vendor\\axenox\\deployer\\Recipes\\SelfExtractingPHP\\SelfDeployment.php';
set('path_script_createphpdeployment', $path_script_createphpdeployment);

task('self_deployment:create', function () {
    //copy deployment script and replace placeholders
    $temp_php = get('builds_archives_path') . DIRECTORY_SEPARATOR . get('release_name') . '.php';
    set('temp_php', $temp_php);
    copy(get('path_script_createphpdeployment'), $temp_php);
    $str=file_get_contents($temp_php);
    $replace_basic_deploy_path = get('basic_deploy_path');
    $replace_relative_deploy_path = get('relative_deploy_path');
    $replace_shared_dirs = "['" . implode("', '", get('shared_dirs')) . "']";
    $replace_copy_dirs = "['" . implode("', '", get('copy_dirs')) . "']";
    $replace_php_path = get('php_path');
    $str=str_replace('[#basic#]', $replace_basic_deploy_path, $str);
    $str=str_replace('[#relative#]', $replace_relative_deploy_path, $str);
    $str=str_replace('[#shared#]', $replace_shared_dirs, $str);
    $str=str_replace('[#copy#]', $replace_copy_dirs, $str);
    $str=str_replace('[#php#]', $replace_php_path, $str);
    file_put_contents($temp_php, $str);
    
    runLocally('copy /b "{{temp_php}}" + "{{builds_archives_path}}\{{archiv_name}}" "{{builds_archives_path}}\{{release_name}}.php"');
});

task('self_deployment:upload', function () {
    runLocally('cat {{builds_archives_path}}\{{release_name}}.php | ssh -F {{host_ssh_config}} {{host_short}} "(cd {{basic_deploy_path_cygwin}}; cat > {{release_name}}.php)"');
});
    
task('self_deployment:run', function () {
    $composer_output = run('cd {{basic_deploy_path_cygwin}} && {{php_path}} -d memory_limit=400M {{release_name}}.php');;
    write($composer_output);
    writeln('');
});

task('self_deployment:delete_local_file', function() {
    runLocally('del /f {{builds_archives_path}}\{{release_name}}.php');
}); 

task('self_deployment:show_link', function() {
    echo(get('builds_archives_path') . DIRECTORY_SEPARATOR . get('release_name') . ".php\n");
    //runLocally('echo {{builds_archives_path}}\{{release_name}}.php');    
});