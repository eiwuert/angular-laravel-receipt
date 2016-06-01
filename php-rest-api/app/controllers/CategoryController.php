<?php
/**
 * Controller for category's interactions
 */
class CategoryController extends BaseController 
{
	
	public function getIndex()
	{
		//Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
		
		$app = Input::get('app', '');
		$dateFrom = Input::get('dateFrom', '');
		$dateTo = Input::get('dateTo', '');
		
		if (empty($app)) {
			$categoryReturnList = Category::fetchAll();
			
			if (count($categoryReturnList)) {
				foreach ($categoryReturnList as $categoryList) {
					$categoryList->Categories = Category::buildTree($categoryList->Categories);
				}
			}
		} else {
			if (! $dateTo || strtotime($dateTo) <= strtotime($dateFrom)) {
				$dateTo = date('Y-m-d', strtotime('+1 day', strtotime($dateFrom)));
			} else {
				$dateTo = date('Y-m-d', strtotime('+1 day', strtotime($dateTo)));
			}
			
			$categoryReturnList = Category::getListByApp($app, $userToken->UserID, strtotime($dateFrom), strtotime($dateTo));
		}
		
		return Response::json($categoryReturnList);
	}
	
	public function getAnalytics()
	{
		//Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
		
		$get = Input::all();
		$validator = Validator::make($get, array(
				'app' => array('required', 'is_app'),
				'filter' => array('required', 'in:Category,Merchant'),
				'dateFrom' => array('required', 'date'),
				'dateTo' => array('date', 'after:dateFrom')
			));
		
		if ($validator->fails()) {
			return Response::json(array('message' => $validator->messages()->all()), 500);
		}
		
		$dateFrom = strtotime(date('Y-m-01', strtotime($get['dateFrom'])));
		if (isset($get['dateTo'])) {
			$dateTo = strtotime(date('Y-m-01', strtotime($get['dateTo'])));
		} else {
			$dateTo = null;
		}
		
		if (! isset($get['id'])) {
			if ($get['filter'] == 'Category') {
				return Response::json(Category::getAnalyticsList($get['app'], $userToken->UserID, $dateFrom, $dateTo));
			} else {
				return Response::json(Merchant::getAnalyticsList($get['app'], $userToken->UserID, $dateFrom, $dateTo));
			}
		} else if (isset($get['dateTo'])) {
			if ($get['filter'] == 'Category') {
				return Response::json(Category::getAnalyticsListByMonth($get['app'], $get['id'], $userToken->UserID, $dateFrom, $dateTo));
			} else {
				return Response::json(Merchant::getAnalyticsListByMonth($get['app'], $get['id'], $userToken->UserID, $dateFrom, $dateTo));
			}
		}
		
		return Response::make('', 204);
	}
	
	/**
	 * Assign an item to a specified category
	 */
	public function putAssign()
	{
		//Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
		
		$put = Input::all();
		$validator = Validator::make(
				$put,
				array(
					'ItemID' => array('required', 'items_belong_to:' . $userToken->UserID),
					'App' => array('required', 'is_app'),
					'CategoryID' => array('required', 'belongs_to_app:' . Input::get('App', '')),
					'TripID' => array('required_if:App,"travel_expense"', 'trips_belong_to:' . $userToken->UserID),
				)
		);
		
		if ($validator->fails()) {
			return Response::json(array('message' => $validator->messages()->all()), 500);
		}

		// Update CategoryID of the item
		$item = Item::find($put['ItemID']);
		$oldCategoryID = $item->CategoryID;
		$item->CategoryID = $put['CategoryID'];
		$oldExpensePeriodFrom = $item->ExpensePeriodFrom;
		
		if ($put['App'] == 'travel_expense') {
			//Check if this item was assigned to a trip before
			$tripItemQuery = DB::table('TripItem')
					->where('TripItemID', $item->ItemID);
			
			if ($tripItemQuery->pluck('TripID')) {
				//Only update this record if it exists
				$tripItemQuery->update(array('TripID' => $put['TripID']));
			} else {
				//Insert a new record
				DB::table('TripItem')
						->insert(array('TripID' => $put['TripID'], 'TripItemID' => $item->ItemID));
			}
			
			$item->ExpensePeriodFrom = DB::table('Trip')
					->select('StartDate')->where('TripID', $put['TripID'])->pluck('StartDate');
		} else if (! empty($put['ExpensePeriod'])) {
			$item->ExpensePeriodFrom = strtotime(date('01-M-Y', strtotime($put['ExpensePeriod'])));
		}
		
		
		if ($item->CategorizeStatus != 2) {
			$item->CategorizeStatus = 2;
		}
		
		$item->save();
		
		//Update category amount
		if ($oldCategoryID && $oldExpensePeriodFrom) {
			CategoryAmount::updateAmount($userToken->UserID, $oldCategoryID, $item->Amount, $oldExpensePeriodFrom, 'minus');
		}

		if ($item->ExpensePeriodFrom) {
			CategoryAmount::updateAmount($userToken->UserID, $item->CategoryID, $item->Amount, $item->ExpensePeriodFrom);
		}
		
		
		return Response::make('', 204);
	}
	
	/**
	 * Remove one or more items from a category
	 */
	public function putUnassign()
	{
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}

		$put = Input::all();

		$itemIDs = explode(',', Input::get('ItemIDs', ''));
		
		$put['ItemIDs'] = $itemIDs;
		
