<?php

ini_set('memory_limit', '-1'); // or you could use 1G

$basic_deploy_path = '[#basic#]'; //placeholder for string
$relative_deploy_path = '[#relative#]'; //placeholder for string
$shared_dirs = [#shared#]; //placeholder for array
$copy_dirs = [#copy#]; //placeholder for array
$php_path = '[#php#]'; //placeholder for array
$relative_releases_path = 'releases';
$relative_shared_path = 'shared';
$relative_current_path = 'current';
$deploy_path = $basic_deploy_path .  DIRECTORY_SEPARATOR . $relative_deploy_path;
$releases_path = $deploy_path . DIRECTORY_SEPARATOR . $relative_releases_path;
$shared_path = $deploy_path . DIRECTORY_SEPARATOR . $relative_shared_path;
$current_path = $deploy_path . DIRECTORY_SEPARATOR . $relative_current_path;

$release_name = pathinfo(__FILE__,  PATHINFO_FILENAME);
$release_path = $releases_path . DIRECTORY_SEPARATOR . $release_name;
$base_config_path = $release_path . DIRECTORY_SEPARATOR . 'base-config';
$config_path = $release_path . DIRECTORY_SEPARATOR . 'config';
$config_files = ['exface.ModxCmsConnector.config.json'];

//create relative deploy path directory if it not exists yet
if (!is_dir($deploy_path)) {
    mkdir($deploy_path);
    echo("Directory {$deploy_path} created!\n");
}

//create releases directory if it not exists yet
if (!is_dir($releases_path)) {
    mkdir($releases_path);
    echo("Directory {$releases_path} created!\n");
}

//create shared directory if it not exists yet
if (!is_dir($shared_path)) {
    mkdir($shared_path);
    echo("Directory {$shared_path} created!\n");
}

//create directories which are shared between releases
foreach ($shared_dirs as $dir) {
    if (!is_dir($shared_path . DIRECTORY_SEPARATOR . $dir)) {
        mkdir($shared_path . DIRECTORY_SEPARATOR . $dir);
        echo("Directory {$dir} created!\n");
    }
}

//create directory with release name
if (!is_dir($release_path)) {
    mkdir($release_path);
    echo("Directory {$release_path} created!\n");
} else {
    throw new Exception("The selected release '{$release_name}' does already exist on the server!');
}

//copy directories which should get copied from old to new releases
if (!is_dir($current_path)) {
    foreach ($copy_dirs as $dir) {
        mkdir($release_path . DIRECTORY_SEPARATOR . $dir);
        echo("Directory {$dir} created!\n");
    }
} else {
    foreach ($copy_dirs as $dir) {
        $dst = $release_path . DIRECTORY_SEPARATOR . $dir;
        $src = $current_path . DIRECTORY_SEPARATOR . $dir;
        recurseCopy($src, $dst);
        echo("Directory {$dir} copied!\n");
    }    
}

//creating symlinks to shared directories
chdir($release_path);
foreach($shared_dirs as $dir) {
    $target_pointer = $shared_path . DIRECTORY_SEPARATOR . $dir;
    $test = symlink($target_pointer, $dir);
    if (! $test)
    {
        throw new Exception("Symlink to {$dir} could not be created");
    }
    echo("Symlink to {$dir} created!\n");
}

//extract archive
echo("Extracting archive ...\n");
chdir($release_path);
extractArchive();
echo("Archive extracted!\n");

//copy needed app configs, if not already exist
if (is_dir($base_config_path)) {
    foreach($config_files as $file) {
        if(!file_exists($config_path . DIRECTORY_SEPARATOR . $file)) {
            copy($base_config_path . DIRECTORY_SEPARATOR . $file, $config_path . DIRECTORY_SEPARATOR . $file);
            echo("Base config {$file} copied!\n");
        }
    }
    deleteDirectory($base_config_path);
    echo("Directory {$base_config_path} removed!\n");
}

//permissions
//chmod($release_path, 0777);
//chmod($release_path . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin', 0777);
//echo("Permissions set!\n");

//create 'current' symlink to new release
chdir($deploy_path);
if (is_dir($current_path)) {
    rmdir($current_path);
}
$target_pointer = $release_path;
$test = symlink($target_pointer, $relative_current_path);
if (!$test)
{
    throw new Exception("Symlink to {$relative_current_path} could not be created");
} else {
    echo("Symlink to {$relative_current_path} created!\n");
}

//create 'exface' symlink to 'current'
chdir($basic_deploy_path);
if (is_dir($basic_deploy_path . DIRECTORY_SEPARATOR . 'exface')) {
    rmdir($basic_deploy_path . DIRECTORY_SEPARATOR . 'exface');
}
$target_pointer = $current_path;
$test = symlink($target_pointer, 'exface');
if (!$test)
{
    throw new Exception("Symlink to exface could not be created");
} else {
    echo("Symlink to exface created!\n");
}

//Install Apps
//require $release_path . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
//echo axenox\PackageManager\StaticInstaller::composerFinishInstall();

$path = $basic_deploy_path . DIRECTORY_SEPARATOR . 'exface';
if (substr(php_uname(), 0, 7) == "Windows"){
    $command = "cd {$path} && {$php_path} composer.phar run-script post-install-cmd";
}
else {
    //TODO not tested yet on Linux!
    $command = "cd {$path} && {$php_path} composer.phar run-script post-install-cmd";
}
echo ("Installing apps...\n");
$cmdarray = [];
echo exec("{$command}", $cmdarray);
foreach($cmdarray as $line) {
    echo ($line . "\n");
}

//delete this file
unlink(__FILE__);
echo ("Self deployment file deleted!\n");

//Functions
//copy whole directory (with subdirectories)
function recurseCopy(string $src, string $dst) {
    $dir = opendir($src);
    mkdir($dst);
    while(false !== ( $file = readdir($dir)) ) {
        if (( $file != '.' ) && ( $file != '..' )) {
            if ( is_dir($src . DIRECTORY_SEPARATOR . $file) ) {
                recurseCopy($src . DIRECTORY_SEPARATOR . $file, $dst . DIRECTORY_SEPARATOR . $file);
            }
            else {
                copy($src . DIRECTORY_SEPARATOR . $file, $dst . DIRECTORY_SEPARATOR . $file);
            }
        }
    }
    closedir($dir);
}

//removing dir that is not empty
function deleteDirectory(string $dir) {
    if (!file_exists($dir)) {
        return true;
    }
    
    if (!is_dir($dir)) {
        return unlink($dir);
    }
    
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }
        
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
        
    }
    
    return rmdir($dir);
}

//extracting archive that is appended to this php file
function extractArchive()
{
    try {
        $pharfilename = md5(time()).'archive.tar'; //remove with tempname()
        $fp_tmp = fopen($pharfilename,'w');
        $fp_cur = fopen(__FILE__, 'r');
        fseek($fp_cur, __COMPILER_HALT_OFFSET__);
        while($buffer = fread($fp_cur,10240)) {
            fwrite($fp_tmp,$buffer);
        }
        fclose($fp_cur);
        fclose($fp_tmp);
        try {
            $phar = new PharData($pharfilename);
            $phar->extractTo('.');
        } catch (Exception $e) {
            throw new Exception('extraction failed!');
        }
        unlink($pharfilename);
    } catch (Exception $e) {
        printf("Error:<br/>%s<br>%s>",$e->getMessage(),$e->getTraceAsString());
    };
}
//IMPORTANT: no empty lines after "____HALT_COMPILER();" else archive extraction wont work!
__HALT_COMPILER();