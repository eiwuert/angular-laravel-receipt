<?php

class Receipt extends BaseModel {

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'Receipt';
    protected static $_table = 'Receipt';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'ReceiptID';
    protected static $_primaryKey = 'ReceiptID';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Object of AWS SDK
     */
    protected static $s3 = null;

    /**
     * Constant variable for non paper receipt type
     */
    const NRType = 5;

    /**
     * Constant variable for unrecorgnized receipt type
     */
    const NRMcName = 'Receipt Unrecognized';
    const UnMcName = 'Merchant Unrecognized';

    /**
     * Define the One-to-One relationship between Receipt and ReceiptOriginal
     */
    public function ReceiptOriginal() {
        return $this->hasOne('ReceiptOriginal', 'ReceiptID');
    }

    /**
     * Define the One-to-Many relationship between Receipt and Item
     */
    public function Item() {
        return $this->hasMany('Item');
    }

    /**
     *  Return number of receipts by receipt type
     */
    public static function getCountByType($userId, $filter = array()) {
        $countQuery = DB::table('Receipt')->where('UserID', $userId)->where('IsArchived', 0);        
        $receiptType = array();
        for ($i = 1; $i < 8; $i++):
            $tmpQuery = clone $countQuery;
            $tmpQuery->where('ReceiptType', $i);

            $tmpReceiptType = count($tmpQuery->lists('ReceiptID'));
            $tmpNewReceiptType = count($tmpQuery->where('IsOpened', 0)->lists('ReceiptID'));

            $receiptType[$i] = array('total' => $tmpReceiptType, 'new' => $tmpNewReceiptType);
        endfor;

        $totalReceipt = count($countQuery->lists('ReceiptID'));
        $countQuery   = DB::table('Receipt')->where('UserID', $userId)->where('IsOpened', 0); 
        $totaNewlReceipt = count($countQuery->lists('ReceiptID'));
        
        return array(
            'TotalReceipts'   => $totalReceipt,
            'ReceiptByType'   => $receiptType,
            'TotalNewReceipt' => $totaNewlReceipt
        );
    }
    
    /**
     * Function to return all of receipt ID of a user.
     * 
     * @param type $userId
     * @return array List of receipts ID
     */
    
    public static function getListReceiptsId($userId){
        $receiptsQuery = DB::table('Receipt')->where('UserID', $userId)->where('IsArchived', 0);
        $receiptsQuery->select('ReceiptID')->where('UserID', $userId)->orderBy('ReceiptID', 'desc');;
        return $receiptsQuery->lists('ReceiptID');
    }

