<?php

/**
 * Controller for receipts
 */
class ReportV2Controller extends BaseApiController 
{
    public function __construct()
    {
        return parent::__construct();
    }
    
    protected $model = 'Report';
    
    public function validateObjectPermission($object)
    {
        if ($object == null) {
            $jsend = JSend\JSendResponse::error("Cannot find resource", 404);
            return $jsend->respond();
        }
    }
    
    public function validateSingleObjectPermission($object)
    {
        $message = '';
        if ($object == null) {
            $message = "Cannot find resource";
            return $message;
        }
        
        return $message;
    }
    
    public function showObjectById($id) {
        $model = $this->model;
        
        return $model::getByIdAndUser($id, $this->getUser());
    }
    
    /* PUT /objects
     * 
     * Update multiple objects
     */
    public function getcustom() {
        $query = $this->processInput();
        $user = $this->getUser();
        $finalResult = array();
        
        $model = $this->model;
        
        /* GET REPORT */
        $report = $model::getAll($query['where'], $query['sort'], $query['limit'], $query['offset']);
        
        foreach ($report as $id=>$object) {
            if(!empty($query['fields'])) {
                foreach ($object as $key=>$value) {
                    if(in_array($key, $query['fields'])) {
                        continue;
                    } else {
                        unset($object->$key);
                    }
                }
            }
        }
        
        $arrayReportID = array();
        foreach ($report as $id=>$object) {
            foreach ($object as $key=>$value) {
                if($key == 'ReportID') {
                    $arrayReportID[] = $value;
                }
            }
        }
        
        /* GET TRIP FROM REPORT */
        // trips?:: rIds <=> arrayReportID
        $tripWhere['rIds'] = $arrayReportID;
        $tripFromReport = Trip::getAll($tripWhere, array(), '', 0);
        
        /* GET 20 TRIP FREE (NOT IN ANY REPORTS) */
        $tripFreeWhere['tripFree'] = 1;
        $tripFreeWhere['UserID'] = $user->UserID;
        $tripFree = Trip::getAll($tripFreeWhere, array(), 20, 0);
        $trip = array_merge($tripFromReport, $tripFree);
        
        $arrayTripID = array();
        foreach ($trip as $id=>$object) {
            foreach ($object as $key=>$value) {
                if($key == 'TripID') {
                    $arrayTripID[] = $value;
                }
            }
        }

        /* GET ITEM FROM TRIP */
        //tIds <=> arrayTripID
        $itemWhere['tIds'] = $arrayTripID;
        $item = TripItem::getAll($itemWhere, array(), '', 0);
        $arrayItemID = array();
        foreach ($item as $id=>$object) {
            foreach ($object as $key=>$value) {
                if($key == 'ItemID') {
                    $arrayItemID[] = $value;
                }
            }
        }
        
        /* GET ITEM FROM TRIPITEM */
        $itemModelWhere['ItemID'] = $arrayItemID;
        $itemModelWhere['UserID'] = $user->UserID;
        $items = Item::getAll($itemModelWhere, array(), '', 0);
        
        /* GET RECEIPT FROM ITEM */
        $receiptWhere['arrayItemID'] = $arrayItemID;
        $receiptWhere['UserID'] = $user->UserID;
        $receiptFromItem = Receipt::getAll($receiptWhere, array(), '', 0);
        
        $arrayReceiptFromItemID = array();
        foreach ($receiptFromItem as $id=>$object) {
            foreach ($object as $key=>$value) {
                if($key == 'ReceiptID') {
                    $arrayReceiptFromItemID[] = $value;
                }
            }
        }
        
        /* GET 30 MANUAL RECEIPT */
        $receiptManualWhere['UserID'] = $user->UserID;
        $receiptManualWhere['ReceiptType'] = 4;
        $receiptManual = Receipt::getAll($receiptManualWhere, array(), 30, 0);
        
        /* GET 30 EMAIL RECEIPT */
        $receiptEmailWhere['UserID'] = $user->UserID;
        $receiptEmailWhere['ReceiptType'] = 2;
        $receiptEmail = Receipt::getAll($receiptEmailWhere, array(), 30, 0);
        
        /* GET 30 PAPER RECEIPT */
        $receiptPaperWhere['UserID'] = $user->UserID;
        $receiptPaperWhere['ReceiptType'] = 3;
        $receiptPaper = Receipt::getAll($receiptPaperWhere, array(), 30, 0);
        
        // MERGE 30 MANUAL, 30 EMAIL, 30 PAPER RECEIPT
        $receiptManualEmailPaper = array_merge($receiptManual, $receiptEmail, $receiptPaper);
        foreach ($receiptManualEmailPaper as $id=>$object) {
            foreach ($object as $key=>$value) {
                if($key == 'ReceiptID' && in_array($value, $arrayReceiptFromItemID)) {
                    unset($receiptManualEmailPaper[$id]);
                }
            }
        }
        
        $receipt = array_merge($receiptFromItem, $receiptManualEmailPaper);
        $arrayReceiptID = array();
        foreach ($receipt as $id=>$object) {
            foreach ($object as $key=>$value) {
                if($key == 'ReceiptID') {
                    $arrayReceiptID[] = $value;
                }
            }
        }
        
        /* GET MERCHANT FROM RECEIPT */
        // tripitems?:: rIds <=> arrayReceiptID
        $merchantWhere['rIds'] = $arrayReceiptID;
        $merchantObjects = Merchant::getAll($merchantWhere, array(), '', 0);
        $arrayMerchantID = array();
        foreach ($merchantObjects as $id=>$object) {
            foreach ($object as $key=>$value) {
                if($key == 'MerchantID') {
                    $arrayMerchantID[] = $value;
                }
            }
        }
        $merchant = array();
        $uniqueMerchantID = array_unique($arrayMerchantID);
        foreach ($uniqueMerchantID as $index=>$value) {
            $merchant[] = $merchantObjects[$index];
        }
        
        $finalResult['Report'] = $report;
        $finalResult['Trip'] = $trip;
        $finalResult['TripItem'] = $item;
        $finalResult['Item'] = $items;
        $finalResult['Receipt'] = $receipt;
        $finalResult['Merchant'] = $merchant;
        
        
        $jsend = JSend\JSendResponse::success($finalResult);
        return $jsend->respond();
    }
    
