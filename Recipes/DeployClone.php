<?php
namespace Deployer;

require 'vendor/deployer/deployer/recipe/common.php';

ini_set('memory_limit', '-1'); // deployment may exceed 128MB internal memory limit

$source_path = 'vendor';
$source_files = 'vendor composer.json composer.lock composer.phar';
$config_dir = 'config';
$modx_config_file = 'exface.ModxCmsConnector.config.json';

// === semantic versioning parameters  ===
$time_zone = 'Europe/Berlin';
set('time_zone', $time_zone);
set('customer_specific_version', $version);
set('use_atomic_symlink', false); // atomic symlinks are not functional on several Windows systems
set('use_relative_symlink', true);

// === path definitions ===
set('basic_deploy_path', $basic_deploy_path);
set('relative_deploy_path', $relative_deploy_path);
set('basic_deploy_path_cygwin', '/cygdrive/' . str_replace(['\\', ':'], ['/', ''], $basic_deploy_path));
set('host_short', $host_short);
set('host_ssh_config', $host_ssh_config);

// === name of modx config file
set('modx_config_file', $modx_config_file);

// === Deployer specific parameters ===
$composer_options = 'install --verbose --prefer-dist --no_progress --no-interaction --optimize-autoloader';
set('source_path', $source_path);
set('source_files', $source_files);
set('config_dir', $config_dir);
set('git_tty', false); 
set('shared_files', []);
set('shared_dirs', ['backup', 'cache', 'export', 'logs', 'UserData']);
set('copy_dirs', ['config']);
set('keep_releases', $keep_releases);
set('allow_anonymous_stats', false);

// === connection parameters
$host_deploy_path = str_replace('\\', '/', $basic_deploy_path) . '/' . $relative_deploy_path;
set('stage', $stage );
set('application', $application);
set('host_upload_dir', 'deployer\\' . $project . '\\hosts\\' . $host_short . '\\initial');
set('host_deploy_path', $host_deploy_path);
set('ssh_multiplexing', false);
host($host_short)
    ->set('composer_options', $composer_options)
    ->set('deploy_path', $host_deploy_path)
    ->configFile($host_ssh_config)
    ->forwardAgent(false);

task('use_bin_symlink_with_cygwin_prefix' , function () {
   set('symlink_prefix' , 'export set CYGWIN=winsymlinks:nativestrict && ');
   set('bin/symlink', function () {
        return get('use_relative_symlink') ? get('symlink_prefix') . 'ln -nfs --relative' : get('symlink_prefix') . 'ln -nfs';
   });
});

task('create_shared_links', function() {
    foreach (get('shared_dirs') as $dir) {
       $shared_dir = get('deploy_path') .'/shared';
       run('cd {{deploy_path}}; export set CYGWIN=winsymlinks:nativestrict; ln -nfs --relative ' . $shared_dir . '/' . $dir . ' ' . get('release_path') . '/' . $dir);
    };
});

task('show_release_names', function () {
    writeln('Deployed to new release: {{deploy_path}}/{{release_path}}');
    if(has('previous_release')) {
        writeln('Previous release: {{previous_release}}');
    }
});

task('create_paths', function() {
    run('mkdir -p {{host_deploy_path}}');
    run('mkdir -p {{host_deploy_path}}/releases');
});

task('set_release_path', function() { // after 'generate_release_name' 
   set('release_path' , 'releases/{{release_name}}');
});

task('generate_release_name', function(){
   $current_date = new \DateTime( 'now' , new \DateTimeZone(get('time_zone')) );
   $current_date = $current_date->format('YmdHis');
   $releaseName = get('customer_specific_version') . '+build' . $current_date;
   set('release_name', $releaseName );      
}); // name of folder in releases

task('create_exface_symlink', function() {
    run("cd {{basic_deploy_path_cygwin}} && {{bin/symlink}} {{relative_deploy_path}}/current exface"); 
});

task('upload_to_release_path', function () { 
    runLocally('tar czf - {{source_files}} | ssh -F {{host_ssh_config}} {{host_short}} "(cd {{deploy_path}}/{{release_path}}; tar xzf -)"'); 
    run('cd {{deploy_path}}');
});

