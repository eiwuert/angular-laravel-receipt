<?php
class TripItem extends BaseModel
{

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'TripItem';
	
    protected static $_table = 'TripItem';
	/**
	 * The primary key for the model.
	 *
	 * @var string
	 */
	protected $primaryKey = 'TripItemID';
    
    protected static $_primaryKey = 'TripItemID';
	
	/**
	 * Indicates if the model should be timestamped.
	 *
	 * @var bool
	 */
	public $timestamps = false;
	
    public static function onPreQuery(\Illuminate\Database\Query\Builder $query, &$where = null)
    {   
        $arrayReport = array();
        
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
        
		$query->leftJoin('Item AS it', 'r.TripItemID', '=', 'it.ItemID')
            ->leftJoin('Trip AS tr', 'r.TripID', '=', 'tr.TripID');
        
        $query->leftJoin('ReportTrip AS rt', 'rt.TripID', '=', 'tr.TripID')
            ->leftJoin('Report AS rep', 'rep.ReportID', '=', 'rt.ReportID')
            ->leftJoin('ReportApprover AS ra', 'ra.ReportID', '=', 'rep.ReportID');
        
        $query->select('tr.*', 'it.*', 'r.*');
        
        if ($where != null) {
            if (isset($where['UserID'])) {
                $userID = $where['UserID'];
                
                $query->where(function($query) use ($userID) {
                    $query->where('tr.UserID', $userID);
                });
                
                if (isset($where['getTripApprover'])) {
                    $query->orWhere(function($query) use ($userID) {
                        $query->where('ra.Approver', $userID)
                            ->where('rep.IsSubmitted', 1)
                            ->where('ra.IsDeleted', 0);
                    });
                    unset($where['getTripApprover']);
                }
                unset($where['UserID']);
            }
        }
        
        if(count($arrayReport) > 0) {
            $query->whereIn('rep.ReportID', $arrayReport);
        }
        
        if ($where != null) {
            //tIds <=> arrayTripID
            if (isset($where['tIds'])) {
                $query->whereIn('r.TripID', $where['tIds']);
                unset($where['tIds']);
            }
        }
        
        if ($where != null) {
            // tripitems?:: rIds <=> arrayReceiptID
            if (isset($where['rIds'])) {
                $query->whereIn('it.ReceiptID', $where['rIds']);
                unset($where['rIds']);
            }
        }
    }
    
    public static function getById($tripitemId) 
    {
        $tripitem = parent::getById($tripitemId);
        
        if (!empty($tripitem->CreatedTime)) {
            $tripitem->CreatedTime = date('Y-m-d\TH:i:s.B\Z', $tripitem->CreatedTime);
        }
        if (!empty($tripitem->ModifiedTime)) {
            $tripitem->ModifiedTime = date('Y-m-d\TH:i:s.B\Z', $tripitem->ModifiedTime);
        }
        if (!empty($tripitem->ExpensePeriodFrom)) {
            $tripitem->ExpensePeriodFrom = date('Y-m-d\TH:i:s.B\Z', $tripitem->ExpensePeriodFrom);
        }
        if (!empty($tripitem->ExpensePeriodTo)) {
            $tripitem->ExpensePeriodTo = date('Y-m-d\TH:i:s.B\Z', $tripitem->ExpensePeriodTo);
        }
                
        return $tripitem;
    }
    
    public static function getAll(array $where = array(), array $sort = array(), $limit = 10, $offset = 0) 
    {
        $tripitems = parent::getAll($where, $sort, $limit, $offset);
        
        if (count($tripitems)) {
            foreach ($tripitems as $tripitem) {
                if (!empty($tripitem->CreatedTime)) {
                    $tripitem->CreatedTime = date('Y-m-d\TH:i:s.B\Z', $tripitem->CreatedTime);
                }
                if (!empty($tripitem->ModifiedTime)) {
                    $tripitem->ModifiedTime = date('Y-m-d\TH:i:s.B\Z', $tripitem->ModifiedTime);
                }
                if (!empty($tripitem->ExpensePeriodFrom)) {
                    $tripitem->ExpensePeriodFrom = date('Y-m-d\TH:i:s.B\Z', $tripitem->ExpensePeriodFrom);
                }
                if (!empty($tripitem->ExpensePeriodTo)) {
                    $tripitem->ExpensePeriodTo = date('Y-m-d\TH:i:s.B\Z', $tripitem->ExpensePeriodTo);
                }
            }
        }
        
        return $tripitems;
    }
    
