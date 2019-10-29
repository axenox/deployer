<?php
namespace Deployer;

require 'vendor/deployer/deployer/recipe/common.php';

$source_files = 'vendor composer.json composer.lock composer.phar';
set('source_files', $source_files);

// === semantic versioning parameters  ===
$time_zone = 'Europe/Berlin';
set('time_zone', $time_zone);

