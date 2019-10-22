<?php
namespace Deployer;

use Symfony\Component\Console\Input\InputArgument;
use Deployer\Exception\Exception;

$path_script_createphparchive = 'C:\\wamp\\www\\exface\\exface\\vendor\\axenox\\deployer\\Recipes\\CreatePHPArchive.php';
$path_script_createphpdeployment = 'C:\\wamp\\www\\exface\\exface\\vendor\\axenox\\deployer\\Recipes\\CreatePHPDeployment.php';
set('path_script_createphparchive', $path_script_createphparchive);
set('path_script_createphpdeployment', $path_script_createphpdeployment);

task('create_self_extracting_deployment', function () {
    runLocally('copy /b "{{path_script_createphpdeployment}}" + "{{builds_archives_path}}\{{archiv_name}}" "{{builds_archives_path}}\{{release_name}}.php"');
});

task('create_self_extracting_archive', function () {
    runLocally('copy /b "{{path_script_createphparchive}}" + "{{builds_archives_path}}\{{archiv_name}}" "{{builds_archives_path}}\{{release_name}}.php"');
});

task('get_build_name', function () {
    if (get('release_name') == '') {
		if (input()->getOption('build') != null) {
			$releaseName = input()->getOption('build');
			$archivName = $releaseName . '.tar.gz';
			set('release_name', $releaseName );
			set('archiv_name', $archivName);
			runLocally('echo release name set: {{release_name}}');
		} else {
		    $directory = get('builds_archives_path');
			$files = scandir($directory, SCANDIR_SORT_DESCENDING);
			$newest_file = $files[0];
			$archivName = $newest_file;
			$stringLength = strlen($archivName);
			$releaseName = substr($archivName,0,($stringLength-7));
			set('release_name', $releaseName );
			set('archiv_name', $archivName);
			runLocally('echo release name set: {{release_name}}');
			//throw new Exception('Build name you want to deploy needs to be given when only running deploy_build task!');
		}		
	}		
});

desc('Creating symlinks for shared files and dirs');
task('deploy:shared', function () {
    $sharedPath = "{{deploy_path}}/shared";

    // Validate shared_dir, find duplicates
    foreach (get('shared_dirs') as $a) {
        foreach (get('shared_dirs') as $b) {
            if ($a !== $b && strpos(rtrim($a, '/') . '/', rtrim($b, '/') . '/') === 0) {
                throw new Exception("Can not share same dirs `$a` and `$b`.");
            }
        }
    }

    
	foreach (get('shared_dirs') as $dir) {
        // Check if shared dir does not exist.
        if (!test("[ -d $sharedPath/$dir ]")) {
            // Create shared dir if it does not exist.
            run("mkdir -p $sharedPath/$dir");

            // If release contains shared dir, copy that dir from release to shared.
            //if (test("[ -d $(echo {{release_path}}/$dir) ]")) {
            //    run("cp -rv {{release_path}}/$dir $sharedPath/" . dirname(parse($dir)));
            //}
        }
		// Remove from source.
        //run("rm -rf {{release_path}}/$dir");

        // Create path to shared dir in release dir if it does not exist.
        // Symlink will not create the path and will fail otherwise.
        run("mkdir -p `dirname {{release_path}}/$dir`");

        // Symlink shared dir to release dir
        run("{{bin/symlink}} $sharedPath/$dir {{release_path}}/$dir");
        //sleep(60);
    }

    foreach (get('shared_files') as $file) {
        $dirname = dirname(parse($file));

        // Create dir of shared file if not existing
        if (!test("[ -d {$sharedPath}/{$dirname} ]")) {
            run("mkdir -p {$sharedPath}/{$dirname}");
        }

        // Check if shared file does not exist in shared.
        // and file exist in release
        if (!test("[ -f $sharedPath/$file ]") && test("[ -f {{release_path}}/$file ]")) {
            // Copy file in shared dir if not present
            run("cp -rv {{release_path}}/$file $sharedPath/$file");
        }

        // Remove from source.
        run("if [ -f $(echo {{release_path}}/$file) ]; then rm -rf {{release_path}}/$file; fi");

        // Ensure dir is available in release
        run("if [ ! -d $(echo {{release_path}}/$dirname) ]; then mkdir -p {{release_path}}/$dirname;fi");

        // Touch shared
        run("touch $sharedPath/$file");

        // Symlink shared dir to release dir
        run("{{bin/symlink}} $sharedPath/$file {{release_path}}/$file");
    }
});

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

