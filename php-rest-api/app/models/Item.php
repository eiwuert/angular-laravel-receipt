<?php
class Item extends BaseModel
{

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'Item';
	
    protected static $_table = 'Item';
	/**
	 * The primary key for the model.
	 *
	 * @var string
	 */
	protected $primaryKey = 'ItemID';
    
    protected static $_primaryKey = 'ItemID';
	
	/**
	 * Indicates if the model should be timestamped.
	 *
	 * @var bool
	 */
	public $timestamps = false;
	
	/**
	 * Define the Many-to-One relationship between Receipt and Item
	 */
	public function Receipt()
	{
		return $this->belongsTo('Receipt');
	}
	
	public static function getList($itemIDs)
	{
		if (! is_array($itemIDs)) {
			$itemIDs = array($itemIDs);
		}
		
		return DB::table('Item AS i')
				->leftJoin('TripItem AS ti', 'ti.TripItemID', '=', 'i.ItemID')
				->select('ItemID', 'i.CategoryID', 'i.Amount', 'i.ExpensePeriodFrom', 'ti.TripID')
				->whereIn('ItemID', $itemIDs)
				->get();
	}
	
	public static function getListOfReceipts($receiptIDs, $excludeJoinedItems = false)
	{
		$query = DB::table('Item AS i')
					->leftJoin('Category AS c', 'c.CategoryID', '=', 'i.CategoryID')
					->leftJoin('TripItem AS ti', 'ti.TripItemID', '=', 'i.ItemID')
					->leftJoin('Trip AS t', 't.TripID', '=', 'ti.TripID')
					->leftJoin('ReportTrip As rt', 'rt.TripID', '=', 't.TripID')
					->leftJoin('Report As r', 'r.ReportID', '=', 'rt.ReportID')
					->select('i.*', 'c.Name AS CategoryName', 'c.App AS CategoryApp', 'c.Parent AS CategoryParent', 't.TripID', 't.Reference', 'r.IsSubmitted', 'ti.IsApproved', 'ti.IsClaimed');
		if (! is_array($receiptIDs)) {
			$query->where('ReceiptID', $receiptIDs);
		} else {
			$query->whereIn('ReceiptID', $receiptIDs);
		}
		
		if ($excludeJoinedItems) {
			$query->where('IsJoined', 0);
		}
		
		$query->orderBy('IsJoined')->orderBy('ItemID');
		$items = $query->get();
		
		// Get only item ids from the list
		$itemIds = $query->lists('ItemID');
		//We only query to get attachments for 1 time, then use a temp array to add attachments to the right items
		$tmpAttachments = array();
		if (count($itemIds)) {
			//Get attachments of items in the list
			$attachmentList = File::getListByEntities($itemIds, 'receipt_item');
			//Add from query result to temp array
			if (count($attachmentList)) {
				foreach ($attachmentList as $attachment) {
					$tmpAttachments[$attachment->EntityID][] = $attachment;
				}
			}
		}
		
		$tmpTags = array();
		if (count($itemIds)) {
			//Get receipt items of receipts in the list
			$tagList = Tag::getList($itemIds, 'receipt_item');
			//Add from query result to temp array
			if (count($tagList)) {
				foreach ($tagList as $tag) {
					$tmpTags[$tag->EntityID][] = $tag->Name;
				}
			}
		}
		
		if (count($items)) {
			$appList = Config::get('app.appList');
			foreach ($items as $item) {
				$item->CategoryAppAbbr = null;
				if ($item->CategoryApp) {
					if (isset($appList[$item->CategoryApp])) {
						$item->CategoryAppAbbr = $appList[$item->CategoryApp]['abbr'];
					}
				}
				$item->Amount = number_format($item->Amount, 2, '.', '');
				$item->Price = number_format($item->Price, 2, '.', '');
				$item->Total = number_format($item->Total, 2, '.', '');
				
				$item->CreatedTime = date('Y-m-d\TH:i:s.B\Z', $item->CreatedTime);
				$item->ModifiedTime = date('Y-m-d\TH:i:s.B\Z', $item->ModifiedTime);
				if (isset($tmpTags[$item->ItemID])) {
					$item->Tags = $tmpTags[$item->ItemID];
				} else {
					$item->Tags = array();
				}
				
				Item::buildItemMore($item);
				
				if (! empty($item->ExpensePeriodFrom)) {
					$item->ExpensePeriod = date('Y-m-d\TH:i:s.B\Z', $item->ExpensePeriodFrom);
				} else {
					$item->ExpensePeriod = null;
				}
				
				if (isset($tmpAttachments[$item->ItemID])) {
					$item->Attachments = $tmpAttachments[$item->ItemID];
				} else {
					$item->Attachments = array();
				}
				
				//Initialize the boolean isChecked for item
				$item->IsChecked = false;
				
				//Initialize the array of deleted files
				$item->DeletedFileIDs = array();
			}
		}
		
		return $items;
	}
	