    /**
     * Get receipt list by filters
     */
    public static function getList($userId, $limitAndOffset = array(), $filter = array(), $receiptIDs = array()) {
        $receiptQuery = DB::table('Receipt AS r');        
        //We limits selected fields to information that we really display in the screen, depends on the client: Mobile or Web
        $fields = array('ReceiptID', 'MerchantName', 'OriginalTotal', 'DigitalTotal', 'IsNew', 'CurrencyCode',
            'VerifyStatus', 'ReceiptType', 'PaymentType', 'PurchaseTime', 'Tax', 'Discount',
            'CreatedTime', 'IsArchived', 'IsOpened', 'ItemCount', 'MerchantReview', 'Memo',
            'CouponCode', 'RebateAmount', 'EmailSender');

        if (isset($filter['mobile'])) {
            $fields = array_merge($fields, array('f.FileID', 'f.FilePath', 'f.FileName'));
            $receiptQuery->leftJoin('File AS f', function($join) {
                $join->on('f.EntityID', '=', 'r.ReceiptID')->on('f.EntityName', '=', DB::raw('"receipt_image"'));
            });
        }

        //Join merchant name
        $fields = array_merge($fields, array('m.Name AS MerchantName', 'r.MerchantID'));
        $receiptQuery->leftJoin('Merchant AS m', function($join) {
            $join->on('m.MerchantID', '=', 'r.MerchantID');
        });
                
        
        $receiptQuery->select($fields)->where('r.UserID', $userId);

        if (isset($filter['mobile'])) {
            $receiptQuery->whereIn('ReceiptType', array(3, 4));
        }

        if (count($receiptIDs)) {
            $receiptQuery->whereIn('ReceiptID', $receiptIDs);
        }
           
        //Filter by receipt type
        if (!isset($filter['type'])) {
            $filter['type'] = 'all';
        }

        if ($filter['type'] == 'all') {
            
            $receiptQuery->where('IsArchived', 0);
            
        } else if ($filter['type'] == 'newReceipts') {   
            
            $receiptQuery->where('IsArchived', 0)->where('IsOpened', 0);
            
        } else if ($filter['type'] == 'archivedReceipts') {
            $receiptQuery->where('IsArchived', '>', 0);
        } else if (self::getTypeValue($filter['type'])) {
            $receiptQuery->where('IsArchived', '=', 0)->where('ReceiptType', self::getTypeValue($filter['type']));
        }

        if (isset($filter['transaction'])) {
            $filter['transaction'] = ucfirst($filter['transaction']);
        }
        if (!isset($filter['transaction']) || ($filter['transaction'] != 'CreatedTime' && $filter['transaction'] != 'PurchaseTime')) {
            $filter['transaction'] = 'CreatedTime';
        }

        if (!isset($filter['from'])) {
            $filter['allDate'] = true;
        }

                
        if (isset($filter['timestamp'])) {
            $receiptQuery->where(function($query) use ($filter) {
                $query->where('CreatedTime', '>', $filter['timestamp'])
                        ->orWhere('ModifiedTime', '>', $filter['timestamp']);
            });
        } else if (!isset($filter['allDate']) || !$filter['allDate']) {            
            //Filter by a date period            
            $receiptQuery->where($filter['transaction'], '>=', $filter['from']);

            if (isset($filter['to'])) {
                $receiptQuery->where($filter['transaction'], '<=', $filter['to']);
            }
        }

        //$receiptQuery->orderBy('IsOpened', 'asc');
        //Filter by fields that is chosen to be transaction date type or to be sorted
        if (isset($filter['sortField']) && $filter['sortField']) {
            switch ($filter['sortField']) {
                case 'amount':
                    $sortField = 'DigitalTotal';
                    if (!isset($filter['sortValue']) || !$filter['sortValue']) {
                        $sortValue = 'asc';
                    } else {
                        $sortValue = 'desc';
                    }

                    break;
                case 'date':
                    $sortField = $filter['transaction'];
                    if (!isset($filter['sortValue']) || !$filter['sortValue']) {
                        $sortValue = 'desc';
                    } else {
                        $sortValue = 'asc';
                    }

                    break;
            }

            if (isset($sortField) && isset($sortValue)) {
                $receiptQuery->orderBy($sortField, $sortValue);
            }
        }

        if (!isset($sortField) || $sortField != $filter['transaction']) {
            $receiptQuery->orderBy($filter['transaction'], 'desc');
        }

        if (isset($filter['queryFrom'])) {
            if (!isset($filter['range'])) {
                $filter['range'] = 20;
            }
            $receiptQuery->skip($filter['queryFrom'] - 1)->take($filter['range']);
        }

        if (isset($filter['newManualreceipt'])) {
            if ($filter['newManualreceipt'] == true) {
                $receiptQuery->where('IsArchived', 0)->where('ReceiptType', 4)->take(1);
            }
        }

        //Filter receipt by receipt by type
        if (isset($filter['filterByType'])) {
            $receiptQuery->where('IsArchived', 0)->where('ReceiptType', '=', $filter['filterByType']);
        }
        
        $receiptQueryClone = clone $receiptQuery;
        
        if (isset($filter['NewReceipt']) && boolval($filter['NewReceipt']) ) {                                         
            $receiptQuery->where('IsNew', 1);           
        }
        
        /**
         * @param reloadTotal  If client call api with this param server must count all receipt and return
         */
        $tmpCountList = 0;
        
        if (isset($filter['reloadTotal']) && boolval($filter['reloadTotal'])) {
            
            $tmpCountList = count($receiptQueryClone->lists('ReceiptID'));
        
        }
        
        if(isset($limitAndOffset['offsetReceipt']) || isset($limitAndOffset['limitReceipt'])):                        
            
            if(isset($limitAndOffset['offsetReceipt'])):
                $receiptQuery->skip($limitAndOffset['offsetReceipt']);            
            endif;                            
            $limit = (int)$limitAndOffset['limitReceipt'];
                $receiptQuery->take($limit);
        endif;
        
        //Get receipts.
        $receipts = $receiptQuery->get();

        //Set merchant name for unrecognized receipts
        foreach ($receipts as $rc) {
            if ($rc->MerchantID == 0) {
                if ($rc->ReceiptType == self::NRType) {
                    $rc->MerchantName = self::NRMcName;
                } else {
                    $rc->MerchantName = self::UnMcName;
                }
            }
        }

        //Get only receipt ids of the list
        $receiptIDList = $receiptQuery->lists('ReceiptID');

        //We only query to get items for 1 time, then use a temp array to add items to the right receipt
        $tmpItems = array();
        if (count($receiptIDList)) {
            //Get receipt items of receipts in the list
            $itemList = Item::getListOfReceipts($receiptIDList, true);



            //Add from query result to temp array
            if (count($itemList)) {
                foreach ($itemList as $item) {
                    $tmpItems[$item->ReceiptID][] = $item;
                }
            }
        }

        //We only query to get attachments for 1 time, then use a temp array to add attachments to the right receipt
        $tmpAttachments = array();
        if (count($receiptIDList)) {
            $attachmentList = File::getListByEntities($receiptIDList);
            //Add from query result to temp array
            if (count($attachmentList)) {
                foreach ($attachmentList as $attachment) {
                    $tmpAttachments[$attachment->EntityID][] = $attachment;
                }
            }
        }

        //We only query to get attachments for 1 time, then use a temp array to add tags to the right receipt
        $tmpTags = array();
        if (count($receiptIDList)) {
            $tagList = Tag::getList($receiptIDList);
            //Add from query result to temp array
            if (count($tagList)) {
                foreach ($tagList as $tag) {
                    $tmpTags[$tag->EntityID][] = $tag->Name;
                }
            }
        }

        //$newReceipts = array();
        //$oldReceipts = array();
        //Add from temp array to receipt + format receipt data before returning it
        if (count($receipts)) {
            foreach ($receipts as $receipt) {
                //Merchant info
                $merchant = new Merchant();
                if ($receipt->MerchantID) {
                    $merchant = Merchant::find($receipt->MerchantID);
                } else if ($receipt->ReceiptType == self::NRType) {
                    $merchant->Name = self::NRMcName;
                } else {
                    $merchant->Name = self::UnMcName;
                }
                $receipt->MerchantName = $merchant->Name;
                $receipt->MerchantLogo = $merchant->Logo;
                $receipt->MerchantPhone = $merchant->PhoneNumber;
                $receipt->MerchantAddress = $merchant->Address;
                $receipt->MerchantCountry = $merchant->CountryCode;
                $receipt->MerchantCity = $merchant->City;
                $receipt->MerchantState = $merchant->State;
                $receipt->MerchantCode = $merchant->ZipCode;
                $receipt->MerchantNaicsCode = $merchant->NaicsCode;
                $receipt->MerchantSicCode = $merchant->SicCode;
                $receipt->MerchantMccCode = $merchant->MccCode;

                $receipt->OriginalTotal = number_format($receipt->OriginalTotal, 2, '.', '');

                $receipt->More = new stdClass();
                $receipt->More->Memo = $receipt->Memo;
                $receipt->More->ItemCount = $receipt->ItemCount;
                $receipt->More->MerchantReview = $receipt->MerchantReview;

                if (empty($receipt->DigitalTotal)) {
                    $receipt->DigitalTotal = $receipt->OriginalTotal;
                } else {
                    $receipt->DigitalTotal = number_format($receipt->DigitalTotal, 2, '.', '');
                }

                $receipt->PurchaseTime = date('Y-m-d\TH:i:s.B\Z', $receipt->PurchaseTime);
                $receipt->CreatedTime = date('Y-m-d\TH:i:s.B\Z', round(($receipt->CreatedTime)/1000));

                if (isset($filter['timestamp'])) {
                    if ($receipt->CreatedTime > $filter['timestamp']) {
                        $receipt->SyncType = 0;
                    } else if ($receipt->CreatedTime > $filter['timestamp']) {
                        $receipt->SyncType = 1;
                    }
                }

                if (isset($tmpItems[$receipt->ReceiptID])) {
                    $receipt->Items = $tmpItems[$receipt->ReceiptID];
                } else {
                    $receipt->Items = array();
                }

                $receipt->IsReported = 0;
                foreach ($receipt->Items as $item) {                    
                    if (intval($item->IsSubmitted) && intval($item->IsApproved) < 1) {                        
                        $receipt->IsReported = 1;
                        break;
                    }
                }

                if (isset($tmpAttachments[$receipt->ReceiptID])) {
                    $receipt->Attachments = $tmpAttachments[$receipt->ReceiptID];
                } else {
                    $receipt->Attachments = array();
                }

                if (isset($tmpTags[$receipt->ReceiptID])) {
                    $receipt->More->Tags = $tmpTags[$receipt->ReceiptID];
                } else {
                    $receipt->More->Tags = array();
                }

                if (!$receipt->More->Memo && !$receipt->More->ItemCount && !$receipt->More->MerchantReview && !count($receipt->More->Tags)) {
                    $receipt->More->IsEmpty = true;
                } else {
                    $receipt->More->IsEmpty = false;
                }

                $receipt = self::setCategorizedState($receipt);

                //Initialize the boolean isChecked for receipt
                $receipt->IsChecked = false;
                $receipt->IsCollapsed = true;

                //Initialize the array of deleted files
                $receipt->DeletedFileIDs = array();

                if (!$receipt->IsOpened) {
                    $receipt->VerifyStatus = 0;
                    //$newReceipts[] = $receipt;
                } else {
                    //$oldReceipts[] = $receipt;
                }
            }
        }
        
        if (count($receiptIDList)) {      
            
            if(isset($filter['markNotNew']) && boolval($filter['markNotNew'])){                    
                DB::table('Receipt')
                    ->whereIn('ReceiptID', $receiptIDList)
                    ->where('IsNew', 1)
                    ->where('UserID', $userId)
                    ->update(array(
                        'IsNew' => 0));
            }     
            
        }

        if (isset($filter['timestamp'])) {
            return array(
                'timestamp' => $_SERVER['REQUEST_TIME'],
                'receipts' => $receipts,
            );
        }
        
        return array('receipts'     => $receipts,
                     'totalReceipt' => $tmpCountList);
    }

