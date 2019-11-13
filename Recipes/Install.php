<?php

namespace Deployer;

//run installer for new release
task('install:install_current_packages', function(){
    within('{{deploy_path}}', function() {
        $composerOutput =  run('cd ../exface && {{php_path}} composer.phar run-script post-install-cmd');
        write($composerOutput);
        writeln('');
    });
});

//TODO
//uninstall apps that are not in new release anymore
task('install:uninstall_old_packages', function(){
    /*within('{{deploy_path}}', function() {
        $runOutput =  run('for d in ../exface/vendor/*; do
                            if [ -d "$d" ]; then 
                                for dir in d; do 
                                    if [ -d "$dir" ]; then 
                                        echo "$dir"
                                    fi fi');
        write($runOutput);
        writeln('');
    });
    */
});