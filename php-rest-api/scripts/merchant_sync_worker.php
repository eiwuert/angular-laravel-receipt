<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/start.php';
$app->boot();

    /*
     * Initialize Gearman connection
     */
    if ($_ENV['STAGE'] == STAGE_DEV) {
        //Development environment
        $worker= new \Net\Gearman\Worker();
        $worker->addServer($_ENV['GLB_LOCAL_GEARMAN_URL'], $_ENV['GLB_LOCAL_GEARMAN_PORT']);
    } else {
        //Production environment
        $worker= new GearmanWorker();
        $worker->setTimeout(900000); //Set time out for 15 min
        $worker->addServer($_ENV['GLB_LOCAL_GEARMAN_URL'], $_ENV['GLB_LOCAL_GEARMAN_PORT']);
    }

    /*
     * Worker for getting all merchant algos
     */
    $work_get_merchants = function($workload) {
        //$email = ($_ENV['STAGE'] == STAGE_DEV) ? $workload : $workload->workload();

        $merchantIDList = Merchant::getAllActiveAdminMerchants(true);
        $mcData = MerchantSynch::buildDataPackage($merchantIDList);

        return base64_encode(json_encode(array(
            "data" => $mcData
        )));
    };

    $worker->addFunction('GetAllMerchantAlgos', $work_get_merchants);

    while ($worker->work());
