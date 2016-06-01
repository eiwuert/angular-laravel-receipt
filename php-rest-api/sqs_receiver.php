<?php

$startTime = time();

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/start.php';

// retrieve amazon SQS client object
$amazonSqs = App::make('aws')->get('sqs');
$amazonSqs->setRegion(Config::get('aws::config.SQS.region'));

// get the processed receipts queue url
$response = $amazonSqs->getQueueUrl(array('QueueName' => Config::get('aws::config.SQS.processedQueue')));
$queueUrl = $response->get('QueueUrl');

// do our works on 15 minutes (900 seconds)
do {
    receiveMessages($amazonSqs, $queueUrl);
} while(time() - $startTime < 900);

/**
 * Receive messages from Sqs
 *
 * @param \Aws\Sqs\SqsClient $amazonSqs
 * @param string $queueUrl
 * @return void
 */
function receiveMessages(\Aws\Sqs\SqsClient $amazonSqs, $queueUrl)
{
    $response = $amazonSqs->receiveMessage(array(
        'QueueUrl' => $queueUrl,
        'MaxNumberOfMessages' => Config::get('aws::config.SQS.maxNumberOfMessages'),
        'VisibilityTimeout' => Config::get('aws::config.SQS.visibilityTimeout'),
        'WaitTimeSeconds' => Config::get('aws::config.SQS.waitTimeSeconds'),
    ));
    
    if (is_array($response->get('Messages'))) {
        foreach ($response->get('Messages') as $message) {
            if (processData($message['Body'])) {
                // tell sqs that this message is processed
                $amazonSqs->deleteMessage(array(
                    'QueueUrl' => $queueUrl,
                    'ReceiptHandle' => $message['ReceiptHandle'],
                ));
            }
        }
    }
};

/**
 * Process a receipt data.
 *
 * @param string $msg
 * @return boolean True if success
 */
function processData($msg)
{
    $responseObject = @json_decode(utf8_encode(base64_decode($msg)));
    
    if ( ! $responseObject) {
        echo " [!] Empty message received\n";
        return false;
    }
    
    echo " [x] Received\n";
    
    $receipt = new Receipt();
		
	//Set timezone by user settings
    $userID = (int) $responseObject->userId;
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
        if (isset($responseObject->rawData)) {
			$receipt->RawData = htmlspecialchars($responseObject->rawData);  //Remove all HTML tags
		}
		$receipt->save();
        
        return true;
	} else {
		$receipt->MerchantName = $responseObject->merchant;
		$receipt->MerchantName = trim($receipt->MerchantName);
		$merchant = Merchant::where('Name', $receipt->MerchantName)->first();
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
		
		/*
		// Added on 2013-10-14 by KhanhDN: auto correct invalid amount from 4 fields: Subtotal, Tax, Extra & Total			
		$receipt->Subtotal = isset($receipt->Subtotal)?$receipt->Subtotal:0;
		$receipt->DigitalTotal = isset($receipt->DigitalTotal)?$receipt->DigitalTotal:0;
		$receipt->Tax = isset($receipt->Tax)?$receipt->Tax:0;
		$receipt->ExtraValue = isset($receipt->ExtraValue)?$receipt->ExtraValue:0;
		$subtotal = sprintf('%01.2f', $receipt->Subtotal);
		$total    = sprintf('%01.2f', $receipt->DigitalTotal);						
		$tax      = sprintf('%01.2f', $receipt->Tax);
		$extra    = sprintf('%01.2f', $receipt->ExtraValue);
		
		$tmpTotal = sprintf('%01.2f', $subtotal + $tax + $extra);
					
		if ($tmpTotal != $total && $total > 0 && $subtotal > 0 && $tax > 0 && $extra > 0) {
			// do nothing
		} else { // Auto correct fields amount
			if ($subtotal == 0 && ($total - ($tax + $extra) > 0)) {
				$receipt->Subtotal = $total - ($tax + $extra);
			} else if ($subtotal > 0 && $tax == 0 && $extra > 0 && ($total - $subtotal - $extra > 0)) {
				$receipt->Tax = $total - $subtotal - $extra;
			} else if ($subtotal > 0 && $tax > 0 && $extra == 0 && ($total - $subtotal - $tax > 0)) {
				$receipt->ExtraValue = $total - $subtotal - $tax;
			} else if ($total == 0) {
				$receipt->DigitalTotal = $subtotal + $tax + $extra;
			}			
		}
		*/
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
    
    return true;
};
