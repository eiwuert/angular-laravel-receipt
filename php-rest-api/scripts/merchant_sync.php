<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/start.php';
$app->boot();

if (isset($argv[2])) {
    $ocrIP = $argv[1];
    $merchantIds = $argv[2];

    /** REQUEST OCR PROCESS RECEIPT **/
    $jobDone       = false;
    $retry         = 0;

    $mcData = MerchantSynch::buildDataPackage(explode(',', $merchantIds), $ocrIP);

    do {
        try {
            if ($_ENV['STAGE'] == STAGE_DEV) {
                $client = new \Net\Gearman\Client();
                $client->addServer($ocrIP, 4730);
            } else {
                $client = new GearmanClient();
                $client->addServer($ocrIP, 4730);
            }

            $params = array(
                'data' => $mcData
            );

            $msg = $client->doNormal('UpdateMerchantData', base64_encode(json_encode($params)));

            if ($msg) {
                $jobDone = true;
            }
        } catch (\Exception $e) {
            $jobDone = false;
            $retry++;
        }

        //Maximum of retry time is 3
        if ($retry >= 3) {
            $jobDone = true;
        }
    } while (!$jobDone);

    //Save fallback case to retry later
    if (!$jobDone) {
        MerchantSynch::storeFallback($ocrIP, $merchantIds);
    } else {
        //Remove fallbacks for this OCR if update is successfully
        MerchantSynch::deleteFallback($ocrIP);
    }
}

/*
 * Validate Json object
 */
function isJson($string) {
    json_decode($string);
    return (json_last_error() == JSON_ERROR_NONE);
}
