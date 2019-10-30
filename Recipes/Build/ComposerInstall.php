<?php

namespace Deployer;

require 'vendor/axenox/deployer/Recipes/Config.php';
require 'vendor/axenox/deployer/Recipes/Build.php';

task('ComposerInstall', [
    'config:setup_build_config',
    'build:generate_release_name',
    'build:create_from_composer',
]); 