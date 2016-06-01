<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/start.php';
require_once __DIR__.'/app/start/local.php';

require_once __DIR__ . '/email-worker/DtoReceipt.php';
require_once __DIR__ . '/email-worker/EmailProcessAbstract.php';
require_once __DIR__ . '/email-worker/adapter/AmazonMerchantParser.php';
require_once __DIR__ . '/email-worker/adapter/VirtualReceiptParser.php';
require_once __DIR__ . '/email-worker/adapter/CenturyCarServiceParser.php';
require_once __DIR__ . '/email-worker/adapter/TheWaldorfAstoriaParser.php';
require_once __DIR__ . '/email-worker/adapter/DeltaAirlinesParser.php';
require_once __DIR__ . '/email-worker/adapter/GrandHyattParser.php';

function parseEmailContent ($htmlRaw, $userID)
{
    //KhanhDN's magic code
    // Available email parser
    $parserAdapter = array(
        'VirtualReceiptParser',
        'AmazonMerchantParser',
        'CenturyCarServiceParser',
        'TheWaldorfAstoriaParser',
        'DeltaAirlinesParser',
        'GrandHyattParser'
    );

    //Try all the parsers
    $foundParser = false;
    foreach ($parserAdapter as $key => $adapter) {
        $parser = new $adapter;
        //$text   = $parser->fetchBody($mbox, $overview->msgno);
        $text   = $htmlRaw;

        if (!$parser->analyze($text, '')) {
            continue;
        }

        $orders = $parser->getOrders();

        foreach ($orders as $order) {
            //$rcData = $order;
            $order->uid  = $userID;
            $foundParser = true;
            processOcrData($order);
        }

        // Break when analyze successfully
        break;

    }

    if (!$foundParser) {
        $order = new stdClass();
        $order->uid = $userID;
        $order->RawData = $htmlRaw;
        processOcrData($order);
    }
}

if(CUR_ENVIRONMENT == STAGE_DEV) {
    //Delvelopment environment
    $worker= new \Net\Gearman\Worker();
    $worker->addServer(OCR_SERVER_URL_LOCAL, OCR_SERVER_PORT_LOCAL);

    $work_parse_email = function($emailContent) {
        $responseObject = $emailContent;

        if (empty($responseObject['uid'])) {
            return false;
        }

        $userID = $responseObject['uid'];
        $htmlRaw = utf8_encode(base64_decode($responseObject['htmlContent']));

        parseEmailContent($htmlRaw, $userID);
    };
} else {
    //Prodcution environment
    $worker = new GearmanWorker();
    $worker->setTimeout(900000); //Set time out for 15 min
    $worker->addServer(IMAP_SERVER_URL, IMAP_SERVER_PORT);

    $work_parse_email = function($job) {
        $responseObject = @json_decode($job->workload());

        if (empty($responseObject->uid)) {
            return false;
        }
        echo 'pas';
        $userID = $responseObject->uid;
        $htmlRaw = utf8_encode(base64_decode($responseObject->htmlContent));

        parseEmailContent($htmlRaw, $userID);
    };
}

//Enanle worker
$worker->addFunction('EmailProcessing_HTML', $work_parse_email);
while ($worker->work());

function generateReceiptContent ($userID, $receipID) {
    if ($userID && $receipID) {
        return Receipt::getList($userID, array('NewReceipt' => true), array($receipID));
    }

    return null;
}

/**
 * Send push message to clients
 *
 * @param $html string
 * @return string
 */