		$validator = Validator::make(
			$put,
			array(
				'ItemIDs' => array('required', 'items_belong_to:' . $userToken->UserID),
			)
		);
				
		if ($validator->fails()) {
			return Response::json(array('message' => $validator->messages()->all()), 500);
		}
		
		$items = Item::getList($put['ItemIDs']);
        
		if (count($items)) {
			foreach ($items as $item) {
				CategoryAmount::updateAmount($userToken->UserID, $item->CategoryID, $item->Amount, $item->ExpensePeriodFrom, 'minus', $item->TripID);
				DB::table('Item')
						->where('ItemID', $item->ItemID)
						->update(array('CategoryID' => 0));

                //Remove trip item relationships if exist
                if ($item->TripID) {
                    Item::deleteTripItemRecord($item->ItemID, $item->TripID);
                }
			}
		}

		return Response::make('', 204);
	}
	
	public function putUpdateAmount()
	{
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
		if ($userToken->UserID != 1) {
			return;
		}
		
		DB::table('CategoryAmountPerMonth')
				->update(array('Amount' => 0));
		
		$items = DB::table('Item AS i')
				->join('Receipt AS r', 'r.ReceiptID', '=', 'i.ReceiptID')
				->leftJoin('TripItem AS ti', 'ti.TripItemID', '=', 'i.ItemID')
                 ->where('i.IsJoined', '=', 0)
				->select('ItemID', 'CategoryID', 'ExpensePeriodFrom', 'UserID', 'Amount', 'r.UserID', 'ti.TripID')
				->get();
		
		foreach ($items as $item) {
			if ($item->CategoryID && $item->ExpensePeriodFrom) {
				$parentIDs = Category::getAllParents($item->CategoryID);
				
				foreach ($parentIDs as $parentID) {
					if ($item->TripID) {
						$categoryAmount = CategoryAmount::whereRaw('CategoryID=:CategoryID AND UserID=:UserID AND Date=:Date AND TripID=:TripID', array(
								':CategoryID' => $parentID, 
								':UserID' => $item->UserID,
								':Date' => $item->ExpensePeriodFrom,
								':TripID' => $item->TripID,
							))->first();
					} else {
						$categoryAmount = CategoryAmount::whereRaw('CategoryID=:CategoryID AND UserID=:UserID AND Date=:Date', array(
								':CategoryID' => $parentID, 
								':UserID' => $item->UserID,
								':Date' => $item->ExpensePeriodFrom
							))->first();
						
						$item->TripID = 0;
					}

					if (! $categoryAmount) {
						$categoryAmount = new CategoryAmount();
						$categoryAmount->CategoryID = $parentID;
						$categoryAmount->UserID = $item->UserID;
						$categoryAmount->Date = $item->ExpensePeriodFrom;
						$categoryAmount->Amount = $item->Amount;
						$categoryAmount->TripID = $item->TripID;
						$categoryAmount->save();
					} else {
						$categoryAmount->Amount += $item->Amount;
						$categoryAmount->TripID = $item->TripID;
						$categoryAmount->save();
					}
				}
			}
		}
	}
	
	public function putCategoryName()
	{
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
		if ($userToken->UserID != 1) {
			return;
		}
		
		$categories = Category::where('CategoryID', '>', 0)->get();
		foreach ($categories as $category) {
			$category->Name = str_replace('&', '-', $category->Name);
			$category->save();
		}
	}
	
	public function putCategoryOrder()
	{
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
		if ($userToken->UserID != 1) {
			return;
		}
		
		$categories = Category::where('App', '=', 'travel_expense')->get();
		$currentOrders = array();
		foreach ($categories as $category) {
			if (! isset($currentOrders[$category->Parent])) {
				$currentOrders[$category->Parent] = 0;
			} else {
				$currentOrders[$category->Parent]++;
			}
			
			$category->CategoryOrder = $currentOrders[$category->Parent];
			$category->save();
		}
	}
	
    /*
    //issues/34821
	public function getFixOldReceiptNoMerchant() 
    {
        //Find all receipt contain merchantID = 0
        $list = DB::table('Receipt AS r')
            ->select('r.MerchantName', 'r.UserID', 'r.ReceiptID')
			//->where('r.UserID', 3)
            ->where('r.MerchantID', 0)
            ->whereNotIn('r.MerchantName', array('Receipt Unrecognized', 'Merchant Unrecognized'))
            ->get();
        
        //dd($list);
		$update = 0;
		$new = 0;
		$newList = array();
        $len = count($list);
        //$len = 1;
        //Check existed merchants
        for($i=0; $i<$len; $i++){
            $mc = $list[$i]->MerchantName;
            $mid = Merchant::checkExisted($list[$i]->UserID, $mc);

            //Update Merchant ID for Receipt
            if($mid > 0){
                $list[$i]->foundID = $mid;
				$update++;
            } else if ($mid == 0) {
                $merchant = new Merchant();
                $merchant->Name = $list[$i]->MerchantName;
                $merchant->UserID = $list[$i]->UserID;
				$new++;
				$newList[] = $list[$i]->MerchantName;
                $merchant->save();
				$mid = $merchant->MerchantID;
            }
            
            DB::table('Receipt')->where('ReceiptID', $list[$i]->ReceiptID)
                    ->update(array('MerchantID' => $mid));
        }
        
        //dd(count($list));
		dd($newList);
		dd('updated : ' . $update . ' - added: ' . $new);
    }
    */
}