	public static function getExportedList($userID, $from, $to = null, $app = null)
	{
		$query = DB::table('Item AS i')
				->join('Receipt AS r', 'r.ReceiptID', '=', 'i.ReceiptID')
				->join('Category AS c', 'c.CategoryID', '=', 'i.CategoryID')
				->select('c.Name AS CategoryName', 'i.Amount', 'i.Name AS ItemName', 'r.MerchantName', 'r.PurchaseTime AS Date', 'i.Memo', 'i.ItemID')
				->where('UserID', $userID)
				->where('r.PurchaseTime', '>=', $from);
		
		if ($to) {
			$query->where('r.PurchaseTime', '<=', strtotime('+1 month', $to));
		} else {
			$query->where('r.PurchaseTime', '<=', strtotime('+1 month', $from));
		}

		if($app) {
			$query->where('c.App', $app);	
		}
		
		$items = $query->get();
		$itemIDs = $query->lists('ItemID');
		
		$tmpTags = array();
		if (count($itemIDs)) {
			//Get receipt items of receipts in the list
			$tagList = Tag::getList($itemIDs, 'receipt_item');
			//Add from query result to temp array
			if (count($tagList)) {
				foreach ($tagList as $tag) {
					$tmpTags[$tag->EntityID][] = $tag->Name;
				}
			}
		}
		
		foreach ($items as $item) {
			$item->Date = date('M-Y', $item->Date);
			if (isset($tmpTags[$item->ItemID])) {
				$item->Tags = implode(', ', $tmpTags[$item->ItemID]);
			} else {
				$item->Tags = '';
			}
		}
		
		return $items;
	}
	
	/**
	 * NOTE: This method will also delete all item attachments, update amount and delete trip-item relationships
	 */
	public static function deleteList($itemIDs, $userID) {
		if (! is_array($itemIDs)) {
			$itemIDs = array($itemIDs);
		}
		
		if (count($itemIDs)) {
			$attachmentList = File::getListByEntities($itemIDs, 'receipt_item');
			$deletedFileIDs = array();
			if (count($attachmentList)) {
				foreach ($attachmentList as $file) {
					$filePath = Config::get('app.fileBasePath') . $file->FilePath;
					if (is_file($filePath)) {
						unlink($filePath);
					}
					
					$deletedFileIDs[] = $file->FileID;
				}
			}
			
			File::deleteList($deletedFileIDs);
			
			CategoryAmount::updateAmountByItemIDs($itemIDs, $userID);
					
			//Delete trip-item relationship
			self::deleteItemTripRelationship($itemIDs);
			
			//Delete items
			DB::table('Item')
					->whereIn('ItemID', $itemIDs)
					->delete();
		}		
	}
	
	public static function deleteListByReceipts($receiptIDs)
	{
		if (! is_array($receiptIDs)) {
			$receiptIDs = array($receiptIDs);
		}
		
		if (count($receiptIDs)) {
			DB::table('Item')
					->whereIn('ReceiptID', $receiptIDs)
					->delete();
		}
	}
	
	public static function getCategorizeStatus($value)
	{
		switch ($value) {
			case 0:
				return 'Not categorized';
			case 1:
				return 'User categorized';
			case 2:
				return 'Auto categorized';
			default:
				return '';
		}
	}
	