    /*
     * PUT /reports-approve
     * 
     * approve one or multiple reports
     */

    public function approve() 
    {
        $put = Input::all();

        $model = $this->model;
        
        $messages = $model::validateApprove($put, $this->getUser());
        
        if (count($messages)) {
            $jsend = JSend\JSendResponse::fail($messages);
            return $jsend->respond();
        }	

        $model::processApprove($put, $this->getUser());
        
        $jsend = JSend\JSendResponse::success();
        return $jsend->respond();
    }
    
    public function update($id) 
    {
		$put = Input::all();
        
        $model = $this->model;
        
        $object = $model::find($id);

        $this->validateObjectPermission($object);

        // Check if user is submitter of approver
        $isSubmmitter = $model::checkUser($this->getUser(), $object);
        
        if (!$isSubmmitter) {
            $jsend = JSend\JSendResponse::error("Donot have permission to find resource", 403);
            return $jsend->respond();
        }
        
        if ($isSubmmitter == "submitter") {
            $messages = $model::validateModel($put, $this->getUser(), $object);
            if (count($messages)) {
                $jsend = JSend\JSendResponse::fail($messages);
                return $jsend->respond();
            }		

            $model::processUpdate($put, $this->getUser(), $object);
        } else if ($isSubmmitter == "approver") {
            $messages = $model::validateApprove($put, $this->getUser(), $object);
            if (count($messages)) {
                $jsend = JSend\JSendResponse::fail($messages);
                return $jsend->respond();
            }	

            $model::processApprove($put, $this->getUser(), $object);
        }
		
		
		$jsend = JSend\JSendResponse::success();
        return $jsend->respond();
    }
    
    public function updateSingleObject($put) 
    {
		$model = $this->model;
        $primaryKey = $model::getPrimaryKey();
        
        $id = $put[$primaryKey];
        unset($put[$primaryKey]);
        
        if (isset($put['MobileSync'])) {
            $objectFromMobileSync = DB::table($model)->where('MobileSync', $put['MobileSync'])->first();
            $object = $model::find($objectFromMobileSync->$primaryKey);
            unset($put['MobileSync']);
        } else {
            $object = $model::find($id);
        }
        
        $returnObject = new stdClass();

        $havePermission = $this->validateSingleObjectPermission($object);
        
        if(strlen($havePermission) > 0) {
            $returnObject->status = 'fail';
            $returnObject->$primaryKey = $id;
            $returnObject->messages = (array)$havePermission;
            return $returnObject;
        }
        
        // Check if user is submitter of approver
        $isSubmmitter = $model::checkUser($this->getUser(), $object);
        
        if (!$isSubmmitter) {
            $messageError = "Donot have permission to find resource";
            $returnObject->status = 'fail';
            $returnObject->$primaryKey = $id;
            $returnObject->messages = (array)$messageError;
            return $returnObject;
        }

        if ($isSubmmitter == "submitter") {
            $messages = $model::validateModel($put, $this->getUser(), $object);
        
            if (count($messages)) {
                $returnObject->status = 'fail';
                $returnObject->$primaryKey = $id;
                $returnObject->messages = $messages['message'];
                return $returnObject;
            }

            $model::processUpdate($put, $this->getUser(), $object);

            $returnObject->status = 'success';
            $returnObject->$primaryKey = $object->$primaryKey;
            $returnObject->messages = array();
            return $returnObject;
        } else if ($isSubmmitter == "approver") {
            $messages = $model::validateApprove($put, $this->getUser(), $object);
        
            if (count($messages)) {
                $returnObject->status = 'fail';
                $returnObject->$primaryKey = $id;
                $returnObject->messages = $messages['message'];
                return $returnObject;
            }

            $model::processApprove($put, $this->getUser(), $object);

            $returnObject->status = 'success';
            $returnObject->$primaryKey = $object->$primaryKey;
            $returnObject->messages = array();
            return $returnObject;
        }
        

    }

