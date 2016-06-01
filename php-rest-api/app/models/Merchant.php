<?php
class Merchant extends BaseModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'Merchant';

    protected static $_table = 'Merchant';
    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'MerchantID';

    protected static $_primaryKey = 'MerchantID';
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;
    
    /**
     * Constant variable for unrecorgnized receipt type
     */
    const NRMcName = 'Receipt Unrecognized';
    const UnMcName = 'Merchant Unrecognized';
	
    public static function getList()
    {
        return DB::table('Merchant')
            ->select('Name')
            ->lists('Name');
    }
	
    public static function getAutoCombineList()
    {
        return DB::table('Merchant')
            ->select('Name')
            ->where('UserID', 0)
            ->where('Searchable', 1)
            ->lists('Name');
    }
    
    public static function getAutoCompleteList($uid)
    {
        return DB::table('Merchant')
            ->where('UserID', $uid)
            ->orwhere('UserID', 0)
            ->where('Searchable', 1)
            ->get();
    }
	
    public static function getAnalyticsList($app, $userID, $dateFrom, $dateTo = null)
    {
        $merchantQuery = DB::table('Item AS i')
                ->select('r.MerchantID', 'm.Name AS MerchantName', DB::raw('SUM(i.Amount) AS Amount'))
                ->join('Receipt AS r', 'r.ReceiptID', '=', 'i.ReceiptID')
                ->join('Category AS c', 'c.CategoryID', '=', 'i.CategoryID')
                ->join('Merchant AS m', 'm.MerchantID', '=', 'r.MerchantID')
                ->where('App', $app)
                ->where('r.UserID', $userID)
                ->where('ExpensePeriodFrom', '>=', $dateFrom);

        if ($dateTo) {
            $merchantQuery->where('ExpensePeriodFrom', '<=', $dateTo);
        }

        $merchants = $merchantQuery->groupBy('r.MerchantID')->get();
        $months = 0;
        if ($dateTo) {
            $months = ((date('Y', $dateTo) - date('Y', $dateFrom)) * 12) + (date('n', $dateTo) - date('n', $dateFrom));
        }
        $totalAmount = 0;
        foreach ($merchants as $merchant) {
            if (! $merchant->Amount) {
                    $merchant->Amount = 0;
            }
            $totalAmount = $totalAmount + $merchant->Amount;
            if ($months) {
                    $merchant->AverageAmount = number_format($merchant->Amount / ($months+1), 2, '.', '');
            } else {
                    $merchant->AverageAmount = number_format($merchant->Amount, 2, '.', '');
            }
        }

        return array(
            'Months' => $months + 1,
            'Merchants' => $merchants,
            'TotalAmount' => $totalAmount
        );
    }

    public static function getAnalyticsListByMonth($app, $merchantID, $userID, $dateFrom, $dateTo)
    {
        $merchants = DB::table('Item AS i')
                        ->select('r.MerchantID', 'ExpensePeriodFrom AS Date', 'r.MerchantName', DB::raw('SUM(i.Amount) AS Amount'))
                        ->join('Receipt AS r', 'r.ReceiptID', '=', 'i.ReceiptID')
                        ->join('Category AS c', 'c.CategoryID', '=', 'i.CategoryID')
                        ->where('c.App', $app)
                        ->where('MerchantID', $merchantID)
                        ->where('r.UserID', $userID)
                        ->where('ExpensePeriodFrom', '>=', $dateFrom)
                        ->where('ExpensePeriodFrom', '<=', $dateTo)
                        ->groupBy('ExpensePeriodFrom')->get();

        $months = ((date('Y', $dateTo) - date('Y', $dateFrom)) * 12) + (date('n', $dateTo) - date('n', $dateFrom));
        $totalAmount = 0;
        if (count($merchants)) {
            foreach ($merchants as $merchant) {
                if (! $merchant->Amount) {
                    $merchant->Amount = 0;
                }
                $totalAmount = $totalAmount + $merchant->Amount;
            }
        }


        return array(
            'Months' => $months + 1,
            'Merchants' => $merchants,
            'TotalAmount' => $totalAmount
        );
    }
    
    public static function checkExisted ($userID, $mcName, $mcAddress = NULL) {
        $query = DB::table('Merchant')
            ->whereIn('UserID', array($userID, 0))
            ->where('Searchable', 1)
            ->where('Name', $mcName);
        if (!empty($mcAddress)) {
            $query->where('Address', $mcAddress);
        }
            
        $merchant = $query->first();
        if ($merchant) {
            return $merchant->MerchantID;
        }
        
        return 0;
    }
    
    /**
     * Use this method to build vaidator for both creating and updating merchant
     */
    public static function validateModel($inputs, $user, $merchant = null) 
    {
        if(isset($inputs['Address'])) {
            $address = $inputs['Address'];
        } else {
            $address = null;
        }
        $rules = array(
            'Name' => array('required', 'max:255', 'merchant_existed:' . $user->UserID . ',' . $address),
            'Address' => array('max:255'),
        );

        if($merchant != null) {
            foreach ($rules as $key => $value) {
                if(!isset($inputs[$key])) {
                    unset($rules[$key]);
                }
            }
            if(isset($inputs['Name']) && ($inputs['Name'] == $merchant->Name)) {
                 unset($rules['Name']);
            }
        }
        
        if($merchant != null) {
            if (isset($merchant['MerchantID']) && $merchant['MerchantID'] && $user->UserID) {
                $inputs['MerchantID'] = $merchant['MerchantID'];
                $rules['MerchantID'] = array('merchant_belongs_to_user:' . $user->UserID);
            }
        }
        
        $message = array(
            'Name.merchant_existed' => 'This merchant has already existed.',
            'MerchantID.merchant_belongs_to_user' => 'This merchant does not belongs to you',
        );
        
        //Validate all inputs for merchants
        $validator = Validator::make($inputs, $rules, $message);

        if ($validator->fails()) {
            return array('message' => $validator->messages()->all());
        }

        return array();
    }
    
    public static function processStore($post, $user) 
    {
        $merchant         = new Merchant();
        $merchant->Name   = $post['Name'];
        $merchant->UserID = $user->UserID;
        
        if (isset($post['Logo'])) {
            $merchant->Logo = $post['Logo'];
        }
        
        if (isset($post['Address'])) {
            $merchant->Address = $post['Address'];
        }
        
        if (isset($post['City'])) {
            $merchant->City = $post['City'];
        }
        
        if (isset($post['State'])) {
            $merchant->State = $post['State'];
        }
        
        if (isset($post['ZipCode'])) {
            $merchant->ZipCode = $post['ZipCode'];
        }
        
        if (isset($post['CountryCode'])) {
            $merchant->CountryCode = $post['CountryCode'];
        }
        
        if (isset($post['PhoneNumber'])) {
            $merchant->PhoneNumber = $post['PhoneNumber'];
        }
        
        if (isset($post['NaicsCode'])) {
            $merchant->NaicsCode = $post['NaicsCode'];
        }
        
        if (isset($post['SicCode'])) {
            $merchant->SicCode = $post['SicCode'];
        }
        
        if (isset($post['MccCode'])) {
            $merchant->MccCode = $post['MccCode'];
        }
        
        if (isset($post['MobileSync']) && $post['MobileSync']) {
            $merchant->MobileSync = $post['MobileSync'];
        }

        $merchant->Searchable = 1;
        $merchant->save();

        $createdMerchant = Merchant::getById($merchant->MerchantID);
        
        return $createdMerchant;
    }
    
    public static function processUpdate($put, $user, $merchant) 
    {   
        if (isset($put['Name'])) {
            $merchant->Name = $put['Name'];
        }
        
        if (isset($post['Logo'])) {
            $merchant->Logo = $post['Logo'];
        }
        
        if (isset($post['Address'])) {
            $merchant->Address = $post['Address'];
        }
        
        if (isset($post['City'])) {
            $merchant->City = $post['City'];
        }
        
        if (isset($post['State'])) {
            $merchant->State = $post['State'];
        }
        
        if (isset($post['ZipCode'])) {
            $merchant->ZipCode = $post['ZipCode'];
        }
        
        if (isset($post['CountryCode'])) {
            $merchant->CountryCode = $post['CountryCode'];
        }
        
        if (isset($post['PhoneNumber'])) {
            $merchant->PhoneNumber = $post['PhoneNumber'];
        }
        
        if (isset($post['NaicsCode'])) {
            $merchant->NaicsCode = $post['NaicsCode'];
        }
        
        if (isset($post['SicCode'])) {
            $merchant->SicCode = $post['SicCode'];
        }
        
        if (isset($post['MccCode'])) {
            $merchant->MccCode = $post['MccCode'];
        }
        
        $merchant->save();
    }
    
    public static function onPreQuery(\Illuminate\Database\Query\Builder $query, &$where = null)
    {
        if ($where != null) {
            if (isset($where['UserID'])) {
                $userID = $where['UserID'];
                unset($where['UserID']);
            }
        }
        
        if ($where != null) {
            //tIds <=> arrayTripID
            if (isset($where['tIds'])) {
                /* GET ITEM FROM TRIP */
                $itemWhere['tIds'] = $where['tIds'];
                $item = TripItem::getAll($itemWhere, array(), '', 0);
                $arrayItemID = array();
                foreach ($item as $id=>$object) {
                    foreach ($object as $key=>$value) {
                        if($key == 'ItemID') {
                            $arrayItemID[] = $value;
                        }
                    }
                }
                $where['arrayItemID'] = $arrayItemID;
                unset($where['tIds']);
            }
        }
        
        if ($where != null) {
            if (isset($where['arrayItemID'])) {
                /* GET RECEIPT FROM ITEM */
                $receiptWhere['arrayItemID'] = $where['arrayItemID'];
                $receiptWhere['UserID'] = $userID;
                $receiptFromItem = Receipt::getAll($receiptWhere, array(), '', 0);

                $arrayReceiptFromItemID = array();
                foreach ($receiptFromItem as $id=>$object) {
                    foreach ($object as $key=>$value) {
                        if($key == 'ReceiptID') {
                            $arrayReceiptFromItemID[] = $value;
                        }
                    }
                }
                // tripitems?:: rIds <=> arrayReceiptID
                $where['rIds'] = $arrayReceiptFromItemID;
                unset($where['arrayItemID']);
            }
        }
        
        if ($where != null) {
            // tripitems?:: rIds <=> arrayReceiptID
            if (isset($where['rIds'])) {
                $query->leftJoin('Receipt AS re', 'r.MerchantID', '=', 're.MerchantID');
                $query->distinct();
                $query->whereIn('re.ReceiptID', $where['rIds']);
                unset($where['rIds']);
            }
        }

        $query->where('Searchable', 1);
    }
    
    public static function getAll(array $where = array(), array $sort = array(), $limit = 10, $offset = 0) 
    {
        $merchants = parent::getAll($where, $sort, $limit, $offset);

        if (count($merchants)) {
//            foreach ($merchants as $merchant) {
//                // set merchant logo for mobile
//                if (!empty($merchant->Logo)) {
//                    $split = explode('/', $merchant->Logo);
//                    $merchantLogoName = end($split);
//                    $link = str_replace($merchantLogoName, "mobile/" . $merchantLogoName, $merchant->Logo);
//                    $merchant->Logo = $link;
//                }
//            }
            
            $arrayMerchantID = array();
            foreach ($merchants as $id=>$object) {
                foreach ($object as $key=>$value) {
                    if($key == 'MerchantID') {
                        $arrayMerchantID[] = $value;
                    }
                }
            }
            $merchant = array();
            $uniqueMerchantID = array_unique($arrayMerchantID);
            foreach ($uniqueMerchantID as $index=>$value) {
                $merchant[] = $merchants[$index];
            }

            return $merchant;
        }
       
        return $merchants;
    }
    
    public static function getById($merchantId) 
    {
        $merchant = parent::getById($merchantId);
        
        if ($merchant) {
            // set merchant logo for mobile
//            if (!empty($merchant->Logo)) {
//                $split = explode('/', $merchant->Logo);
//                $merchantLogoName = end($split);
//                $link = str_replace($merchantLogoName, "mobile/" . $merchantLogoName, $merchant->Logo);
//                $merchant->Logo = $link;
//            }
        }

        return $merchant;
    }
    
    public static function getName($merchantId)
    {
        return DB::table('Merchant')->where('MerchantID', $merchantId)->pluck('Name');
    }


    public static function getAllActiveAdminMerchants ($takeIdOnly = true)
    {
        if ($takeIdOnly) {
            return DB::table('Merchant')->where('UserID', 0)->where('Searchable', 1)->lists('MerchantID');
        } else {
            return DB::table('Merchant')->where('UserID', 0)->where('Searchable', 1)->get();
        }
    }

    public static function processDestroy($post, $user) 
    {
        DB::table('Merchant')
			->where('MerchantID', $post['MerchantID'])
            ->delete();
    }

}