    /**
     * Get a receipt and all infos associated with it (merchants, receipt items ...)
     */
    public static function fetch($receiptId, $userId) {
        $receipt = DB::table('Receipt AS r')
                //->leftJoin('Merchant AS m', 'm.MerchantID', '=', 'r.merchantID')
                ->leftJoin('File AS f', function($join) {
                    $join->on('f.EntityID', '=', 'r.ReceiptID')->on('f.EntityName', '=', DB::raw('"receipt_image"'));
                })
                ->leftJoin('ReceiptOriginal AS ro', 'ro.ReceiptID', '=', 'r.ReceiptID')
                ->select('r.*', 'f.FileID', 'f.FileBucket','f.FilePath', 'f.FileName', 'ro.ReceiptData', 'ro.ReceiptItemData', 'ro.Amount AS OriginalAmount', 'ro.ItemAmount AS OriginalItemAmount')
                ->where('r.ReceiptID', $receiptId)
                ->where('r.UserID', $userId)
                ->first();

        if ($receipt) {
            //Merchant info
            $merchant = new Merchant();
            if ($receipt->MerchantID) {
                $merchant = Merchant::find($receipt->MerchantID);
            } else if ($receipt->ReceiptType == self::NRType) {
                $merchant->Name = self::NRMcName;
            } else {
                $merchant->Name = self::UnMcName;
            }
            $receipt->MerchantName = $merchant->Name;
            $receipt->MerchantLogo = $merchant->Logo;
            $receipt->MerchantAddress = $merchant->Address;
            //QuyPV-20141218: Temporary disable this and use receipt info instead
            /*
            $receipt->MerchantPhone = $merchant->PhoneNumber;
            $receipt->MerchantCountry = $merchant->CountryCode;
            $receipt->MerchantCity = $merchant->City;
            $receipt->MerchantState = $merchant->State;
            $receipt->MerchantCode = $merchant->ZipCode;
            $receipt->MerchantNaicsCode = $merchant->NaicsCode;
            $receipt->MerchantSicCode = $merchant->SicCode;
            $receipt->MerchantMccCode = $merchant->MccCode;
            */

            //Receipt info
            $receipt->OriginalTotal = number_format($receipt->OriginalTotal, 2, '.', '');
            $receipt->Discount = number_format($receipt->Discount, 2, '.', '');
            $receipt->Subtotal = number_format($receipt->Subtotal, 2, '.', '');
            $receipt->Tip = number_format($receipt->Tip, 2, '.', '');
            $receipt->Tax = number_format($receipt->Tax, 2, '.', '');

            if (empty($receipt->DigitalTotal)) {
                $receipt->DigitalTotal = $receipt->OriginalTotal;
            } else {
                $receipt->DigitalTotal = number_format($receipt->DigitalTotal, 2, '.', '');
            }

            $receipt->PurchaseTime = date('Y-m-d\TH:i:s.B\Z', $receipt->PurchaseTime);
            $receipt->CreatedTime = date('Y-m-d\TH:i:s.B\Z', round(($receipt->CreatedTime)/1000));
            $receipt->ModifiedTime = date('Y-m-d\TH:i:s.B\Z', $receipt->ModifiedTime);

            $receipt->ReceiptImage = new stdClass();
            if (!empty($receipt->FileID)) {
                $receipt->ReceiptImage->FileID = $receipt->FileID;
            }
            if (!empty($receipt->FilePath)) {
                $receipt->ReceiptImage->FileBucket = $receipt->FileBucket;
                $receipt->ReceiptImage->FilePath = $receipt->FilePath;                
            }
            if (!empty($receipt->FileName)) {
                $receipt->ReceiptImage->FileName = $receipt->FileName;
            }
            unset($receipt->FileID);
            unset($receipt->FilePath);
            unset($receipt->FileBucket);

            //Get all items of the receipt
            $receipt->Items = Item::getListOfReceipts($receiptId);

            $receipt->IsReported = 0;            
            foreach ($receipt->Items as $item) {
                if (intval($item->IsSubmitted) && intval($item->IsApproved) < 1) {                                        
                    $receipt->IsReported = 1;
                    break;
                }
            }

            //Set categorized state of the receipt
            $receipt = self::setCategorizedState($receipt);

            //Get all attachments of the receipt
            $receipt->Attachments = File::getListByEntities($receiptId);
            $receipt->Tags = Tag::getList($receipt->ReceiptID, 'receipt', true);

            //Initialize the array of deleted files
            $receipt->DeletedFileIDs = array();
            $receipt->DeletedItems = array();
            if ($receipt->ReceiptType == 2) {
                $receipt->RawData = html_entity_decode($receipt->RawData, ENT_QUOTES);
            } else {
                $receipt->RawData = nl2br(str_replace('  ', ' &nbsp;', $receipt->RawData));

                if (isset($receipt->ReceiptImage->FileName)) {
                    $receipt->RawData = $receipt->ReceiptImage->FileName . '<br/><br/>' . $receipt->RawData;
                }

                if (!empty($receipt->UploadType)) {
                    $receipt->RawData = 'Image Source - ' . ucfirst($receipt->UploadType) . '<br/>' . $receipt->RawData;
                }

                if (isset($receipt->RocrBots) && !empty($receipt->RocrBots)) {
                    $receipt->RawData = $receipt->RocrBots . '<br/>' . $receipt->RawData;
                }
            }

            $updates = array();
            if (!$receipt->IsOpened) {
                $updates['IsOpened'] = 1;
            }
            if (!$receipt->VerifyStatus) {
                $updates['VerifyStatus'] = 1;
            }
            if (count($updates)) {
                DB::table('Receipt')
                        ->where('ReceiptID', $receipt->ReceiptID)
                        ->update($updates);
            }
        }

        return $receipt;
    }

    public static function deleteList($receiptIDs) {
        if (!is_array($receiptIDs)) {
            $receiptIDs = array($receiptIDs);
        }

        if (count($receiptIDs)) {
            DB::table('Receipt')
                    ->whereIn('ReceiptID', $receiptIDs)
                    ->delete();

            DB::table('ReceiptOriginal')
                    ->whereIn('ReceiptID', $receiptIDs)
                    ->delete();
        }
    }

    public static function archiveList($receiptIDs) {
        if (!is_array($receiptIDs)) {
            $receiptIDs = array($receiptIDs);
        }

        if (count($receiptIDs)) {
            DB::table('Receipt')
                    ->whereIn('ReceiptID', $receiptIDs)
                    ->update(array('IsArchived' => 1));
        }
    }

    public static function getNewReceipts($userID) {
        return DB::table('Receipt')
                        ->where('UserID', $userID)
                        ->where('IsNew', 1)
                        ->get();
    }

    public static function getTypeValue($typeName) {
        switch ($typeName) {
            case 'digitalReceipts':
                return 1;
            case 'emailReceipts':
                return 2;
            case 'paperReceipts':
                return 3;
            case 'manualReceipts':
                return 4;
            case 'nonReceipts':
                return 5;
            case 'paperInvoices':
                return 6;
            case 'electronicInvoices':
                return 7;
            default:
                return 0;
        }
    }

    public static function getVerifyStatus($value) {
        switch ($value) {
            case 0:
                return 'New Receipt';
            case 1:
                return 'Awaiting verification';
            case 2:
                return 'User verified';
            default:
                return '';
        }
    }

