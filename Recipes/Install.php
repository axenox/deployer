<?php

namespace Deployer;

task('install:install_current_packages', function(){
    within('{{deploy_path}}', function() {
        $composerOutput =  run('cd ../exface && {{php_path}} composer.phar run-script post-install-cmd');
        write($composerOutput);
        writeln('');
    });
});

//TODO
task('install:uninstall_unused_packages', function(){
    
});