<?php
/**
 * Controller for report and trip relation
 */
class ConvertReportTripV2Controller extends BaseV2Controller 
{
    /**
	 * API to convert trip to report
	 */
	public function postConvertTripReport()
	{

        $userToken = UserApiToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN']);
        
        if (empty($_SERVER['HTTP_AUTH_TOKEN']) || !$userToken) {
            $jsend = JSend\JSendResponse::error('The authentication is failed.', 401);
            return $jsend->respond();
        }
        
        $user = User::find($userToken->UserID);
		$post = Input::all();
        
        $messages = $this->validateConvertTripReport($post, $user);

        if (count($messages)) {
            $jsend = JSend\JSendResponse::fail($messages);
            return $jsend->respond();
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
            $jsend = JSend\JSendResponse::fail($messagesValidate);
            return $jsend->respond();
        }
        
        $createdReport = Report::processStore($postForReport, $user);
        
        $result = Report::getByIdAndUser($createdReport->ReportID, $user);
        
		$jsend = JSend\JSendResponse::success((array)$result);
        return $jsend->respond();
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
