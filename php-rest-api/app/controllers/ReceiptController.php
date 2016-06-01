<?php
/**
 * Controller for receipts
 */
class ReceiptController extends BaseController 
{
    
	public function getIndex()
	{
		// Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
		
		$receiptId = Input::get('receiptID', '');
		if (empty($receiptId)) { // resource for get all receipts of the user
			return Response::json(Receipt::getList($userToken->UserID, Input::all(), Input::all()));
		} else { // resource for get a receipt
			$receipt = Receipt::fetch($receiptId, $userToken->UserID);
			
			if ($receipt) {
				return Response::json($receipt);
			}
			
			return Response::json(array('message' => array('The requested receipt does not exist.')), 500);
		}
	}
        
        public function getListReceiptsId(){
            //Need to check authentication
            if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
                    return Response::json(array('message' => 'The authentication is failed.'), 401);
            }
            
            return Receipt::getListReceiptsId($userToken->UserID);
            
        }


    public function postIndex()
	{
		//Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
		
		$post = Input::all();
		$messages = $this->validateReceipt($post, $userToken->UserID);
		if (count($messages)) {
			return Response::json($messages, 500);
		}
		
        $receipt = new Receipt();
        
        // Assign Merchant ID based on Merchant Name
        // Merchant case:
        // - Assign ID if merchant is check as existed
        // - Create new Merchant if MerchantID is 0 or merchant is check as not existed
        $mcId = isset($post['MerchantID']) ? $post['MerchantID'] : 0;
        ($mcId > 0) && $mcId = Merchant::checkExisted($userToken->UserID, $post['MerchantName'], $post['MerchantAddress']);
        if ($mcId == 0) {
            $merchant = $this->merchantFromReceiptInputData($post);
            $merchant->UserID = $userToken->UserID;
            $merchant->save();
            $mcId = $merchant->MerchantID;
        }
        $receipt->MerchantID = $mcId;
        isset($post['MerchantPhone'])   && $receipt->MerchantPhone   = trim($post['MerchantPhone']);
        isset($post['MerchantCountry']) && $receipt->MerchantCountry = trim($post['MerchantCountry']);
        isset($post['MerchantCity'])    && $receipt->MerchantCity    = trim($post['MerchantCity']);
        isset($post['MerchantState'])   && $receipt->MerchantState   = trim($post['MerchantState']);
        isset($post['MerchantCode'])    && $receipt->MerchantCode    = trim($post['MerchantCode']);
        isset($post['MerchantEmail'])   && $receipt->MerchantEmail   = trim($post['MerchantEmail']);

		if (isset($post['ItemCount']) && $post['ItemCount']) {
			$receipt->ItemCount = $post['ItemCount'];
		}
		
		$originalAmount = new stdClass();
		if (isset($post['OriginalTotal'])) {
			$originalAmount->OriginalTotal = $receipt->OriginalTotal = (float) $post['OriginalTotal'];
		}
		if (isset($post['Discount'])) {
			$originalAmount->Discount = $receipt->Discount = (float) $post['Discount'];
		}
		if (isset($post['DigitalTotal'])) {
			$originalAmount->DigitalTotal = $receipt->DigitalTotal = (float) $post['DigitalTotal'];
		}
		if (isset($post['Subtotal'])) {
			$originalAmount->Subtotal = $receipt->Subtotal = (float) $post['Subtotal'];
		}
		if (isset($post['Tip'])) {
			$originalAmount->Tip = $receipt->Tip = (float) $post['Tip'];
		}
		if (isset($post['Tax'])) {
			$originalAmount->Tax = $receipt->Tax = (float) $post['Tax'];
		}
		
		if (isset($post['ExchangeRate'])) {
			$receipt->ExchangeRate = (float) $post['ExchangeRate'];
		}
		
		if (isset($post['CurrencyCode'])) {
			$receipt->CurrencyCode = $post['CurrencyCode'];
		} 
		if (isset($post['PaymentType'])) {
			$receipt->PaymentType = $post['PaymentType'];
		}
		if (isset($post['PurchaseTime'])) {
			if (strpos($post['PurchaseTime'], 'T') !== false) {
				$post['PurchaseTime'] = substr(str_replace('T', ' ', $post['PurchaseTime']), 0, -5);
			}
			
			$receipt->PurchaseTime = strtotime($post['PurchaseTime']);
		}
		
		if (isset($post['VerifyStatus'])) {
			$receipt->VerifyStatus = $post['VerifyStatus'];
		} else {
			$receipt->VerifyStatus = 2;
		}
		
		if (isset($post['Memo'])) {
			$receipt->Memo = $post['Memo'];
		}
		
		if (isset($post['HasCombinedItem'])) {
			$receipt->HasCombinedItem = $post['HasCombinedItem'];
		}
		
		if (isset($post['ExtraField'])) {
			$originalAmount->ExtraField = $receipt->ExtraField = $post['ExtraField'];
		}
		if (isset($post['ExtraValue'])) {
			$originalAmount->ExtraValue = $receipt->ExtraValue = $post['ExtraValue'];
		}
		
		if (isset($post['CurrencyConverted'])) {
			$receipt->CurrencyConverted = $post['CurrencyConverted'];
		}
		
		$receipt->IsOpened = 1;
		$receipt->ReceiptType = Receipt::getTypeValue('manualReceipts');
		$receipt->CreatedTime = round(microtime(true) * 1000);
		$receipt->UserID = $userToken->UserID;
		$receipt->IsNew = 0;
		
		//Save the new receipt
		$receipt->save();
		
		$originalAmount->ReceiptID = $receipt->ReceiptID;
		
		if (isset($post['Attachments'])) {
			if (count($post['Attachments'])) {
				$fileIDs = array();
				foreach ($post['Attachments'] as $attachment) {
					$fileIDs[] = $attachment['FileID'];
				}

				File::updateList($fileIDs, array(
					'Permanent' => 1, 
					'EntityID' => $receipt->ReceiptID,
				));
			}
		}
		
		if (isset($post['ReceiptImage'])) {
			if (! empty ($post['ReceiptImage']['FileID'])) {
				File::updateList($post['ReceiptImage']['FileID'], array(
					'Permanent' => 1, 
					'EntityID' => $receipt->ReceiptID,
				));
			}
		}
		
		if (isset($post['DeletedFileIDs']) && count($post['DeletedFileIDs'])) {
			File::deleteList(File::getList($post['DeletedFileIDs']));
		}
		
		// We use a flag to indicate whether we need to refresh the trip list in client side
		$refreshTripList = false;
		$originalItems = array();
		$originalItemAmount = array();
		if (count($post['Items'])) {
			foreach ($post['Items'] as $postItem) {
				$postItem['Name'] = trim($postItem['Name']);
				if (empty($postItem['Name']) || empty($postItem['Amount'])) {
					continue;
				}
				
				$item = new Item();
				$item->ReceiptID = $receipt->ReceiptID;
				
				if (isset($postItem['CategoryID'])) {
					$item->CategoryID = $postItem['CategoryID'];
					if ($postItem['CategoryID']) {
						$item->CategorizeStatus = 2;
					}
				}
				
				$item->Name = $postItem['Name'];
				$item->Amount = $item->Price = $postItem['Amount'];
				$item->Quantity = 1;
				
				if (isset($postItem['IsJoined'])) {
					$item->IsJoined = $postItem['IsJoined'];
				} else {
					$item->IsJoined = 0;
				}
				
				$addTripItemRelationship = false;
				if (isset($postItem['CategoryApp']) && isset($postItem['CategoryID'])) {
					if ($postItem['CategoryApp'] == 'travel_expense' && $postItem['TripID']) {
						$item->ExpensePeriodFrom = DB::table('Trip')
								->select('StartDate')->where('TripID', $postItem['TripID'])->pluck('StartDate');
						
						if (! $item->IsJoined && $item->CategoryID) {
							CategoryAmount::updateAmount($receipt->UserID, $item->CategoryID, $item->Amount, $item->ExpensePeriodFrom, 'plus', $postItem['TripID']);
						}
						
						$addTripItemRelationship = true;
						$refreshTripList = true;
					} else if ($postItem['CategoryApp'] != 'travel_expense' && ! empty($postItem['CategoryApp']) && ! empty($postItem['ExpensePeriod'])) {
						if (strpos($postItem['ExpensePeriod'], 'T') !== false) {
							$postItem['ExpensePeriod'] = substr(str_replace('T', ' ', $postItem['ExpensePeriod']), 0, -5);
						}
						
						$item->ExpensePeriodFrom = strtotime(date('01-M-Y', strtotime($postItem['ExpensePeriod'])));
						
						if (! $item->IsJoined && $item->ExpensePeriodFrom && $item->CategoryID) {
							CategoryAmount::updateAmount($receipt->UserID, $item->CategoryID, $item->Amount, $item->ExpensePeriodFrom);
						}
					}
				}
				
				$item->CreatedTime = round(microtime(true) * 1000);
				$item->save();
				
				$originalItems[] = $item['attributes'];
				
				$itemAmount = new stdClass();
				$itemAmount->ItemID = $item->ItemID;
				$itemAmount->Amount = $item->Amount;
				$originalItemAmount[] = $itemAmount;
				
				if ($addTripItemRelationship) {
                    Item::addTripItemRecord($item->ItemID, $item->Amount, $item->IsJoined, $postItem['TripID']);
				}
				
				if (isset($postItem['Attachments'])) {
					if (count($postItem['Attachments'])) {
						$fileIDs = array();
						foreach ($postItem['Attachments'] as $attachment) {
							$fileIDs[] = $attachment['FileID'];
						}
						
						File::updateList($fileIDs, array(
								'Permanent' => 1, 
								'EntityID' => $item->ItemID,
							));
					}
				}
				
				if (isset($postItem['Tags'])) {
					Tag::saveTags($item->ItemID, 'receipt_item', explode(',', $postItem['Tags']));
				}
				
				if (isset($postItem['DeletedFileIDs']) && count($postItem['DeletedFileIDs'])) {
					File::deleteList(File::getList($postItem['DeletedFileIDs']));
				}
                
                //Push event for item
                PushBackground::send($userToken->UserID, 'item', 'post', $item->ItemID);
			}
		}
		
		$receiptOriginal = new ReceiptOriginal();
		$receiptOriginal->ReceiptID = $receipt->ReceiptID;
		$receiptOriginal->ReceiptData = json_encode($receipt['attributes']);
		$receiptOriginal->ReceiptItemData = json_encode($originalItems);
		$receiptOriginal->Amount = json_encode($originalAmount);
		$receiptOriginal->ItemAmount = json_encode($originalItemAmount);
		$receiptOriginal->save();
        
        //Push event for receipt
        PushBackground::send($userToken->UserID, 'receipt', 'post', $receipt->ReceiptID);
		
		return Response::json(array(
			'RefreshTripList' => $refreshTripList,
            'ReceiptID' => $receipt->ReceiptID
		));
	}
	
    public function putQuickSave() {
        //Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
		
		$put = Input::all();
        $receipt  = Receipt::find($put['ReceiptID']);
        
		if (isset($put['More']['Memo']) && $put['More']['Memo'] != $receipt->Memo) {
			$receipt->Memo = $put['Memo'];
		}
        
		$receipt->ModifiedTime = round(microtime(true) * 1000);
		
		//Update the receipt
		$receipt->save();
    }
	public function putIndex()
	{
		//Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
		
		$put = Input::all();
		$messages = $this->validateReceipt($put, $userToken->UserID);
		if (count($messages)) {
			return Response::json($messages, 500);
		}		

        $receipt  = Receipt::find($put['ReceiptID']);
        
        // Assign Merchant ID based on Merchant Name
        // Merchant case:
        // - Assign ID if merchant is check as existed
        // - Create new Merchant if MerchantID is 0 or merchant is check as not existed
        $mcId = isset($put['MerchantID']) ? $put['MerchantID'] : 0;
        ($mcId > 0) && $mcId = Merchant::checkExisted($userToken->UserID, $put['MerchantName'], $put['MerchantAddress']);
        if ($mcId == 0) {
            $merchant = $this->merchantFromReceiptInputData($put);
            $merchant->UserID = $userToken->UserID;
            $merchant->save();
            $mcId = $merchant->MerchantID;
        }
        $receipt->MerchantID = $mcId;

        isset($put['MerchantPhone'])   && $receipt->MerchantPhone   = trim($put['MerchantPhone']);
        isset($put['MerchantCountry']) && $receipt->MerchantCountry = trim($put['MerchantCountry']);
        isset($put['MerchantCity'])    && $receipt->MerchantCity    = trim($put['MerchantCity']);
        isset($put['MerchantState'])   && $receipt->MerchantState   = trim($put['MerchantState']);
        isset($put['MerchantCode'])    && $receipt->MerchantCode    = trim($put['MerchantCode']);
        isset($put['MerchantEmail'])   && $receipt->MerchantEmail   = trim($put['MerchantEmail']);
        
		if (isset($put['ItemCount']) && $put['ItemCount'] && $put['ItemCount'] != $receipt->ItemCount) {
			$receipt->ItemCount = $put['ItemCount'];
		}
		
		if (isset($put['OriginalTotal']) && $put['OriginalTotal'] != $receipt->OriginalTotal) {
			$receipt->OriginalTotal = (float) $put['OriginalTotal'];
		}
		if (isset($put['Discount']) && $put['Discount'] != $receipt->Discount) {
			$receipt->Discount = (float) $put['Discount'];
		}
		if (isset($put['DigitalTotal']) && $put['DigitalTotal'] != $receipt->DigitalTotal) {
			$receipt->DigitalTotal = (float) $put['DigitalTotal'];
		}
		if (isset($put['Subtotal']) && $put['Subtotal'] != $receipt->Subtotal) {
			$receipt->Subtotal = (float) $put['Subtotal'];
		}
		if (isset($put['Tip']) && $put['Tip'] != $receipt->Tip) {
			$receipt->Tip = (float) $put['Tip'];
		}
		if (isset($put['Tax']) && $put['Tax'] != $receipt->Tax) {
			$receipt->Tax = (float) $put['Tax'];
		} else if ($put['Tax'] == null) {
            $receipt->Tax = 0.0000;
        }
		
                if (isset($put['Memo']) && $put['Memo'] != $receipt->Memo ) {
			$receipt->Memo = $put['Memo'];
		}
                
		if (isset($put['ExchangeRate']) && $put['ExchangeRate'] != $receipt->ExchangeRate) {
			$receipt->ExchangeRate = (float) $put['ExchangeRate'];
		}
		
		if (isset($put['CurrencyCode']) && $put['CurrencyCode'] != $receipt->CurrencyCode) {
			$receipt->CurrencyCode = $put['CurrencyCode'];
		} 
		
		$homeCurrency = Profile::find($userToken->UserID)->CurrencyCode;
		$updateOriginal = false;
		if ($receipt->CurrencyCode == $homeCurrency) {
			$updateOriginal = true;
		}
		
		if (isset($put['PaymentType']) && $put['PaymentType'] != $receipt->PaymentType) {
			$receipt->PaymentType = $put['PaymentType'];
		}
		if (isset($put['PurchaseTime']) && $put['PurchaseTime'] != $receipt->PurchaseTime) {
			if (strpos($put['PurchaseTime'], 'T') !== false) {
				$put['PurchaseTime'] = substr(str_replace('T', ' ', $put['PurchaseTime']), 0, -5);
			}
			
			$receipt->PurchaseTime = strtotime($put['PurchaseTime']);
		}
		
		$verifyStatusChanged = false;
		if (isset($put['VerifyStatus']) && $put['VerifyStatus'] != $receipt->VerifyStatus) {
			$receipt->VerifyStatus = $put['VerifyStatus'];
			$verifyStatusChanged = true;
		}
		
		if (isset($put['HasCombinedItem']) && $put['HasCombinedItem'] != $receipt->HasCombinedItem) {
			$receipt->HasCombinedItem = $put['HasCombinedItem'];
		}
		
		if (isset($put['ExtraField']) && $put['ExtraField'] != $receipt->ExtraField) {
			$receipt->ExtraField = $put['ExtraField'];
		}
		if (isset($put['ExtraValue']) && $put['ExtraValue'] != $receipt->ExtraValue) {
			$receipt->ExtraValue = $put['ExtraValue'];
		} else {
			$receipt->ExtraValue = null;
		}
		
		if (isset($put['CurrencyConverted']) && $put['CurrencyConverted'] != $receipt->CurrencyConverted) {
			$receipt->CurrencyConverted = $put['CurrencyConverted'];
		} else {
			$receipt->CurrencyConverted = null;
		}
		
		//Fields in the More popup
		if (isset($put['More']['Memo']) && $put['More']['Memo'] != $receipt->Memo) {
			$receipt->Memo = $put['Memo'];
		}
		
		if (isset($put['More']['Memo']) && $put['More']['Memo'] != $receipt->Memo) {
			$receipt->Memo = $put['Memo'];
		}
		
		if (! $receipt->IsOpened) {
			$receipt->IsOpened = 1;
		}
		
		$receipt->ModifiedTime = round(microtime(true) * 1000);
		
		//Update the receipt
		$receipt->save();
		
		if (isset($put['Attachments']) && count($put['Attachments'])) {
			$fileIDs = array();
			foreach ($put['Attachments'] as $attachment) {
				$fileIDs[] = $attachment['FileID'];
			}
			
			File::updateList($fileIDs, array(
				'Permanent' => 1,
				'EntityID' => $receipt->ReceiptID,
			));
		}
		
		if (isset($put['ReceiptImage'])) {
			if (! empty ($put['ReceiptImage']['FileID'])) {
				$query = DB::table('File')
						->where('FileID', $put['ReceiptImage']['FileID'])
						->where('Permanent', 0)->first();
				
				if ($query) {
					File::deleteList(DB::table('File')
							->where('FileID', '!=', $put['ReceiptImage']['FileID'])
							->where('EntityID', $receipt->ReceiptID)
							->where('EntityName', 'receipt_image')
							->get());
				}
				
				File::updateList($put['ReceiptImage']['FileID'], array(
					'Permanent' => 1, 
					'EntityID' => $receipt->ReceiptID,
				));
			}
		}
		
		if (isset($put['DeletedFileIDs']) && count($put['DeletedFileIDs'])) {
			File::deleteList(File::getList($put['DeletedFileIDs']));
		}
		
		$refreshTripList = false;
		$setDefaultApp = -1;
                 $itemAction = ''; //Action defined for push service                 
		if (count($put['Items'])) {
			$originalItems = array();
			foreach ($put['Items'] as $key => $putItem) {
				if (! isset($putItem['Name']) || ! isset($putItem['Amount'])) {
					continue;
				}
				$putItem['Name'] = trim($putItem['Name']);
				if (empty($putItem['Name']) || empty($putItem['Amount'])) {
					continue;
				}
				
				$addTripItemRelationship = false;
				$updateTripItemRelationship = false;
				$removeTripItemRelationship = false;
				if (isset($putItem['ItemID']) && $putItem['ItemID']) {
					$item = Item::find($putItem['ItemID']);
					
					if ($putItem['Name'] != $item->Name) {
						$item->Name = $putItem['Name'];
					}
					
					$oldAmount = $item->Amount;
					if ($putItem['Amount'] != $item->Amount) {
						$item->Amount = $item->Price = $putItem['Amount'];
					}
					
					$oldCategoryID = $item->CategoryID;
					$oldCategoryApp = Category::getApp($oldCategoryID);
					if ($oldCategoryApp == 'travel_expense') {
						$refreshTripList = true;
					}
					
					//Check if this item was assigned to a trip before
					$tripItemQuery = DB::table('TripItem')
							->where('TripItemID', $item->ItemID);

					$oldTripID = $tripItemQuery->pluck('TripID');
					if (! $oldTripID) $oldTripID = 0;
					
					if (isset($putItem['CategoryID']) && $putItem['CategoryID'] != $item->CategoryID) {
						//Assign the new category ID to this item
						$item->CategoryID = $putItem['CategoryID'];
						if ($putItem['CategoryID']) {
							$setDefaultApp = $key;
							$item->CategorizeStatus = 2;
						} else {
							$item->CategorizeStatus = 0;
						}
					}
					
					if ($item->CategoryID) {
						if (isset($putItem['TripID'])) {
							if ($oldTripID) {
								//Only update this record if it exists
                                if ($oldTripID != $putItem['TripID']) {
                                    Item::updateTripItemRecord($item->ItemID, $putItem['Amount'], $putItem['IsJoined'], $putItem['TripID']);
                                }
							} else {
								//Insert a new record
                                Item::addTripItemRecord($item->ItemID, $putItem['Amount'], $putItem['IsJoined'], $putItem['TripID']);
							}
						}
					} else if ($oldCategoryID && $oldTripID) {
						$removeTripItemRelationship = true;
					}
					
					$oldExpensePeriodFrom = $item->ExpensePeriodFrom;
//					if (isset($putItem['ExpensePeriod']) && isset($putItem['CategoryApp'])) {
//						if ($putItem['CategoryApp'] == 'personal_expense') {
//							$item->ExpensePeriodFrom = strtotime(date('01-M-Y', strtotime($putItem['ExpensePeriod'])));
//						}
//					}
					if (isset($putItem['CategoryApp'])) {
						if ($putItem['CategoryApp'] == 'travel_expense' && $putItem['TripID']) {
							$item->ExpensePeriodFrom = DB::table('Trip')
									->select('StartDate')->where('TripID', $putItem['TripID'])->pluck('StartDate');

							$updateTripItemRelationship = true;
							$refreshTripList = true;
						} else if ($putItem['CategoryApp'] != 'travel_expense' && ! empty($putItem['CategoryApp']) && ! empty($putItem['ExpensePeriod'])) {
							if (strpos($putItem['ExpensePeriod'], 'T') !== false) {
								$putItem['ExpensePeriod'] = substr(str_replace('T', ' ', $putItem['ExpensePeriod']), 0, -5);
							}
							
							$item->ExpensePeriodFrom = strtotime(date('01-M-Y', strtotime($putItem['ExpensePeriod'])));
						}
						
						if ($oldCategoryApp == 'travel_expense' && $putItem['CategoryApp'] != $oldCategoryApp) {
							$removeTripItemRelationship = true;
						}
					}
					
					$isJoinedChange = false;
					if (! isset($putItem['TripID'])) {
						$tripID = 0;
					} else {
						$tripID = $putItem['TripID'];
					}
					if (isset($putItem['IsJoined']) && $putItem['IsJoined'] != $item->IsJoined) {
						$item->IsJoined = $putItem['IsJoined'];
						$isJoinedChange = true;
					}
					
					if ($isJoinedChange) {
						if ($item->IsJoined && $oldExpensePeriodFrom) {
							CategoryAmount::updateAmount($receipt->UserID, $oldCategoryID, $oldAmount, $oldExpensePeriodFrom, 'minus', $oldTripID);
						}
						
						if (! $item->IsJoined && $item->ExpensePeriodFrom && $item->CategoryID) {
							CategoryAmount::updateAmount($receipt->UserID, $item->CategoryID, $item->Amount, $item->ExpensePeriodFrom, 'plus', $tripID);
						}
					} else if (! $item->IsJoined) {
						if ($oldExpensePeriodFrom) {
							CategoryAmount::updateAmount($receipt->UserID, $oldCategoryID, $oldAmount, $oldExpensePeriodFrom, 'minus', $oldTripID);
						}

						if ($item->ExpensePeriodFrom && $item->CategoryID) {
							CategoryAmount::updateAmount($receipt->UserID, $item->CategoryID, $item->Amount, $item->ExpensePeriodFrom, 'plus', $tripID);
						}
					}
					
					if (isset($putItem['Memo']) && $putItem['Memo'] != $item->Memo) {
						$item->Memo = $putItem['Memo'];
					}
					
					$item->ModifiedTime = round(microtime(true) * 1000);

                    $itemAction = 'update';
				} else {
					$item = new Item();                                        
					$tripID = 0;
					$item->ReceiptID = $receipt->ReceiptID;
				
					if (isset($putItem['CategoryID']) && $putItem['CategoryID']) {
						$item->CategoryID = $putItem['CategoryID'];
						if ($putItem['CategoryID']) {
							$item->CategorizeStatus = 2;
							$setDefaultApp = $key;
						}
					}

					$item->Name = $putItem['Name'];
					$item->Amount = $item->Price = $putItem['Amount'];
					$item->Quantity = 1;
					
					if (isset($putItem['IsJoined'])) {
						$item->IsJoined = $putItem['IsJoined'];
					} else {
						$item->IsJoined = 0;
					}
                                        
					if (isset($putItem['CategoryApp'])) {                                            
						if ($putItem['CategoryApp'] == 'travel_expense' && $putItem['TripID']) {
							$item->ExpensePeriodFrom = DB::table('Trip')
									->select('StartDate')->where('TripID', $putItem['TripID'])->pluck('StartDate');

							if (! $item->IsJoined && $item->CategoryID) {
								CategoryAmount::updateAmount($receipt->UserID, $item->CategoryID, $item->Amount, $item->ExpensePeriodFrom, 'plus', $putItem['TripID']);
							}

							$addTripItemRelationship = true;
							$refreshTripList = true;
						} else if ($putItem['CategoryApp'] != 'travel_expense' && ! empty($putItem['CategoryApp']) && ! empty($putItem['ExpensePeriod'])) {
							$item->ExpensePeriodFrom = strtotime(date('01-M-Y', strtotime($putItem['ExpensePeriod'])));                                                        
							if (! $item->IsJoined && $item->ExpensePeriodFrom && $item->CategoryID) {
								CategoryAmount::updateAmount($receipt->UserID, $item->CategoryID, $item->Amount, $item->ExpensePeriodFrom);
							}
						}                                                    
					}                                        
					
					if (isset($putItem['Memo'])) {
						$item->Memo = $putItem['Memo'];
					}
					
					$item->CreatedTime = round(microtime(true) * 1000);
                                        
                    
                    $itemAction = 'create';                    
				}
                    
				//Update or create the receipt item
                $item->save();
				$_originalItem = $item['attributes'];
				if (isset($_originalItem['CategoryID']) && $_originalItem['CategoryID'] > 0) {
					$_originalItem['CategoryApp'] = $putItem['CategoryApp'];
					$_originalItem['CategoryAppAbbr'] = $putItem['CategoryAppAbbr'];
					$_originalItem['CategoryName'] = $putItem['CategoryName'];
					$_originalItem['TripID'] = $tripID;
					if ($tripID) {
						$_originalItem['Reference'] = Trip::find($tripID)->Reference;
					}
					if (! empty($item->ExpensePeriodFrom)) {
						$_originalItem['ExpensePeriod'] = date('Y-m-d\TH:i:s.B\Z', $item->ExpensePeriodFrom);
					} else {
						$_originalItem['ExpensePeriod'] = null;
					}
				}
				$originalItems[] = $_originalItem;
				
				//Save tags here
				if (isset($putItem['Tags'])) {
                                    $tmpTags = null;
                                    if(gettype($putItem['Tags']) == 'array'){
                                        $tmpTags = $putItem['Tags'];
                                    }elseif(gettype($putItem['Tags']) == 'string'){
                                        $tmpTags = explode(',', $putItem['Tags']);
                                    }
                                    $putItem['Tags'] = ($putItem['Tags']) ? $putItem['Tags'] : '';
                                    
					if (isset($putItem['ItemID'])) {                                                                          
						Tag::saveTags($item->ItemID, 'receipt_item', $tmpTags, Tag::getList($putItem['ItemID'], 'receipt_item', true));
					} else {                                            
						Tag::saveTags($item->ItemID, 'receipt_item', $tmpTags, array());
					}
				}
				
				//If flag $addTripItemRelationship is true, we need to add a relationship between this item to the specified trip
				if ($addTripItemRelationship) {
                    Item::addTripItemRecord($item->ItemID, $putItem['Amount'], $putItem['IsJoined'], $putItem['TripID']);
				}
				
				//If flag $updateTripItemRelationship is true, we need to update the relationship of this item to a new trip
				if ($updateTripItemRelationship) {
                    Item::updateTripItemRecord($item->ItemID, $putItem['Amount'], $putItem['IsJoined'], $putItem['TripID']);
				}
				
				//If flag $removeTripItemRelationship is true, we need to delete the relationship between this item and the old trip
				if ($removeTripItemRelationship) {
                    Item::deleteTripItemRecord($item->ItemID, $oldTripID);
				}
				
				if (isset($putItem['Attachments']) && count($putItem['Attachments'])) {
					$fileIDs = array();
					foreach ($putItem['Attachments'] as $attachment) {
						$fileIDs[] = $attachment['FileID'];
					}
					
					File::updateList($fileIDs, array(
							'Permanent' => 1, 
							'EntityID' => $item->ItemID,
						));
				}
				
				if (isset($putItem['DeletedFileIDs']) && count($putItem['DeletedFileIDs'])) {
					File::deleteList(File::getList($putItem['DeletedFileIDs']));
				}
			}
		}                
        
        //Push event for item
        if($itemAction == 'create') {
            PushBackground::send($userToken->UserID, 'item', 'post', $item->ItemID);
        } else if($itemAction == 'update') {
            PushBackground::send($userToken->UserID, 'item', 'put', $item->ItemID);
        }
		
		//Delete items that was chosen to be deleted
		if (isset($put['DeletedItems'])) {
			if (count($put['DeletedItems'])) {
				foreach ($put['DeletedItems'] as $key => $deletedItems) {
					if (! $deletedItems) {
						unset($put['DeletedItems'][$key]);
					}
				}
				Item::deleteList($put['DeletedItems'], $receipt->UserID);
                //Push event for item
                PushBackground::send($userToken->UserID, 'item', 'delete', implode(",", $put['DeletedItems']));
			}
		}
		
		$receiptOriginal = ReceiptOriginal::find($receipt->ReceiptID);
		if ($updateOriginal) {
			if (! $receiptOriginal) {
				$receiptOriginal = new ReceiptOriginal();
				$receiptOriginal->ReceiptID = $receipt->ReceiptID;
			}
			$receiptOriginal->ReceiptData = json_encode($receipt['attributes']);
			$receiptOriginal->ReceiptItemData = json_encode($originalItems);
			$receiptOriginal->save();
		}
        
        //Push event for receipt
        PushBackground::send($userToken->UserID, 'receipt', 'put', $receipt->ReceiptID);
        
		return Response::json(array(
			'RefreshTripList' => $refreshTripList,
			'SetDefaultApp' => $setDefaultApp,
			'Items' => Item::getListOfReceipts($receipt->ReceiptID),
			'ReceiptData' => $receiptOriginal->ReceiptData,
			'ReceiptItemData' => $receiptOriginal->ReceiptItemData,
			'VerifyStatusChanged' => $verifyStatusChanged
		));
	}
	
	public function deleteIndex()
	{
		//Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
        
		$receiptIDs = explode(',', Input::get('ReceiptIDs', ''));
		//Validate to be sure that all specified receipts belongs to the user who send this request
		$messages = array('ReceiptIDs.required' => 'You need to specify at least one receipt.');
		if (count($receiptIDs) === 1) {
			$messages['ReceiptIDs.receipts_for_submitted_report'] = 'This receipt is reported. You can not delete it.';
		}
		$validator = Validator::make(
				array('ReceiptIDs' => $receiptIDs),
				array('ReceiptIDs' => array('required', 'receipts_belong_to:' . $userToken->UserID, 'receipts_for_submitted_report')),
				$messages
			);
		
		if ($validator->fails()) {
			return Response::json(array('message' => $validator->messages()->all()), 500);
		}
        
		$refreshTrip = false; $refreshReport = false;
		
		File::deleteList(File::getAttachmentListOfReceipts($receiptIDs));

		// Get path of all attachments of items to delete them physically
		File::deleteList(File::getItemAttachmentListByReceipts($receiptIDs));

		// Delete all specified receipts themselves, also delete original version if it exists
		Receipt::deleteList($receiptIDs);
		
		// Update category amount before deleting all items
		CategoryAmount::updateAmountByReceipts($receiptIDs, $userToken->UserID);
		
		// Delete all tag index of receipts and receipt items
		Tag::deleteIndexList($receiptIDs, 'receipt');
		$itemIDs = Item::getItemIDsOfReceipts($receiptIDs);
		if (count($itemIDs)) {
			Tag::deleteIndexList($itemIDs, 'receipt_item');
			
			//Get relationships between items, trips and reports
			$itemTripReportRelationships = Item::checkItemTripReportRelationships($itemIDs);
			if ($itemTripReportRelationships === 2) {
				$refreshTrip = true;
				$refreshReport = true;
			} else if ($itemTripReportRelationships === 1) {
				$refreshTrip = true;
			}
			
			//Delete all relationships between receipt items and trips
			Item::deleteItemTripRelationship($itemIDs);
		}
		
		// Delete all items of specified receipts
		Item::deleteListByReceipts($receiptIDs);
		
        //Push event for receipt
        PushBackground::send($userToken->UserID, 'receipt', 'delete', Input::get('ReceiptIDs', ''));
        //Push event for item
        if (count($itemIDs)) {
            PushBackground::send($userToken->UserID, 'item', 'delete', implode(",", $itemIDs));
        }
        
		return Response::json(array(
				'RefreshTrip' => $refreshTrip,
				'RefreshReport' => $refreshReport
		));
	}
	
	public function postUploadFile()
	{
		//Need to check authentication
		if (! $userToken = UserToken::checkAuth(Input::get('AUTH_TOKEN', ''))) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
		
		$rules = array(
			'Filedata' => array('required', 'valid_ext:' . implode(',', Config::get('app.attachmentRules.extensions')), 
					'file_size:' . Config::get('app.attachmentRules.fileSizeLimit')),
			'EntityName' => array('required', 'in:' . Config::get('app.attachmentRules.entities')),
			'EntityID' => array('required'),
		);
		
		$validator = Validator::make(Input::all(), $rules, array(
			'Filedata.file_size' => 'This file is bigger than the file size limit: ' . Config::get('app.attachmentRules.fileSizeLimit')
		));
		
		if ($validator->fails()) {
			return Response::json(array('message' => $validator->messages()->all()), 500);
		}
		
		$fileData = Input::file('Filedata');
		$entityID = Input::get('EntityID');
		$entityName = Input::get('EntityName');
		
		$filename = $fileData->getClientOriginalName();
		if ($fileData->move('files/attachments', round(microtime(true) * 1000) . '_' . $filename)) {
			$file = new File();
			$file->FileName = $filename;
			$file->FilePath = 'attachments/' . round(microtime(true) * 1000) . '_' .  $filename;
			$file->Timestamp = round(microtime(true) * 1000);
			$file->EntityID = $entityID;
			$file->EntityName = $entityName;
			$file->Permanent = 0;
			$file->save();
			
			$file->FilePath = Config::get('app.fileBaseUrl') . $file->FilePath;
			return Response::json($file, 200);
		}
		
		return Response::json(array('message' => array('Cannot upload this file.')), 500);
	}
	
	public function putArchive()
	{
		//Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
		
		//Validate to be sure that all specified receipts belongs to the user who send this request
		$validator = Validator::make(
				Input::all(), 
				array('ReceiptIDs' => array('required', 'receipts_belong_to:' . $userToken->UserID)),
				array('ReceiptIDs.required' => 'You need to specify at least one receipt.'));
		
		if ($validator->fails()) {
			return Response::json(array('message' => $validator->messages()->all()), 500);
		}
		
		//Archive the selected receipts
		Receipt::archiveList(Input::get('ReceiptIDs'));
		
		return Response::make('', 204);
	}
	
	public function getReceive()
	{
		//Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
		
        $newReceipts = Receipt::getList($userToken->UserID, null,  array('NewReceipt' => true));
        $newReceipts = $newReceipts['receipts'];
        
        if ((count($newReceipts) < 1)) {
            
            return Response::json(array(), 500);
            
        }
		return Response::json($newReceipts, 200);
	}
        
        public function getCountReceipts()
	{
		//Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
		
		return Response::json(Receipt::getCountByType($userToken->UserID, Input::all()));
	}
	
	public function getPrint()
	{
		//Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}

		$receiptIDs = explode(',', Input::get('receiptIDs', ''));

		if (count($receiptIDs)) {
			$receipts = Receipt::getList($userToken->UserID, array(), $receiptIDs);
			$receipts = $receipts['receipts'];
                        
			if (count($receipts)) {
				$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, TRUE, 'UTF-8', FALSE);

				$pdf->SetCreator(PDF_CREATOR);
				$pdf->SetTitle('Receipt');
				$pdf->SetKeywords('TCPDF, PDF, example, test, guide');
				$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
				$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
				$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

				$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
				$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
				$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
				$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
				$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

				$pdf->AddPage();
				$pdf->setFontSubsetting(true);
				$pdf->SetFont('freeserif', '', 7);

				$user = User::find($userToken->UserID);
				$view = View::make('pdfs.receipt', array(
					'user' => $user,
					'receipts' => $receipts, 
					'dateFrom' => Input::get('dateFrom', ''), 
					'dateTo' => Input::get('dateTo', '')
				));
				$pdf->writeHTML($view->render(), TRUE, FALSE, FALSE, FALSE, '');
				
				$file = new File();
				$file->FileName = $user->Username . '_receiptbox_' . date('Y-m-d_H-i-s') .  '.pdf';
				$file->FilePath = 'receipt_pdf/' . $file->FileName;
				$file->Timestamp = round(microtime(true) * 1000);
				$file->EntityID = 0;
				$file->EntityName = 'receipt_pdf';
				$file->save();
				
				$pdf->Output('files/' . $file->FilePath, 'F');
				
				return Response::json(array(
					'FileName' => $file->FileName
				));
			}
		}	
		
		return Response::json(array('message' => array('Cannot find the specified receipt(s).')), 500);
	}
	
	public function getDownloadPdf()
	{
		$fileName = Input::get('fileName', '');
		$file = Config::get('app.fileBasePath') . 'receipt_pdf/' . $fileName;
		
		if (! is_file($file)) {
			return Response::make('Cannot find the specified pdf.', 500);
		}
		
		header('Content-Description: File Transfer');
		header('Content-Type: application/pdf');
		header('Content-Disposition: attachment; filename=' . $fileName);
		header('Content-Transfer-Encoding: binary');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: ' . filesize($file));
		ob_clean();
		flush();
		readfile($file);
		exit;
	}
	
	public function getDownloadImage()
	{
		$fileName = Input::get('fileName', '');
		$file = Config::get('app.fileBasePath') . 'receipts/' . $fileName;
		
		if (! is_file($file)) {
			$file = Config::get('app.fileBasePath') . 'attachments/' . $fileName;
			if (! is_file($file)) {
				return Response::make('Cannot find the receipt image.', 500);
			}
		}
		
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename=' . $fileName);
		header('Content-Transfer-Encoding: binary');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: ' . filesize($file));
		ob_clean();
		flush();
		readfile($file);
		exit;
	}
	
	public function putMore()
	{
		//Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
		
		$put = Input::all();
		//Validate to be sure that all specified receipt belongs to the user who send this request
		$validator = Validator::make(
				$put, 
				array(
					'ReceiptID' => array('required', 'receipts_belong_to:' . $userToken->UserID)
				));
		
		if ($validator->fails()) {
			return Response::json(array('message' => $validator->messages()->all()), 500);
		}
		
		$receipt = Receipt::find($put['ReceiptID']);
		if (isset($put['Memo']) && $put['Memo'] != $receipt->Memo) {
			$receipt->Memo = $put['Memo'];
		}
		if (isset($put['MerchantReview']) && $put['MerchantReview'] != $receipt->MerchantReview) {
			$receipt->MerchantReview = $put['MerchantReview'];
		}
		if (isset($put['ItemCount']) && $put['ItemCount'] != $receipt->Memo) {
			$receipt->ItemCount = $put['ItemCount'];
		}
		if (isset($put['Tag'])) {
			$tags = Tag::getList($receipt->ReceiptID, 'receipt', true);
			$diffDelete = array_diff($tags, $put['Tag']);
			$diffAdd = array_diff($put['Tag'], $tags);
			
			if (count($diffDelete)) {
				//Get all the tag ID to be deleted
				$diffDeletedIDs = DB::table('Tag')
						->select('TagID')
						->whereIn('Name', $diffDelete)
						->lists('TagID');
				
				DB::table('TagIndex')
						->whereIn('TagID', $diffDeletedIDs);
			}
			
			if (count($diffAdd)) {
			}
		}
	}
	
	public function postOcr()
	{
		CLog::message('ocr');
		$xmlFileName = Input::get('XmlFile', '');
		$fileBasePath = Config::get('app.fileBasePath');
		
		//Check the existence of the .tar file
//		if (! is_file($fileBasePath . 'receipt_tar/' . $tarFileName)) {
//			return Response::json(array('message' => array('Cannot find the specified .tar file.')), 500);
//		}
		
		//execute TAR command to retrieve the XML and image file
//		exec('tar -C ' . $fileBasePath . 'receipt_tar -xvf ' . $fileBasePath . 'receipt_tar/' . $tarFileName, $output);
		
		//The TAR filename has this following structure: {UserID}_{imagename.ext}.xml.tar
//		$xmlFileName = str_replace('.tar', '', $tarFileName);
		//Check the existence of the .xml file
//		if (! is_file($fileBasePath . 'receipt_tar/' . $xmlFileName)) {
//			return Response::json(array('message' => array('Cannot find the XML file after extracting.')), 500);
//		}
		
		//Move XML file to the XML folder (receipt_xml)
//		exec('mv ' . $fileBasePath . 'receipt_tar/' . $xmlFileName . ' ' . $fileBasePath . '/receipt_xml');
		if (! is_file($fileBasePath . 'receipt_xml/' . $xmlFileName)) {
			return Response::json(array('message' => array('Cannot find the XML file.')), 500);
		}
		
		//Remove extension and explode the XML filename to retrieve UserID and image filename
		list($userID, $receiptFileName) = explode('_', str_replace('.xml', '', $xmlFileName), 2);
		
		//Check the existence of the image file
//		if (! is_file($fileBasePath . 'receipt_tar/' . $receiptFileName)) {
//			return Response::json(array('message' => array('Cannot find the image file after extracting.')), 500);
//		}
//		
//		//Move image to the receipt image folder (receipts)
//		exec('mv ' . $fileBasePath . 'receipt_tar/' . $receiptFileName . ' ' . $fileBasePath . '/receipts');
		
		// Process the XML to extract information of the receipt
		$xml = simplexml_load_string(preg_replace('/[\x00-\x08\x0b-\x0c\x0e-\x1f]/', '', 
				utf8_encode(file_get_contents($fileBasePath . 'receipt_xml/' . $xmlFileName))
			));
		
		$receipt = new Receipt();
		
		//Set timezone by user settings
		$profile = Profile::find($userID);
		if ($profile) {
			date_default_timezone_set($profile->Timezone);
		} else {
			date_default_timezone_set('UTC');
		}
		
		if (! $xml) {
			$receipt->UserID = $userID;
			$receipt->MerchantID = 0;
			$receipt->MerchantName = 'Receipt Unrecognized';
			$receipt->PurchaseTime = strtotime(date('Y-m-d'));
			$receipt->VerifyStatus = 1;
			$receipt->ReceiptType = 5;
			$receipt->save();
		} else {
			$receipt->MerchantName = $xml->merchant->__toString();
			$receipt->MerchantName = trim($receipt->MerchantName);
			$merchant = Merchant::where('Name', $receipt->MerchantName)->first();
			if ($merchant) {
				$receipt->MerchantID = $merchant->MerchantID;
			} else {
				$receipt->MerchantID = 0;
			}
			
			$receipt->UserID = $userID;
			if (! $xml->date->__toString() || $xml->date->__toString() == '0' || strtotime($xml->date->__toString()) > round(microtime(true) * 1000) || ! strtotime($xml->date->__toString())) {
				$receipt->PurchaseTime = strtotime(date('Y-m-d'));
			} else {
				$receipt->PurchaseTime = strtotime($xml->date->__toString() . (isset($xml->time) ?  ' ' . $xml->time->__toString() : ''));
			}
			
			$receipt->VerifyStatus = 1;
			if (isset($xml->reduction)) {
				$receipt->Discount = $xml->reduction->value->__toString();
			} else {
				$receipt->Discount = 0;
			}

			$receipt->OriginalTotal = 0;
			if(isset($xml->total)) {
				$receipt->OriginalTotal = $xml->total->__toString();
			}

			if (isset($xml->subtotal)) {
				$receipt->Subtotal = $xml->subtotal->__toString();
			}
			
			if (isset($xml->tax->value)) {
				$receipt->Tax = $xml->tax->value->__toString();
				if (!isset($xml->subtotal)) {					
					$receipt->Subtotal = $receipt->OriginalTotal - $receipt->tax;
				}
			}

			$receipt->DigitalTotal = $receipt->OriginalTotal;
			if (! isset($receipt->Subtotal)) {
				$receipt->Subtotal = $receipt->OriginalTotal;
			}
			
			if (isset($xml->receiptType)) {
				$receipt->ReceiptType = $xml->receiptType->__toString();
			} else {
				$receipt->ReceiptType = 3;
			}
			
			if (isset($xml->RocrBots)) {
				$receipt->RocrBots = $xml->RocrBots->__toString();
			}

			if (isset($xml->rawData)) {
				$receipt->RawData = strip_tags($xml->rawData->__toString());  //Remove all HTML tags
			}
			
			// 25/10/2013: A new field called UploadType was added to table Receipt
			// It indicates by which way users send their receipts. Currently we
			// provide 3 ways for them: Upload, Snap (only on mobile), and send via email
			if (isset($xml->uploadType)) {
				$receipt->UploadType = $xml->uploadType;
			}
			
			$receipt->IsOpened = 0;
			$receipt->CreatedTime = round(microtime(true) * 1000);
			
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
			if (isset($xml->item) && count($xml->item)) {
				$receipt->ItemCount = count($xml->item);
				$receipt->save();
				
				foreach ($xml->item as $item) {
					$receiptItem = new Item();
					$receiptItem->ReceiptID = $receipt->ReceiptID;
					$receiptItem->Name = $item->title->__toString();
					$receiptItem->Quantity = (int) $item->quantity->__toString();
					$receiptItem->Price = $item->price->__toString();
					$receiptItem->Amount = max($receiptItem->Quantity * $receiptItem->Price, 0);
					$receiptItem->Total = $receiptItem->Amount * (1 + $receipt->Tax / 100);
					$receiptItem->CreatedTime = round(microtime(true) * 1000);
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
		
		if (isset($xml->oriName)) {
			$file->FileName = $xml->oriName;
		} else {
			$file->FileName = $receiptFileName;
		}
		
//		$file->FilePath = 'receipts/' . $receiptFileName;
		$file->FilePath = $receiptFileName;
		$file->Timestamp = round(microtime(true) * 1000);
		$file->EntityID = $receipt->ReceiptID;
		$file->EntityName = 'receipt_image';
		$file->Permanent = 1;
		$file->save();
		
		//$handle = fopen($fileBasePath . '/ocr_debug.txt', 'a');
		//fwrite($handle, $debugContent);
		//fclose($handle);
		
		return Response::make('', 204);
	}
	
	/**
	 * Notify users by an email when our system failed to analyze email receipts
	 */
	public function postNotify()
	{
		$validator = Validator::make(
			Input::all(),
			array('emails' => 'required', 'multiple_emails'),
			array('emails.required' => 'You need to specify at least one email.')
		);
		
		if ($validator->fails()) {
			return Response::json(array('message' => $validator->messages()->all()), 500);
		}
		
		$users = User::getFullNamesByEmails(Input::get('emails'));
		foreach ($users as $user) {
			Mail::send(
				'emails.receipt.notify', array(
					'name' => $user->Name
				), function($message) use ($user) {
					$message->to($user->Email)->subject('Error Notification from ReceiptClub');
				});
		}
				
		return Response::make('', 204);
	}
	
	/**
	 * Email process
	 */
	public function postProcessEmail()
	{
		$validator = Validator::make(
			Input::all(),
			array('data' => 'required')
		);
		
		if ($validator->fails()) {
			return Response::json(array('message' => $validator->messages()->all()), 500);
		}
		
		$universalMail = Config::get('app.universalMail');
		
		$mailBox = imap_open($universalMail['host'] . "INBOX", $universalMail['addr'], $universalMail['pass']) or CLog::message(imap_last_error());
		
		$data = Input::get('data');
		$hasAttachments = array();
		foreach ($data as $key => $value) {
			foreach ($value as $receipt) {
				$receipt['UserID'] = DB::table('User')->select('UserID')->where('Email', $receipt['EmailSender'])->pluck('UserID');
				
				//If the email has attachment(s), we need to pen the mailbox again and download them
				if (isset($receipt['HasAttachment'])) {
					$tmpFlagHasAttachment = $receipt['HasAttachment'];
					if ($tmpFlagHasAttachment) {
						$hasAttachments[] = $key;
					}
					unset($receipt['HasAttachment']);
				}
				
				$receipt['MerchantID'] = 0;
				if (isset($receipt['MerchantName'])) {
					$merchant = Merchant::where('Name', $receipt['MerchantName'])->first();
					if ($merchant) {
						$receipt['MerchantID'] = $merchant['MerchantID'];
					}
				}
				
				// Added on 2013-10-14 by KhanhDN: auto correct invalid amount from 4 fields: Subtotal, Tax, Extra & Total		
				$receipt['Subtotal'] = isset($receipt['Subtotal'])?$receipt['Subtotal']:0;
				$receipt['DigitalTotal'] = isset($receipt['DigitalTotal'])?$receipt['DigitalTotal']:0;
				$receipt['Tax'] = isset($receipt['Tax'])?$receipt['Tax']:0;
				$receipt['ExtraValue'] = isset($receipt['ExtraValue'])?$receipt['ExtraValue']:0;
				$subtotal = sprintf('%01.2f', $receipt['Subtotal']);
				$total    = sprintf('%01.2f', $receipt['DigitalTotal']);						
				$tax      = sprintf('%01.2f', $receipt['Tax']);
				$extra    = sprintf('%01.2f', $receipt['ExtraValue']);
				
				$tmpTotal = sprintf('%01.2f', $subtotal + $tax + $extra);
							
				if ($tmpTotal != $total && $total > 0 && $subtotal > 0 && $tax > 0 && $extra > 0) {
					// do nothing
				} else { // Auto correct fields amount
					if ($subtotal == 0 && ($total - ($tax + $extra) > 0)) {
						$receipt['Subtotal'] = $total - ($tax + $extra);
					} else if ($subtotal > 0 && $tax == 0 && $extra > 0 && ($total - $subtotal - $extra > 0)) {
						$receipt['Tax'] = $total - $subtotal - $extra;
					} else if ($subtotal > 0 && $tax > 0 && $extra == 0 && ($total - $subtotal - $tax > 0)) {
						$receipt['ExtraValue'] = $total - $subtotal - $tax;
					} else if ($total == 0) {
						$receipt['DigitalTotal'] = $subtotal + $tax + $extra;
					}			
				}
				
				if (isset($receipt['Items'])) {
					$items = $receipt['Items'];
					$receipt['ItemCount'] = count($items);
					unset($receipt['Items']);
				}
				
				unset($receipt['ShippingFee']);
				
				$receipt['VerifyStatus'] = 1;
				$receipt['CreatedTime'] = round(microtime(true) * 1000);
				
				if (! isset($receipt['PurchaseTime'])) {
					$receipt['PurchaseTime'] = $receipt['CreatedTime'];
				}
				
				try {
					$receiptID = DB::table('Receipt')
						->insertGetId($receipt);
				} catch (Exception $e) {
					CLog::message('Exception: ' . $e->getMessage());
				}
				
				if (isset($tmpFlagHasAttachment) && $tmpFlagHasAttachment) {
					if (imap_check($mailBox)->Nmsgs) {
						$this->saveEmailAttachments($mailBox, $key, $receiptID);
					}
				}
				
				$insertedItems = array();
//				$originalItemAmount = array();
				if (count($items)) {
					foreach ($items as $item) {
						$item['ReceiptID'] = $receiptID;
						$item['CreatedTime'] = round(microtime(true) * 1000);
						$insertedItems[] = $item;
					}
					
					DB::table('Item')->insert($insertedItems);
					
//					$originalItemAmount = DB::table('Item')
//							->select('ItemID', 'Amount')
//							->where('ReceiptID', $receiptID)
//							->get();
				}
				
//				$originalAmount = new stdClass();
//				$originalAmount->ReceiptID = $receiptID;
//				$originalAmount->DigitalTotal = $receipt['DigitalTotal'];
//				$originalAmount->Subtotal = $receipt['Subtotal'];
//				$originalAmount->Tax = $receipt['Tax'];
//				$originalAmount->ExtraField = null;
//				$originalAmount->ExtraValue = $receipt['ExtraValue'];
				
				$receiptOriginal = new ReceiptOriginal();
				$receiptOriginal->ReceiptID = $receiptID;
				$receiptOriginal->ReceiptData = json_encode($receipt);
//				$receiptOriginal->Amount = json_encode($originalAmount);
				if (count($insertedItems)) {
					$receiptOriginal->ReceiptItemData = json_encode($insertedItems);
//					$receiptOriginal->ItemAmount = json_encode($originalItemAmount);
				}
				$receiptOriginal->save();
			}
		}
		
		if (count($hasAttachments)) {
			$this->moveEmailFolder($mailBox, $hasAttachments, $universalMail['processedFolder']);
		}
		
		return Response::make('', 204);
	}
	
	/**
	 * Update item count
	 */
	public function putCountItems()
	{
		//Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
		
		if ($userToken->UserID == 1) {
			$receipts = Receipt::where('ReceiptID' , '>', 0)->get();
			
			if (count($receipts)) {
				foreach ($receipts as $receipt) {
					$receipt->ItemCount = DB::table('Item')
							->where('ReceiptID', $receipt->ReceiptID)
							->where('Name', '!=', 'Combined Item')
							->count();
					
					$receipt->save();
				}
			}
		}
	}
	
	/**
	 * Update original receipt data
	 */
	public function putOriginal()
	{
		//Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
		
		if ($userToken->UserID === 1) {
			$receipts = Receipt::where('ReceiptID' , '>', 0)->get();
			foreach ($receipts as $receipt) {
				$originalReceipt = ReceiptOriginal::find($receipt->ReceiptID);
				$originalReceipt->ReceiptData = json_encode($receipt['attributes']);
				
				$items = Item::where('ReceiptID', $receipt->ReceiptID)->orderBy('IsJoined')->get();
				$originalItems = array();
				foreach ($items as $item) {
					if ($item->CategoryID) {
						$category = Category::find($item->CategoryID);
						$item->CategoryName = $category->Name;
						$item->CategoryApp = $category->App;
						if ($category->App == 'personal_expense') {
							$item->CategoryAppAbbr = 'PE';
							$item->TripID = 0;
						}
						if ($category->App == 'travel_expense') {
							$item->CategoryAppAbbr = 'TE';
							$trip = DB::table('Trip AS t')
									->join('TripItem AS ti', 'ti.TripID', '=', 't.TripID')
									->select('t.TripID', 't.Reference')
									->where('TripItemID', $item->ItemID)
									->first();
							
							if ($trip) {
								$item->TripID = $trip->TripID;
								$item->Reference = $trip->Reference;
							}
						}
						if (! empty($item->ExpensePeriodFrom)) {
							$item->ExpensePeriod = date('Y-m-d\TH:i:s.B\Z', $item->ExpensePeriodFrom);
						} else {
							$item->ExpensePeriod = null;
						}
					}
					
					$originalItems[] = $item['attributes'];
				}

				$originalReceipt->ReceiptItemData = json_encode($originalItems);
				$originalReceipt->save();
			}
		}
	}
	
	/**
	 * Get S3 URL for receipt images
	 */
	public function getImageUrl()
	{
		//Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
		
		$receiptID = Input::get('receiptID', 0);
		$validator = Validator::make(
				array('ReceiptID' => $receiptID),
				array('ReceiptID' => array('required', 'exists:Receipt,ReceiptID,UserID,' . $userToken->UserID)),
				array(
					'ReceiptID.exists' => 'The selected receipt does not belong to you.',
					'ReceiptID.required' => 'You need to specify a receipt.'
				)
			);
		
		if ($validator->fails()) {
			return Response::json(array('message' => $validator->messages()->all()), 500);
		}
		
		$receiptImage = DB::table('File')
				->select('FileID', 'FileBucket', 'FilePath')
				->where('EntityID', $receiptID)
				->where('EntityName', 'receipt_image')
				->first();
		
//		if (! $receiptImage || strpos($receiptImage->FilePath, 'receipts/') !== false || strpos($receiptImage->FilePath, 'attachments/') !== false) {
//			return Response::json(array('message' => array('This receipt is not a paper receipt.')), 500);
//		} else {
			return Response::json(array(
				'FileID'   => $receiptImage->FileID,
				'FilePath' => File::getS3PreSignedUrl($receiptImage->FileBucket, $receiptImage->FilePath)
			));
//		}
	}
        
        
	/*
         * Get file PDF from Amazone
         */
        public function getPdfFile(){
           //Need to check authentication
             //   if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
               //     return Response::json(array('message' => 'The authentication is failed.'), 401);
                //}
            $param = Input::all();
           $urlPDF =$param['url'].'&Expires='.rawurlencode($param['Expires']).'&Signature='.rawurlencode($param['Signature']);
            $infile = file_get_contents($urlPDF);
            return $infile;
        }
	/**
	 * Use this method to build vaidator for both creating and updating receipt
	 */
	private function validateReceipt($inputs, $userID = 0)
	{
		$rules = array(
			'MerchantName' => array('required', 'max:255'),
			'MerchantAddress' => array('max:45'),
			'MerchantPhone' => array('max:45'),
			'MerchantCountry' => array('max:45'),
			'MerchantCity' => array('max:45'),
			'MerchantState' => array('max:45'),
			'MerchantCode' => array('max:45'),
			'OriginalTotal' => array('numeric'),
			'Discount' => array('numeric'),
			'DigitalTotal' => array('numeric'),
			'Subtotal' => array('numeric'),
			'Tip' => array('numeric'),
			'Tax' => array('numeric'),
			'ExchangeRate' => array('numeric'),
			'CurrencyCode' => array('size:3'),
			'PaymentType' => array('integer'),
			'PurchaseTime' => array('required', 'date'),
			'Items' => array('item_required'),
			'ItemCount' => array('integer')
		);
		
		if (isset($inputs['ReceiptID']) && $inputs['ReceiptID'] && $userID) {
			$rules['ReceiptID'] = array('receipts_for_submitted_report', 'exists:Receipt,ReceiptID,UserID,' . $userID);
		}
		
		//Validate all inputs for receipt (not receipt items)
		$validator = Validator::make($inputs, $rules, array(
			'ReceiptID.exists' => 'The selected receipt does not belong to you.',
			'ReceiptID.receipts_for_submitted_report' => 'This receipt is reported. You cannot modify or delete it.',
			'ItemID.exists' => 'This item does not belong to the receipt.',
			'MerchantName.required' => 'Please enter Merchant Name.',
			'PurchaseTime.required' => 'Please select Purchase Date.',
			'MerchantName.max' => 'Merchant name is limited to 255 characters.',
			'MerchantAddress.max' => 'Merchant address is limited to 45 characters.',
			'MerchantPhone.max' => 'Merchant phone is limited to 45 characters.',
			'MerchantCountry.max' => 'Merchant country is limited to 45 characters.',
			'MerchantCity.max' => 'Merchant city is limited to 45 characters.',
			'MerchantState.max' => 'Merchant state is limited to 45 characters.',
			'MerchantCode.max' => 'Merchant code is limited to 45 characters.',
			'OriginalTotal.numeric' => 'Please enter a valid original total.',
			'Discount.numeric' => 'Please enter a valid discount.',
			'DigitalTotal.numeric' => 'Please enter a valid digital total.',
			'Subtotal.numeric' => 'Please enter a valid subtotal.',
			'Tip.numeric' => 'Please enter a valid tip.',
			'Tax.numeric' => 'Please enter a valid tax.',
			'CurrencyCode.size' => 'Currency code needs to have exactly 3 characters.',
			'PaymentType.integer' => 'Please choose a valid payment type.',
			'ItemCount.integer' => 'Item count must be an integer value',
		));
		
		if ($validator->fails()) {
			return array('message' => $validator->messages()->all());
		}
		
		//Validate input for receipt items
		if (count($inputs['Items'])) {
			foreach ($inputs['Items'] as $key => $item) {
				$rules = array(
					'Name' => array('max:255'),
					'Amount' => array('numeric'),
					'CategoryID' => array('integer'),
					'Memo' => array('max:1000'),
				);
				
				if ($userID) {
//					$rules['TripID'] = array('required_if:CategoryApp,"travel_expense"', 'trips_belong_to:' . $userID);
					$rules['TripID'] = array('trips_belong_to:' . $userID);
				}
				
				if (isset($item['ItemID']) && $item['ItemID']) {
					$rules['ItemID'] = array('exists:Item,ItemID,ReceiptID,' . $inputs['ReceiptID']);
				}
				
				$validator = Validator::make($item, $rules, array(
					'ItemID' => 'This item does not belong to you.',
					'Name.max' => 'Item name is limited to 255 characters.',
					'Amount.numeric' => 'Please enter a valid amount.',
					'CategoryID.integer' => 'Please choose a valid category.',
					'Memo.max' => 'Item name is limited to 1000 characters.',
//					'TripID.required_if' => 'If you choose a category from Travel Expense, you also need to specify a trip.',
				));
				
				if ($validator->fails()) {
					return array(
						'message' => $validator->messages()->all(),
						'itemRow' => $key,
					);
				}
			}
		}
		
		return array();
	}
        
        
	
	private function moveEmailFolder($mailBox, $numMessages, $folderName)
	{
		imap_mail_move($mailBox, implode (",", $numMessages), $folderName, CP_UID);
		imap_expunge($mailBox); 
		imap_close($mailBox);
	}
	
	private function saveEmailAttachments($mailBox, $numMessages, $receiptID)
	{
		$structure = imap_fetchstructure($mailBox, $numMessages, FT_UID);
		$count = 1;
		
		if (isset($structure->parts) && count($structure->parts)) {
			foreach ($structure->parts as $part) {
				$attachment = array(
					'is_attachment' => false,
					'filename' => '',
					'name' => '',
					'attachment' => '',
				);
				
				if ($part->ifdparameters) {
					foreach ($part->dparameters as $obj) {
						if (strtolower($obj->attribute) == 'filename') {
							$attachment['is_attachment'] = true;
							$attachment['filename'] = $obj->value;
						}
					}
				}
				
				if ($part->ifparameters) {
					foreach ($part->parameters as $obj) {
						if (strtolower($obj->attribute) == 'name') {
							$attachment['is_attachment'] = true;
							$attachment['name'] = $obj->value;
						}
					}
				}
				
				if ($attachment['is_attachment']) {
					$attachment['attachment'] = imap_fetchbody($mailBox, $numMessages, $count, FT_UID);
					/* 4 = QUOTED-PRINTABLE encoding */
					if ($part->encoding == 3) {
						$attachment['attachment'] = base64_decode($attachment['attachment']);
					} else if ($part->encoding == 4) { /* 3 = BASE64 encoding */ 
						$attachment['attachment'] = quoted_printable_decode($attachment['attachment']);
					}
					$file = new File();
					$file->FileName = ! empty($attachment['name']) ? $attachment['name'] : $attachment['filename'];
					$file->FilePath = 'attachments/' . round(microtime(true) * 1000) . '_' . $file->FileName;
					$fullPath = Config::get('app.fileBasePath') . $file->FilePath;
					
					$fileHandler = fopen($fullPath, 'w+');
					fwrite($fileHandler, $attachment['attachment']);
					fclose($fileHandler);
					$validator = Validator::make(
							array(
								'extension' => pathinfo($fullPath, PATHINFO_EXTENSION),
								'file_size' => filesize($fullPath)
							),
							array(
								'extension' => array('required', 'in:' . implode(',', Config::get('app.attachmentRules.extensions'))),
								'file_size' => array('max:' . Config::get('app.attachmentRules.fileSizeLimit')),
							)
					);
					if (! $validator->fails()) {
						$file->Timestamp = round(microtime(true) * 1000);
						$file->EntityID = $receiptID;
						$file->EntityName = 'receipt';
						$file->Permanent = 1;
						$file->save();
					}
				}
				
				$count++;
			}
		}
	}
    
    private function merchantFromReceiptInputData($inputData){
        $merchant = new Merchant();

        isset($inputData['MerchantName']) && $merchant->Name           = trim($inputData['MerchantName']);
        isset($inputData['MerchantLogo']) && $merchant->Logo           = trim($inputData['MerchantLogo']);
        isset($inputData['MerchantAddress']) && $merchant->Address     = trim($inputData['MerchantAddress']);

        //QuyPV-20141218: temporary disable and store these merchant info to Receipt table
        /*
        isset($inputData['MerchantPhone']) && $merchant->PhoneNumber   = trim($inputData['MerchantPhone']);
        isset($inputData['MerchantCountry']) && $merchant->CountryCode = trim($inputData['MerchantCountry']);
        isset($inputData['MerchantCity']) && $merchant->City           = trim($inputData['MerchantCity']);
        isset($inputData['MerchantState']) && $merchant->State         = trim($inputData['MerchantState']);
        isset($inputData['MerchantCode']) && $merchant->ZipCode        = trim($inputData['MerchantCode']);
        */
        
        return $merchant;
    }
    
}