	public static function getItemIDsOfReceipts($receiptIDs)
	{
		//Get item IDs
		return DB::table('Item')
				->select('ItemID')
				->whereIn('ReceiptID', $receiptIDs)
				->lists('ItemID');
	}
	
	public static function deleteItemTripRelationship($itemIDs)
	{
//        return DB::table('TripItem')
//			->whereIn('TripItemID', $itemIDs)
//			->delete();
        foreach ($itemIDs as $itemID) {
            self::deleteTripItemRecord($itemID);
        }
	}
	
	public static function buildItemMore($item)
	{
		$item->More = new stdClass();
		$item->More->Memo = $item->Memo;
		$item->More->TaxDeductible = $item->TaxDeductible;
		$item->More->TaxDeductibleCategory = $item->TaxDeductibleCategory;
		$item->More->UnitPrice = $item->UnitPrice;
		$item->More->UnitCostBeforeTax = $item->UnitCostBeforeTax;
		$item->More->UnitCostAfterTax = $item->UnitCostAfterTax;
		$item->More->Manufacturer = $item->Manufacturer;
		$item->More->ServiceProviderInfo = $item->ServiceProviderInfo;
		$item->More->MaintenanceTerm = $item->MaintenanceTerm;
		$item->More->ReturnExpirationDate = $item->ReturnExpirationDate;
		$item->More->WarrantyRegistration = $item->WarrantyRegistration;
		$item->More->WarrantyExpirationDate = $item->WarrantyExpirationDate;
		$item->More->WarrantyDocUri = $item->WarrantyDocUri;
		$item->More->InsuranceDocUri = $item->InsuranceDocUri;
		$item->More->Rate = $item->Rate;
		$item->More->Review = $item->Review;
		$item->More->IsEmpty = true;
		foreach ($item->More as $key => $attribute) {
			if ($attribute && $key != 'IsEmpty') {
				$item->More->IsEmpty = false;
				break;
			}
		}
		
		return $item;
	}
	
	/**
	 * This method will check whether items was added to trips,
	 * and whether trips which items was added was also added to reports
	 * 
	 * @return int
	 * 0: these items do not relate to any trips
	 * 1: these items have relationships with some trips but these trips
	 * do not relate to any reports
	 * 2: these items have relationships with some trips and these trips
	 * have relationships with some reports
	 */
	public static function checkItemTripReportRelationships($itemIDs)
	{
		$tripIDs = DB::table('TripItem')
				->select('TripID')
				->whereIn('TripItemID', $itemIDs)
				->lists('TripID');
		
		if (! count($tripIDs)) {
			return 0;
		} else {
			$query = DB::table('ReportTrip')
					->select('ReportTripID')
					->whereIn('TripID', $tripIDs)
					->get();
			
			if (! count($query)) {
				return 1;
			}
			
			return 2;
		}
	}
    
    /**
     * Functions for add, update, remove trip item relationships
     * and update claimed value of trip
     */
    public static function addTripItemRecord($itemID, $itemAmount, $isJoined, $tripID) {
        $toClaim = ($isJoined==1) ? 0:1;
        $amount = ($isJoined==1) ? 0:$itemAmount;
        DB::table('TripItem')
            ->insert(array(
                'TripID'     => $tripID, 
                'TripItemID' => $itemID,
                'IsClaimed'  => $toClaim,
                'Claimed'    => $amount));
        
        self::updateTripClaimedValue($tripID);
    }
	
    public static function updateTripItemRecord($itemID, $itemAmount, $isJoined, $tripID=0) {
        $values = array(
            'IsClaimed' => ($isJoined==1) ? 0:1,
            'Claimed'   => ($isJoined==1) ? 0:$itemAmount
            );
        if ($tripID) {
            $values['TripID'] = $tripID;
        }
        
        DB::table('TripItem')
            ->where('TripItemID', $itemID)
            ->update($values);
        
        if ($tripID) {
            self::updateTripClaimedValue($tripID);
        }
    }
    
    public static function deleteTripItemRecord($itemID, $tripID = 0) {  
        $row = DB::table('TripItem')->where('TripItemID', $itemID);
        if (!$tripID) {
            $tripID = $row->pluck('TripID');
        }
        $row->delete();
        
        if ($tripID) {
            self::updateTripClaimedValue($tripID);
        }
    }
    
