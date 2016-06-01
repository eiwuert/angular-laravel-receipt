<?php
/*
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/start.php';
require_once __DIR__.'/app/start/local.php';
*/

function writeLog($content) {
    $file 	  = dirname(__FILE__) . '/stress_log.txt';
    $current  = file_get_contents($file);
    $current .= "[>>] " . $content;
    file_put_contents($file, $current);
}

$ftype = "IMG";
$time = time();
writeLog ('Start Stress Test at: ' . $time);

for ($i=1; $i<=25; $i++) {
    $newFilePath = "/var/www/receiptclub_test/api/v1/files/tmp_upload/stresstest/receipt_test_" . $i . ".jpg";
    $oriFileName = "receipt_test_" . $i . ".jpg";
    shell_exec('php stress_gearman.php ' . 100 . ' "' . $ftype .  '" "' . $newFilePath . '" "' . $oriFileName . '" > /dev/null 2>/dev/null &');
}
