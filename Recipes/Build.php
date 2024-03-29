<?php

namespace Deployer;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\StreamOutput;
use Deployer\Exception\ConfigurationException;
use kabachello\ComposerAPI\ComposerAPI;

/**
 * get the given name of the release, if none is give, take newest file in builds directory
 */
task('build:find', function () {
    if (get('release_name') == '') {
        if (input()->getOption('build') != null) {
            $releaseName = input()->getOption('build');
            $archivName = $releaseName . '.tar.gz';
            set('release_name', $releaseName);
            set('archiv_name', $archivName);
        } else {
            $directory = get('builds_archives_path');
            $files = scandir($directory, SCANDIR_SORT_DESCENDING);
            $temp = [];
            foreach ($files as $file) {
                if ($file == '.' || $file == '..') {
                    continue;
                }
                $temp[$file] = filemtime($directory . DIRECTORY_SEPARATOR . $file);
            }            
            arsort($temp);
            $filesName = array_keys($temp);
            $newest_file = $filesName[0];
            $archivName = $newest_file;
            $stringLength = strlen($archivName);
            $releaseName = substr($archivName,0,($stringLength-7));
            set('release_name', $releaseName );
            set('archiv_name', $archivName);
        }
    }
    set('release_path' , 'releases/{{release_name}}');    
});

/**
 * generate release name if none is given
 */
task('build:generate_release_name', function(){
    try {
        $releaseName = get('release_name');
    } catch (ConfigurationException $e) {
        $releaseName = null;
    }
    echo('Name: ' . $releaseName . PHP_EOL);
    if ($releaseName ==='' || $releaseName === null) {
        $currentDate = new \DateTime('now' , new \DateTimeZone(get('time_zone')));
        $currentDate = $currentDate->format('YmdHis');
        $releaseName = get('customer_specific_version') . '+build' . $currentDate;
        set('release_name', $releaseName );
    }
    $archivName = $releaseName . '.tar.gz';
    set('archiv_name', $archivName);
});

/**
 * create build archive by cloning local PowerUI installation
 */
task('build:create_from_local', function() {
    $buildsPath = get('builds_archives_path');
    if (!is_dir($buildsPath)) {
        mkdir($buildsPath);
    }
    runLocally('tar -czf {{builds_archives_path}}\{{archiv_name}} {{source_files}}');        
});

/**
 * create a builds archive using composer.json
 */
task('build:create_from_composer', function() {
    $buildsPath = get('builds_archives_path');
    if (!is_dir($buildsPath)) {
        mkdir($buildsPath);
    }
    
    // Use a high timeout for composer install!!!
    $composer_timeout = get('composer_timeout');
    $composerOutput = runLocally('cd {{builds_archives_path}}\.. && php composer.phar install --prefer-dist', ['timeout' => $composer_timeout]);
    write($composerOutput);
    writeln('');
    
    /*
    $composerApi = new ComposerAPI(get('builds_archives_path') . DIRECTORY_SEPARATOR . '..');
    $composerApi->set_path_to_composer_home(get('builds_archives_path') . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '.composer');
    $output = $composerApi->install();
    if ($output instanceof StreamOutput) {
        $stream = $output->getStream();
        rewind($stream);
        echo stream_get_contents($stream) . PHP_EOL;
    } else {
        echo $output . PHP_EOL;
    }
    */
    $inp = file_get_contents($buildsPath . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR. 'composer.json');
    $tempArray = json_decode($inp, true);
    $tmp1 = [];
    $tmp1["axenox\\PackageManager"] = "vendor/";
    $tmp2 = [];
    $tmp2["psr-0"] = $tmp1;
    $tempArray["autoload"] = $tmp2;
    $tempArray["optimize-autoloader"] = true;
    $tmp3 = [];
    $tmp3["post-update-cmd"] = array("axenox\\PackageManager\\StaticInstaller::composerFinishUpdate");
    $tmp3["post-install-cmd"] = array("axenox\\PackageManager\\StaticInstaller::composerFinishInstall");
    $tmp3["post-package-install"] = array("axenox\\PackageManager\\StaticInstaller::composerFinishPackageInstall");
    $tmp3["post-package-update"] = array("axenox\\PackageManager\\StaticInstaller::composerFinishPackageUpdate");
    $tempArray["scripts"] = $tmp3;
    $jsonData = json_encode($tempArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    file_put_contents($buildsPath . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR. 'composer.json', $jsonData);
    
    runLocally('cd {{builds_archives_path}} && tar -czf {{archiv_name}} -C {{builds_archives_path}}/.. {{source_files}}', ['timeout' => $composer_timeout]);

    if (substr(php_uname(), 0, 7) == "Windows"){
        runLocally('rmdir /s /q "{{builds_archives_path}}' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'vendor"', ['timeout' => $composer_timeout]);
    } else {
        runLocally('rm -rf {{builds_archives_path}}' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'vendor', ['timeout' => $composer_timeout]);
    }
});