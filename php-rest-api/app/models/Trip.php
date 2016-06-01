<?php
class Trip extends BaseModel
{
	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'Trip';
    
    protected static $_table = 'Trip';

	/**
	 * The primary key for the model.
	 *
	 * @var string
	 */
	protected $primaryKey = 'TripID';
    
    protected static $_primaryKey = 'TripID';

	/**
	 * Indicates if the model should be timestamped.
	 *
	 * @var bool
	 */
	public $timestamps = false;

	public static function getList($userID, $filter = array())
	{
		//Use a boolean to indicate whether wee need to filter results by date or not
		$needDateFilter = true;
                /**
                 * Get 50 Trip On First Load
                 */
                $tripQuery = DB::table('Trip AS t');
		if (isset($filter['loadFirstTrip'])) {
                    $tripQuery = DB::table('Trip AS t')->take(50);
		}
                /**
                 * Skip Trip Loaded
                 */
		if (isset($filter['loadMoreTrip'])) {
                    $tripQuery = DB::table('Trip AS t')->take($filter['loadMoreTrip'] + 50);
		}
                
		
		if (! isset($filter['dropdown'])) {
			$filter['dropdown'] = false;
		}
		// If dropdown filter = true, we only get TripID, Ref and StartDate of the trip
		// Otherwise, we get all information of trips
		if ($filter['dropdown']) {
			$needDateFilter = false;
			//$startDateOrder = 'desc';
			$tripQuery->select('t.TripID', 't.Reference', 't.StartDate', 't.Name');
		} else {
			//$startDateOrder = 'asc';
			$tripQuery->select('t.*', 'rt.Claimed', 'rt.Approved', 'rt.IsClaimed', 'rt.IsApproved', 'r.Reference AS Report', 'r.ReportID', 'r.Submitter', 'r.IsSubmitted', 'r.IsApproved AS IsReportApproved', 'ra.Approver');
		}
        
        //Filter by newest startdate
        $startDateOrder = 'desc';
        
		// We always do "LEFT JOIN" between Trip and ReportTrip, ReportTrip and Report
		$tripQuery->leftJoin('ReportTrip AS rt', 'rt.TripID', '=', 't.TripID')
				->leftJoin('Report AS r', 'r.ReportID', '=', 'rt.ReportID')
				->leftJoin('ReportApprover AS ra', 'ra.ReportID', '=', 'r.ReportID');
		
		if ($filter['dropdown']) {
			$tripQuery->where(function($query) {
				$query->where('r.IsSubmitted', 0)
					->orWhere('r.IsSubmitted', null)
					->orWhere(function($query) {
						$query->where('r.IsSubmitted', 1)
							->where('r.IsApproved', 2);
					});
			});
		}
                
		if (! isset($filter['type'])) {
			$filter['type'] = 'all';
		}
		
		//Get the UNIX timestamp for today
		//$todayTimestamp = strtotime(date('Y-m-d\TH:i:s.B\Z', $_SERVER['REQUEST_TIME']));
        $todayTimestamp = strtotime(date('Y-m-d'));
		
		if (! isset($filter['from'])) {
			$filter['allDate'] = true;
		}
		
		if (isset($filter['allDate']) && $filter['allDate']) {
			$needDateFilter = false;
		}
		
		if (isset($filter['reportID']) && $filter['reportID']) {
			$tripQuery->where('r.ReportID', $filter['reportID']);
			$needDateFilter = false;
		} else if (isset($filter['tripIDs']) && count($filter['tripIDs'])) {
			$tripQuery->whereIn('t.TripID', $filter['tripIDs']);
			$needDateFilter = false;
		} else {
			$tripQuery->where('t.IsArchived', 0);

			if ($filter['type'] == 'upcoming') {
				$tripQuery->where('t.StartDate', '>', $todayTimestamp);
			}
            else if ($filter['type'] == 'current') {
				$tripQuery->where('t.EndDate', '>=', $todayTimestamp)
					->where('t.StartDate', '<=', $todayTimestamp);
			}
            else if ($filter['type'] == 'past') {
				$tripQuery->where('t.EndDate', '<', $todayTimestamp);
			}
            else if ($filter['type'] == 'reported') {
				$tripQuery->where('r.IsSubmitted', '>', 0);
			}
            else if ($filter['type'] == 'claimed') {
				$tripQuery->where('r.IsClaimed', '>', 0);
			}
            else if ($filter['type'] == 'approved') {
				$tripQuery->where('r.IsApproved', '>', 0);
			}
		}
		
		if (! isset($filter['reportID']) || isset($filter['addTrip'])) {
			$tripQuery->where('UserID', $userID);
			if (isset($filter['addTrip'])) {
				$needDateFilter = false;
			}
		}
			
		if ($needDateFilter) {
			if (! isset($filter['from'])) {
				$filter['from'] = strtotime('-3 months');
			} else {
				$filter['from'] = strtotime($filter['from']);
			}

			if (isset($filter['to'])) {
				$filter['to'] = strtotime($filter['to']);

				$tripQuery->where(function($query) use ($filter) {
					$query->where(function($query) use ($filter) {
						$query->where('t.StartDate', '<=', $filter['from'])
							->where('t.EndDate', '>=', $filter['from']);
					})
					->orWhere(function($query) use ($filter) {
						$query->where('t.StartDate', '>', $filter['from'])
							->where('t.StartDate', '<', $filter['to']);
					});
				});
			} else {
				$tripQuery->where('t.StartDate', '>=', $filter['from']);
			}
		}

        if (isset($filter['order']) && $filter['order'] == 'reportTripAsc') {
            $tripQuery->orderBy('rt.ReportTripID', 'asc');
        } else {
            $tripQuery->orderBy('t.StartDate', $startDateOrder)
                ->orderBy('t.Departure');
        }

        //Query pagination
        if (isset($filter['queryFrom'], $filter['queryStep'])) {
            $queryCopy = clone $tripQuery;
            $total     = $queryCopy->count();
            $pageStep  = intval($filter['queryStep']);
            //$pageForward  = boolval($filter['queryForward']);


            if ($filter['queryFrom'] == 'last') {
                //For the last page, we need to calculate since the queryStep is unpredictable
                $remainder = intval($total % $pageStep);
                $pageFrom  = $total - ($remainder ?  $remainder : $pageStep) + 1;
            } else {
                $pageFrom  = intval($filter['queryFrom']);
            }

            $tripQuery->skip($pageFrom-1)
                ->take($pageStep);
        }

		$trips = $tripQuery->get();
		$tripIDList = $tripQuery->lists('t.TripID');
		
		if (! $filter['dropdown']) {
			//We only query to get attachments for 1 time, then use a temp array to add attachments to the right trip
			$tmpAttachments = array();
			if (count($tripIDList)) {
				$attachmentList = File::getListByEntities($tripIDList, 'trip');
				//Add from query result to temp array
				if (count($attachmentList)) {
					foreach ($attachmentList as $attachment) {
						$tmpAttachments[$attachment->EntityID][] = $attachment;
					}
				}
			}

			$tmpTags = array();
			if (count($tripIDList)) {
				$tagList = Tag::getList($tripIDList, 'trip');
				//Add from query result to temp array
				if (count($tagList)) {
					foreach ($tagList as $tag) {
						$tmpTags[$tag->EntityID][] = $tag->Name;
					}
				}
			}
		}

		if (count($trips)) {
			foreach ($trips as $trip) {
				if (! $filter['dropdown']) {
					//Set state for the returned trip
					self::staticSetState($trip);
					//get amount for the returned trip
					self::staticGetAmount($trip);
					
					$trip->ReportStatus = Report::staticSetStatus($trip, $userID);
					if (! $trip->ReportStatus) {
						$trip->ReportStatus = '';
					}
					
					$trip->EndDate = date('Y-m-d\TH:i:s.B\Z', $trip->EndDate);
					$trip->CreatedTime = date('Y-m-d\TH:i:s.B\Z', $trip->CreatedTime);
					$trip->ModifiedTime = date('Y-m-d\TH:i:s.B\Z', $trip->ModifiedTime);

					$trip->IsChecked = false;

					if (isset($tmpAttachments[$trip->TripID])) {
						$trip->Attachments = $tmpAttachments[$trip->TripID];
					} else {
						$trip->Attachments = array();
					}

					if (isset($tmpTags[$trip->TripID])) {
						$trip->Tags = $tmpTags[$trip->TripID];
					} else {
						$trip->Tags = array();
					}
					
					if (is_null($trip->Claimed)) {
						$trip->Claimed = 0.00;
					} else {
						$trip->Claimed = number_format($trip->Claimed, 2, '.', '');
					}
					
					if (is_null($trip->Approved)) {
						$trip->Approved = 0.00;
					} else {
						$trip->Approved = number_format($trip->Approved, 2, '.', '');
					}
					
					if (isset($filter['addTrip'])) {
						if ($trip->ReportID) {
							$trip->IsNew = 0;
						} else {
							$trip->IsNew = 1;
						}
					}
				}
				
				$trip->StartDate = date('Y-m-d\TH:i:s.B\Z', $trip->StartDate);
			}
		}

        return $trips;
	}

