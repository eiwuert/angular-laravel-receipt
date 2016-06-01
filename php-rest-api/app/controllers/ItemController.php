<?php
/**
 * Controller for items
 */
class ItemController extends BaseController 
{
	
	public function deleteIndex()
	{
		//Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
		
		$itemIDs = explode(',', Input::get('ItemIDs', ''));
		//Validate to be sure that all specified receipts belongs to the user who send this request
		$messages = array('ItemIDs.required' => 'You need to specify at least one item.');
		if (count($itemIDs) === 1) {
			$messages['ItemIDs.items_for_submitted_report'] = 'This item is reported. You can not delete it.';
		}
		$validator = Validator::make(
				array('ItemIDs' => $itemIDs), 
				array('ItemIDs' => array('required', 'items_belong_to:' . $userToken->UserID, 'items_for_submitted_report')),
				$messages
			);
		
		if ($validator->fails()) {
			return Response::json(array('message' => $validator->messages()->all()), 500);
		}
		
		//Get relationships between items, trips and reports
		$refreshTrip = false; $refreshReport = false;
		$itemTripReportRelationships = Item::checkItemTripReportRelationships($itemIDs);
		if ($itemTripReportRelationships === 2) {
			$refreshTrip = true;
			$refreshReport = true;
		} else if ($itemTripReportRelationships === 1) {
			$refreshTrip = true;
		}
		
		Item::deleteList($itemIDs, $userToken->UserID);
        
        
        if (is_array($itemIDs)) {
            $itemIDs = implode(",", $itemIDs);
        }
        PushBackground::send($userToken->UserID, 'item', 'delete', $itemIDs);
		
		return Response::json(array(
				'RefreshTrip' => $refreshTrip,
				'RefreshReport' => $refreshReport
		));
	}
}