    public static function setCategorizedState($receipt) {
        //Loop receipt items to defy App, Category, ExpensePeriod for receipt
        $receipt->App = null;
        $receipt->Category = null;
        $receipt->ExpensePeriod = null;
        if (count($receipt->Items)) {
            $arrayItem = $receipt->Items;
            $firstItem = $arrayItem[0];
            $tempExpensePeriod = $firstItem->ExpensePeriod;
            $theSameExpensePeriod = true;
            foreach ($receipt->Items as $key => $item) {
                if ($receipt->App == 'MX' && $receipt->Category == 'Mixed' && $receipt->ExpensePeriod == 'Mixed') {
                    break;
                }

                if (!$key && !$item->IsJoined) {
                    if (!empty($item->CategoryAppAbbr)) {
                        $receipt->App = $item->CategoryAppAbbr;
                    }

                    if (!empty($item->CategoryName)) {
                        $receipt->Category = $item->CategoryName;
                    }

                    if (!empty($item->Reference)) {
                        $receipt->ExpensePeriod = $item->Reference;
                    } else if (!empty($item->ExpensePeriod)) {
                        $receipt->ExpensePeriod = date('M-Y', strtotime($item->ExpensePeriod));
                    }
                } else if (!$item->IsJoined) {
                    if ($item->CategoryAppAbbr != $receipt->App) {
                        $receipt->App = 'MX';
                        $receipt->Category = 'Mixed';
                        $receipt->ExpensePeriod = 'Mixed';
                    } else {
                        if ($item->CategoryName != $receipt->Category && $receipt->Category != 'Mixed') {
                            $receipt->Category = 'Mixed';
                        }

                        if ($receipt->ExpensePeriod != 'Mixed') {
                            if (!empty($item->Reference)) {
                                if ($item->Reference != $receipt->ExpensePeriod && $receipt->ExpensePeriod != 'Mixed') {
                                    $receipt->ExpensePeriod = 'Mixed';
                                }
                            } else {
                                if (empty($item->CategoryID) && !empty($receipt->ExpensePeriod)) {
                                    $receipt->ExpensePeriod = 'Mixed';
                                } else if (!empty($item->CategoryID) && date('M-Y', strtotime($item->ExpensePeriod)) != $receipt->ExpensePeriod) {
                                    $receipt->ExpensePeriod = 'Mixed';
                                }
                            }
                        }
                    }
                    if (($tempExpensePeriod != $item->ExpensePeriod) || ($item->CategoryApp == "travel_expense")) {
                        $theSameExpensePeriod = false;
                    }
                    if ($theSameExpensePeriod == true) {
                        $receipt->ExpensePeriod = date('M-Y', strtotime($tempExpensePeriod));
                    }
                }
            }
        }

        return $receipt;
    }

    public static function getReceiptImageAndRawData($itemID) {
        return DB::table('Receipt AS r')
                        ->join('Item AS i', 'i.ReceiptID', '=', 'r.ReceiptID')
                        ->leftJoin('File AS f', function($join) {
                            $join->on('f.EntityID', '=', 'r.ReceiptID')->on('f.EntityName', '=', DB::raw('"receipt_image"'));
                        })
                        ->select('FileID', 'FileBucket', 'FilePath', 'EntityID', 'FileName', 'i.Name AS ItemName', 'ReceiptType', 'RawData')
                        ->where('i.ItemID', $itemID)
                        ->first();
    }

    public static function createReceiptImageUrl($filePath) {
        if (!self::$s3) {
            self::$s3 = App::make('aws')->get('s3');
        }

        $request = self::$s3->get($filePath);
        
        return self::$s3->createPresignedUrl($request, '+ 1 hour');
    }

    public static function getReceiptsBeArchive($dateConfig = 90, $arrUsers = array()) {
        //var_dump(time());die;
        $entryDate = time() - $dateConfig * 24 * 60 * 60;
        return DB::table('Receipt')
                        ->where('CreatedTime', '<', $entryDate)
                        ->whereIn('UserId', $arrUsers)
                        ->orderBy('UserId', 'asc')
                        ->get();
    }

    public static function getReceiptsByUser($dateConfig = 90) {
        $entryDate = time() - $dateConfig * 24 * 60 * 60;
        //var_dump($entryDate);die;
        return DB::table('Receipt as R')
                        ->Join('Settings AS S', function($join) {
                            $join->on('R.UserID', '=', 'S.UserID');
                        })
                        ->select('R.ReceiptID')
                        ->where('R.CreatedTime', '<', $entryDate)
                        ->where('R.IsArchived', 0)
                        ->where('S.AutoArchive', $dateConfig)
                        ->get();
        //->toSql();
        //var_dump($a);die;
    }

    public static function updateArchiveReceipt($ReceiptIDs = array()) {
        DB::table('Receipt')
                ->whereIn('ReceiptID', $ReceiptIDs)
                ->update(array(
                    'IsArchived' => 1));
    }

    public static function onPreQuery(\Illuminate\Database\Query\Builder $query, &$where = null) {
        $userID = $where['UserID'];

        $query->leftJoin('Merchant AS m', 'm.MerchantID', '=', 'r.merchantID')
                ->leftJoin('File AS f', function($join) {
                    $join->on('f.EntityID', '=', 'r.ReceiptID')->on('f.EntityName', '=', DB::raw('"receipt_image"'));
                })
                ->leftJoin('ReceiptOriginal AS ro', 'ro.ReceiptID', '=', 'r.ReceiptID')
                ->select('r.*', 'm.Logo AS MerchantLogo', 'f.FileID', 'f.FilePath', 'f.FileName', 'ro.ReceiptData', 'ro.ReceiptItemData', 'ro.Amount AS OriginalAmount', 'ro.ItemAmount AS OriginalItemAmount');

        if ($where != null) {
            if (isset($where['noApp'])) {
                if ($where['noApp'] == '1') {
                    $tempQuery = DB::table(static::$_table . ' as r');
                    $tempQuery->leftJoin('Item AS it', 'r.ReceiptID', '=', 'it.ReceiptID')
                            ->leftJoin('Category AS ca', 'ca.CategoryID', '=', 'it.CategoryID')
                            ->where(function($tempQuery) {
                                $tempQuery->where('ca.App', 'personal_expense');
                            });
                    $tempQuery->groupBy('r.ReceiptID');
                    $tempQuery->where('r.UserID', $userID);
                    $tempQuery->select('r.ReceiptID');
                    $receiptPersonalExpenseIDs = $tempQuery->lists('r.ReceiptID');

                    if (!empty($receiptPersonalExpenseIDs)) {
                        $query->whereNotIn('r.ReceiptID', $receiptPersonalExpenseIDs);
                    }
                }
                unset($where['noApp']);
            }
        }

        if ($where != null) {
            //tIds <=> arrayTripID
            if (isset($where['tIds'])) {
                /* GET ITEM FROM TRIP */
                $itemWhere['tIds'] = $where['tIds'];
                $item = TripItem::getAll($itemWhere, array(), '', 0);
                $arrayItemID = array();
                if (count($item)) {
                    foreach ($item as $id => $object) {
                        foreach ($object as $key => $value) {
                            if ($key == 'ItemID') {
                                $arrayItemID[] = $value;
                            }
                        }
                    }
                }
                $where['arrayItemID'] = $arrayItemID;
                unset($where['UserID']);
                unset($where['tIds']);
            }
        }

        if ($where != null) {
            if (isset($where['arrayItemID'])) {
                if (count($where['arrayItemID'])) {
                    $query->leftJoin('Item AS it', 'r.ReceiptID', '=', 'it.ReceiptID');
                    $query->distinct();
                    $query->whereIn('it.ItemID', $where['arrayItemID']);
                } else {
                    $query->leftJoin('Item AS it', 'r.ReceiptID', '=', 'it.ReceiptID');
                    $query->distinct();
                    $query->where('it.ItemID', 0);
                }
                unset($where['arrayItemID']);
            }
        }

        if ($where != null) {
            // receipts?:: nRIds <=> arrayNotReceiptID
            if (isset($where['nRIds'])) {
                $query->whereNotIn('r.ReceiptID', $where['nRIds']);
                unset($where['nRIds']);
            }

            if (isset($where['ReceiptFree'])) {
                if ($where['ReceiptFree'] == '1') {
                    $tempQuery = DB::table(static::$_table . ' as r');
                    $tempQuery->leftJoin('Item AS it', 'r.ReceiptID', '=', 'it.ReceiptID')
                            ->leftJoin('TripItem AS ti', 'it.ItemID', '=', 'ti.TripItemID')
                            ->havingRaw(DB::raw('COUNT(ti.TripID) > 1'));
                    $tempQuery->groupBy('r.ReceiptID');
                    $tempQuery->where('r.UserID', $userID);
                    $tempQuery->select('r.ReceiptID');
                    $receiptHaveTrips = $tempQuery->lists('r.ReceiptID');

                    if (!empty($receiptHaveTrips)) {
                        $query->whereNotIn('r.ReceiptID', $receiptHaveTrips);
                    }
                }
                unset($where['ReceiptFree']);
            }
        }
    }