    /**
     * Count number of trips by type
     *
     * @param  $userID      int       User ID
     * @param  $types       array     Array of type name
     * @param  $dateFrom    string    Start date range
     * @param  $dateTo      string    End date range
     *
     * @return array        List of array and count number
     */
    public static function count ($userID, $types, $dateFrom='', $dateTo='')
    {
        $result = array();
        //$todayTimestamp = strtotime(date('Y-m-d\TH:i:s.B\Z', $_SERVER['REQUEST_TIME']));
        $todayTimestamp = strtotime(date('Y-m-d'));

        foreach ($types as $type) {
            $tripQuery = DB::table('Trip AS t')
                ->where('t.IsArchived', 0)
                ->where('UserID', $userID)
                ->leftJoin('ReportTrip AS rt', 'rt.TripID', '=', 't.TripID')
                ->leftJoin('Report AS r', 'r.ReportID', '=', 'rt.ReportID');

            $found = true;
            switch ($type) {
                case 'upcoming':
                    $tripQuery->where('t.StartDate', '>', $todayTimestamp);
                    break;
                case 'current':
                    $tripQuery->where('t.EndDate', '>=', $todayTimestamp)
                        ->where('t.StartDate', '<=', $todayTimestamp);
                    break;
                case 'past':
                    $tripQuery->where('t.EndDate', '<', $todayTimestamp);
                    break;
                case 'reported':
                    $tripQuery->where('r.IsSubmitted', '>', 0);
                    break;
                case 'all':
                    break;
                default:
                    $found = false;
                    break;
            }

            //Additional date filters
            if ($found) {
                $tmpQuery = clone $tripQuery;
                $filterCount = $countAll = $tmpQuery->count();

                if ($dateFrom && $dateTo) {
                    $dateFrom = strtotime($dateFrom);
                    $dateTo   = strtotime($dateTo);

                    $tripQuery->where(function ($query) use ($dateFrom, $dateTo) {
                        $query->where(function ($query) use ($dateFrom, $dateTo) {
                            $query->where('t.StartDate', '<=', $dateFrom)
                                ->where('t.EndDate', '>=', $dateFrom);
                        })
                            ->orWhere(function ($query) use ($dateFrom, $dateTo) {
                                $query->where('t.StartDate', '>', $dateFrom)
                                    ->where('t.StartDate', '<', $dateTo);
                            });
                    });

                    //if (isset($dateTo)) {
                    //} else {
                        //$tripQuery->where('t.StartDate', '>=', $dateFrom);
                    //}

                    $filterCount = $tripQuery->count();
                }
            }

            //Count trips
            if ($found) $result[] = array('type' => $type, 'count' => $countAll, 'filterCount' => $filterCount);
        }

        return $result;
    }
	