    public static function updateTripClaimedValue ($tripID) {
        $items = DB::table('TripItem')
            ->where('TripID', $tripID)
            ->where('IsClaimed', 1)
            ->get();
        
        $totalClaimed = 0;
        if (count($items)) {
            //total claimed of current items
            foreach ($items as $item) {
                $totalClaimed += $item->Claimed;
            }
        }
        
        $tripReport = DB::table('ReportTrip')->where('TripID', $tripID)->first();
        //Update if total claimed does not match trip claimed value 
        if ($tripReport && $totalClaimed != $tripReport->Claimed) {
            DB::table('ReportTrip')->where('TripID', $tripID)->update(array('Claimed' => $totalClaimed));
            $report = DB::table('Report')->where('ReportID', $tripReport->ReportID)->first();
            if ($report) {
                //Update report claimed value
                $newClaimedVal = $report->Claimed - $tripReport->Claimed + $totalClaimed;
                DB::table('Report')
                ->where('ReportID', $report->ReportID)
                ->update(array('Claimed' => $newClaimedVal));
            }
        }
    }
    
    public static function onPreQuery(\Illuminate\Database\Query\Builder $query, &$where = null)
    {
        $arrayReport = array();
        
        if ($where == null) {
            $query ->leftJoin('Receipt AS re', 're.ReceiptID', '=', 'r.ReceiptID')
                ->leftJoin('User AS u', 'u.UserID', '=', 're.UserID')
                ->leftJoin('TripItem AS ti', 'ti.TripItemID', '=', 'r.ItemID')
                ->leftJoin('Trip AS t', 't.TripID', '=', 'ti.TripID')
                ->leftJoin('ReportTrip As rt', 'rt.TripID', '=', 't.TripID')
                ->leftJoin('Report As rep', 'rep.ReportID', '=', 'rt.ReportID')
                ->leftJoin('ReportApprover AS ra', 'ra.ReportID', '=', 'rep.ReportID')
                ->select('r.*', 'ti.*', 're.ReceiptID', 're.UserID', 't.TripID', 't.Reference', 'rep.IsSubmitted', 'ti.IsApproved', 'ti.IsClaimed');
        }
        if ($where != null) {
            if (isset($where['tripCount'])) {
                $qr = DB::table('Report as r');
                $qr->select('r.*', 'ra.Approver', 'us.Email AS SubmitterEmail', 'ua.Email AS ApproverEmail');

                $qr->leftJoin('ReportApprover AS ra', 'ra.ReportID', '=', 'r.ReportID')
                    ->leftJoin('User AS us', 'us.UserID', '=', 'r.Submitter')
                    ->leftJoin('User AS ua', 'ua.UserID', '=', 'ra.Approver');

                $qr->leftJoin('ReportTrip AS rt', 'r.ReportID', '=', 'rt.ReportID')
                    ->havingRaw(DB::raw('COUNT(rt.TripID) <= ' . (int)$where['tripCount']));
                $qr->groupBy('r.ReportID');
                unset($where['tripCount']);
                
                if (isset($where['UserID'])) {
                    $userID = $where['UserID'];
                    $qr->where(function($qr) use ($userID) {
                        $qr->where('r.Submitter', $userID);
                    })
                    ->orWhere(function($qr) use ($userID) {
                        $qr->where('ra.Approver', $userID)
                            ->where('IsSubmitted', 1)
                            ->where('IsDeleted', 0);
                    });
                }
                
                $arrayReport = $qr->lists('r.ReportID');
            }
        }
        
        if(isset($where['ReceiptID'])) {
            $query ->leftJoin('Category AS c', 'c.CategoryID', '=', 'r.CategoryID')
                ->leftJoin('TripItem AS ti', 'ti.TripItemID', '=', 'r.ItemID')
                ->leftJoin('Trip AS t', 't.TripID', '=', 'ti.TripID')
                ->leftJoin('ReportTrip As rt', 'rt.TripID', '=', 't.TripID')
                ->leftJoin('Report As re', 're.ReportID', '=', 'rt.ReportID')
                ->select('r.*', 'c.Name AS CategoryName', 'c.App AS CategoryApp', 'c.Parent AS CategoryParent', 't.TripID', 't.Reference', 're.IsSubmitted', 'ti.IsApproved', 'ti.IsClaimed');
        } else if (isset($where['TripID'])) {
            $query ->leftJoin('TripItem AS ti', 'ti.TripItemID', '=', 'r.ItemID')
                ->leftJoin('Trip AS t', 't.TripID', '=', 'ti.TripID')
                ->where('t.TripID', $where['TripID'])
                ->select('r.*', 't.TripID', 't.Reference', 'ti.IsApproved', 'ti.IsClaimed');
            if (isset($where['TripID'])) {
                unset($where['TripID']);
            }
        } else if ($where != null){
            if (isset($where['UserID'])) {
                $userID = $where['UserID'];
                
                $query ->leftJoin('Receipt AS re', 're.ReceiptID', '=', 'r.ReceiptID')
                    ->leftJoin('User AS u', 'u.UserID', '=', 're.UserID')
                    ->leftJoin('TripItem AS ti', 'ti.TripItemID', '=', 'r.ItemID')
                    ->leftJoin('Trip AS t', 't.TripID', '=', 'ti.TripID')
                    ->leftJoin('ReportTrip As rt', 'rt.TripID', '=', 't.TripID')
                    ->leftJoin('Report As rep', 'rep.ReportID', '=', 'rt.ReportID')
                    ->leftJoin('ReportApprover AS ra', 'ra.ReportID', '=', 'rep.ReportID')
                    ->select('r.*', 'ti.*', 're.ReceiptID', 're.UserID', 't.TripID', 't.Reference', 'rep.IsSubmitted', 'ti.IsApproved', 'ti.IsClaimed');
                
                $query->where(function($query) use ($userID) {
                    $query->where('t.UserID', $userID);
                });
                
                if (isset($where['getTripApprover'])) {
                    $query->orWhere(function($query) use ($userID) {
                        $query->where('ra.Approver', $userID)
                            ->where('rep.IsSubmitted', 1)
                            ->where('ra.IsDeleted', 0);
                    });
                    unset($where['getTripApprover']);
                }
            }
        }
		
        if($where != null) {
            if (isset($where['noApp']) && isset($where['UserID'])) {
                if($where['noApp'] == '1') {
                    $tempQuery = DB::table('Receipt as r');
                    $tempQuery->leftJoin('Item AS it', 'r.ReceiptID', '=', 'it.ReceiptID')
                        ->leftJoin('Category AS ca', 'ca.CategoryID', '=', 'it.CategoryID')
                        ->where(function($tempQuery) { 
                            $tempQuery->where('ca.App' , 'personal_expense');
                        });
                    $tempQuery->groupBy('r.ReceiptID');
                    $tempQuery->where('r.UserID', $where['UserID']);
                    $tempQuery->select('r.ReceiptID');
                    $receiptPersonalExpenseIDs = $tempQuery->lists('r.ReceiptID');
                    
                    if(!empty($receiptPersonalExpenseIDs)) {
                        $query->whereNotIn('r.ReceiptID',$receiptPersonalExpenseIDs);
                    }
                    
                }
                unset($where['noApp']);
            }
        }
        
        if ($where != null) {
            if (isset($where['UserID'])) {
                unset($where['UserID']);
            }
        }
        //tIds <=> arrayTripID
        if ($where != null) {
            if (isset($where['tIds'])) {
                $query->whereIn('ti.TripID', $where['tIds']);
                unset($where['tIds']);
            }
        }
        
    }
    