    /* POST /objects
     * 
     * Create multiple objects
     */
    public function create() 
    {
        $post = Input::all();
        
        $bodyArrayKey = $this->model . '-Array';
		if(isset($post[$bodyArrayKey]) && !empty($post[$bodyArrayKey])) {
            $arrayInput = $post[$bodyArrayKey];
			parse_str($arrayInput, $output);
            $post[$this->model] = $output[$this->model] ;
        }
        
        $jsonKey = $this->model . '-Multiple';
        
        if(isset($post[$jsonKey]) && !empty($post[$jsonKey])) {
            $jsonInput = $post[$jsonKey];
            $arrayObject = json_decode($jsonInput);
            foreach($arrayObject as $key => $object) {
                $arrayObject[$key] = get_object_vars($object);
            }
            
            $post[$this->model] = $arrayObject;
        }
        
        $result = array();
        foreach ($post[$this->model] as $singlePost) {
            if(isset($singlePost['TripID'])) {
                $singleResult = $this->convertSingleTripToReport($singlePost);
            } else {
                $mobileSyncID = $singlePost['MobileSync'];
            
                // Check if there is an existing MobileSync
                $model = $this->model;
                $objectRecord = DB::table($model)->where('MobileSync', $mobileSyncID)->first();

                if(empty($objectRecord)) {
                    // There is no existing MobileSync Record, create new
                    $singleResult = $this->storeSingleObject($singlePost);
                } else {
                    // There is an existing MobileSync Record, update it
                    $singleResult = $this->updateSingleObject($singlePost);
                }
            }
            $result[] = $singleResult;
        }
        
        return $jsend = JSend\JSendResponse::success($result);
    }

    public function convertSingleTripToReport($singlePost) {
        $model = $this->model;
        $primaryKey = $model::getPrimaryKey();
        
        $id = $singlePost[$primaryKey];

        $user = $this->getUser();
        $post = array();
        $post['TripID'] = $singlePost['TripID'];
        $post['Date'] = $singlePost['Date'];
        
        $messages = $this->validateConvertTripReport($post, $user);

        $returnObject = new stdClass();
        
        if (count($messages)) {
            $returnObject->status = 'fail';
            $returnObject->$primaryKey = $id;
            $returnObject->messages = $messages['message'];
            return $returnObject;
        }
        
        // After checking inputs, prepare data for saving new report
        $trip = Trip::find($post['TripID']);
        
        $postForReport = array();
        $postForReport['Title'] = $trip->Name; 
        $postForReport['Reference'] = 'R' . str_replace('-', '', $post['Date']);
        $postForReport['Date'] = $post['Date']; 
        $postForReport['IsSubmitted'] = 0;
        $postForReport['Claimed'] = Trip::staticGetAmount($trip);
        $postForReport['IsClaimed'] = 1;
        
        $postForTrip = array();
        $postForTrip[0]['TripID'] = $trip->TripID;
        $postForTrip[0]['Claimed'] = Trip::staticGetAmount($trip);
        $postForTrip[0]['IsClaimed'] = 1;
        
        $postForTripItem = array();
        $item = array();
        $tripItems = Trip::getTripItems($trip->TripID);
        foreach ($tripItems as $tripitem) {
            $item['ItemID'] = $tripitem->ItemID;
            $item['Claimed'] = $tripitem->Amount;
            $item['IsClaimed'] = 1;
            $postForTripItem[] = $item;
        }
        $postForTrip[0]['Items'] = $postForTripItem;
        
        $postForReport['Trips'] = $postForTrip;
        
        $messagesValidate = $this->validateTripsNotAddedToReport($postForReport, $user);

        if (count($messagesValidate)) {
            $returnObject->status = 'fail';
            $returnObject->$primaryKey = $id;
            $returnObject->messages = $messagesValidate['message'];
            return $returnObject;
        }
        
        $createdReport = Report::processStore($postForReport, $user);
        
        $returnObject->status = 'success';
        $returnObject->$primaryKey = $createdReport->$primaryKey;
        $returnObject->messages = array();
        return $returnObject;
    }

    protected function validateConvertTripReport($post, $user) {
        $rules = array(
            'TripID' => array('required', 'trips_belong_to:' . $user->UserID),
            'Date' => array('required', 'date', 'date_true_format'),
        );
        $message = array(
            'Date.date_true_format' => 'Please enter a Date in yyyy-mm-dd format',
        );

        $validator = Validator::make($post, $rules, $message);

        if ($validator->fails()) {
            return array('message' => $validator->messages()->all());
        }
        
        return array();
    }
    
    protected function validateTripsNotAddedToReport($inputs, $user) {
        $rules = array(
            'Trips' => array('trips_obj_not_added'),
        );
        
        $customMessages = array(
            'Trips.trips_obj_not_added' => 'This trip is added to another report',
        );
                
        $validator = Validator::make($inputs, $rules, $customMessages);
        
        if ($validator->fails()) {
            return array('message' => $validator->messages()->all());
        }
        
        return array();
    }

}