    public static function getAll(array $where = array(), array $sort = array(), $limit = 10, $offset = 0) {
        $receipts = parent::getAll($where, $sort, $limit, $offset);

        $receiptIDList = array();
        foreach ($receipts as $receipt) {
            $receiptIDList[] = $receipt->ReceiptID;
        }

        //We only query to get items for 1 time, then use a temp array to add items to the right receipt
        $tmpItems = array();
        if (count($receiptIDList)) {
            //Get receipt items of receipts in the list
            $itemList = Item::getListOfReceipts($receiptIDList, true);
            //Add from query result to temp array
            if (count($itemList)) {
                foreach ($itemList as $item) {
                    $tmpItems[$item->ReceiptID][] = $item;
                }
            }
        }

        //We only query to get attachments for 1 time, then use a temp array to add attachments to the right receipt
        $tmpAttachments = array();
        if (count($receiptIDList)) {
            $attachmentList = File::getListByEntities($receiptIDList);
            //Add from query result to temp array
            if (count($attachmentList)) {
                foreach ($attachmentList as $attachment) {
                    $tmpAttachments[$attachment->EntityID][] = $attachment;
                }
            }
        }

        //We only query to get attachments for 1 time, then use a temp array to add tags to the right receipt
        $tmpTags = array();
        if (count($receiptIDList)) {
            $tagList = Tag::getList($receiptIDList);
            //Add from query result to temp array
            if (count($tagList)) {
                foreach ($tagList as $tag) {
                    $tmpTags[$tag->EntityID][] = $tag->Name;
                }
            }
        }

        //$newReceipts = array();
        //$oldReceipts = array();
        //Add from temp array to receipt + format receipt data before returning it
        if (count($receipts)) {
            foreach ($receipts as $receipt) {
                $receipt->OriginalTotal = number_format($receipt->OriginalTotal, 2, '.', '');

                $receipt->More = new stdClass();
                $receipt->More->Memo = $receipt->Memo;
                $receipt->More->ItemCount = $receipt->ItemCount;
                $receipt->More->MerchantReview = $receipt->MerchantReview;

                if (empty($receipt->DigitalTotal)) {
                    $receipt->DigitalTotal = $receipt->OriginalTotal;
                } else {
                    $receipt->DigitalTotal = number_format($receipt->DigitalTotal, 2, '.', '');
                }

                $receipt->PurchaseTime = date('Y-m-d\TH:i:s.B\Z', $receipt->PurchaseTime);
                $receipt->CreatedTime = $receipt->CreatedTime;

                if (isset($filter['timestamp'])) {
                    if ($receipt->CreatedTime > $filter['timestamp']) {
                        $receipt->SyncType = 0;
                    } else if ($receipt->CreatedTime > $filter['timestamp']) {
                        $receipt->SyncType = 1;
                    }
                }

                if (isset($tmpItems[$receipt->ReceiptID])) {
                    $receipt->Items = $tmpItems[$receipt->ReceiptID];
                } else {
                    $receipt->Items = array();
                }

                if (isset($tmpAttachments[$receipt->ReceiptID])) {
                    $receipt->Attachments = $tmpAttachments[$receipt->ReceiptID];
                } else {
                    $receipt->Attachments = array();
                }

                if (isset($tmpTags[$receipt->ReceiptID])) {
                    $receipt->More->Tags = $tmpTags[$receipt->ReceiptID];
                } else {
                    $receipt->More->Tags = array();
                }

                if (!$receipt->More->Memo && !$receipt->More->ItemCount && !$receipt->More->MerchantReview && !count($receipt->More->Tags)) {
                    $receipt->More->IsEmpty = true;
                } else {
                    $receipt->More->IsEmpty = false;
                }

                $receipt = self::setCategorizedState($receipt);

                //Initialize the boolean isChecked for receipt
                $receipt->IsChecked = false;
                $receipt->IsCollapsed = true;

                //Initialize the array of deleted files
                $receipt->DeletedFileIDs = array();

                if (!$receipt->IsOpened) {
                    $receipt->VerifyStatus = 0;
                    //$newReceipts[] = $receipt;
                } else {
                    //$oldReceipts[] = $receipt;
                }

                // set merchant logo for mobile
                if (!empty($receipt->MerchantLogo)) {
                    $split = explode('/', $receipt->MerchantLogo);
                    $merchantLogoName = end($split);
                    $link = str_replace($merchantLogoName, "mobile/" . $merchantLogoName, $receipt->MerchantLogo);
                    $receipt->MerchantLogo = $link;
                }

                // set receipt_image
                $receiptImage = DB::table('File')
                        ->select('FileID', 'FileBucket' , 'FilePath')
                        ->where('EntityID', $receipt->ReceiptID)
                        ->where('EntityName', 'receipt_image')
                        ->first();

                if(isset($receiptImage)) {
                    $filePathReceipt = $receiptImage->FileBucket . '/' . $receiptImage->FilePath;
//                    if (!$receiptImage || strpos($filePathReceipt, 'receipts/') !== false || strpos($filePathReceipt, 'attachments/') !== false) {
//                        $receipt->ReceiptImage = '';
//                    } else {
                        $receipt->ReceiptImage = File::getS3PreSignedUrl($receiptImage->FileBucket, $receiptImage->FilePath);
//                    }
                }                
            }
        }

        unset($receipts->Items);        
        return $receipts;        
    }

