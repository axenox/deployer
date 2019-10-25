<?php

namespace Deployer;

task('deploy:create_shared_links', function() {
    foreach (get('shared_dirs') as $dir) {
        $shared_dir = get('deploy_path') .'/shared';
        run('cd {{deploy_path}}; export set CYGWIN=winsymlinks:nativestrict; ln -nfs --relative ' . $shared_dir . '/' . $dir . ' ' . get('release_path') . '/' . $dir);
    };
});
    
task('deploy:show_release_names', function () {
    writeln('Deployed to new release: {{deploy_path}}/{{release_path}}');
    if(has('previous_release')) {
        writeln('Previous release: {{previous_release}}');
    }
});
    
task('depploy:create_paths', function() {
    run('mkdir -p {{host_deploy_path}}');
    run('mkdir -p {{host_deploy_path}}/releases');
});
    
task('deploy:create_exface_symlink', function() {
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