    public static function getAll(array $where = array(), array $sort = array(), $limit = 10, $offset = 0) 
    {
        $items = parent::getAll($where, $sort, $limit, $offset);

        //if(isset($where['ReceiptID'])) {
            // Get only item ids from the list
            $itemIds = array();
            foreach ($items as $item) {
                $itemIds[] = $item->ItemID;
            }
        
            //We only query to get attachments for 1 time, then use a temp array to add attachments to the right items
            $tmpAttachments = array();
            if (count($itemIds)) {
                //Get attachments of items in the list
                $attachmentList = File::getListByEntities($itemIds, 'receipt_item');
                //Add from query result to temp array
                if (count($attachmentList)) {
                    foreach ($attachmentList as $attachment) {
                        $tmpAttachments[$attachment->EntityID][] = $attachment;
                    }
                }
            }

            $tmpTags = array();
            if (count($itemIds)) {
                //Get receipt items of receipts in the list
                $tagList = Tag::getList($itemIds, 'receipt_item');
                //Add from query result to temp array
                if (count($tagList)) {
                    foreach ($tagList as $tag) {
                        $tmpTags[$tag->EntityID][] = $tag->Name;
                    }
                }
            }

            if (count($items)) {
                $appList = Config::get('app.appList');
                foreach ($items as $item) {
                    $item->CategoryAppAbbr = null;
                    if (isset($item->CategoryApp)) {
                        if (isset($appList[$item->CategoryApp])) {
                            $item->CategoryAppAbbr = $appList[$item->CategoryApp]['abbr'];
                        }
                    }
                    $item->Amount = number_format($item->Amount, 2, '.', '');
                    $item->Price = number_format($item->Price, 2, '.', '');
                    $item->Total = number_format($item->Total, 2, '.', '');

                    $item->CreatedTime = date('Y-m-d\TH:i:s.B\Z', $item->CreatedTime);
                    $item->ModifiedTime = date('Y-m-d\TH:i:s.B\Z', $item->ModifiedTime);
                    if (isset($tmpTags[$item->ItemID])) {
                        $item->Tags = $tmpTags[$item->ItemID];
                    } else {
                        $item->Tags = array();
                    }

                    Item::buildItemMore($item);

                    if (! empty($item->ExpensePeriodFrom)) {
                        $item->ExpensePeriod = date('Y-m-d\TH:i:s.B\Z', $item->ExpensePeriodFrom);
                    } else {
                        $item->ExpensePeriod = null;
                    }

                    if (isset($tmpAttachments[$item->ItemID])) {
                        $item->Attachments = $tmpAttachments[$item->ItemID];
                    } else {
                        $item->Attachments = array();
                    }

                    //Initialize the boolean isChecked for item
                    $item->IsChecked = false;

                    //Initialize the array of deleted files
                    $item->DeletedFileIDs = array();
                    
                    if($item->TripID == null) {
                        $item->TripID = 0;
                    }
                }
            }
        //}
        return $items;
    }
    
