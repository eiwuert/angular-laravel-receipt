<?php
class CategoryAmount extends Eloquent
{

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'CategoryAmountPerMonth';
	
	/**
	 * The primary key for the model.
	 *
	 * @var string
	 */
	protected $primaryKey = 'CategoryAmountID';
	
	/**
	 * Indicates if the model should be timestamped.
	 *
	 * @var bool
	 */
	public $timestamps = false;
	
	public static function updateAmount($userID, $categoryID, $amount, $dateFrom, $operation = 'plus', $tripID = 0)
	{
		//Get all parents of the category, contain the category itself
		$parentIDs = Category::getAllParents($categoryID);
		foreach ($parentIDs as $parentID) {
			if ($tripID) {
				$categoryAmount = self::whereRaw('CategoryID=:CategoryID AND UserID=:UserID AND Date=:Date AND TripID=:TripID', array(
					':CategoryID' => $parentID, 
					':UserID' => $userID,
					':Date' => $dateFrom,
					':TripID' => $tripID,
				))->first();
			} else {
				$categoryAmount = self::whereRaw('CategoryID=:CategoryID AND UserID=:UserID AND Date=:Date', array(
					':CategoryID' => $parentID, 
					':UserID' => $userID,
					':Date' => $dateFrom,
				))->first();
			}
			
			if (! $categoryAmount) {
				$categoryAmount = new CategoryAmount();
				$categoryAmount->CategoryID = $parentID;
				$categoryAmount->UserID = $userID;
				$categoryAmount->Date = $dateFrom;
				$categoryAmount->TripID = $tripID;

				$categoryAmount->save();
			}

			if ($operation == 'plus') {
				$categoryAmount->Amount += $amount;
			}
			if ($operation == 'minus') {
				if ($categoryAmount > $amount) {
					$categoryAmount->Amount -= $amount;
				} else {
					$categoryAmount->Amount = 0;
				}
			}

			$categoryAmount->save();
		}
	}
	
	public static function updateAmountByReceipts($receiptIDs, $userID)
	{
		if (! is_array($receiptIDs)) {
			$receiptIDs = array($receiptIDs);
		}
		
		$items = DB::table('Item AS i')
				->leftJoin('TripItem AS ti', 'ti.TripItemID', '=', 'i.ItemID')
				->select('CategoryID', 'Amount', 'ExpensePeriodFrom', 'ExpensePeriodTo', 'IsJoined', 'TripID')
				->whereIn('ReceiptID', $receiptIDs)
				->get();
		
		if (count($items)) {
			foreach ($items as $item) {
				if (! $item->IsJoined && $item->ExpensePeriodFrom) {
					if ($item->TripID) {
						CategoryAmount::updateAmount($userID, $item->CategoryID, $item->Amount, $item->ExpensePeriodFrom, 'minus', $item->TripID);
					} else {
						CategoryAmount::updateAmount($userID, $item->CategoryID, $item->Amount, $item->ExpensePeriodFrom, 'minus', 0);
					}
				}
			}
		}
	}
	
	public static function updateAmountByItemIDs($itemIDs, $userID)
	{
		if (! is_array($itemIDs)) {
			$itemIDs = array($itemIDs);
		}
		
		$items = DB::table('Item AS i')
				->leftJoin('TripItem AS ti', 'ti.TripItemID', '=', 'i.ItemID')
				->select('CategoryID', 'Amount', 'ExpensePeriodFrom', 'ExpensePeriodTo', 'IsJoined', 'TripID')
				->whereIn('ItemID', $itemIDs)
				->get();
		
		if (count($items)) {
			foreach ($items as $item) {
				if (! $item->IsJoined && $item->ExpensePeriodFrom) {
					if ($item->TripID) {
						CategoryAmount::updateAmount($userID, $item->CategoryID, $item->Amount, $item->ExpensePeriodFrom, 'minus', $item->TripID);
					} else {
						CategoryAmount::updateAmount($userID, $item->CategoryID, $item->Amount, $item->ExpensePeriodFrom, 'minus', 0);
					}
				}
			}
		}
	}
}