    /**
     * Use this method to build vaidator for both creating and updating item
     */
    public static function validateModel($inputs, $user, $tripitem = null) 
    {
        $rules = array(
            'TripID' => array('required'),            
        );

        if($tripitem == null) {
            $rules['TripItemID'] = array('required', 'tripitem_existed');
        } else {
            $rules['TripItemID'] = array('required');
        }
        
        if($tripitem != null) {
            foreach ($rules as $key => $value) {
                if(!isset($inputs[$key])) {
                    unset($rules[$key]);
                }
            }
        }
        
        $message = array(
            'TripItemID.tripitem_belong_to' => 'This item does not belong to you.',
            'TripID.trips_belong_to_sa' => 'This trip does not belong to you.',
            'TripItemID.tripitem_existed' => 'This tripitem already existed',
        );
        
        //Validate all inputs for receipt (not receipt items)
        $validator = Validator::make($inputs, $rules, $message);

        if ($validator->fails()) {
            return array('message' => $validator->messages()->all());
        }

        return array();
    }
    
    public static function validateDestroy($tripItemIDs, $user)
    {
        if (! is_array($tripItemIDs)) {
			$tripItemIDs = array($tripItemIDs);
		}
        
        //Validate to be sure that all specified receipts belongs to the user who send this request
		$messages = array(
            'ItemIDs.required' => 'You need to specify at least one TripItemID.',
            'ItemIDs.items_belong_to' => 'This item does not belong to you.'
        );
        
		$validator = Validator::make(
            array('ItemIDs' => $tripItemIDs),
            array('ItemIDs' => array('required', 'items_belong_to:' . $user->UserID)),
            $messages
        );
		
		if ($validator->fails()) {
			return array('message' => $validator->messages()->all());
		}
        
        return array();
    }
    
    public static function processDestroy($tripItemIDs, $user) 
    {
        if (! is_array($tripItemIDs)) {
			$tripItemIDs = array($tripItemIDs);
		}

		if (count($tripItemIDs)) {
			//Delete tripitems
			DB::table('TripItem')
                ->whereIn('TripItemID', $tripItemIDs)
                ->delete();
		}
    }
    
    public static function processStore($post, $user) 
    {
        $tripItem = new TripItem();
        $tripItem->TripItemID = $post['TripItemID'];
        $tripItem->TripID = $post['TripID'];
        
        if (isset($post['IsClaimed'])) {
            $tripItem->IsClaimed = $post['IsClaimed'];
        } 
        
        if (isset($post['IsApproved'])) {
            $tripItem->IsApproved = $post['IsApproved'];
        } 
        
        if (isset($post['Claimed'])) {
            $tripItem->Claimed = $post['Claimed'];
        } 
        
        if (isset($post['Approved'])) {
            $tripItem->Approved = $post['Approved'];
        } 
        
        if (isset($post['MobileSync']) && $post['MobileSync']) {
            $tripItem->MobileSync = $post['MobileSync'];
        }
        
        $tripItem->save();

        $createdTripItem = TripItem::getById($tripItem->TripItemID);
        
        return $createdTripItem;
    }
    
    public static function processUpdate($putTripItem, $user, $tripItem) 
    {
        if (isset($putTripItem['IsClaimed'])) {
            $tripItem->IsClaimed = $putTripItem['IsClaimed'];
        }
        
        if (isset($putTripItem['TripID'])) {
            $tripItem->TripID = $putTripItem['TripID'];
        }
        
        if (isset($putTripItem['IsApproved'])) {
            $tripItem->IsApproved = $putTripItem['IsApproved'];
        }

        if (isset($putTripItem['Claimed'])) {
            $tripItem->Claimed = $putTripItem['Claimed'];
        }
        
        if (isset($putTripItem['Approved'])) {
            $tripItem->Approved = $putTripItem['Approved'];
        } 

        $tripItem->save();

        // Update ReportTrip Claimed
        $tripItemClaimedRecords = DB::table('TripItem')
            ->where('TripID', $tripItem->TripID)
            ->where('IsClaimed', 1)
            ->get();        

        $totalClaimed = 0;

        if(count($tripItemClaimedRecords)) {
            foreach ($tripItemClaimedRecords as $ti) {
                $totalClaimed = $totalClaimed + $ti->Claimed;
            }
        }

        // Update ReportTrip Approved
        $tripItemApprovedRecords = DB::table('TripItem')
            ->where('TripID', $tripItem->TripID)
            ->where('IsApproved', 1)
            ->get();        

        $totalApproved = 0;
        
        if(count($tripItemApprovedRecords)) {
            foreach ($tripItemApprovedRecords as $ti) {
                $totalApproved = $totalApproved + $ti->Approved;
            }
        }
        
        $reportTripRecord = DB::table('ReportTrip')
            ->where('TripID', $tripItem->TripID)
            ->get();    
            

        if(count($reportTripRecord)) {
            $reportTrip = $reportTripRecord[0];
            $reportTripModel = ReportTrip::find($reportTrip->ReportTripID);
            
            $reportTripModel->Claimed = $totalClaimed;
            $reportTripModel->Approved = $totalApproved;
            $reportTripModel->save();
        }
        
        $createdTripItem = TripItem::getById($tripItem->TripItemID);
        
        return $createdTripItem;
    }
}