<?php
namespace Deployer;

$pathScriptCreatephpdeployment = 'vendor\\axenox\\deployer\\Recipes\\SelfExtractingPHP\\SelfExtractingDeployment.php';
set('path_script_createphpdeployment', $pathScriptCreatephpdeployment);
set('self_extractor_extension', '.phx');

/**
 * copy deployment script and replace placeholders
 */
task('self_deployment:create', function () {
    set('self_extractor_filename', get('release_name') . '_' . get('host_short') . get('self_extractor_extension'));
    $temp_php = get('builds_archives_path') . DIRECTORY_SEPARATOR . get('self_extractor_filename');
    set('temp_php', $temp_php);
    copy(get('path_script_createphpdeployment'), $temp_php);
    $str=file_get_contents($temp_php);
    $replaceBasicDeployPath = get('basic_deploy_path');
    $replaceRelativeDeployPath = get('relative_deploy_path');
    $replaceSharedDirs = "['" . implode("', '", get('shared_dirs')) . "']";
    $replaceCopyDirs = "['" . implode("', '", get('copy_dirs')) . "']";
    $deployConfig = get('deploy_config') ?? [];
    $deployConfigPHP = var_export($deployConfig, true);
    $replacePhpPath = get('php_path');
    $replaceKeepReleases = get('keep_releases');
    $str=str_replace('[#basic#]', $replaceBasicDeployPath, $str);
    $str=str_replace('[#relative#]', $replaceRelativeDeployPath, $str);
    $str=str_replace('[#shared#]', $replaceSharedDirs, $str);
    $str=str_replace('[#copy#]', $replaceCopyDirs, $str);
    $str=str_replace('[#deploy_config#]', $deployConfigPHP, $str);
    $str=str_replace('[#php#]', $replacePhpPath, $str);
    $str=str_replace('[#releases#]', $replaceKeepReleases, $str);
    file_put_contents($temp_php, $str);
    
    $buildFileAbs = get('builds_archives_path') . DIRECTORY_SEPARATOR . get('archiv_name');
    if (false === file_exists($buildFileAbs)) {
        throw new \RuntimeException('Build file "' . get('archiv_name') . '" not found!');
    }
    
    runLocally('copy /b "{{temp_php}}" + "{{builds_archives_path}}\{{archiv_name}}" "{{builds_archives_path}}\{{self_extractor_filename}}"');
});

/**
 * upload self deployment php file to remote host
 */
task('self_deployment:upload', function () {
    runLocally('cat {{builds_archives_path}}\{{self_extractor_filename}} | ssh -F "{{host_ssh_config}}" "{{host_short}}" "(cd {{basic_deploy_path_cygwin}}; cat > {{self_extractor_filename}})"', ['timeout' => 900]);
});
    
/**
 * run self deployment php file on remote host
 */
task('self_deployment:run', function () {
    $composerOutput = run('cd {{basic_deploy_path_cygwin}} && {{php_path}} -d memory_limit=2G {{self_extractor_filename}}', ['timeout' => null]);;
    write($composerOutput);
    writeln('');
});

/**
 * delete self deplyoment php file on local machine
 */
task('self_deployment:delete_local_file', function() {
    runLocally('del /f {{builds_archives_path}}\{{self_extractor_filename}}');
});

/**
 * delete self deplyoment php file on remote machine
 */
task('self_deployment:delete_remote_file', function() {
    run('cd {{basic_deploy_path_cygwin}} && rm {{self_extractor_filename}}');
});

/**
 * show link to created local self deployment php file
 */
task('self_deployment:show_link', function() {
    $filename = get('self_extractor_filename');
    $filePath = get('builds_archives_path') . DIRECTORY_SEPARATOR . $filename;
    $phpPath = get('php_path');
    $text = <<<cli

ⓘ Please transfer the self-extractor PHP file to the server manually and execute it there:

1) Copy/Download "$filePath"
2) Upload it to anywhere on the host
3) Open the host's command line as administrator (IMPORTANT - otherwise you will get symlink-errors!) 
4) Run the command "$phpPath -d memory_limit=2G path/to/$filename"

NOTE: if anything goes wrong and you need to roll back:

1) Delete the "current" symlink in the installation folder
2) Create a new one pointing to the last working release in the "releases" folder: 
   e.g. "mklink /D current .\\releases\\..." on Windows.
3) Run all installers via "current\\vendor\\bin\\action axenox.PackageManager:InstallApp all"



cli;
    echo ($text);
});