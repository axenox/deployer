<?php

namespace Deployer;

task('install:install_current_packages', function(){
    within('{{deploy_path}}', function() {
        $composer_output =  run('cd ../exface && {{php_path}} composer.phar run-script post-install-cmd');
        write($composer_output);
        writeln('');
    });
});

//TODO
task('install:uninstall_unused_packages', function(){
    
});