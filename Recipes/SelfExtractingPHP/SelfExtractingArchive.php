<?php

ini_set('memory_limit', '-1'); // or you could use 1G
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
        throw new Exception('extraction failed...');
    }
    unlink($pharfilename);
} catch (Exception $e) {
    printf("Error:<br/>%s<br>%s>",$e->getMessage(),$e->getTraceAsString());
};
__HALT_COMPILER();