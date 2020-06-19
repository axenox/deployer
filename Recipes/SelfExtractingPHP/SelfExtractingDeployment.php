<?php

ini_set('memory_limit', '1G'); // or you could use -1
set_time_limit(0);
ini_set('max_execution_time', 0);

$basicDeployPath = '[#basic#]'; //placeholder for string
$relativeDeployPath = '[#relative#]'; //placeholder for string
$sharedDirs = [#shared#]; //placeholder for array
$copyDirs = [#copy#]; //placeholder for array
$keepReleases = [#releases#]; //placeholder for integer
// The deployment config of the host
$deployConfig = [#deploy_config#]; // array with local vendors, base config, etc.
$phpPath = '[#php#]'; //placeholder for string
$relativeReleasesPath = 'releases';
$relativeSharedPath = 'shared';
$relativeCurrentPath = 'current';
$exfaceFolderName = 'exface';
$deployPath = $basicDeployPath .  DIRECTORY_SEPARATOR . $relativeDeployPath;
$releasesPath = $deployPath . DIRECTORY_SEPARATOR . $relativeReleasesPath;
$sharedPath = $deployPath . DIRECTORY_SEPARATOR . $relativeSharedPath;
$currentPath = $deployPath . DIRECTORY_SEPARATOR . $relativeCurrentPath;
$exfacePath = $basicDeployPath . DIRECTORY_SEPARATOR . $exfaceFolderName;

$releaseName = pathinfo(__FILE__,  PATHINFO_FILENAME);
$releasePath = $releasesPath . DIRECTORY_SEPARATOR . $releaseName;
$configPath = $releasePath . DIRECTORY_SEPARATOR . 'config';

$oldReleasePath = null;
if (is_dir($currentPath)) {
    chdir($currentPath);
    $oldReleasePath = getcwd();
    chdir($basicDeployPath);
}

if (is_dir($releasePath)) {
    exit("The selected release '{$releaseName}' does already exist on the server!\n");
}

try {
    //create relative deploy path directory if it not exists yet
    if (!is_dir($deployPath)) {
        if (mkdir($deployPath) === true) {
            echo("Directory {$deployPath} created!\n");
        } else {
            throw new Exception("Directory {$deployPath} could not be created!\n");
        }
    }
    
    //create releases directory if it not exists yet
    if (!is_dir($releasesPath)) {
        if (mkdir($releasesPath) === true) {
            echo("Directory {$releasesPath} created!\n");
        } else {
            throw new Exception("Directory {$releasesPath} could not be created!\n");
        }
    }
    
    //create shared directory if it not exists yet
    if (!is_dir($sharedPath)) {
        if (mkdir($sharedPath) === true) {
            echo("Directory {$sharedPath} created!\n");
        } else {
            throw new Exception("Directory {$sharedPath} could not be created!\n");
        }
    }
    
    //create directories which are shared between releases
    foreach ($sharedDirs as $dir) {
        if (!is_dir($sharedPath . DIRECTORY_SEPARATOR . $dir)) {
            if (mkdir($sharedPath . DIRECTORY_SEPARATOR . $dir) === true) {
                echo("Directory {$sharedPath}\\{$dir} created!\n");
            } else {
                throw new Exception("Directory {$sharedPath}\\{$dir} could not be created!\n");
            }
        }
    }
    
    //create directory with release name
    if (mkdir($releasePath) === true) {
        echo("Directory {$releasePath} created!\n");
    } else {
        throw new Exception("Directory {$releasePath} could not be created!\n");
    }
    
    //copy directories which should get copied from old to new releases
    if (!is_dir($currentPath)) {
        foreach ($copyDirs as $dir) {
            if (mkdir($releasePath . DIRECTORY_SEPARATOR . $dir) === true) {
                echo("Directory {$releasePath}\\{$dir} created!\n");
            } else {            
                deleteDirectory($releasePath);
                throw new Exception("Directory {$releasePath}\\{$dir} could not be created!\n");
            }
            
        }
    } else {
        foreach ($copyDirs as $dir) {
            $dst = $releasePath . DIRECTORY_SEPARATOR . $dir;
            $src = $currentPath . DIRECTORY_SEPARATOR . $dir;
            recurseCopy($src, $dst);
            echo("Directory {$releasePath}\\{$dir} copied!\n");
        }    
    }
    
    //creating symlinks to shared directories
    chdir($releasePath);
    foreach($sharedDirs as $dir) {
        $target_pointer = $sharedPath . DIRECTORY_SEPARATOR . $dir;
        echo ('DEBUG: ' . $target_pointer . (count(scandir($target_pointer)) == 2 ? ' EMPTY!!!' : ' not empty') . PHP_EOL);
        $test = symlink($target_pointer, $dir);
        if (! $test) {
            throw new Exception("Symlink to {$releasesPath}\\{$dir} could not be created: from {$target_pointer}");
        }
        echo("Symlink to {$dir} created!\n");
    }
    
    //extract archive
    echo("Extracting archive ...\n");
    chdir($releasePath);
    if (extractArchive() === true) {        
        echo("Archive extracted!\n");
    } else {
        throw new Exception("Archive could not be extracted from file!\n");
    }
    
    //copy needed app configs, if not already exist
    if (is_array($deployConfig['default_app_config'])) {
        foreach($deployConfig['default_app_config'] as $fileName => $configArray) {
            $filePath = $configPath . DIRECTORY_SEPARATOR . $fileName;
            if(! file_exists($filePath)) {
                file_put_contents($filePath, json_encode($configArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                echo("Config {$fileName} created." . PHP_EOL);
            }
        }
    }
    
    //uninstall old apps
    if ($oldReleasePath !== null) {
        chdir($basicDeployPath);
        require $releasePath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        $oldVendorPath = $currentPath . DIRECTORY_SEPARATOR . 'vendor';
        chdir($deployPath);
        $newAppsAliases = axenox\PackageManager\Actions\ListApps::findAppAliasesInVendorFolders($releasePath . DIRECTORY_SEPARATOR . 'vendor');
        $oldAppsAliases = axenox\PackageManager\Actions\ListApps::findAppAliasesInVendorFolders($oldVendorPath);
        $uninstallAppsAliases = array_diff($oldAppsAliases, $newAppsAliases);
        $uninstallAppsAliases = array_values($uninstallAppsAliases);
        for ($i = 0; $i < count($uninstallAppsAliases); $i++) {
            $arr = explode('.', $uninstallAppsAliases[$i]);
            $appsVendor = $arr[0];
            if (is_array($deployConfig['local_vendors'])) {
                foreach ($deployConfig['local_vendors'] as $vendor) {
                    if (strpos($vendor, $appsVendor) !== FALSE) {                
                        unset ($uninstallAppsAliases[$i]);
                    }
                }
            }
        }
        $uninstallAppsAliases = array_values($uninstallAppsAliases);
        $actionPath = 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'action';
        if (count($uninstallAppsAliases) > 0) {
            echo("Uninstalling old apps...\n");
        }
        foreach ($uninstallAppsAliases as $alias) {
            $command = "cd {$currentPath} && {$actionPath} axenox.packagemanager:uninstallApp {$alias}";
            $cmdarray = [];
            try {
                exec("{$command}", $cmdarray);
                foreach($cmdarray as $line) {
                    echo ($line . "\n");
                }
            } catch (\Throwable $e) {
                echo ('ERROR: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() . PHP_EOL);
                while ($e = $e->getPrevious()) {
                    echo ('ERROR: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() . PHP_EOL);
                }
            }
        }
    }
    
    //permissions
    chmod($releasePath, 0777);
    chmod($releasePath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin', 0777);
    echo("Permissions set!\n");
    
    //create 'current' symlink to new release
    echo ('DEBUG 2: ' . $sharedPath . DIRECTORY_SEPARATOR . 'logs' . (count(scandir($sharedPath . DIRECTORY_SEPARATOR . 'logs')) == 2 ? ' EMPTY!!!' : ' not empty') . PHP_EOL);
    chdir($deployPath);
    if (is_dir($currentPath)) {
        rmdir($currentPath);
    }
    $target_pointer = $releasePath;
    $test = symlink($target_pointer, $relativeCurrentPath);
    if (!$test) {
        throw new Exception("Symlink to {$relativeCurrentPath} could not be created: from {$target_pointer}");
    } else {
        echo("Symlink to {$relativeCurrentPath} created!\n");
    }
    echo ('DEBUG 3: ' . $sharedPath . DIRECTORY_SEPARATOR . 'logs' . (count(scandir($sharedPath . DIRECTORY_SEPARATOR . 'logs')) == 2 ? ' EMPTY!!!' : ' not empty') . PHP_EOL);
    
    // when relative path is empty (no modx) then create special .htaccess
    if ($relativeDeployPath == '' || $relativeDeployPath == null) {
        createHtaccess($basicDeployPath);
    } else {
        //create 'exface' symlink to 'current'
        chdir($basicDeployPath);
        if (is_dir($exfacePath)) {
            rmdir($exfacePath);
        }
        $target_pointer = $currentPath;
        $test = symlink($target_pointer, $exfaceFolderName);
        if (!$test)
        {
            throw new Exception("Symlink to exface could not be created");
        } else {
            echo("Symlink to exface created!\n");
        }
    }
    
    //Install Apps
    //require $release_path . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    //echo axenox\PackageManager\StaticInstaller::composerFinishInstall();
    
    if (substr(php_uname(), 0, 7) == "Windows"){
        $command = "cd {$currentPath} && {$phpPath} composer.phar run-script post-install-cmd";
    }
    else {
        //TODO not tested yet on Linux!
        $command = "cd {$currentPath} && {$phpPath} composer.phar run-script post-install-cmd";
    }
    echo ("Installing apps...\n");
    echo ('DEBUG 4: ' . $sharedPath . DIRECTORY_SEPARATOR . 'logs' . (count(scandir($sharedPath . DIRECTORY_SEPARATOR . 'logs')) == 2 ? ' EMPTY!!!' : ' not empty') . PHP_EOL);
    echo ("Execute command: {$command} \n");
    $cmdarray = [];
    echo exec("{$command}", $cmdarray);
    foreach($cmdarray as $line) {
        echo ($line . "\n");
    }
    
    echo ('DEBUG 5: ' . $sharedPath . DIRECTORY_SEPARATOR . 'logs' . (count(scandir($sharedPath . DIRECTORY_SEPARATOR . 'logs')) == 2 ? ' EMPTY!!!' : ' not empty') . PHP_EOL);
    //copy Apps from local vendors
    if ($oldReleasePath !== null && is_array($deployConfig['local_vendors'])) {
        foreach ($deployConfig['local_vendors'] as $local) {
            if ($local === null || $local === '') {
                continue;
            }
            $releaseLocalDir = $releasePath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . $local;
            if (!is_dir($releaseLocalDir)) {
                if (mkdir($releaseLocalDir) === true) {
                    echo("Directory {$releasePath}\\{$dir} created!\n");
                } else {
                    throw new Exception("Directory {$releaseLocalDir} could not be created!\n");
                }
            }
            foreach (glob($oldReleasePath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . $local . DIRECTORY_SEPARATOR . '*' , GLOB_ONLYDIR) as $appPath) {
                $tmp = explode($local, $appPath);
                $appPathRelative = array_pop($tmp);
                $appPathNew = $releaseLocalDir . $appPathRelative;
                if ( !is_dir($appPathNew)) {
                    echo ("Copying local app: " . $appPathRelative . "\n");
                    recurseCopy($appPath, $appPathNew);
                }
            }
        }
    }
    
    //create/append release list file, deleting old releases
    echo ('DEBUG 6: ' . $sharedPath . DIRECTORY_SEPARATOR . 'logs' . (count(scandir($sharedPath . DIRECTORY_SEPARATOR . 'logs')) == 2 ? ' EMPTY!!!' : ' not empty') . PHP_EOL);
    cleanupReleases($deployPath, $releaseName, $releasesPath, $keepReleases);
    echo ('DEBUG 7: ' . $sharedPath . DIRECTORY_SEPARATOR . 'logs' . (count(scandir($sharedPath . DIRECTORY_SEPARATOR . 'logs')) == 2 ? ' EMPTY!!!' : ' not empty') . PHP_EOL);
    
    echo ("Self deployment successful!\n");
    
} catch (Exception $e) {
    echo("Error - Line {$e->getLine()}: {$e->getMessage()}");
    if (is_dir($releasePath)) {
        deleteDirectory($releasePath);
        echo("Directory {$releasePath} removed!\n");
        //create 'current' symlink to old release
        chdir($deployPath);
        if (is_dir($currentPath)) {
            rmdir($currentPath);
        }
        if ($oldReleasePath !== null) {
            $target_pointer = $oldReleasePath;
            $test = symlink($target_pointer, $relativeCurrentPath);
            if (!$test)
            {
                echo("Symlink to {$relativeCurrentPath} could not be created: from old release {$target_pointer}!\n");
            } else {
                echo("Symlink to {$relativeCurrentPath} created: from old release {$target_pointer}!\n");
            }
            //create 'exface' symlink to 'current'
            chdir($basicDeployPath);
            chdir($basicDeployPath);
            if (is_dir($exfacePath)) {
                rmdir($exfacePath);
            }
            $target_pointer = $currentPath;
            $test = symlink($target_pointer, $exfaceFolderName);
            if (!$test)
            {
                echo("Symlink to exface could not be created!\n");
            } else {
                echo("Symlink to exface created!\n");
            }
        }       
    }
}

//Functions
//copy whole directory (with subdirectories)
function recurseCopy(string $src, string $dst) : void
{
    $dir = opendir($src);
    if (mkdir($dst) === true) {
        while(false !== ( $file = readdir($dir)) ) {
            if (( $file != '.' ) && ( $file != '..' )) {
                if ( is_dir($src . DIRECTORY_SEPARATOR . $file) ) {
                    recurseCopy($src . DIRECTORY_SEPARATOR . $file, $dst . DIRECTORY_SEPARATOR . $file);
                }
                else {
                    if (copy($src . DIRECTORY_SEPARATOR . $file, $dst . DIRECTORY_SEPARATOR . $file) === false) {
                        throw new Exception("File {$file} could not be copied from {$src} to {$dst}!\n");
                    }
                }
            }
        }
    } else {
        throw new Exception("Directory {$dst} could not be created!\n");
    }
    closedir($dir);
    return;
}

//removing dir that is not empty
function deleteDirectory(string $dir) : bool
{
    $success = false;
    if (!file_exists($dir)) {
        return true;
    }
    
    if (is_link($dir)) {
        echo ("Removing link '{$dir}'!\n");
        chmod($dir, 0777);
        $success = false;
        $success = @unlink($dir);
        if ($success === false) {
            $success = rmdir($dir);
        }
        echo ('DEBUG: Deleting symbolic link: ' . $dir . ($success === true ? ' successful!' : ' failed!') . PHP_EOL);
        
        return $success;
    }
    
    if (!is_dir($dir)) {
        chmod($dir, 0777);
        $success = unlink($dir);
        return $success;
    }
    
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
        
    }
    chmod($dir, 0777);
    $success = rmdir($dir);
    return $success;
}

//deletin old releases, adding new release to logfile
function cleanupReleases(string $deployPath, string $releaseName, string $releasesPath, int $keepReleases) : void
{
    $depPath = $deployPath . DIRECTORY_SEPARATOR . '.dep';
    
    //creating .dep directory
    if (!is_dir($depPath)) {
        if (mkdir($depPath) === false) {
            throw new Exception("Directory {$depPath} could not be created!\n");
        }
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
        $success = deleteDirectory($dir);        
        echo ('Deleting release: ' . $dir . ($success === true ? ' successful!' : ' failed!') . PHP_EOL);
    }    
    return;
}

function createHtaccess($path) : void
{
    $content = <<<TXT
    
RewriteEngine On
RewriteRule ^(.*)$ current/$1 [L,QSA]
TXT;
    file_put_contents($path . DIRECTORY_SEPARATOR . '.htaccess', $content);
    echo("htaccess file created!\n");
    return;
}

function createWebConfig($path) : void
{
    $content = <<<TXT
    
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
	<location path="." inheritInChildApplications="false"> 
		<system.webServer>
			<rewrite>
			  <rules>
				<rule name="Redirect to Current" stopProcessing="false">
				  <match url="^(.*)$" ignoreCase="false" />
				  <action type="Redirect" url="current/{R:1}" appendQueryString="true" redirectType="Temporary"/>
				</rule>
			  </rules>
			</rewrite>
		</system.webServer>
	</location>
</configuration>
TXT;
    file_put_contents($path . DIRECTORY_SEPARATOR . 'Web.config', $content);
    echo("Web.Config file created!\n");
    return;
}

//extracting archive that is appended to this php file
function extractArchive() : bool
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
            throw new Exception($e->getMessage());
        }
        unlink($pharfilename);
    } catch (Exception $e) {
        return false;
    }
    return true;
}
//IMPORTANT: no empty lines after "____HALT_COMPILER();" else archive extraction wont work!
__HALT_COMPILER();