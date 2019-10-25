<?php

namespace Deployer;

require 'vendor/axenox/deployer/Recipes/Config.php';
require 'vendor/axenox/deployer/Recipes/Build.php';
    
task('CloneLocal', [
    'build:generate_release_name',
    'build:create_from_local',
]); 