function sendPushMsg ($userID, $rid) {
    //Send push message
    $newReceipt = generateReceiptContent($userID, $rid);
    $webMessage = json_encode(array(
        'uploadType' => "email",
        'obReceipt'  => $newReceipt[0]));

    $pushDone   = false;
    $retryPush  = 0;

    while ($rid && !$pushDone) {
        //Push server
        try {
            $pushService = $GLOBALS['app']->make('pushService');
            $user = User::find($userID);
            $pushService->push($webMessage, 'newReceipt', $user, 'WEB');
            $pushService->push('R:C:' . $rid, '', $user, 'MOBILE');
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
 * Remove unsafe HTML tags
 *
 * @param $html string
 * @return string
 */
function sanitize_html($html) {
    $find 	 = array('<script', '<frame', '<iframe');
    $replace = array('&lt;script', '&lt;frame', '&lt;iframe');

    return str_ireplace($find, $replace, $html);
}

/**
 * Process a receipt data.
 *
 * @param string $msg
 * @return boolean True if success
 */
function processOcrData($rcData)
{
    //$responseObject = @json_decode(utf8_encode(base64_decode($msg)));
    $responseObject = $rcData;

    if ( !$responseObject ) {
        return false;
    }

    //echo " [x] Received\n";

    $receipt = new Receipt();

    //Set timezone by user settings
    $userID = (int) $responseObject->uid;
    if (!$userID) {
        return false;
    }

    $profile = Profile::find($userID);
    if ($profile) {
        date_default_timezone_set($profile->Timezone);
    } else {
        date_default_timezone_set('UTC');
    }

    if (empty($responseObject->MerchantName)) {
        //Merchant not found or unmatched parser
        $receipt->UserID = $userID;
        $receipt->MerchantID = 0;
        $receipt->MerchantName = 'Merchant Unrecognized';
        $receipt->PurchaseTime = strtotime(date('Y-m-d'));
        $receipt->VerifyStatus = 1;
        $receipt->ReceiptType = 2;
        $receipt->UploadType = "email";
        $receipt->CreatedTime = $receipt->ModifiedTime = round(microtime(true) * 1000);
        $receipt->RawData = sanitize_html($responseObject->RawData);
        $receipt->save();
    } else {
        $receipt->MerchantName = $responseObject->MerchantName;
        $receipt->MerchantName = trim($receipt->MerchantName);
        $merchant = DB:: table('Merchant')->whereIn('UserID', array(0,$userID))
            ->where('Name', $receipt->MerchantName)->first();
        if ($merchant) {
            $receipt->MerchantID = $merchant->MerchantID;
        } else {
            $receipt->MerchantID = 0;
        }

        $receipt->UserID = $userID;
        if (isset($responseObject->PurchaseTime)) {
            $receipt->PurchaseTime = $responseObject->PurchaseTime;
        } else {
            $receipt->PurchaseTime = strtotime(date('Y-m-d'));
        }

//		if ( empty($responseObject->date) || $responseObject->date == '0' || strtotime($responseObject->date) > $_SERVER['REQUEST_TIME'] || ! strtotime($responseObject->date)) {
//			$receipt->PurchaseTime = strtotime(date('Y-m-d'));
//		} else {
//			$receipt->PurchaseTime = strtotime($responseObject->date . (isset($responseObject->time) ?  ' ' . $responseObject->time : ''));
//		}

        $receipt->VerifyStatus = 1;
        if (isset($responseObject->Discount)) {
            $receipt->Discount = $responseObject->Discount;
        } else {
            $receipt->Discount = 0;
        }

        $receipt->OriginalTotal = 0;
        if(isset($responseObject->OriginalTotal)) {
            $receipt->OriginalTotal = $responseObject->OriginalTotal;
        }

        if (isset($responseObject->Subtotal)) {
            $receipt->Subtotal = $responseObject->Subtotal;
        }

        if (isset($responseObject->Tax)) {
            $receipt->Tax = $responseObject->Tax;
            if (!isset($responseObject->Subtotal)) {
                $receipt->Subtotal = $receipt->OriginalTotal - $receipt->Tax;
            }
        }

        if (isset($responseObject->DigitalTotal)) {
            $receipt->DigitalTotal = $responseObject->DigitalTotal;
        } else {
            $receipt->DigitalTotal = $receipt->OriginalTotal;
        }

        if (! isset($receipt->Subtotal)) {
            $receipt->Subtotal = $receipt->Subtotal;
        }

        if (isset($responseObject->ReceiptType)) {
            $receipt->ReceiptType = $responseObject->ReceiptType;
        } else {
            $receipt->ReceiptType = 3;
        }

        if (isset($responseObject->RocrBots)) {
            $receipt->RocrBots = $responseObject->RocrBots;
        }

        if (isset($responseObject->RawData)) {
            $receipt->RawData = sanitize_html($responseObject->RawData);
            //$receipt->RawData = htmlspecialchars($responseObject->RawData);  //Remove all HTML tags
        }

        if (isset($responseObject->EmailSender)) {
            $receipt->EmailSender = $responseObject->EmailSender;
        }

        // 25/10/2013: A new field called UploadType was added to table Receipt
        // It indicates by which way users send their receipts. Currently we
        // provide 3 ways for them: Upload, Snap (only on mobile), and send via email
//		if (isset($responseObject->uploadType)) {
//			$receipt->UploadType = $responseObject->uploadType;
//		}
        $receipt->UploadType = "email";

        $receipt->IsOpened = 0;
        $receipt->CreatedTime = round(microtime(true) * 1000);

        $receiptItems = array();
        $originalItemAmount = array();
        if (isset($responseObject->Items) && count($responseObject->Items)) {
            $receipt->ItemCount = count($responseObject->Items);
            $receipt->save();

            foreach ($responseObject->Items as $item) {
                $receiptItem = new Item();
                $receiptItem->ReceiptID = $receipt->ReceiptID;
                $receiptItem->Name = $item->Name;
                $receiptItem->Quantity = (int) $item->Quantity;
                $receiptItem->Price = $item->Price;
                $receiptItem->Amount = max($receiptItem->Quantity * $receiptItem->Price, 0);
                $receiptItem->Total = $receiptItem->Amount * (1 + $receipt->Tax / 100);
                $receiptItem->CreatedTime = $_SERVER['REQUEST_TIME'];
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

    sendPushMsg($userID, $receipt->ReceiptID);
    return true;
};