    public static function getById($itemId) 
    {
        $item = parent::getById($itemId);
        
        if ($item) {
            $itemIds = array($item->ItemID);
            //We only query to get attachments for 1 time, then use a temp array to add attachments to the right items
            $tmpAttachments = array();
            if (count($itemIds)) {
                //Get attachments of items in the list
                $attachmentList = File::getListByEntities($itemIds, 'receipt_item');
                //Add from query result to temp array
                if (count($attachmentList)) {
                    foreach ($attachmentList as $attachment) {
                        $tmpAttachments[$attachment->EntityID][] = $attachment;
                    }
                }
            }

            $tmpTags = array();
            if (count($itemIds)) {
                //Get receipt items of receipts in the list
                $tagList = Tag::getList($itemIds, 'receipt_item');
                //Add from query result to temp array
                if (count($tagList)) {
                    foreach ($tagList as $tag) {
                        $tmpTags[$tag->EntityID][] = $tag->Name;
                    }
                }
            }
            
            $appList = Config::get('app.appList');
            
            $item->CategoryAppAbbr = null;
            if (isset($item->CategoryApp)) {
                if (isset($appList[$item->CategoryApp])) {
                    $item->CategoryAppAbbr = $appList[$item->CategoryApp]['abbr'];
                }
            }
            $item->Amount = number_format($item->Amount, 2, '.', '');
            $item->Price = number_format($item->Price, 2, '.', '');
            $item->Total = number_format($item->Total, 2, '.', '');

            $item->CreatedTime = date('Y-m-d\TH:i:s.B\Z', $item->CreatedTime);
            $item->ModifiedTime = date('Y-m-d\TH:i:s.B\Z', $item->ModifiedTime);
            if (isset($tmpTags[$item->ItemID])) {
                $item->Tags = $tmpTags[$item->ItemID];
            } else {
                $item->Tags = array();
            }

            Item::buildItemMore($item);

            if (! empty($item->ExpensePeriodFrom)) {
                $item->ExpensePeriod = date('Y-m-d\TH:i:s.B\Z', $item->ExpensePeriodFrom);
            } else {
                $item->ExpensePeriod = null;
            }

            if (isset($tmpAttachments[$item->ItemID])) {
                $item->Attachments = $tmpAttachments[$item->ItemID];
            } else {
                $item->Attachments = array();
            }

            //Initialize the boolean isChecked for item
            $item->IsChecked = false;

            //Initialize the array of deleted files
            $item->DeletedFileIDs = array();
        }
        
        return $item;
    }

