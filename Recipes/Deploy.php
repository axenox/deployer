<?php

namespace Deployer;

use Deployer\Exception\Exception;
use function Deployer\Support\str_contains;

task('deploy:copy_directories', function () {
    $copy_dir_array = get('copy_dirs');
    $has_previous_release = false;
    set('target_dir_cygwin', get('basic_deploy_path_cygwin') . '/' . get('relative_deploy_path') . '/' . get('release_path') );
    set('target_dir', get('deploy_path'). '/'. get('release_path'));
    //check if symlink 'curent' exists, if so, set path to it as previous_release
    if (test("[ -L {{deploy_path}}/current ]")) {        
        set('cygwin_path_previous_release', get('basic_deploy_path_cygwin') . '/' . get('relative_deploy_path') . '/current');
        $has_previous_release = true;
    }        
    foreach ($copy_dir_array as $dir_to_copy) {
        set('current_copy_dir' , $dir_to_copy );
        run('cd {{target_dir}} && mkdir -p {{current_copy_dir}}');
        if ($has_previous_release === true) {
            if(test( '[ -d {{cygwin_path_previous_release}}/{{current_copy_dir}} ]')) {
                run('cp -rf {{cygwin_path_previous_release}}/{{current_copy_dir}}/. {{target_dir}}/{{current_copy_dir}}/');
            }
        }
    }  
    
    // make sure config dir exists and copy files form base-config folder that dont already exist
    if (!test('[ -d {{basic_deploy_path_cygwin}}/{{relative_deploy_path}}/{{release_path}}/{{config_dir}} ]')) {        
        run('cd {{basic_deploy_path_cygwin}}/{{relative_deploy_path}}/{{release_path}}/ && chmod +w ./ && mkdir -p {{config_dir}}');
    }
    
    run('cp -rn {{target_dir}}/base-config/*.* {{target_dir}}/{{config_dir}}/');
    run('rm -rf {{target_dir}}/base-config');
    
    writeln('Generated initial configuration files.');
});

desc('Preparing host for deploy');
task('deploy:prepare', function () {
    // Check if shell is POSIX-compliant
    $result = run('echo $0');
    
    if (!str_contains($result, 'bash') && !str_contains($result, 'sh')) {
        throw new \RuntimeException(
            'Shell on your server is not POSIX-compliant. Please change to sh, bash or similar.'
            );
    }
    
    //create deploy path folder
    run('if [ ! -d {{deploy_path}} ]; then mkdir -p {{deploy_path}}; fi');
    
    // Check for existing /current directory (not symlink)
    $result = test('[ ! -L {{deploy_path}}/current ] && [ -d {{deploy_path}}/current ]');
    if ($result) {
        throw new Exception('There already is a directory (not symlink) named "current" in ' . get('deploy_path') . '. Remove this directory so it can be replaced with a symlink for atomic deployments.');
    }
    
    // Create metadata .dep dir.
    //run("cd {{deploy_path}} && if [ ! -d .dep ]; then mkdir .dep; fi");
    
    // Create releases dir.
    run("cd {{deploy_path}} && if [ ! -d releases ]; then mkdir releases; fi");
    
    // Create shared dir.
    run("cd {{deploy_path}} && if [ ! -d shared ]; then mkdir shared; fi");
    
    //test if the release already exists on server, if not, create folder
    $result = test('[ -d {{deploy_path}}/{{release_path}} ] ');
    if ($result) {
        throw new Exception('The selected release "' . get('release_name') . '" does already exist on the server!');
    } else {
        run('cd {{basic_deploy_path_cygwin}}/{{relative_deploy_path}} && mkdir {{release_path}}');
    }
});

task('deploy:create_shared_links', function() {
    foreach (get('shared_dirs') as $dir) {
        $shared_dir = get('deploy_path') .'/shared';
        run('cd {{deploy_path}}; export set CYGWIN=winsymlinks:nativestrict; ln -nfs --relative ' . $shared_dir . '/' . $dir . ' ' . get('release_path') . '/' . $dir);
    };
});
    
task('deploy:show_release_names', function () {
    writeln('Deployed to new release: {{deploy_path}}/{{release_path}}');
    /*if(has('previous_release')) {
        writeln('Previous release: {{previous_release}}');
    }*/
});
    
task('deploy:create_paths', function() {
    run('mkdir -p {{host_deploy_path}}');
    run('mkdir -p {{host_deploy_path}}/releases');
});
    
task('deploy:create_symlinks', function() {
    run("cd {{basic_deploy_path_cygwin}}/{{relative_deploy_path}}  && {{bin/symlink}} {{release_path}} current");
    run("cd {{basic_deploy_path_cygwin}} && {{bin/symlink}} {{relative_deploy_path}}/current exface");
});

task('deploy:fix_permissions', function() {
    run('chmod +x {{deploy_path}}/{{release_path}}');
    run('chmod +x {{deploy_path}}/{{release_path}}/vendor/bin');
});

task('deploy:success', function () {
    writeln('<info>Successfully deployed!</info>');
})
->local()
->shallow()
->setPrivate();