	/**
	 * Get category list of a trip, including assigned items and amount
	 */
	public static function getDetail($tripID, $userID, $pdfTripType = '') {
        $trip = DB::table('Trip AS t')
                ->select('t.*', 'rt.Claimed', 'rt.Approved', 'rt.IsClaimed', 'r.IsApproved', 'r.ReportID', 'r.Reference AS Report', 'r.IsSubmitted', 'r.Submitter', 'ra.Approver')
                ->leftJoin('ReportTrip AS rt', 'rt.TripID', '=', 't.TripID')
                ->leftJoin('Report AS r', 'r.ReportID', '=', 'rt.ReportID')
                ->leftJoin('ReportApprover AS ra', 'ra.ReportID', '=', 'r.ReportID')
                ->where('t.TripID', $tripID)
                ->where('t.UserID', $userID)
                ->first();
        if ($trip) {
            //Set state for the returned trip
            self::staticSetState($trip);
            self::staticGetAmount($trip);
            $trip->Categories = Category::getListByApp('travel_expense', $trip->UserID, $trip->StartDate, $trip->EndDate, $tripID);
            
            $trip->ReportStatus = Report::staticSetStatus($trip, $userID);
            
            $trip->TripInfo = Trip::getById($tripID);
                        
            $reportByTrip =  Report::getDetail($trip->ReportID, $userID);
            
            $trip->ReportTitle = $reportByTrip ? $reportByTrip->Title : '';
            
            if (!$trip->ReportStatus) {
                $trip->ReportStatus = '';
            }            
            $trip->StartDate = date('Y-m-d\TH:i:s.B\Z', $trip->StartDate);
            $trip->EndDate = date('Y-m-d\TH:i:s.B\Z', $trip->EndDate);
            $trip->CreatedTime = date('Y-m-d\TH:i:s.B\Z', $trip->CreatedTime);
            $trip->ModifiedTime = date('Y-m-d\TH:i:s.B\Z', $trip->ModifiedTime);
            $trip->Attachments = File::getListByEntities(array($tripID), 'trip');
            $trip->Tags = Tag::getList($tripID, 'trip', true);
            if (is_null($trip->Claimed)) {
                $trip->Claimed = 0.00;
            } else {
                $trip->Claimed = number_format($trip->Claimed, 2, '.', '');
            }
            if (is_null($trip->Approved)) {
                $trip->Approved = 0.00;
            } else {
                $trip->Approved = number_format($trip->Approved, 2, '.', '');
            }
            //Genarate 
            $trip->CategoryItems = array();
            Trip::staticSetState($trip);
            
            $trip->Amount = 0;
            $trip->Items = Trip::getTripItems($trip->TripID);            
            $countReceipts = 1;
            $uniqueReceipts = array();
            $trip->HasImagesOrEmails = false;
            
            foreach ($trip->Items as $item) {
                if (!empty($pdfTripType)) {
                    $receiptData = Receipt::getReceiptImageAndRawData($item->ItemID);
                    $item->ReceiptImage = null;
                    $item->RawData = null;                         
                    if ($receiptData) {
                        $trip->HasImagesOrEmails = true;
                        if ($receiptData->ReceiptType != 2 && $receiptData->FilePath) {                            
                            $item->ReceiptImage = $receiptData;
                            if ($item->ReceiptImage) {
                                $item->ReceiptImage->FileExtension = substr($item->ReceiptImage->FilePath, -3);
                            }
                            if (!in_array($receiptData->FilePath, $uniqueReceipts)) {
                                $uniqueReceipts[$countReceipts] = $receiptData->FilePath;
                                $item->ReceiptImage->Used = false;
                                $item->ReceiptImage->Number = $countReceipts;
                                $countReceipts++;
                            } else {
                                $item->ReceiptImage->Number = array_search($item->ReceiptImage->FilePath, $uniqueReceipts);
                                $item->ReceiptImage->Used = true;
                            }
                            unset($item->ReceiptImage->RawData);
                        } else if ($receiptData->RawData) {
                            $trip->HasImagesOrEmails = true;
                            $item->RawData = $receiptData;
                            if (!in_array($receiptData->RawData, $uniqueReceipts)) {
                                $uniqueReceipts[$countReceipts] = $receiptData->RawData;
                                $item->RawData->Used = false;
                                $item->RawData->Number = $countReceipts;
                                $countReceipts++;
                            } else {
                                $item->RawData->Number = array_search($item->RawData->RawData, $uniqueReceipts);
                                $item->RawData->Used = true;
                            }
                        }
                    }
                    if ($item->CategoryID) {
                        if (isset($trip->CategoryItems[$item->CategoryID])) {
                            $trip->CategoryItems[$item->CategoryID]->Amount += $item->Amount;
                            
                        } else {                            
                            $trip->CategoryItems[$item->CategoryID] = new stdClass();
                            $trip->CategoryItems[$item->CategoryID]->Name = $item->CategoryName;
                            $trip->CategoryItems[$item->CategoryID]->Amount = $item->Amount;
                        }
                    }
                }                
                //End Genarate                 
                if (isset($tmpMemos[$item->ItemID])) {
                    $item->ReportMemos = $tmpMemos[$item->ItemID];
                } else {
                    $item->ReportMemos = array();
                }
                $trip->Amount += $item->Amount;
            }
            $trip->IsApproved = (bool) $trip->IsApproved;
            $trip->IsClaimed = (bool) $trip->IsClaimed;
        }
        return $trip;
    }

	
	/**
	 * Get item list of a trip, which will be used in the Report Detail screen
	 */
	public static function getTripItems($tripIDs, $pdfItemType = '')
	{
		if (! is_array($tripIDs)) {
			$tripIDs = array($tripIDs);
		}
		
		$query = DB::table('Item AS i')
				->select('c.Name AS CategoryName', 'i.CategoryID', 'i.Name', 'r.MerchantID', 'r.PurchaseTime', 'i.ExpensePeriodFrom', 
						'ti.Claimed', 'ti.Approved', 'i.Amount', 'i.ItemID', 'ti.IsClaimed', 'ti.IsApproved', 'i.ReceiptID', 'i.IsJoined')
				->join('TripItem AS ti', 'ti.TripItemID', '=', 'i.ItemID')
				->join('Receipt AS r', 'r.ReceiptID', '=', 'i.ReceiptID')
				->join('Category AS c', 'c.CategoryID', '=', 'i.CategoryID')
				->whereIn('ti.TripID', $tripIDs)
				->where('IsJoined', 0);
		
		if (! empty($pdfItemType)) {
			if ($pdfItemType == 'claimed') {
				$query->where('IsClaimed', '>', 0);
			}
			if ($pdfItemType == 'approved') {
				$query->where('IsApproved', '>', 0);
			}
		}
		
        //sort by category ID order
        $query->orderBy('c.CategoryID', 'ASC')
              ->orderBy('r.PurchaseTime', 'DESC');
        
		$items = $query->get();
		if (count($items)) {
			foreach ($items as $item) {
                $item->MerchantName = Merchant::getName($item->MerchantID);
				$item->Amount       = number_format($item->Amount, 2, '.', '');
				$item->Claimed      = number_format($item->Claimed, 2, '.', '');
				$item->Approved     = number_format($item->Approved, 2, '.', '');
				
				$item->IsApproved   = (bool) $item->IsApproved;
				$item->IsClaimed    = (bool) $item->IsClaimed;
				$item->ReportMemos  = array();
				
				$item->ExpensePeriodFrom = date('Y-m-d\TH:i:s.B\Z', $item->ExpensePeriodFrom);
				$item->PurchaseTime      = date('Y-m-d\TH:i:s.B\Z', $item->PurchaseTime);
			}
		}
		
		return $items;
	}
	
