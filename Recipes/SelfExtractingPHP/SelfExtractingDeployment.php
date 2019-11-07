<?php

ini_set('memory_limit', '-1'); // or you could use 1G

$basicDeployPath = '[#basic#]'; //placeholder for string
$relativeDeployPath = '[#relative#]'; //placeholder for string
$sharedDirs = [#shared#]; //placeholder for array
$copyDirs = [#copy#]; //placeholder for array
$keepReleases = [#releases#]; //placeholder for integer
$phpPath = '[#php#]'; //placeholder for string
$relativeReleasesPath = 'releases';
$relativeSharedPath = 'shared';
$relativeCurrentPath = 'current';
$deployPath = $basicDeployPath .  DIRECTORY_SEPARATOR . $relativeDeployPath;
$releasesPath = $deployPath . DIRECTORY_SEPARATOR . $relativeReleasesPath;
$sharedPath = $deployPath . DIRECTORY_SEPARATOR . $relativeSharedPath;
$currentPath = $deployPath . DIRECTORY_SEPARATOR . $relativeCurrentPath;

$releaseName = pathinfo(__FILE__,  PATHINFO_FILENAME);
$releasePath = $releasesPath . DIRECTORY_SEPARATOR . $releaseName;
$baseConfigPath = $releasePath . DIRECTORY_SEPARATOR . 'base-config';
$configPath = $releasePath . DIRECTORY_SEPARATOR . 'config';
$configFiles = ['exface.ModxCmsConnector.config.json'];

//create relative deploy path directory if it not exists yet
if (!is_dir($deployPath)) {
    mkdir($deployPath);
    echo("Directory {$deployPath} created!\n");
}

//create releases directory if it not exists yet
if (!is_dir($releasesPath)) {
    mkdir($releasesPath);
    echo("Directory {$releasesPath} created!\n");
}

//create shared directory if it not exists yet
if (!is_dir($sharedPath)) {
    mkdir($sharedPath);
    echo("Directory {$sharedPath} created!\n");
}

//create directories which are shared between releases
foreach ($sharedDirs as $dir) {
    if (!is_dir($sharedPath . DIRECTORY_SEPARATOR . $dir)) {
        mkdir($sharedPath . DIRECTORY_SEPARATOR . $dir);
        echo("Directory {$dir} created!\n");
    }
}

//create directory with release name
if (!is_dir($releasePath)) {
    mkdir($releasePath);
    echo("Directory {$releasePath} created!\n");
} else {
    throw new Exception("The selected release '{$releaseName}' does already exist on the server");
}

//copy directories which should get copied from old to new releases
if (!is_dir($currentPath)) {
    foreach ($copyDirs as $dir) {
        mkdir($releasePath . DIRECTORY_SEPARATOR . $dir);
        echo("Directory {$dir} created!\n");
    }
} else {
    foreach ($copyDirs as $dir) {
        $dst = $releasePath . DIRECTORY_SEPARATOR . $dir;
        $src = $currentPath . DIRECTORY_SEPARATOR . $dir;
        recurseCopy($src, $dst);
        echo("Directory {$dir} copied!\n");
    }    
}

//creating symlinks to shared directories
chdir($releasePath);
foreach($sharedDirs as $dir) {
    $target_pointer = $sharedPath . DIRECTORY_SEPARATOR . $dir;
    $test = symlink($target_pointer, $dir);
    if (! $test)
    {
        throw new Exception("Symlink to {$dir} could not be created");
    }
    echo("Symlink to {$dir} created!\n");
}

//extract archive
echo("Extracting archive ...\n");
chdir($releasePath);
extractArchive();
echo("Archive extracted!\n");

//copy needed app configs, if not already exist
if (is_dir($baseConfigPath)) {
    foreach($configFiles as $file) {
        if(!file_exists($configPath . DIRECTORY_SEPARATOR . $file)) {
            copy($baseConfigPath . DIRECTORY_SEPARATOR . $file, $configPath . DIRECTORY_SEPARATOR . $file);
            echo("Base config {$file} copied!\n");
        }
    }
    deleteDirectory($baseConfigPath);
    echo("Directory {$baseConfigPath} removed!\n");
}

//permissions
chmod($releasePath, 0777);
chmod($releasePath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin', 0777);
echo("Permissions set!\n");

//create 'current' symlink to new release
chdir($deployPath);
if (is_dir($currentPath)) {
    rmdir($currentPath);
}
$target_pointer = $releasePath;
$test = symlink($target_pointer, $relativeCurrentPath);
if (!$test)
{
    throw new Exception("Symlink to {$relativeCurrentPath} could not be created");
} else {
    echo("Symlink to {$relativeCurrentPath} created!\n");
}

//create 'exface' symlink to 'current'
chdir($basicDeployPath);
if (is_dir($basicDeployPath . DIRECTORY_SEPARATOR . 'exface')) {
    rmdir($basicDeployPath . DIRECTORY_SEPARATOR . 'exface');
}
$target_pointer = $currentPath;
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

$path = $basicDeployPath . DIRECTORY_SEPARATOR . 'exface';
if (substr(php_uname(), 0, 7) == "Windows"){
    $command = "cd {$path} && {$phpPath} composer.phar run-script post-install-cmd";
}
else {
    //TODO not tested yet on Linux!
    $command = "cd {$path} && {$phpPath} composer.phar run-script post-install-cmd";
}
echo ("Installing apps...\n");
$cmdarray = [];
echo exec("{$command}", $cmdarray);
foreach($cmdarray as $line) {
    echo ($line . "\n");
}

//create/append release list file
if (!file_exists ($releasesPath . DIRECTORY_SEPARATOR . "releases.txt")) {
    file_put_contents($releasesPath . DIRECTORY_SEPARATOR . "releases.txt", $releaseName . "\r\n");
    echo ("Release list file created!\n");
} else {
    file_put_contents($releasesPath . DIRECTORY_SEPARATOR . "releases.txt", $releaseName . "\r\n", FILE_APPEND);
    echo ("Release added to list file!\n");
}

//delete old releases
$dirList =file($releasesPath . DIRECTORY_SEPARATOR . "releases.txt", FILE_IGNORE_NEW_LINES);
$releaseCount = count($dirList);
if ($releaseCount > $keepReleases) {
    echo ("Deleting old releases...\n");
    for ($i = 0; $i < $releaseCount - $keepReleases; $i++) {
        $dir = $releasesPath . DIRECTORY_SEPARATOR . $dirList[$i];
        deleteDirectory($dir);
        $contents = file_get_contents($releasesPath . DIRECTORY_SEPARATOR . "releases.txt");
        $contents = str_replace($dirList[$i] . "\r\n", '', $contents);
        file_put_contents($releasesPath . DIRECTORY_SEPARATOR . "releases.txt", $contents);
        echo ("Deleted directory: " . $dir . "\n");
    }
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
        chmod($dir, 0777);
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