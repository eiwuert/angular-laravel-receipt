<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/start.php';
$app->boot();

MerchantSynch::updateOCR(array(1, 2));

/*
 * Validate Json object
 */
function isJson($string) {
    json_decode($string);
    return (json_last_error() == JSON_ERROR_NONE);
}