	public static function staticSetState($trip)
	{
		$trip->State = false;
		if (isset($trip->StartDate) && $trip->StartDate) {
			$todayTimestamp = strtotime(date('Y-m-d\TH:i:s.B\Z', $_SERVER['REQUEST_TIME']));
			if ($trip->StartDate > $todayTimestamp) {
				$trip->State = 'Upcoming';
			} else {
				if (isset($trip->EndDate) && $trip->EndDate) {
					if ($trip->EndDate >= $todayTimestamp) {
						$trip->State = 'Current';
					} else {
						$trip->State = 'Past';
					}
				} else {
					$trip->State = 'Current';
				}
			}
		}
		
		return $trip->State;
	}
	
	public static function staticGetAmount($trip)
	{
		$trip->Amount = 0;
		if (isset($trip->TripID) && isset($trip->StartDate) && $trip->StartDate) {
			$trip->Amount = DB::table('Item AS i')
					->select(DB::raw('SUM(ROUND(Amount, 2)) AS Amount'))
					->join('TripItem AS ti', 'ti.TripItemID', '=', 'i.ItemID')
					->where('ti.TripID', $trip->TripID)
					->where('i.IsJoined', 0)
					->pluck('Amount');
		}
		
		$trip->Amount = number_format($trip->Amount, 2, '.', '');
		return $trip->Amount;
	}
	
	public static function archiveList($tripIDs, $archived = 1)
	{
		if (! is_array($tripIDs)) {
			$tripIDs = array($tripIDs);
		}
		
		if (count($tripIDs)) {
			DB::table('Trip')
					->whereIn('TripID', $tripIDs)
					->update(array('IsArchived' => $archived));
		}
	}
	
	public static function checkRef($ref, $userID)
	{
		$lastRef = DB::table('Trip')->select('Reference')
				->where('Reference', 'LIKE', "{$ref}%")
				->where('UserID', $userID)
				->orderBy('Reference', 'DESC')
				->orderBy('TripID', 'DESC')->take(1)
				->pluck('Reference');
		
		if ($lastRef) {
			$diff = substr($lastRef, -1);
						
			if (is_numeric($diff)) {
				return $ref . 'B';
			} else {
				return substr($lastRef, 0, -1) . (++$diff);
			}
		}
		
		return $ref;
	}
	
	public function setState()
	{
		return self::staticSetState($this);
	}
	
	public function getAmount()
	{
		return self::staticGetAmount($this);
	}
    
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
        $query->select(array('r.*', 'rt.*', 're.*', 'ra.*', 'r.TripID', 'rt.ReportID', 'r.Reference'));
        // We always do "LEFT JOIN" between Trip and ReportTrip, ReportTrip and Report
		$query->leftJoin('ReportTrip AS rt', 'rt.TripID', '=', 'r.TripID')
				->leftJoin('Report AS re', 're.ReportID', '=', 'rt.ReportID')
				->leftJoin('ReportApprover AS ra', 'ra.ReportID', '=', 're.ReportID');
        
