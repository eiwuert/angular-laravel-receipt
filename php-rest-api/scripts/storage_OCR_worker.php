<?php

$startTime = time();

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/start.php';
$app->boot();

    /*
     * Initialize Gearman connection
     */
    if ($_ENV['STAGE'] == STAGE_DEV) {
        //Development environment
        $worker= new \Net\Gearman\Worker();
        $worker->addServer($_ENV['GLB_IMAP_SERVER_URL'], $_ENV['GLB_IMAP_SERVER_PORT']);
    } else {
        //Production environment
        $worker= new GearmanWorker();
        $worker->setTimeout(900000); //Set time out for 15 min
        $worker->addServer($_ENV['GLB_IMAP_SERVER_URL'], $_ENV['GLB_IMAP_SERVER_PORT']);
    }

    /*
     * Worker for getting UserID by Email address
     */
    $work_get_uid = function($workload) {
        $email = ($_ENV['STAGE'] == STAGE_DEV) ? $workload : $workload->workload();
        $uid   = User::getUserIDFromEmail($email);
        $profile = Profile::find($uid);
        $country = Country::find($profile->CountryName);

        if (!$uid) {
            return json_encode(array("Uid"=>"0", "FirstName"=>""));
        }

        $name = User::getFirstNameByEmail($email);

        return json_encode(array(
            "Uid"         => (string)$uid,
            "FirstName"   => $name,
            "regionCode"  => strtolower($country->S3RegionCode),
            "countryCode" => strtolower($country->CountryCode)
        ));
    };

    /*
     * Worker for saving processed data to db
     */
    $work_save_data = function($workload) {
        $responseData = ($_ENV['STAGE'] == STAGE_DEV) ? $workload : $workload->workload();

        return json_encode(array(
            "message" => processOcrData($responseData) ? "Successully" : "Failed"
        ));
    };

    $worker->addFunction('GetUid', $work_get_uid);
    $worker->addFunction('ProcessedData', $work_save_data);

    while ($worker->work());

/*
 * Prepare message for pushing to web clients
 */
function generateReceiptContent ($userID, $receipID) {
    if ($userID && $receipID) {
        $rs = Receipt::getList($userID, array(), array('NewReceipt' => true, 'markNotNew' => false), array($receipID));

        return $rs['receipts'];
    }

    return null;
}

/**
 * Send push message to clients
 *
 * @param $userID  int  User ID
 * @param $rid     int  Receipt ID
 */
