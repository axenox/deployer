<?php
namespace Deployer;

$path_script_createphparchive = 'vendor\\axenox\\deployer\\Recipes\\SelfExtractingPHP\\SelfExtractingArchive.php';
set('path_script_createphparchive', $path_script_createphparchive);

task('self_extractor:create', function () {
    runLocally('copy /b "{{path_script_createphparchive}}" + "{{builds_archives_path}}\{{archiv_name}}" "{{builds_archives_path}}\{{release_name}}.php"');
});
    
task('self_extractor:upload', function () {
    runLocally('cat {{builds_archives_path}}\{{release_name}}.php | ssh -F {{host_ssh_config}} {{host_short}} "(cd {{deploy_path}}/{{release_path}}; cat > {{release_name}}.php)"');
});
    
task('self_extractor:extract', function() {
    run('cd {{basic_deploy_path_cygwin}}/{{relative_deploy_path}}/{{release_path}} && {{php_path}} -d memory_limit=400M {{release_name}}.php');
});
    
task('self_extractor:delete_remote_file', function() {
    run('cd {{basic_deploy_path_cygwin}}/{{relative_deploy_path}}/{{release_path}} && rm -f {{release_name}}.php');
});
    
task('self_extractor:delete_local_file', function() {
    runLocally('rm -f {{builds_archives_path}}\{{release_name}}.php');
});
                    