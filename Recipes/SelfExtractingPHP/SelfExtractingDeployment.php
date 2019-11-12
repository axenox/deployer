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
$exfacePath = $basicDeployPath . DIRECTORY_SEPARATOR . 'exface';

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

//uninstall old apps
if (is_dir($currentPath)) {
    echo("Uninstalling old apps...\n");
    require $releasePath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    $oldVendorPath = $currentPath . DIRECTORY_SEPARATOR . 'vendor';
    $newAppsAliases = axenox\PackageManager\Actions\ListApps::findAppAliasesInVendorFolders($releasePath . DIRECTORY_SEPARATOR . 'vendor');
    $oldAppsAliases = axenox\PackageManager\Actions\ListApps::findAppAliasesInVendorFolders($oldVendorPath);
    $uninstallAppsAliases = array_diff($oldAppsAliases, $newAppsAliases);
    $actionPath = 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'action';
    foreach ($uninstallAppsAliases as $alias) {        
        $command = "cd {$exfacePath} && {$actionPath} axenox.packagemanager:uninstallApp {$alias}";
        $cmdarray = [];
        echo exec("{$command}", $cmdarray);
        foreach($cmdarray as $line) {
            echo ($line . "\n");
        }
    }
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

if (substr(php_uname(), 0, 7) == "Windows"){
    $command = "cd {$exfacePath} && {$phpPath} composer.phar run-script post-install-cmd";
}
else {
    //TODO not tested yet on Linux!
    $command = "cd {$exfacePath} && {$phpPath} composer.phar run-script post-install-cmd";
}
echo ("Installing apps...\n");
$cmdarray = [];
echo exec("{$command}", $cmdarray);
foreach($cmdarray as $line) {
    echo ($line . "\n");
}

//create/append release list file, deleting old releases
cleanupReleases($deployPath, $releaseName, $releasesPath, $keepReleases);

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
    return;
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

//deletin old releases, adding new release to logfile
function cleanupReleases(string $deployPath, string $releaseName, string $releasesPath, int $keepReleases) {
    $depPath = $deployPath . DIRECTORY_SEPARATOR . '.dep';
    
    //creating .dep directory
    if (!is_dir($depPath)) {
        mkdir($depPath);
    }
    $date = date('YmdHis');
    $line = $date . ',' . $releaseName;
    
    //adding new release to logfile
    if (!file_exists ($depPath . DIRECTORY_SEPARATOR . "releases")) {
        file_put_contents($depPath . DIRECTORY_SEPARATOR . "releases", $line . "\n");
        echo ("Releases log file created!\n");
    } else {
        file_put_contents($depPath . DIRECTORY_SEPARATOR . "releases", $line . "\n", FILE_APPEND);
        echo ("Release added to log file!\n");
    }
    
    if ($keepReleases === -1) {
        // Keep unlimited releases.
        return;
    }
    
    //reading logfile into array, maximum of last n*2+5 lines
    $logList = [];
    $fp = fopen($depPath . DIRECTORY_SEPARATOR . "releases", "r");
    while (!feof($fp))
    {
        $line = fgets($fp, 4096);
        if ($line == '' || $line == "\n" || $line == "\r\n") {
            continue;
        }
        $line = trim(preg_replace('/\s\s+/', '', $line));
        array_push($logList, $line);
        if (count($logList) > (2 * $keepReleases + 5)) {
            array_shift($logList);
        }
    }
    
    //reading directory in $releasesPath into array
    $tmp = getcwd();
    chdir($releasesPath);
    $dirList = glob('*' , GLOB_ONLYDIR);
    chdir($tmp);

    //checking if release in $logList still exists as directory, if so adding it to $releaseList array
    $releasesList = [];
    for ($i = count($logList) - 1; $i >= 0; $i--) {
        $arr = explode(',', $logList[$i]);
        $name = $arr[1];
        $index = array_search($name, $dirList, true);
        if ($index !== false) {
            $releasesList[] = $name;
            unset($dirList[$index]);
        }
    }

    //deleting number of to be kept releases from $releasesList
    $keep = $keepReleases;
    while ($keep > 0) {
        array_shift($releasesList);
        --$keep;
    }
    
    //deleting all folders from releases still in $releasesList
    foreach ($releasesList as $release){
        echo ("Deleting release: " . $release . "\n");
        $dir = $releasesPath . DIRECTORY_SEPARATOR . $release;
        deleteDirectory($dir);
        echo ("Deleted release: " . $release . "\n");
    }    
    return;
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
    return;
}
//IMPORTANT: no empty lines after "____HALT_COMPILER();" else archive extraction wont work!
__HALT_COMPILER();