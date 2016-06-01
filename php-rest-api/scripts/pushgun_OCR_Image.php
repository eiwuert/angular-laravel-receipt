<?php
$startTime = time();

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/start.php';
$app->boot();

if (isset($argv[6])) {
    $userID        = (int) $argv[1];
    $ftype         = $argv[2];
    $uploadType    = $argv[3];
    $fileName      = $argv[4];
    $oriFileName   = $argv[5];
    $SiphonIP      = $argv[6];

    /** REQUEST OCR PROCESS RECEIPT **/
    $jobDone       = false;
    $retry         = 0;
    $rid           = 0;
    $rsProcessTime = 0;
    $resultReceiptProces;

    $profile = Profile::find($userID);
    $country = Country::find($profile->CountryName);

    do {
        try {
            if ($_ENV['STAGE'] == STAGE_DEV) {
                $client = new \Net\Gearman\Client();
                $client->addServer($_ENV['GLB_OCR_SERVER_URL'], $_ENV['GLB_OCR_SERVER_PORT']);
            } else {
                $client = new GearmanClient();
                $client->addServer($_ENV['GLB_OCR_SERVER_URL'], $_ENV['GLB_OCR_SERVER_PORT']);
            }

            $params = array(
                'userID'       => strval($userID),
                'regionCode'   => strtolower($country->S3RegionCode),
                'countryCode'  => strtolower($country->CountryCode),
                'imageName'    => $fileName,
                'oriName'      => $oriFileName,
                'SiphonServer' => $SiphonIP
            );

            if ($ftype == 'PDF') {
                $msg = $client->doNormal('ImageProcessing_PDF_PushGun', json_encode($params));
            } else {
                $msg = $client->doNormal('ImageProcessing_IMG_PushGun', json_encode($params));
            }

            if ($msg) {
                $resultReceiptProces = processOcrData($msg, $userID, $oriFileName, $uploadType);
                $rid = $resultReceiptProces['receiptID'];
                $rsProcessTime = $resultReceiptProces['processTime'];
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

    /** SEND PUSH MESSAGE **/
    $pushDone   = false;
    $retryPush  = 0;

    if ($rid) {
        //1- Notice Siphon Server when receipt is processed
        try {
            markFileAsProcessed($fileName);
        } catch (\Exception $e) {
        }

        //2- Send push to client via Push Server
        $newReceipt = generateReceiptContent($userID, $rid);
        $webMessage = json_encode(array(
            'uploadType' => $uploadType,
            'obReceipt'  => $newReceipt[0],
            'processTime'=> $rsProcessTime)
        );

        while ($rid && !$pushDone) {
            try {
                Push::toWeb($webMessage, 'file-processed', $userID);
                Push::toMobile('R:C:' . $rid, $userID);
                $pushDone = true;
            } catch (\Exception $e) {
                $retryPush++;
            }

            //Maximum of retry time of push is 5
            if ($retryPush >= 5) {
                $pushDone = true;
            }
        }
    }
}

/*
 * Validate Json object
 */
function isJson($string) {
    json_decode($string);
    return (json_last_error() == JSON_ERROR_NONE);
}

/**
 * Notice Siphon that receipt is successfully processed
 * @param   $fileName   String    Name of receipt file in Siphon server
 *
 * @return void
 */
function markFileAsProcessed ($fileName) {
    $configUpload = Config::get('push.uploadServer');
    $api = $configUpload['host'] . $configUpload['ocrApi'];

    $gzclient = new GuzzleHttp\Client();
    $gzclient->post($api, [
        'headers' => [
            'Content-Type' => 'application/json',
            'X-API-Key'    => $configUpload['key']
        ],
        'json'    => ['receiptName' => $fileName],
        'future'  => true,
        'verify'  => false
    ])->then(
        function ($response) {
            //writeLog('Post completed!' . $response);
        }, function ($error) {
            //writeLog('Post error!' .  $error->getMessage());
        }
    );
}

/**
 * Process a receipt data.
 *
 * @param string $msg
 * @return boolean True if success
 */
function processOcrData($msg, $userID, $oriFileName, $uploadType) {
    $responseObject = @json_decode(utf8_encode(base64_decode($msg)));

    if (!$responseObject) {
        echo " [!] Empty message received\n";
        return false;
    }

    echo " [x] Received\n";

    $receipt = new Receipt();
    $receipt->UploadType = $uploadType;
    $now = round(microtime(true) * 1000);

    //Set timezone by user settings
    $userID = (int) $userID;
    $profile = Profile::find($userID);
    $settings = Settings::find($userID);

    if ($profile) {
        date_default_timezone_set($profile->Timezone);
    } else {
        date_default_timezone_set('UTC');
    }

    if (empty($responseObject->imageName)) {
        $receipt->UserID = $userID;
        $receipt->MerchantID = 0;
        $receipt->MerchantName = 'Receipt Unrecognized';
        $receipt->PurchaseTime = strtotime(date('Y-m-d'));
        $receipt->VerifyStatus = 1;
        $receipt->ReceiptType = 5;
        $receipt->CreatedTime = $receipt->ModifiedTime = $now;
        $receipt->RawData = htmlspecialchars($responseObject->rawText);  //Remove all HTML tags
        $receipt->CurrencyCode = $settings->CurrencyCode;
        $receipt->save();

        $receiptID = ($receipt->ReceiptID) ? $receipt->ReceiptID : 0;
        return array(
            'receiptID' => $receiptID,
            'processTime' => $responseObject->processTime
          );
    } else {
        $receipt->CurrencyCode = $settings->CurrencyCode;
        $receipt->MerchantName = $responseObject->merchant;
        $receipt->MerchantName = trim($receipt->MerchantName);
        $merchant = DB:: table('Merchant')->whereIn('UserID', array(0, $userID))
                        ->where('Name', $receipt->MerchantName)->first();
        if ($merchant) {
            $receipt->MerchantID = $merchant->MerchantID;
        } else {
            $receipt->MerchantID = 0;
        }

        $receipt->UserID = $userID;
        if (empty($responseObject->date) || $responseObject->date == '0' || strtotime($responseObject->date) > round(microtime(true)) || !strtotime($responseObject->date)) {
            $receipt->PurchaseTime = strtotime(date('Y-m-d'));
        } else {
            $receipt->PurchaseTime = strtotime($responseObject->date . (isset($responseObject->time) ? ' ' . $responseObject->time : ''));
        }

        $receipt->VerifyStatus = 1;
        if (isset($responseObject->reduction)) {
            $receipt->Discount = $responseObject->reduction;
        } else {
            $receipt->Discount = 0;
        }

        $receipt->OriginalTotal = 0;
        if (isset($responseObject->total)) {
            $receipt->OriginalTotal = $responseObject->total;
        }

        if (isset($responseObject->subtotal)) {
            $receipt->Subtotal = $responseObject->subtotal;
        }

        if (isset($responseObject->tax)) {
            $receipt->Tax = $responseObject->tax;
            if (!isset($responseObject->subtotal)) {
                $receipt->Subtotal = $receipt->OriginalTotal - $receipt->tax;
            }
        }

        $receipt->DigitalTotal = $receipt->OriginalTotal;
        if (!isset($receipt->Subtotal)) {
            $receipt->Subtotal = $receipt->OriginalTotal;
        }

        if (isset($responseObject->receiptType)) {
            $receipt->ReceiptType = $responseObject->receiptType;
        } else {
            $receipt->ReceiptType = 3;
        }

        if (isset($responseObject->RocrBots)) {
            $receipt->RocrBots = $responseObject->RocrBots;
        }

        if (isset($responseObject->rawText)) {
            $receipt->RawData = htmlspecialchars($responseObject->rawText);  //Remove all HTML tags
        }

        // 25/10/2013: A new field called UploadType was added to table Receipt
        // It indicates by which way users send their receipts. Currently we
        // provide 3 ways for them: Upload, Snap (only on mobile), and send via email
        //if (isset($responseObject->uploadType)) {
        //	$receipt->UploadType = $responseObject->uploadType;
        //}

        $receipt->IsOpened = 0;
        $receipt->CreatedTime = $now;

        $receiptItems = array();
        $originalItemAmount = array();
        if (isset($responseObject->items) && count($responseObject->items)) {
            $receipt->ItemCount = count($responseObject->items);
            $receipt->save();

            foreach ($responseObject->items as $item) {
                $receiptItem = new Item();
                $receiptItem->ReceiptID = $receipt->ReceiptID;
                $receiptItem->Name = $item->name;
                $receiptItem->Quantity = (int) $item->quantity;
                $receiptItem->Price = $item->price;
                $receiptItem->Amount = max($receiptItem->Quantity * $receiptItem->Price, 0);
                $receiptItem->Total = $receiptItem->Amount * (1 + $receipt->Tax / 100);
                $receiptItem->CreatedTime = round(microtime(true));
                $receiptItem->save();

                $receiptItems[] = $receiptItem['attributes'];
                $itemAmount = new stdClass();
                $itemAmount->ItemID = $receiptItem->ItemID;
                $itemAmount->Amount = $receiptItem->Amount;
                $originalItemAmount[] = $itemAmount;
            }
        } else {
            $receipt->save();
        }

        $originalAmount = new stdClass();
        $originalAmount->ReceiptID = $receipt->ReceiptID;
        $originalAmount->DigitalTotal = $receipt->DigitalTotal;
        $originalAmount->Subtotal = $receipt->Subtotal;
        $originalAmount->Tax = $receipt->Tax;
        $originalAmount->ExtraField = $receipt->ExtraField;
        $originalAmount->ExtraValue = $receipt->ExtraValue;

        $receiptOriginal = new ReceiptOriginal();
        $receiptOriginal->ReceiptID = $receipt->ReceiptID;
        $receiptOriginal->ReceiptData = json_encode($receipt['attributes']);
        $receiptOriginal->ReceiptItemData = json_encode($receiptItems);
        $receiptOriginal->Amount = json_encode($originalAmount);
        $receiptOriginal->ItemAmount = json_encode($originalItemAmount);
        $receiptOriginal->save();
    }

    $file = new File();
    $file->FileName = $oriFileName;
    $file->FilePath = $responseObject->imageName;
    $file->FileBucket = $responseObject->bucket;
    $file->Timestamp = time();
    $file->EntityID = $receipt->ReceiptID;
    $file->EntityName = 'receipt_image';
    $file->Permanent = 1;
    $file->save();

    /*
     * Return array contain receipt ID and time to process receipt.
     **/
    $receiptID = ($receipt->ReceiptID) ? $receipt->ReceiptID : 0;
    return array(
      'receiptID' => $receiptID,
      'processTime' => $responseObject->processTime
    );
}

function generateReceiptContent ($userID, $receipID) {
    if ($userID && $receipID) {
        $rs = Receipt::getList($userID, array(), array('NewReceipt' => true, 'markNotNew' => false), array($receipID));

        return $rs['receipts'];
    }

    return null;
}