    public static function getById($receiptId) {
        $receipt = parent::getById($receiptId);

        if ($receipt) {
            $receiptIDList = array();
            $receiptIDList[] = $receipt->ReceiptID;

            //We only query to get items for 1 time, then use a temp array to add items to the right receipt
            $tmpItems = array();
            if (count($receiptIDList)) {
                //Get receipt items of receipts in the list
                $itemList = Item::getListOfReceipts($receiptIDList, true);
                //Add from query result to temp array
                if (count($itemList)) {
                    foreach ($itemList as $item) {
                        $tmpItems[$item->ReceiptID][] = $item;
                    }
                }
            }

            //We only query to get attachments for 1 time, then use a temp array to add attachments to the right receipt
            $tmpAttachments = array();
            if (count($receiptIDList)) {
                $attachmentList = File::getListByEntities($receiptIDList);
                //Add from query result to temp array
                if (count($attachmentList)) {
                    foreach ($attachmentList as $attachment) {
                        $tmpAttachments[$attachment->EntityID][] = $attachment;
                    }
                }
            }

            //We only query to get attachments for 1 time, then use a temp array to add tags to the right receipt
            $tmpTags = array();
            if (count($receiptIDList)) {
                $tagList = Tag::getList($receiptIDList);
                //Add from query result to temp array
                if (count($tagList)) {
                    foreach ($tagList as $tag) {
                        $tmpTags[$tag->EntityID][] = $tag->Name;
                    }
                }
            }

            $receipt->OriginalTotal = number_format($receipt->OriginalTotal, 2, '.', '');
            $receipt->More = new stdClass();
            $receipt->More->Memo = $receipt->Memo;
            $receipt->More->ItemCount = $receipt->ItemCount;
            $receipt->More->MerchantReview = $receipt->MerchantReview;

            if (empty($receipt->DigitalTotal)) {
                $receipt->DigitalTotal = $receipt->OriginalTotal;
            } else {
                $receipt->DigitalTotal = number_format($receipt->DigitalTotal, 2, '.', '');
            }

            $receipt->PurchaseTime = date('Y-m-d\TH:i:s.B\Z', $receipt->PurchaseTime);
            $receipt->CreatedTime = date('Y-m-d\TH:i:s.B\Z',$receipt->CreatedTime);

            if (isset($filter['timestamp'])) {
                if ($receipt->CreatedTime > $filter['timestamp']) {
                    $receipt->SyncType = 0;
                } else if ($receipt->CreatedTime > $filter['timestamp']) {
                    $receipt->SyncType = 1;
                }
            }

            if (isset($tmpItems[$receipt->ReceiptID])) {
                $receipt->Items = $tmpItems[$receipt->ReceiptID];
            } else {
                $receipt->Items = array();
            }

            if (isset($tmpAttachments[$receipt->ReceiptID])) {
                $receipt->Attachments = $tmpAttachments[$receipt->ReceiptID];
            } else {
                $receipt->Attachments = array();
            }

            if (isset($tmpTags[$receipt->ReceiptID])) {
                $receipt->More->Tags = $tmpTags[$receipt->ReceiptID];
            } else {
                $receipt->More->Tags = array();
            }

            if (!$receipt->More->Memo && !$receipt->More->ItemCount && !$receipt->More->MerchantReview && !count($receipt->More->Tags)) {
                $receipt->More->IsEmpty = true;
            } else {
                $receipt->More->IsEmpty = false;
            }

            $receipt = self::setCategorizedState($receipt);

            //Initialize the boolean isChecked for receipt
            $receipt->IsChecked = false;
            $receipt->IsCollapsed = true;

            //Initialize the array of deleted files
            $receipt->DeletedFileIDs = array();

            if (!$receipt->IsOpened) {
                $receipt->VerifyStatus = 0;
                //$newReceipts[] = $receipt;
            } else {
                //$oldReceipts[] = $receipt;
            }

            // set merchant logo for mobile
            if (!empty($receipt->MerchantLogo)) {
                $split = explode('/', $receipt->MerchantLogo);
                $merchantLogoName = end($split);
                $link = str_replace($merchantLogoName, "mobile/" . $merchantLogoName, $receipt->MerchantLogo);
                $receipt->MerchantLogo = $link;
            }

            // set receipt_image
            $receiptImage = DB::table('File')
                    ->select('FileID', 'FileBucket', 'FilePath')
                    ->where('EntityID', $receipt->ReceiptID)
                    ->where('EntityName', 'receipt_image')
                    ->first();

            $receiptUrl = $receiptImage->FileBucket . '/' . $receiptImage->FilePath;
            
//            if (!$receiptImage || strpos($receiptUrl, 'receipts/') !== false || strpos($receiptUrl, 'attachments/') !== false) {
//                $receipt->ReceiptImage = '';
//            } else {
                $receipt->ReceiptImage = File::getS3PreSignedUrl($receiptImage->FileBucket, $receiptImage->FilePath);
//            }
        }

        return $receipt;
    }

    public static function processUpdate($put, $user, $receipt) {
        if (isset($put['MerchantID'])) {
            $receipt->MerchantID = $put['MerchantID'];
            $merchant = Merchant::find($put['MerchantID']);

            if (!empty($merchant->Name)) {
                $receipt->MerchantName = $merchant->Name;
            }
            if (!empty($merchant->PhoneNumber)) {
                $receipt->MerchantPhone = $merchant->PhoneNumber;
            }
            if (!empty($merchant->Address)) {
                $receipt->MerchantAddress = $merchant->Address;
            }
            if (!empty($merchant->CountryCode)) {
                $receipt->MerchantCountry = $merchant->CountryCode;
            }
            if (!empty($merchant->City)) {
                $receipt->MerchantCity = $merchant->City;
            }
            if (!empty($merchant->State)) {
                $receipt->MerchantState = $merchant->State;
            }
            if (!empty($merchant->ZipCode)) {
                $receipt->MerchantCode = $merchant->ZipCode;
            }
        }

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
        }

        if (isset($put['ExchangeRate']) && $put['ExchangeRate'] != $receipt->ExchangeRate) {
            $receipt->ExchangeRate = (float) $put['ExchangeRate'];
        }

        if (isset($put['CurrencyCode']) && $put['CurrencyCode'] != $receipt->CurrencyCode) {
            $receipt->CurrencyCode = $put['CurrencyCode'];
        }

        $homeCurrency = Profile::find($user->UserID)->CurrencyCode;
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