task('create_exface_symlink', function() {
    run("cd {{basic_deploy_path_cygwin}} && {{bin/symlink}} {{relative_deploy_path}}/current exface"); 
});

task('upload_tar_to_release_path', function () { 
	runLocally('cat {{builds_archives_path}}\{{release_name}}.tar.gz | ssh -F {{host_ssh_config}} {{host_short}} "(cd {{deploy_path}}/{{release_path}}; cat > {{archiv_name}})"');
	run('cd {{basic_deploy_path_cygwin}}/{{relative_deploy_path}}/{{release_path}} && tar -xzf {{archiv_name}}');
    //run('cd {{deploy_path}}');
});

task('upload_php_archive_to_release_path', function () {
    runLocally('cat {{builds_archives_path}}\{{release_name}}.php | ssh -F {{host_ssh_config}} {{host_short}} "(cd {{deploy_path}}/{{release_path}}; cat > {{release_name}}.php)"');
});

task('extract_php_archive', function() {
    run('cd {{basic_deploy_path_cygwin}}/{{relative_deploy_path}}/{{release_path}} && {{php_path}} -d memory_limit=400M {{release_name}}.php');
});

task('upload_php_deployment_to_basic_deploy_path', function () {
    runLocally('cat {{builds_archives_path}}\{{release_name}}.php | ssh -F {{host_ssh_config}} {{host_short}} "(cd {{basic_deploy_path_cygwin}}; cat > {{release_name}}.php)"');
});

task('run_php_deployment', function () {
    $composer_output = run('cd {{basic_deploy_path_cygwin}} && {{php_path}} -d memory_limit=400M {{release_name}}.php');;
    write($composer_output);
    writeln('');
});

task('delete_local_php_file', function() {
    runLocally('del /f {{builds_archives_path}}\{{release_name}}.php');
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
        run('cd {{basic_deploy_path_cygwin}}/{{relative_deploy_path}}/{{release_path}}/{{config_dir}}/ && (echo \'{"PATH_TO_MODX": "../../../index-exface.php"}\') > exface.ModxCmsConnector.config.json');
        
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
        $composer_output =  run('cd ../exface && {{php_path}} composer.phar run-script post-install-cmd');   
        write($composer_output);
        writeln('');
    });
});

task('wait', function (){
	sleep(180);
});

task('deploy_build_with_archiv', [ 
	'get_build_name',
    'use_bin_symlink_with_cygwin_prefix',
    'deploy:prepare', 
    //'deploy:lock',    // ok for first installation not to use this
    //'generate_release_name',
	
    'set_release_path',
    'deploy:release',
    'create_self_extracting_archive',
    'upload_php_archive_to_release_path',
    'extract_php_archive',
    'fix_permissions',
    'copy_directories',
    //'deploy:writable', 
    'deploy:symlink',    
    // 'deploy:update_code' //, Update does require git repository, otherwise fails with error   The command "/cygdrive/c/Program Files/Git/cmd/git version" //failed.
	//'wait',
    'deploy:shared',     
    ///'deploy:vendors', // Installation via composer requires installation of composer on client
    //'deploy:clear_paths'
    'create_exface_symlink',
	//'deploy:unlock', // ok for first installation not to use this
    //'wait',
    'create_shared_links',
	//'wait',
	'post_install',
    'cleanup',
    'show_release_names',
    'delete_local_php_file',
    'success'
]);

task('deploy_build_with_php_deployment', [
    'get_build_name',    
    'set_release_path',
    'create_self_extracting_deployment',
    'upload_php_deployment_to_basic_deploy_path',
    'run_php_deployment',
    'delete_local_php_file'
]);