    /**
     * Use this method to build vaidator for both creating and updating item
     */
    public static function validateModel($inputs, $user, $item = null) 
    {
        $rules = array(
            'ReceiptID' => array('required'),
            'CategoryID' => array('integer'),
            'Name' => array('required', 'max:255'),
            'Amount' => array('required', 'numeric'),
            'Memo' => array('max:1000'),
        );

        if($item != null) {
            foreach ($rules as $key => $value) {
                if(!isset($inputs[$key])) {
                    unset($rules[$key]);
                }
            }
        }
        
        if($item != null) {
            $inputs['ItemID'] = $item['ItemID'];
            $rules['ItemID'] = array();
        }
        
        $message = array(
            'ItemID.items_belong_to' => 'This item does not belong to you.',
            'Name.max' => 'Item name is limited to 255 characters.',
            'Amount.numeric' => 'Please enter a valid amount.',
            'CategoryID.integer' => 'Please choose a valid category.',
            'Memo.max' => 'Item memo is limited to 1000 characters.',
        );
        
        //Validate all inputs for receipt (not receipt items)
        $validator = Validator::make($inputs, $rules, $message);

        if ($validator->fails()) {
            return array('message' => $validator->messages()->all());
        }

        return array();
    }
    
    public static function processStore($putItem, $user) 
    {
        $item = new Item();
        
        if (isset($putItem['CategoryID']) && $putItem['CategoryID']) {
            $item->CategoryID = $putItem['CategoryID'];
            if ($putItem['CategoryID']) {
                $item->CategorizeStatus = 2;
                $putItem['CategoryApp'] = Category::getApp($putItem['CategoryID']);
            }
        }

        $item->Name = $putItem['Name'];
        $item->Amount = $item->Price = $putItem['Amount'];
        $item->ReceiptID = $putItem['ReceiptID'];
        $item->Quantity = 1;

        $receipt = Receipt::find($item->ReceiptID);
        
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
            }
        }

        if (isset($putItem['Memo'])) {
            $item->Memo = $putItem['Memo'];
        }

        $item->CreatedTime = $_SERVER['REQUEST_TIME'];
        
        if (isset($putItem['MobileSync']) && $putItem['MobileSync']) {
            $item->MobileSync = $putItem['MobileSync'];
        }
        
        $item->save();
        
        $createdItem = Item::getById($item->ItemID);
        
        return $createdItem;
    }
    
    public static function processUpdate($putItem, $user, $item) 
    {
        $oldReceipt = Receipt::find($item->ReceiptID);
        if (isset($putItem['ReceiptID']) && $putItem['ReceiptID'] != $item->ReceiptID) {
            $item->ReceiptID = $putItem['ReceiptID'];
            $receipt = Receipt::find($item->ReceiptID);
        } else {
            $receipt = $oldReceipt;
        }

        if (isset($putItem['Name']) && $putItem['Name'] != $item->Name) {
            $item->Name = $putItem['Name'];
        }

        $oldAmount = $item->Amount;
        if (isset($putItem['Amount']) && $putItem['Amount'] != $item->Amount) {
            $item->Amount = $item->Price = $putItem['Amount'];
        }
        
        //Check if this item was assigned to a trip before
        $tripItemQuery = DB::table('TripItem')
                ->where('TripItemID', $item->ItemID);

        $oldTripID = $tripItemQuery->pluck('TripID');
        if (!$oldTripID) {
            $oldTripID = 0;
        }
        
        $oldCategoryID = $item->CategoryID;
        $putItem['CategoryApp'] = Category::getApp($item->CategoryID);
        if (isset($putItem['CategoryID']) && $putItem['CategoryID'] != $item->CategoryID) {
            //Assign the new category ID to this item
            $item->CategoryID = $putItem['CategoryID'];
            if ($putItem['CategoryID']) {
                $item->CategorizeStatus = 2;
                $putItem['CategoryApp'] = Category::getApp($item->CategoryID);
            } else {
                $item->CategorizeStatus = 0;
            }
        }                 
        
        if (!isset($putItem['TripID'])) {
            $putItem['TripID'] = 0;
            $tripID = 0;
        } else {
            $tripID = $putItem['TripID'];
        }
        
        $oldExpensePeriodFrom = $item->ExpensePeriodFrom;
        if (isset($putItem['CategoryApp'])) {
            if ($putItem['CategoryApp'] == 'travel_expense' && $putItem['TripID']) {
                $item->ExpensePeriodFrom = DB::table('Trip')
                    ->select('StartDate')->where('TripID', $putItem['TripID'])->pluck('StartDate');
            } 
        }

        $isJoinedChange = false;
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
        
        $item->save();
        
        $createdItem = Item::getById($item->ItemID);
        
        return $createdItem;
    }
    
    
    public static function validateDestroy($itemIDs, $user)
    {
        if (! is_array($itemIDs)) {
			$itemIDs = array($itemIDs);
		}
        
        //Validate to be sure that all specified receipts belongs to the user who send this request
		$messages = array(
            'ItemIDs.required' => 'You need to specify at least one receipt.',
            'ItemID.items_belong_to' => 'This item does not belong to you.'
        );
        
		$validator = Validator::make(
            array('ItemIDs' => $itemIDs),
            array('ItemIDs' => array('required', 'items_belong_to:' . $user->UserID)),
            $messages
        );
		
		if ($validator->fails()) {
			return array('message' => $validator->messages()->all());
		}
        
        return array();
    }
    
    public static function processDestroy($itemIDs, $user) 
    {
        if (! is_array($itemIDs)) {
			$itemIDs = array($itemIDs);
		}

		if (count($itemIDs)) {
			$attachmentList = File::getListByEntities($itemIDs, 'receipt_item');
			$deletedFileIDs = array();
			if (count($attachmentList)) {
				foreach ($attachmentList as $file) {
					$filePath = Config::get('app.fileBasePath') . $file->FilePath;
					if (is_file($filePath)) {
						unlink($filePath);
					}
					
					$deletedFileIDs[] = $file->FileID;
				}
			}
			
			File::deleteList($deletedFileIDs);
			
			CategoryAmount::updateAmountByItemIDs($itemIDs, $user->UserID);
					
			//Delete trip-item relationship
			//self::deleteItemTripRelationship($itemIDs);
			
			//Delete items
			DB::table('Item')
                ->whereIn('ItemID', $itemIDs)
                ->delete();
		}
    }
    
    /**
     * Return ids of items attached to a trip
     * @param int $tripId
     */
    public static function getIdsByTrip ($tripId)
    {
        $query = DB::table('TripItem')->where('TripID', $tripId);
        
        return $query->lists('TripItemID');
    }
    
    public static function checkInReport ($tripId)
    {
        $query = DB::table('ReportTrip')->where('TripID', $tripId);
        
        if(count($query->get())) {
            return true;
        }
        return false;
    }
    
    public static function checkInReportDraft ($tripId)
    {
        $query = DB::table('ReportTrip as r');
        $query->leftJoin('Report AS re', 'r.ReportID', '=', 're.ReportID')
            ->where('r.TripID', $tripId)
            ->where('re.IsSubmitted', 0);
        
        if(count($query->get())) {
            return true;
        }
        return false;
    }
    
    
}