function sendPushMsg ($userID, $rid, $rsProcessTime) {
    //Send push message
    $newReceipt = generateReceiptContent($userID, $rid);
    $webMessage = json_encode(array(
            'uploadType' => "email",
            'obReceipt'  => $newReceipt[0],
            'processTime'=> $rsProcessTime)
    );

    $pushDone   = false;
    $retryPush  = 0;

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

/**
 * Process a receipt data.
 *
 * @param string $msg
 * @return boolean True if success
 */
function processOcrData($msg)
{
	$responseObject = @json_decode(utf8_encode(base64_decode($msg)));

    if ( !$responseObject ) return false;

    $userID = (int) $responseObject->uid;

    if (!$userID) return false;

    //Set timezone by user settings
	$profile = Profile::find($userID);
    date_default_timezone_set($profile ? $profile->Timezone : 'UTC');

    $configType = Config::get('app.receiptType');
    $receipt = new Receipt();

    $receipt->ReceiptType = $responseObject->receiptType;

    //Non - Receipt
    if ($receipt->ReceiptType == $configType['non']['code']) {
        $receipt->UserID = $userID;
        $receipt->MerchantID = 0;
        $receipt->MerchantName = 'Receipt Unrecognized';
        $receipt->PurchaseTime = strtotime(date('Y-m-d'));
        $receipt->VerifyStatus = 1;
        $receipt->CreatedTime = $receipt->ModifiedTime = time();
        $receipt->RawData = isset($responseObject->rawData) ?
            htmlspecialchars($responseObject->rawData) : '';
        $receipt->save();

        //Push message
        $processTime = empty($responseObject->processTime) ? -1 : $responseObject->processTime;
        sendPushMsg($userID, $receipt->ReceiptID, $processTime);

        return true;
    }

    //Rest receipt types
    $receipt->UserID       = $userID;
    $receipt->MerchantName = $responseObject->merchant;
    $receipt->MerchantName = trim($receipt->MerchantName);
    $merchant = DB:: table('Merchant')->whereIn('UserID', array(0, $userID))
        ->where('Name', $receipt->MerchantName)->first();

    $receipt->MerchantID = ($merchant) ? $merchant->MerchantID : 0;

    if ( empty($responseObject->date) || $responseObject->date == '0' || strtotime($responseObject->date) > $_SERVER['REQUEST_TIME'] || ! strtotime($responseObject->date)) {
        $receipt->PurchaseTime = strtotime(date('Y-m-d'));
    } else {
        $receipt->PurchaseTime = strtotime($responseObject->date . (isset($responseObject->time) ?  ' ' . $responseObject->time : ''));
    }

    $receipt->VerifyStatus = 1;

    $receipt->Discount      = isset($responseObject->reduction) ? $responseObject->reduction : 0;
    $receipt->OriginalTotal = isset($responseObject->total) ? $responseObject->total : 0;
    $receipt->Subtotal      = isset($responseObject->subtotal) ?  $responseObject->subtotal : 0;
    $receipt->RocrBots      = isset($responseObject->RocrBots) ? $responseObject->RocrBots : '';
    $receipt->UploadType    = isset($responseObject->uploadType) ? $responseObject->uploadType : "email";

    if (isset($responseObject->tax)) {
        $receipt->Tax = $responseObject->tax;
        if (!isset($responseObject->subtotal)) {
            $receipt->Subtotal = $receipt->OriginalTotal - $receipt->tax;
        }
    }

    if (! isset($receipt->Subtotal)) {
        $receipt->Subtotal = $receipt->OriginalTotal;
    }

    //20140611 - Remove rawText since it has been attached in receipt PDF file
    //if (isset($responseObject->rawText)) {
    //    $receipt->RawData = htmlspecialchars($responseObject->rawText);  //Remove all HTML tags
    //}

    $receipt->DigitalTotal = $receipt->OriginalTotal;
    $receipt->IsOpened     = 0;
    $receipt->CreatedTime  = round(microtime(true) * 1000);

    $receiptItems       = array();
    $originalItemAmount = array();
    if (isset($responseObject->items) && count($responseObject->items)) {
        $receipt->ItemCount = count($responseObject->items);
        $receipt->save();

        foreach ($responseObject->items as $item) {
            $receiptItem = new Item();

            $receiptItem->ReceiptID   = $receipt->ReceiptID;
            $receiptItem->Name        = $item->name;
            $receiptItem->Quantity    = (int) $item->quantity;
            $receiptItem->Price       = $item->price;
            $receiptItem->Amount      = max($receiptItem->Quantity * $receiptItem->Price, 0);
            $receiptItem->Total       = $receiptItem->Amount * (1 + $receipt->Tax / 100);
            $receiptItem->CreatedTime = $_SERVER['REQUEST_TIME'];
            $receiptItem->save();

            $receiptItems[]       = $receiptItem['attributes'];
            $itemAmount           = new stdClass();
            $itemAmount->ItemID   = $receiptItem->ItemID;
            $itemAmount->Amount   = $receiptItem->Amount;
            $originalItemAmount[] = $itemAmount;
        }
    } else {
        $receipt->save();
    }

    $originalAmount               = new stdClass();
    $originalAmount->ReceiptID    = $receipt->ReceiptID;
    $originalAmount->DigitalTotal = $receipt->DigitalTotal;
    $originalAmount->Subtotal     = $receipt->Subtotal;
    $originalAmount->Tax          = $receipt->Tax;
    $originalAmount->ExtraField   = $receipt->ExtraField;
    $originalAmount->ExtraValue   = $receipt->ExtraValue;

    //Original receipt
    $receiptOriginal                  = new ReceiptOriginal();
    $receiptOriginal->ReceiptID       = $receipt->ReceiptID;
    $receiptOriginal->ReceiptData     = json_encode($receipt['attributes']);
    $receiptOriginal->ReceiptItemData = json_encode($receiptItems);
    $receiptOriginal->Amount          = json_encode($originalAmount);
    $receiptOriginal->ItemAmount      = json_encode($originalItemAmount);
    $receiptOriginal->save();

    //File attachments
    if (isset($responseObject->imageName)) {
        $file             = new File();
        $file->FileName   = isset($responseObject->oriName) ? $responseObject->oriName : $responseObject->imageName;
        $file->FilePath   = $responseObject->imageName;
        $file->FileBucket = $responseObject->bucket;
        $file->Timestamp = time();
        $file->EntityID   = $receipt->ReceiptID;
        $file->EntityName = 'receipt_image';
        $file->Permanent  = 1;
        $file->save();
    }


    //Push message
    $processTime = empty($responseObject->processTime) ? -1 : $responseObject->processTime;
    sendPushMsg($userID, $receipt->ReceiptID, $processTime);

    return true;
};
