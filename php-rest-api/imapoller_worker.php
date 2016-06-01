<?php

$startTime = time();

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/start.php';
require_once __DIR__.'/app/start/local.php';

if (CUR_ENVIRONMENT == STAGE_DEV) {
    //Development environment
    $worker= new \Net\Gearman\Worker();
    $worker->addServer(OCR_SERVER_URL_LOCAL, OCR_SERVER_PORT_LOCAL);
    
    $work_get_uid = function($email) {
        $uid = User::getUserIDFromEmail($email);
        if (!$uid) {
            return json_encode(array("Uid"=>"0", "FirstName"=>""));
        }

        $name = User::getFirstNameByEmail($email);
        return json_encode(array("Uid"=>(string)$uid, "FirstName"=>$name));
    };
    $work_save_data = function($responseData) {
        if(processOcrData($responseData)){
            return json_encode(array("message"=>"Successully"));
        }

        return json_encode(array("message"=>"Failed"));
    };
} else {
    //Production environment
    $worker= new GearmanWorker();
    $worker->setTimeout(900000); //Set time out for 15 min
    $worker->addServer(IMAP_SERVER_URL, IMAP_SERVER_PORT);

    $work_get_uid = function($job) {
        $email = $job->workload();
        $uid = User::getUserIDFromEmail($email);
        if (!$uid) {
            return json_encode(array("Uid"=>"0", "FirstName"=>""));
        }

        $name = User::getFirstNameByEmail($email);
        return json_encode(array("Uid"=>(string)$uid, "FirstName"=>$name));
    };
    $work_save_data = function($job) {
        $responseData = $job->workload();
        if(processOcrData($responseData)){
            return json_encode(array("message"=>"Successully"));
        }

        return json_encode(array("message"=>"Failed"));
    };
}

$worker->addFunction('GetUid', $work_get_uid);
$worker->addFunction('ProcessedData', $work_save_data);

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
 * Process a receipt data.
 *
 * @param string $msg
 * @return boolean True if success
 */
function processOcrData($msg)
{
	$responseObject = @json_decode(utf8_encode(base64_decode($msg)));
    
    if ( !$responseObject ) {
        //echo " [!] Empty message received\n";
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
    
    if (empty($responseObject->imageName)) {
		$receipt->UserID = $userID;
		$receipt->MerchantID = 0;
		$receipt->MerchantName = 'Receipt Unrecognized';
		$receipt->PurchaseTime = strtotime(date('Y-m-d'));
		$receipt->VerifyStatus = 1;
		$receipt->ReceiptType = 5;
        $receipt->CreatedTime = $receipt->ModifiedTime = time();
        $receipt->RawData = htmlspecialchars($responseObject->rawData);  //Remove all HTML tags
		$receipt->save();
        
        sendPushMsg($userID, $receipt->ReceiptID);
        return true;
	} else {
		$receipt->MerchantName = $responseObject->merchant;
		$receipt->MerchantName = trim($receipt->MerchantName);
		$merchant = DB:: table('Merchant')->whereIn('UserID', array(0,$userID))
            ->where('Name', $receipt->MerchantName)->first();
		if ($merchant) {
			$receipt->MerchantID = $merchant->MerchantID;
		} else {
			$receipt->MerchantID = 0;
		}
		
		$receipt->UserID = $userID;
		if ( empty($responseObject->date) || $responseObject->date == '0' || strtotime($responseObject->date) > $_SERVER['REQUEST_TIME'] || ! strtotime($responseObject->date)) {
			$receipt->PurchaseTime = strtotime(date('Y-m-d'));
		} else {
			$receipt->PurchaseTime = strtotime($responseObject->date . (isset($responseObject->time) ?  ' ' . $responseObject->time : ''));
		}
		
		$receipt->VerifyStatus = 1;
		if (isset($responseObject->reduction)) {
			$receipt->Discount = $responseObject->reduction;
		} else {
			$receipt->Discount = 0;
		}

		$receipt->OriginalTotal = 0;
		if(isset($responseObject->total)) {
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
		if (! isset($receipt->Subtotal)) {
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
        
        //Receipt processed via imapoller is always email upload type
        $receipt->UploadType = "email";
		
		$receipt->IsOpened = 0;
		$receipt->CreatedTime = $_SERVER['REQUEST_TIME'];

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
	
	$file = new File();
	
	if (isset($responseObject->oriName)) {
		$file->FileName = $responseObject->oriName;
	} else {
		$file->FileName = $responseObject->imageName;
	}
	
	$file->FilePath = $responseObject->imageName;
	$file->Timestamp = time();
	$file->EntityID = $receipt->ReceiptID;
	$file->EntityName = 'receipt_image';
	$file->Permanent = 1;
	$file->save();
    
    sendPushMsg($userID, $receipt->ReceiptID);
    return true;
};
