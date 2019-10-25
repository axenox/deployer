<?php

namespace Deployer;

task('remote_windows:use_native_symlinks' , function () {
    set('symlink_prefix' , 'export set CYGWIN=winsymlinks:nativestrict && ');
    set('bin/symlink', function () {
        return get('use_relative_symlink') ? get('symlink_prefix') . 'ln -nfs --relative' : get('symlink_prefix') . 'ln -nfs';
    });
});