        if (!$receipt->IsOpened) {
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
            if (!empty($put['ReceiptImage']['FileID'])) {
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
        $originalItems = array();
        if (isset($put['Items'])) {
            if (count($put['Items'])) {
                $originalItems = array();
                foreach ($put['Items'] as $key => $putItem) {
                    if (!isset($putItem['Name']) || !isset($putItem['Amount'])) {
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
                        if (!$oldTripID)
                            $oldTripID = 0;

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

                        if (isset($putItem['CategoryApp'])) {
                            if ($putItem['CategoryApp'] == 'travel_expense' && $putItem['TripID']) {
                                $item->ExpensePeriodFrom = DB::table('Trip')
                                                ->select('StartDate')->where('TripID', $putItem['TripID'])->pluck('StartDate');

                                $updateTripItemRelationship = true;
                                $refreshTripList = true;
                            } else if ($putItem['CategoryApp'] != 'travel_expense' && !empty($putItem['CategoryApp']) && !empty($putItem['ExpensePeriod'])) {
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
                        if (!isset($putItem['TripID'])) {
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

                            if (!$item->IsJoined && $item->ExpensePeriodFrom && $item->CategoryID) {
                                CategoryAmount::updateAmount($receipt->UserID, $item->CategoryID, $item->Amount, $item->ExpensePeriodFrom, 'plus', $tripID);
                            }
                        } else if (!$item->IsJoined) {
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

                        $item->ModifiedTime = $_SERVER['REQUEST_TIME'];
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

                                if (!$item->IsJoined && $item->CategoryID) {
                                    CategoryAmount::updateAmount($receipt->UserID, $item->CategoryID, $item->Amount, $item->ExpensePeriodFrom, 'plus', $putItem['TripID']);
                                }

                                $addTripItemRelationship = true;
                                $refreshTripList = true;
                            } else if ($putItem['CategoryApp'] != 'travel_expense' && !empty($putItem['CategoryApp']) && !empty($putItem['ExpensePeriod'])) {
                                $item->ExpensePeriodFrom = strtotime(date('01-M-Y', strtotime($putItem['ExpensePeriod'])));

                                if (!$item->IsJoined && $item->ExpensePeriodFrom && $item->CategoryID) {
                                    CategoryAmount::updateAmount($receipt->UserID, $item->CategoryID, $item->Amount, $item->ExpensePeriodFrom);
                                }
                            }
                        }

                        if (isset($putItem['Memo'])) {
                            $item->Memo = $putItem['Memo'];
                        }

                        $item->CreatedTime = $_SERVER['REQUEST_TIME'];
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
                        if (!empty($item->ExpensePeriodFrom)) {
                            $_originalItem['ExpensePeriod'] = date('Y-m-d\TH:i:s.B\Z', $item->ExpensePeriodFrom);
                        } else {
                            $_originalItem['ExpensePeriod'] = null;
                        }
                    }
                    $originalItems[] = $_originalItem;

                    //Save tags here
                    if (isset($putItem['Tags'])) {
                        if (isset($putItem['ItemID'])) {
                            Tag::saveTags($item->ItemID, 'receipt_item', explode(',', $putItem['Tags']), Tag::getList($putItem['ItemID'], 'receipt_item', true));
                        } else {
                            Tag::saveTags($item->ItemID, 'receipt_item', explode(',', $putItem['Tags']), array());
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
        }

        //Delete items that was chosen to be deleted
        if (isset($put['DeletedItems'])) {
            if (count($put['DeletedItems'])) {
                foreach ($put['DeletedItems'] as $key => $deletedItems) {
                    if (!$deletedItems) {
                        unset($put['DeletedItems'][$key]);
                    }
                }
                Item::deleteList($put['DeletedItems'], $receipt->UserID);
            }
        }

        $receiptOriginal = ReceiptOriginal::find($receipt->ReceiptID);
        if ($updateOriginal) {
            if (!$receiptOriginal) {
                $receiptOriginal = new ReceiptOriginal();
                $receiptOriginal->ReceiptID = $receipt->ReceiptID;
            }
            $receiptOriginal->ReceiptData = json_encode($receipt['attributes']);
            $receiptOriginal->ReceiptItemData = json_encode($originalItems);
            $receiptOriginal->save();
        }
    }

    public static function processStore($post, $user) {
        $receipt = new Receipt();

        if (isset($post['MerchantID'])) {
            $receipt->MerchantID = $post['MerchantID'];
            $merchant = Merchant::find($post['MerchantID']);

            if (!empty($merchant->Name)) {
                $receipt->MerchantName = $merchant->Name;
            }
            if (!empty($merchant->PhoneNumber)) {
                $receipt->MerchantPhone = $merchant->PhoneNumber;
            }
            if (!empty($merchant->Address)) {
                $receipt->MerchantAddress = $merchant->Address;
            }
            if (!empty($merchant->CountryCode)) {
                $receipt->MerchantCountry = $merchant->CountryCode;
            }
            if (!empty($merchant->City)) {
                $receipt->MerchantCity = $merchant->City;
            }
            if (!empty($merchant->State)) {
                $receipt->MerchantState = $merchant->State;
            }
            if (!empty($merchant->ZipCode)) {
                $receipt->MerchantCode = $merchant->ZipCode;
            }
        }

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

        if (isset($post['CreatedTime'])) {
            $receipt->CreatedTime = $post['CreatedTime'];
        } else {
            $receipt->CreatedTime = round(microtime(true) * 1000);
        }
        $receipt->UserID = $user->UserID;
        $receipt->IsNew = 0;

        if (isset($post['MobileSync']) && $post['MobileSync']) {
            $receipt->MobileSync = $post['MobileSync'];
        }
        
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
            if (!empty($post['ReceiptImage']['FileID'])) {
                File::updateList($post['ReceiptImage']['FileID'], array(
                    'Permanent' => 1,
                    'EntityID' => $receipt->ReceiptID,
                ));
            }
        }

        if (isset($post['DeletedFileIDs']) && count($post['DeletedFileIDs'])) {
            File::deleteList(File::getList($post['DeletedFileIDs']));
        }

        $originalItems = array();
        $originalItemAmount = array();
        if (isset($post['Items']) && count($post['Items'])) {
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

                        if (!$item->IsJoined && $item->CategoryID) {
                            CategoryAmount::updateAmount($receipt->UserID, $item->CategoryID, $item->Amount, $item->ExpensePeriodFrom, 'plus', $postItem['TripID']);
                        }

                        $addTripItemRelationship = true;
                    } else if ($postItem['CategoryApp'] != 'travel_expense' && !empty($postItem['CategoryApp']) && !empty($postItem['ExpensePeriod'])) {
                        if (strpos($postItem['ExpensePeriod'], 'T') !== false) {
                            $postItem['ExpensePeriod'] = substr(str_replace('T', ' ', $postItem['ExpensePeriod']), 0, -5);
                        }

                        $item->ExpensePeriodFrom = strtotime(date('01-M-Y', strtotime($postItem['ExpensePeriod'])));

                        if (!$item->IsJoined && $item->ExpensePeriodFrom && $item->CategoryID) {
                            CategoryAmount::updateAmount($receipt->UserID, $item->CategoryID, $item->Amount, $item->ExpensePeriodFrom);
                        }
                    }
                }

                $item->CreatedTime = $_SERVER['REQUEST_TIME'];
                $item->save();

                $originalItems[] = $item['attributes'];

                $itemAmount = new stdClass();
                $itemAmount->ItemID = $item->ItemID;
                $itemAmount->Amount = $item->Amount;
                $originalItemAmount[] = $itemAmount;

                if ($addTripItemRelationship) {
                    Item::addTripItemRecord($item->ItemID, $postItem['Amount'], $postItem['IsJoined'], $postItem['TripID']);
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
            }
        }

        $receiptOriginal = new ReceiptOriginal();
        $receiptOriginal->ReceiptID = $receipt->ReceiptID;
        $receiptOriginal->ReceiptData = json_encode($receipt['attributes']);
        $receiptOriginal->ReceiptItemData = json_encode($originalItems);
        $receiptOriginal->Amount = json_encode($originalAmount);
        $receiptOriginal->ItemAmount = json_encode($originalItemAmount);
        $receiptOriginal->save();

        $createdReceipt = Receipt::getById($receipt->ReceiptID);

        return $createdReceipt;
    }

    /**
     * Use this method to build vaidator for both creating and updating receipt
     */
    public static function validateModel($inputs, $user, $receipt = null) {
        $rules = array(
            'MerchantID' => array('required', 'exists:Merchant'),
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
            'ItemCount' => array('integer')
        );

        if ($receipt != null) {
            foreach ($rules as $key => $value) {
                if (!isset($inputs[$key])) {
                    unset($rules[$key]);
                }
            }
        }
        if ($receipt != null) {
            if (isset($receipt['ReceiptID']) && $receipt['ReceiptID'] && $user->UserID) {
                $inputs['ReceiptID'] = $receipt['ReceiptID'];
                $rules['ReceiptID'] = array();
            }
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

        if (isset($inputs['Items'])) {
            //Validate input for receipt items
            if (count($inputs['Items'])) {
                foreach ($inputs['Items'] as $key => $item) {
                    $rules = array(
                        'Name' => array('max:255'),
                        'Amount' => array('numeric'),
                        'CategoryID' => array('integer'),
                        'Memo' => array('max:1000'),
                    );

                    if ($user->UserID) {
                        $rules['TripID'] = array('trips_belong_to:' . $user->UserID);
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
                    ));

                    if ($validator->fails()) {
                        return array(
                            'message' => $validator->messages()->all(),
                            'itemRow' => $key,
                        );
                    }
                }
            }
        }
        return array();
    }

    public static function processDestroy($receiptIDs, $user) {
        if (!is_array($receiptIDs)) {
            $receiptIDs = array($receiptIDs);
        }

        File::deleteList(File::getAttachmentListOfReceipts($receiptIDs));

        // Get path of all attachments of items to delete them physically
        File::deleteList(File::getItemAttachmentListByReceipts($receiptIDs));

        // Delete all specified receipts themselves, also delete original version if it exists
        Receipt::deleteList($receiptIDs);

        // Update category amount before deleting all items
        CategoryAmount::updateAmountByReceipts($receiptIDs, $user->UserID);

        // Delete all tag index of receipts and receipt items
        Tag::deleteIndexList($receiptIDs, 'receipt');
        $itemIDs = Item::getItemIDsOfReceipts($receiptIDs);
        if (count($itemIDs)) {
            Tag::deleteIndexList($itemIDs, 'receipt_item');

            //Get relationships between items, trips and reports
            $itemTripReportRelationships = Item::checkItemTripReportRelationships($itemIDs);
            if ($itemTripReportRelationships === 2) {

            } else if ($itemTripReportRelationships === 1) {

            }

            //Delete all relationships between receipt items and trips
            Item::deleteItemTripRelationship($itemIDs);
        }

        // Delete all items of specified receipts
        Item::deleteListByReceipts($receiptIDs);
    }

    public static function validateDestroy($receiptIDs, $user) {
        if (!is_array($receiptIDs)) {
            $receiptIDs = array($receiptIDs);
        }

        //Validate to be sure that all specified receipts belongs to the user who send this request
        $messages = array('ReceiptIDs.required' => 'You need to specify at least one receipt.');
        if (count($receiptIDs) === 1) {
            $messages['ReceiptIDs.receipts_for_submitted_report'] = 'This receipt is reported. You can not delete it.';
        }
        $validator = Validator::make(
                        array('ReceiptIDs' => $receiptIDs), array('ReceiptIDs' => array('required', 'receipts_belong_to:' . $user->UserID)), $messages
        );

        if ($validator->fails()) {
            return array('message' => $validator->messages()->all());
        }

        return array();
    }

    public static function countAllKind($user, $receiptType) {
        $result = array();
        // Count All Receipts 
        $query = DB::table(static::$_table . ' as r');
        $query->where('r.UserID', $user->UserID);
        $result[0]['name'] = 'All Receipts';
        $result[0]['new'] = 0;
        $result[0]['total'] = 0;

        // Count New Digital Receipts
        $query = DB::table(static::$_table . ' as r');
        $query->where('r.UserID', $user->UserID);
        $query->where('r.IsNew', 1);
        $query->where('r.ReceiptType', 1);
        $newDigitalReceipt = $query->count();

        // Count New Email Receipts
        $query = DB::table(static::$_table . ' as r');
        $query->where('r.UserID', $user->UserID);
        $query->where('r.IsNew', 1);
        $query->where('r.ReceiptType', 2);
        $newEmailReceipt = $query->count();

        // Count New Paper Receipts
        $query = DB::table(static::$_table . ' as r');
        $query->where('r.UserID', $user->UserID);
        $query->where('r.IsNew', 1);
        $query->where('r.ReceiptType', 3);
        $newPaperReceipt = $query->count();

        // Count New Manual Receipts
        $query = DB::table(static::$_table . ' as r');
        $query->where('r.UserID', $user->UserID);
        $query->where('r.IsNew', 1);
        $query->where('r.ReceiptType', 4);
        $newManualReceipt = $query->count();

        // Count New Non-Receipt
        $query = DB::table(static::$_table . ' as r');
        $query->where('r.UserID', $user->UserID);
        $query->where('r.IsNew', 1);
        $query->where('r.ReceiptType', 5);
        $newNonReceipt = $query->count();

        // Count New Paper Invoice
        $query = DB::table(static::$_table . ' as r');
        $query->where('r.UserID', $user->UserID);
        $query->where('r.IsNew', 1);
        $query->where('r.ReceiptType', 6);
        $newPaperInvoice = $query->count();

        // Count New Electronic Invoice
        $query = DB::table(static::$_table . ' as r');
        $query->where('r.UserID', $user->UserID);
        $query->where('r.IsNew', 1);
        $query->where('r.ReceiptType', 7);
        $newElectronicInvoice = $query->count();

        if (count($receiptType)) {
            foreach ($receiptType as $index => $type) {
                switch ($type) {
                    case '1':
                        $name = 'Digital Receipts';
                        $new = $newDigitalReceipt;
                        break;
                    case '2':
                        $name = 'Email Receipts';
                        $new = $newEmailReceipt;
                        break;
                    case '3':
                        $name = 'Paper Receipts';
                        $new = $newPaperReceipt;
                        break;
                    case '4':
                        $name = 'Manual Receipts';
                        $new = $newManualReceipt;
                        break;
                    case '5':
                        $name = 'Non-Receipt';
                        $new = $newNonReceipt;
                        break;
                    case '6':
                        $name = 'Paper Invoice';
                        $new = $newPaperInvoice;
                        break;
                    case '7':
                        $name = 'Electronic Invoice';
                        $new = $newElectronicInvoice;
                        break;
                }

                $query = DB::table(static::$_table . ' as r');
                $query->where('r.UserID', $user->UserID);
                $query->where('r.ReceiptType', $type);
                $result[$index + 1]['name'] = $name;
                $result[$index + 1]['new'] = $new;
                $result[$index + 1]['total'] = $query->count();

                // Count All Receipts
                $result[0]['name'] = 'All Receipts';
                $result[0]['new'] = $result[0]['new'] + $result[$index + 1]['new'];
                $result[0]['total'] = $result[0]['total'] + $result[$index + 1]['total'];
            }
        } else {
            // Count Digital Receipts
            $query = DB::table(static::$_table . ' as r');
            $query->where('r.UserID', $user->UserID);
            $query->where('r.ReceiptType', 1);
            $result[1]['name'] = 'Digital Receipts';
            $result[1]['new'] = $newDigitalReceipt;
            $result[1]['total'] = $query->count();

            // Count Email Receipts
            $query = DB::table(static::$_table . ' as r');
            $query->where('r.UserID', $user->UserID);
            $query->where('r.ReceiptType', 2);
            $result[2]['name'] = 'Email Receipts';
            $result[2]['new'] = $newEmailReceipt;
            $result[2]['total'] = $query->count();

            // Count Paper Receipts
            $query = DB::table(static::$_table . ' as r');
            $query->where('r.UserID', $user->UserID);
            $query->where('r.ReceiptType', 3);
            $result[3]['name'] = 'Paper Receipts';
            $result[3]['new'] = $newPaperReceipt;
            $result[3]['total'] = $query->count();

            // Count Manual Receipts
            $query = DB::table(static::$_table . ' as r');
            $query->where('r.UserID', $user->UserID);
            $query->where('r.ReceiptType', 4);
            $result[4]['name'] = 'Manual Receipts';
            $result[4]['new'] = $newManualReceipt;
            $result[4]['total'] = $query->count();

            // Count Non-Receipt
            $query = DB::table(static::$_table . ' as r');
            $query->where('r.UserID', $user->UserID);
            $query->where('r.ReceiptType', 5);
            $result[5]['name'] = 'Non-Receipt';
            $result[5]['new'] = $newNonReceipt;
            $result[5]['total'] = $query->count();

            // Count Paper Invoice
            $query = DB::table(static::$_table . ' as r');
            $query->where('r.UserID', $user->UserID);
            $query->where('r.ReceiptType', 6);
            $result[6]['name'] = 'Paper Invoice';
            $result[6]['new'] = $newPaperInvoice;
            $result[6]['total'] = $query->count();

            // Count Electronic Invoice
            $query = DB::table(static::$_table . ' as r');
            $query->where('r.UserID', $user->UserID);
            $query->where('r.ReceiptType', 7);
            $result[7]['name'] = 'Electronic Invoice';
            $result[7]['new'] = $newElectronicInvoice;
            $result[7]['total'] = $query->count();

            // Count All Receipts
            $result[0]['name'] = 'All Receipts';
            $result[0]['new'] = $result[0]['new'] + $result[1]['new'] + $result[2]['new'] + $result[3]['new'] + $result[4]['new'] + $result[5]['new'] + $result[6]['new'] + $result[7]['new'];
            $result[0]['total'] = $result[0]['total'] + $result[1]['total'] + $result[2]['total'] + $result[3]['total'] + $result[4]['total'] + $result[5]['total'] + $result[6]['total'] + $result[7]['total'];
        }
        return $result;
    }

    /**
     * Return Ids list of receipts which contain given items
     *
     * @param   array    $itemIds   List ids of items
     * @return  array               List of ids of receipts
     */
    public static function queryAllContainItems ($itemIds)
    {
        $rids = array();

        if (is_array($itemIds)) {
            $rids = DB::table('Item')
                ->whereIn('ItemID', $itemIds)
                ->groupBy('ReceiptID')
                ->lists('ReceiptID');
        }

        return $rids;
    }
}
