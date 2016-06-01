<?php
require __DIR__.'/../../vendor/autoload.php';

$app = require_once __DIR__.'/../../bootstrap/start.php';
$app->boot();

function writeLog($content) {
    $file 	  = dirname(__FILE__) . '/stress_log.txt';
    $current  = file_get_contents($file);
    $current .= $content;
    file_put_contents($file, $current);
}

if (isset($argv[3])) {
    $userID       = (int) $argv[1];
    $ftype        = $argv[2];
    $filePath     = $argv[3];
    $oriFileName  = $argv[4];
	
    $taskID = "" . time() . "-" . $oriFileName;
    
    $encodedImg   = base64_encode(file_get_contents($filePath));
	
	$jobDone = false;
	$retry	 = 0;
	$rid     = 0;
	do {
		try {
			$client = new GearmanClient();
			$client->addServer($_ENV['GLB_OCR_SERVER_URL'], $_ENV['GLB_OCR_SERVER_PORT']);
			
			if ($ftype == 'PDF') {
				$msg = $client->doNormal('ImageProcessing_PDF', $encodedImg);
			} else {
				$msg = $client->doNormal('ImageProcessing_IMG', $encodedImg);
			}
			
			if ($msg) {
				$rid = processOcrData($msg, $userID, $oriFileName);
				$jobDone = true;
                $time = time();
                $qlog = "[3] $time | $taskID - receipt: $rid \r\n";
                writeLog($qlog);
			}
		} catch (\Exception $e) {
			$jobDone = false;
			$retry++;
		}
		
		//Maximum of retry time is 3
		if ($retry >= 3) {
			$jobDone = true;
			//Do not keep tmp uploaded file 
			unlink($filePath);
		}
    } while (!$jobDone);
	
	//Send push message
	$pushDone  = false;
	$retryPush = 0;
	while($rid && !$pushDone) {
		//Push server
		try {
			$pushService = $app->make('pushService');
			$user = User::find($userID);
			$pushService->push('R:' . $rid, 'newReceipt', $user, 'WEB');
			$pushDone = true;
			
			//debug
            $time = time();
			$qlog = "[5] $time | $taskID - receipt: $rid \r\n";
            writeLog($qlog);
		} catch (\Exception $e) {
			$retryPush++;
		}
		
		//Maximum of retry time of push is 5
		if ($retryPush >= 5) {
			$pushDone = true;
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
 * Process a receipt data.
 *
 * @param string $msg
 * @return boolean True if success
 */
function processOcrData($msg, $userID, $oriFileName)
{
	$responseObject = @json_decode(utf8_encode(base64_decode($msg)));
    
    if ( !$responseObject ) {
        echo " [!] Empty message received\n";
        return false;
    }
    
    echo " [x] Received\n";
    
    $receipt = new Receipt();
		
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
        $receipt->CreatedTime = $receipt->ModifiedTime = time();
        $receipt->RawData = htmlspecialchars($responseObject->rawText);  //Remove all HTML tags
		$receipt->CurrencyCode = $settings->CurrencyCode;
		$receipt->save();
        
        return ($receipt->ReceiptID)?$receipt->ReceiptID:0;
	} else {
		$receipt->CurrencyCode = $settings->CurrencyCode;
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
		if (isset($responseObject->uploadType)) {
			$receipt->UploadType = $responseObject->uploadType;
		}
		
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
	
    /*
	if (isset($responseObject->oriName)) {
		$file->FileName = $responseObject->oriName;
	} else {
		$file->FileName = $responseObject->imageName;
	}
    */
	
    $file->FileName   = $oriFileName;
    $file->FilePath   = $responseObject->imageName;
	$file->Timestamp  = time();
	$file->EntityID   = $receipt->ReceiptID;
	$file->EntityName = 'receipt_image';
	$file->Permanent  = 1;
	$file->save();
    
	return ($receipt->ReceiptID)?$receipt->ReceiptID:0;
};