        if ($where != null) {
            if (isset($where['UserID'])) {
                $userID = $where['UserID'];
                
                $query->where(function($query) use ($userID) {
                    $query->where('r.UserID', $userID)
                          ->orWhere('ra.Approver', $userID);
                });
                
                if (isset($where['getTripApprover'])) {
                    $query->orWhere(function($query) use ($userID) {
                        $query->where('ra.Approver', $userID)
                            ->where('re.IsSubmitted', 1)
                            ->where('ra.IsDeleted', 0);
                    });
                    unset($where['getTripApprover']);
                }
                unset($where['UserID']);
            }
            
            if (isset($where['tripFree'])) {
                $query->whereNull('re.ReportID');
                unset($where['tripFree']);
            }
        }
        
        if(count($arrayReport) > 0) {
            $query->whereIn('re.ReportID', $arrayReport);
        }
        
        if ($where != null) {
            // trips?:: rIds <=> arrayReportID
            if (isset($where['rIds'])) {
                $query->whereIn('re.ReportID', $where['rIds']);
                unset($where['rIds']);
            }
            
            // trips?:: nTIds <=> arrayNotTripID
            if (isset($where['nTIds'])) {
                $query->whereNotIn('r.TripID', $where['nTIds']);
                unset($where['nTIds']);
            }
        }
        
    }
    
    public static function getAll(array $where = array(), array $sort = array(), $limit = 10, $offset = 0) 
    {
        $trips = parent::getAll($where, $sort, $limit, $offset);

        $tripIDList = array();
        foreach ($trips as $trip) {
            $tripIDList[] = $trip->TripID;
        }
        
        //We only query to get attachments for 1 time, then use a temp array to add attachments to the right trip
        $tmpAttachments = array();
        if (count($tripIDList)) {
            $attachmentList = File::getListByEntities($tripIDList, 'trip');
            //Add from query result to temp array
            if (count($attachmentList)) {
                foreach ($attachmentList as $attachment) {
                    $tmpAttachments[$attachment->EntityID][] = $attachment;
                }
            }
        }

        $tmpTags = array();
        if (count($tripIDList)) {
            $tagList = Tag::getList($tripIDList, 'trip');
            //Add from query result to temp array
            if (count($tagList)) {
                foreach ($tagList as $tag) {
                    $tmpTags[$tag->EntityID][] = $tag->Name;
                }
            }
        }
        
        if (count($trips)) {
			foreach ($trips as $trip) {
                //Set state for the returned trip
                self::staticSetState($trip);
                //get amount for the returned trip
                self::staticGetAmount($trip);
                
                $categories = Category::getListByApp('travel_expense', $trip->UserID, 0, 0, $trip->TripID);
                $stringCategory = "";
                
                foreach ($categories as $category) {
                    if(count($category->Items)) {
                        $stringCategory = $stringCategory . ',' . $category->CategoryID;
                    }
                }
                $trip->Categories = substr($stringCategory, 1);
                if(isset($where['UserID'])) {
                    $trip->ReportStatus = Report::staticSetStatusReport($trip, $where['UserID']);
                    if (! $trip->ReportStatus) {
                        $trip->ReportStatus = '';
                    }
                }

                $todayTimestamp = strtotime(date('Y-m-d\TH:i:s.B\Z', $_SERVER['REQUEST_TIME']));

                if ($trip->StartDate > $todayTimestamp) {
                    $trip->TripType = 'Upcoming Trips';
                } else if (($trip->StartDate <= $todayTimestamp) && ($trip->EndDate >= $todayTimestamp)) {
                    $trip->TripType = 'Current Trips';
                } else {
                    $trip->TripType = 'Past Trips';
                }
                
                $trip->EndDate = date('Y-m-d\TH:i:s.B\Z', $trip->EndDate);
                $trip->CreatedTime = date('Y-m-d\TH:i:s.B\Z', $trip->CreatedTime);
                $trip->ModifiedTime = date('Y-m-d\TH:i:s.B\Z', $trip->ModifiedTime);

                $trip->IsChecked = false;

                if (isset($tmpAttachments[$trip->TripID])) {
                    $trip->Attachments = $tmpAttachments[$trip->TripID];
                } else {
                    $trip->Attachments = array();
                }

                if (isset($tmpTags[$trip->TripID])) {
                    $trip->Tags = $tmpTags[$trip->TripID];
                } else {
                    $trip->Tags = array();
                }

                if (is_null($trip->ReportID)) {
                    $trip->ReportID = 0;
                } 
                
                if (is_null($trip->IsArchived)) {
                    $trip->IsArchived = 0;
                } 
                
                if (is_null($trip->IsClaimed)) {
                    $trip->IsClaimed = 0;
                } 
                
                if (is_null($trip->IsApproved)) {
                    $trip->IsApproved = 0;
                } 
                
                if (is_null($trip->IsSubmitted)) {
                    $trip->IsSubmitted = 0;
                } 
                
                if (is_null($trip->IsAllApproved)) {
                    $trip->IsAllApproved = 0;
                } 
                
                if (is_null($trip->Claimed)) {
                    $trip->Claimed = 0.00;
                } else {
                    $trip->Claimed = number_format($trip->Claimed, 2, '.', '');
                }

                if (is_null($trip->Approved)) {
                    $trip->Approved = 0.00;
                } else {
                    $trip->Approved = number_format($trip->Approved, 2, '.', '');
                }

                if (isset($where['addTrip'])) {
                    if ($trip->ReportID) {
                        $trip->IsNew = 0;
                    } else {
                        $trip->IsNew = 1;
                    }
                }
				
				$trip->StartDate = date('Y-m-d\TH:i:s.B\Z', $trip->StartDate);                
			}
		}
        
        return $trips;
    }
    
    public static function getById($tripID) 
    {
        $trip = parent::getById($tripID);
		
		if ($trip) {
			//Set state for the returned trip
			self::staticSetState($trip);
			self::staticGetAmount($trip);
			$trip->Categories = Category::getListByApp('travel_expense', $trip->UserID, 0, 0, $tripID);
			
			$trip->ReportStatus = Report::staticSetStatusReport($trip, $trip->UserID);
			if (! $trip->ReportStatus) {
				$trip->ReportStatus = '';
			}
            
            $todayTimestamp = strtotime(date('Y-m-d\TH:i:s.B\Z', $_SERVER['REQUEST_TIME']));

            if ($trip->StartDate > $todayTimestamp) {
                $trip->TripType = 'Upcoming Trips';
            } else if (($trip->StartDate <= $todayTimestamp) && ($trip->EndDate >= $todayTimestamp)) {
                $trip->TripType = 'Current Trips';
            } else {
                $trip->TripType = 'Past Trips';
            }
			
			$trip->StartDate = date('Y-m-d\TH:i:s.B\Z', $trip->StartDate);
			$trip->EndDate = date('Y-m-d\TH:i:s.B\Z', $trip->EndDate);
			$trip->CreatedTime = date('Y-m-d\TH:i:s.B\Z', $trip->CreatedTime);
			$trip->ModifiedTime = date('Y-m-d\TH:i:s.B\Z', $trip->ModifiedTime);
			
			$trip->Attachments = File::getListByEntities(array($tripID), 'trip');
			$trip->Tags = Tag::getList($tripID, 'trip', true);
			
			if (is_null($trip->Claimed)) {
				$trip->Claimed = 0.00;
			} else {
				$trip->Claimed = number_format($trip->Claimed, 2, '.', '');
			}

			if (is_null($trip->Approved)) {
				$trip->Approved = 0.00;
			} else {
				$trip->Approved = number_format($trip->Approved, 2, '.', '');
			}
		}
		
		return $trip;
    }
    
    /**
     * Use this method to build vaidator for both creating and updating receipt
     */
    public static function validateModel(&$inputs, $user, $trip = null) 
    {
        if (isset($inputs['StartDate']) && strpos($inputs['StartDate'], 'T') !== false) {
			$inputs['StartDate'] = substr(str_replace('T', ' ', $inputs['StartDate']), 0, -5);
		}
		
		if (isset($inputs['EndDate']) && strpos($inputs['EndDate'], 'T') !== false) {
			$inputs['EndDate'] = substr(str_replace('T', ' ', $inputs['EndDate']), 0, -5);
		}
        
        $rules = array(
            'Name' => array('required', 'max:255'),
            'Departure' => array('required', 'max:128'),
            'Arrival' => array('required', 'max:128'),
            'StartDate' => array('required', 'date', 'trip_date:' . $user->UserID . ',0,' . (isset($inputs['EndDate']) ? $inputs['EndDate'] : null)),
            'EndDate' => array('date'),
            'Reference' => array('required', 'max:45'),
            'Leg' => array('integer'),
        );

        if($trip != null) {
            if (isset($trip['TripID'])) {
                $inputs['TripID'] = $trip['TripID'];
                $rules['TripID'] = array('required');
                $rules['StartDate'] = array('required', 'date', 'trip_date:' . $user->UserID . ',' . $trip['TripID'] . ',' . (isset($inputs['EndDate']) ? $inputs['EndDate'] : null));
                if (isset($inputs['ReportID'])) {
                    $rules['TripID'] = array(
                        'required', 
						'not_assigned_to_report_mb:' . (isset($inputs['ReportID']) ? $inputs['ReportID'] : null));
                    
                    if($inputs['ReportID'] != 0) { 
                        $rules['ReportID'] = array(
                            'required', 
                            'reports_belong_to:' . $user->UserID
                        );
                    }
                }
            }
        }
		
        if($trip != null) {
            foreach ($rules as $key => $value) {
                if(!isset($inputs[$key])) {
                    unset($rules[$key]);
                }
            }
        }
        
		if (isset($inputs['StartDate'])) {
			$rules['EndDate'][] = 'not_before:' . $inputs['StartDate'];
		}
		
		$customMessages = array(
			'TripID.trips_belong_to' => 'Specified trip does not belong to the user who sent this request.',
			'Name.required' => 'Please enter trip name.',
			'Departure.required' => 'Please enter your departure.',
			'Arrival.required' => 'Please enter your arrival.',
			'StartDate.required' => 'Please choose a start date.',
            'StartDate.trip_date' => 'This trip overlaps with an existing trip, please edit your dates.',
			'Reference.required' => 'Please enter a reference.',
			'Leg.integer' => 'Leg must be an integer.',
			'EndDate.not_before' => 'End date must be equal or greater than start date.',
            'TripID.not_assigned_to_report_mb' => 'This trip is already assigned to another report'
		);
		
        if($trip != null) {
            if (isset($trip['TripID']) && ! is_array($trip['TripID'])) {
                $inputs['TripID'] = $trip['TripID'];
                $customMessages['TripID.trips_not_reported'] = 'This trip is reported. You cannot modify or delete it.';
            }
        }
        
		$validator = Validator::make($inputs, $rules, $customMessages);
		
		if ($validator->fails()) {
			return array('message' => $validator->messages()->all());
		}
		
		return array();
    }
    
    public static function processStore($post, $user) 
    {
        $trip = new Trip();
		$trip->Name = $post['Name'];
		$trip->Departure = $post['Departure'];
		$trip->Arrival = $post['Arrival'];
		$trip->StartDate = strtotime($post['StartDate']);
		$trip->EndDate = strtotime($post['EndDate']);
		
		if (isset($post['Leg'])) {
			$trip->Leg = $post['Leg'];
		}
		
		if (isset($post['Reference'])) {
			$trip->Reference = Trip::checkRef($post['Reference'], $user->UserID);
		}
		if (isset($post['Memo'])) {
			$trip->Memo = $post['Memo'];
		}
		
		$trip->CreatedTime = $_SERVER['REQUEST_TIME'];
		$trip->UserID = $user->UserID;
        
        if (isset($post['MobileSync']) && $post['MobileSync']) {
            $trip->MobileSync = $post['MobileSync'];
        }
        
		$trip->save();
		
		if (isset($post['Tags']) && is_array($post['Tags']) && count($post['Tags'])) {
			Tag::saveTags($trip->TripID, 'trip', $post['Tags']);
		}

        //Push server
//        $pushService = App::make('pushService');
//        $pushService->push('T:' . $trip->TripID, 'createTrip', $user);
		
        $createdTrip = Trip::getById($trip->TripID);
        return $createdTrip;
    }
    
    public static function processUpdate($put, $user, $trip) 
    {
        //Update reporttrip if user input ReportID
        if(isset($put['ReportID']) && $put['ReportID']) {       
            $reportTrip = DB::table('ReportTrip AS r')
				->where('r.TripID', $trip->TripID)
				->first();
            if($reportTrip) {
                $item = ReportTrip::find($reportTrip->ReportTripID);
                $item->ReportID = $put['ReportID'];
                $item->save();
            } else {
                DB::table('ReportTrip')
				->insert(array(
					'ReportID' => $put['ReportID'],
					'TripID' => $trip->TripID,
					'CreatedTime' => $_SERVER['REQUEST_TIME'],
				));
            }
        }
        
        if(isset($put['ReportID']) && $put['ReportID'] == 0) {
            $reportTrip = DB::table('ReportTrip AS r')
				->where('r.TripID', $trip->TripID)
				->first();
            if($reportTrip) {
                $item = ReportTrip::find($reportTrip->ReportTripID);
                $item->delete();
            } 
        }
        
        // Update this trip
        if (isset($put['Name']) && $put['Name'] != $trip->Name) {
            $trip->Name = $put['Name'];
        }

        if (isset($put['Departure']) && $put['Departure'] != $trip->Departure) {
            $trip->Departure = $put['Departure'];
        }

        if (isset($put['Arrival']) && $put['Arrival'] != $trip->Arrival) {
            $trip->Arrival = $put['Arrival'];
        }

        if (isset($put['Leg']) && $put['Leg'] != $trip->Leg) {
            $trip->Leg = $put['Leg'];
        }

        if (isset($put['StartDate'])) {
            if (strtotime($put['StartDate']) != $trip->StartDate) {
                $trip->StartDate = strtotime($put['StartDate']);
            }
        }

        if (isset($put['EndDate'])) {
            if (strtotime($put['EndDate']) != $trip->EndDate) {
                $trip->EndDate = strtotime($put['EndDate']);
            }
        }

        if (isset($put['Reference']) && $put['Reference'] != $trip->Reference) {
            $trip->Reference = Trip::checkRef($put['Reference'], $user->UserID);
        }

        if (isset($put['Memo']) && $put['Memo'] != $trip->Memo) {
            $trip->Memo = $put['Memo'];
        }

        if (isset($put['Tags']) && is_array($put['Tags']) && count($put['Tags'])) {
            Tag::saveTags($trip->TripID, 'trip', $put['Tags'], Tag::getList($trip->TripID, 'trip', true));
        }

        $trip->ModifiedTime = $_SERVER['REQUEST_TIME'];
        $trip->save();
    }
    
    public static function validateDestroy($tripIDs, $user)
    {
        if (! is_array($tripIDs)) {
			$tripIDs = array($tripIDs);
		}
        
        $customMessages = array();
		if (count($tripIDs) === 1) {
			$customMessages['trips_not_reported'] = 'This trip is reported. You cannot modify or delete it.';
		}
		
		// Validate to be sure that all specified trips belongs to the user who send this request
		$validator = Validator::make(
				array('TripIDs' => $tripIDs),
				array('TripIDs' => array('required', 'trips_belong_to:' . $user->UserID)),
				$customMessages);
		
		if ($validator->fails()) {
			return array('message' => $validator->messages()->all());
		}
        
        return array();
    }
    
    public static function processDestroy($tripIDs, $user) 
    {
        if (! is_array($tripIDs)) {
			$tripIDs = array($tripIDs);
		}
		
        $itemIDs = DB::table('TripItem')
            ->select('TripItemID')
            ->whereIn('TripID', $tripIDs)
            ->lists('TripItemID');

        if (count($itemIDs)) {
            // Update category amount
            CategoryAmount::updateAmountByItemIDs($itemIDs, $user->UserID);

            // Update items to be uncategorized
            DB::table('Item')
                ->whereIn('ItemID', $itemIDs)
                ->update(array(
                    'CategoryID' => 0,
                    'ExpensePeriodFrom' => null
            ));

            //Delete trip item relationship
            Item::deleteItemTripRelationship($itemIDs);

            //14/01/2014: Need to update receipts which contain these items to have the verify status = 1
            $receiptIDs = DB::table('Item')
                ->select('ReceiptID')
                ->whereIn('ItemID', $itemIDs)
                ->lists('ReceiptID');

            DB::table('Receipt')
                ->whereIn('ReceiptID', $receiptIDs)
                ->where('VerifyStatus', 2)
                ->update(array('VerifyStatus' => 1));
        }

        //Delete trips
        DB::table('Trip')
            ->whereIn('TripID', $tripIDs)
            ->delete();
    }
    
    public static function countAllKind($user, $tripType) 
    {
        $result = array();
        
        $todayTimestamp = strtotime(date('Y-m-d\TH:i:s.B\Z', $_SERVER['REQUEST_TIME']));
        // Count All Trips 
        $query = DB::table(static::$_table . ' as r');
        $query->where('r.UserID', $user->UserID);
        $result[0]['name'] = 'All Trips';
        $result[0]['total'] = 0;

        if(count($tripType)) {
            foreach($tripType as $index=>$type) {
                switch ($type) {
                    case 'past':
                        // Count Past Trips
                        $query = DB::table(static::$_table . ' as r');
                        $query->where('r.UserID', $user->UserID);
                        $query->where('r.StartDate', '<', $todayTimestamp);
                        $query->where('r.EndDate', '<', $todayTimestamp);
                        $result[$index+1]['name'] = 'Past Trips';
                        $result[$index+1]['total'] = $query->count();
                        break;
                    case 'current':
                        // Count Current Trips
                        $query = DB::table(static::$_table . ' as r');
                        $query->where('r.UserID', $user->UserID);
                        $query->where('r.StartDate', '<=', $todayTimestamp);
                        $query->where('r.EndDate', '>=', $todayTimestamp);
                        $result[$index+1]['name'] = 'Current Trips';
                        $result[$index+1]['total'] = $query->count();
                        break;
                    case 'upcoming':
                        // Count Upcoming Trips
                        $query = DB::table(static::$_table . ' as r');
                        $query->where('r.UserID', $user->UserID);
                        $query->where('r.StartDate', '>', $todayTimestamp);
                        $result[$index+1]['name'] = 'Upcoming Trips';
                        $result[$index+1]['total'] = $query->count();
                        break;
                    case 'reported':
                        // Count Reported Trips
                        $query = DB::table(static::$_table . ' as r');
                        static::onPreQuery($query);
                        $query->where('r.UserID', $user->UserID);
                        $query->where('re.IsSubmitted', 1);
                        $result[$index+1]['name'] = 'Reported Trips';
                        $result[$index+1]['total'] = $query->count();
                        break;
                    case 'archived':
                        // Count Archived Trips
                        $query = DB::table(static::$_table . ' as r');
                        $query->where('r.UserID', $user->UserID);
                        $query->where('r.IsArchived', '>', 0);
                        $result[$index+1]['name'] = 'Archived Trips';
                        $result[$index+1]['total'] = $query->count();
                        break;
                }
                // Count All Trips 
                $result[0]['name'] = 'All Trips';
                $result[0]['total'] = $result[0]['total'] + $result[$index+1]['total'];
            }
        } else {
            // Count Past Trips
            $query = DB::table(static::$_table . ' as r');
            $query->where('r.UserID', $user->UserID);
            $query->where('r.StartDate', '<', $todayTimestamp);
            $query->where('r.EndDate', '<', $todayTimestamp);
            $result[1]['name'] = 'Past Trips';
            $result[1]['total'] = $query->count();
            
            // Count Current Trips
            $query = DB::table(static::$_table . ' as r');
            $query->where('r.UserID', $user->UserID);
            $query->where('r.StartDate', '<=', $todayTimestamp);
            $query->where('r.EndDate', '>=', $todayTimestamp);
            $result[2]['name'] = 'Current Trips';
            $result[2]['total'] = $query->count();
            
            // Count Upcoming Trips
            $query = DB::table(static::$_table . ' as r');
            $query->where('r.UserID', $user->UserID);
            $query->where('r.StartDate', '>', $todayTimestamp);
            $result[3]['name'] = 'Upcoming Trips';
            $result[3]['total'] = $query->count();
            
            // Count Reported Trips
            $query = DB::table(static::$_table . ' as r');
            static::onPreQuery($query);
            $query->where('r.UserID', $user->UserID);
            $query->where('re.IsSubmitted', 1);
            $result[4]['name'] = 'Reported Trips';
            $result[4]['total'] = $query->count();
            
            // Count Archived Trips
            $query = DB::table(static::$_table . ' as r');
            $query->where('r.UserID', $user->UserID);
            $query->where('r.IsArchived', '>', 0);
            $result[5]['name'] = 'Archived Trips';
            $result[5]['total'] = $query->count();
            
            // Count All Trips 
            $result[0]['name'] = 'All Trips';
            $result[0]['total'] = $result[0]['total'] + $result[1]['total'] + $result[2]['total']
                    + $result[3]['total'] + $result[4]['total'] + $result[5]['total'];
        }

        return $result;
    }

    /**
     * Return ids of trips attached to a report
     * @param int $reportId
     */
    public static function getIdsByReport ($reportId) 
    {
        $query = DB::table('ReportTrip')->where('ReportID', $reportId);
        
        return $query->lists('TripID');
    }

    /**
     * Check if user have permission on report
     */
    public static function checkUserPermission($tripID, $userID) 
    {
        $q1 = DB::table('Trip')
            ->where('TripID', $tripID)
            ->where('UserID', $userID)
            ->get();
        if ($q1) return true;
        
        $q2 = DB::table('ReportTrip as rt')
            ->where('TripID', $tripID)
            ->pluck('ReportID');
        if ($q2) {
            return Report::checkUserPermission($q2, $userID);
        }
        
        return false;
    }
}