task('copy_directories', function () {
    $copy_dir_array = get('copy_dirs');
    if (has('previous_release')) {
        $previous_release = get('previous_release');
        $previous_release = str_replace('\\', '/', substr( $previous_release, 2, strlen( $previous_release ) ) );
    } else {
        $previous_release = '';
    }
    
    if($previous_release !== ''){
        set('cygwin_path_previous_release', '/cygdrive/c' . $previous_release  );
        set('target_dir', get('basic_deploy_path_cygwin') . '/' . get('relative_deploy_path') . '/' . get('release_path') );
        foreach ( $copy_dir_array as $dir_to_copy ) {
            set('current_copy_dir' , $dir_to_copy );
          
            run('cd {{target_dir}} && mkdir -p {{current_copy_dir}}');
            if( test( '[ -d {{cygwin_path_previous_release}}/{{current_copy_dir}} ]') ){
              run('cp -rf {{cygwin_path_previous_release}}/{{current_copy_dir}}/. {{target_dir}}/{{current_copy_dir}}/');  
            } 
        }
    }
    
    
    // make sure config dir exists
    if ( ! test('[ -f {{basic_deploy_path_cygwin}}/{{relative_deploy_path}}/{{release_path}}/{{config_dir}}/{{modx_config_file}} ]' ) ) {
        
        run('cd {{basic_deploy_path_cygwin}}/{{relative_deploy_path}}/{{release_path}}/ && chmod +w ./ && mkdir -p {{config_dir}}');
        run('cd {{basic_deploy_path_cygwin}}/{{relative_deploy_path}}/{{release_path}}/{{config_dir}}/ && (echo \'{\\"PATH_TO_MODX\": \\"../../../index-exface.php\\"}\') > exface.ModxCmsConnector.config.json');
        
        // FIXME scp would be much nicer, but it keeps saying, remote path is neither file nor directory!
        // runLocally('scp -r -F "{{host_ssh_config}}" {{host_upload_dir}} {{host_short}}:{{basic_deploy_path_cygwin}}/{{relative_deploy_path}}/{{release_path}}/');
        
        writeln('Generated initial configuration files.');

    }
});

task('fix_permissions', function() {
   run('chmod +x {{deploy_path}}/{{release_path}}');    
   run('chmod +x {{deploy_path}}/{{release_path}}/vendor/bin');
});

task('cleanup_only',[
    'cleanup'
]);
    
task('post_install', function(){
    within('{{deploy_path}}', function() {
        $composer_output =  run('cd ../exface && php composer.phar run-script post-install-cmd');   
        write($composer_output);
        writeln('');
    });
});
    
task('deploy', [  
    'use_bin_symlink_with_cygwin_prefix',
    'deploy:prepare', 
    //'deploy:lock',    // ok for first installation not to use this
    'generate_release_name',
    'set_release_path',
    'deploy:release', 
    'upload_to_release_path',
    'fix_permissions',
    'copy_directories',
    //'deploy:writable', 
    'deploy:symlink',    
    // 'deploy:update_code' //, Update does require git repository, otherwise fails with error   The command "/cygdrive/c/Program Files/Git/cmd/git version" //failed.
    'deploy:shared', 
    
    ///'deploy:vendors', // Installation via composer requires installation of composer on client
    //'deploy:clear_paths'
    'create_exface_symlink',
	//'deploy:unlock', // ok for first installation not to use this
    'create_shared_links',
	'post_install',
    'cleanup',
    'show_release_names',
    'success'
]); 

//task('redirect_current_to_previous_release', function () {
//    if (has('previous_release')) {
//         run('cd {{basic_deploy_path_cygwin}} && {{bin/symlink}} {{relative_deploy_path}}/current exface'); 
//    } else{
//        Writeln('No previous release found. Current revision unchanged.'); 
//    }
//});
//task('deploy_reset_to_previous_release', [
//   'redirect_current_to_previous_release',
//   'post_install'
//]);


