<?php
class Report extends BaseModel
{
	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'Report';
    
    protected static $_table = 'Report';
	
	/**
	 * The primary key for the model.
	 *
	 * @var string
	 */
	protected $primaryKey = 'ReportID';
    
    protected static $_primaryKey = 'ReportID';
	
	/**
	 * Indicates if the model should be timestamped.
	 *
	 * @var bool
	 */
	public $timestamps = false;
       
        /*
         * Declare AWS SDK variable
         */        
        protected static $s3 = null;
	
	public static function getList($userID, $filter = array())
	{
		$reportQuery = DB::table('Report AS r')
				->leftJoin('ReportApprover AS ra', 'ra.ReportID', '=', 'r.ReportID')
				->leftJoin('User AS us', 'us.UserID', '=', 'r.Submitter')
				->leftJoin('User AS ua', 'ua.UserID', '=', 'ra.Approver')
				->select('r.*', 'ra.Approver', 'us.Email AS SubmitterEmail', 'ua.Email AS ApproverEmail');
		
		if (! isset($filter['type'])) {
			$filter['type'] = 'all';
		}
		
		if ($filter['type'] == 'pending') {
			$reportQuery->where('Approver', $userID)
					->where('ra.IsArchived', 0)
					->where('IsSubmitted', 1)
                    ->where('IsApproved', 0)
					->where('IsDeleted', 0);
		} else {
			$reportQuery->where(function($query) use ($userID, $filter) {
                if ($filter['role'] == 'submitter') {
                    $query->where(function($query) use ($userID, $filter) {
                        $query->where('Submitter', $userID);
                        $query->where('r.IsArchived', 0);
                    });
                }

                if ($filter['role'] == 'approver') {
                    $query->where(function ($query) use ($userID, $filter) {
                        $query->where('Approver', $userID)
                            ->where('IsSubmitted', 1)
                            ->where('IsDeleted', 0);
                        $query->where('ra.IsArchived', 0);
                    });
                }
			});
		}
		
		if ($filter['type'] == 'draft') {
			$reportQuery->where('IsSubmitted', 0);
		}
		
		if ($filter['type'] == 'submitted') {
			$reportQuery->where('Submitter', $userID)
                        ->where('Approver', '<>', $userID)
                        ->where('IsSubmitted', 1)
                        ->where('IsApproved', 0);
		}
		
		if ($filter['type'] == 'approved') {
			$reportQuery->where('IsApproved', 1);
		}
		
		if ($filter['type'] == 'rejected') {
			$reportQuery->where('IsApproved', 2);
		}
		
		if (! isset($filter['from'])) {
			$filter['allDate'] = true;
		}
		
		if (! isset($filter['allDate']) || ! $filter['allDate']) {
            $filter['from'] = strtotime($filter['from']);
			$reportQuery->where('Date', '>=', $filter['from']);

			if (isset($filter['to'])) {
				//$filter['to'] = strtotime('+1 day', strtotime($filter['to']));
                $filter['to'] = strtotime($filter['to']);
				$reportQuery->where('Date', '<=', $filter['to']);
			}
		}
		
        $reportQuery->orderBy('r.Date', 'desc');

        //Query pagination
        if (isset($filter['queryFrom'], $filter['queryStep'])) {
            $queryCopy = clone $reportQuery;
            $total     = $queryCopy->count();

            //Skip pagination if total is zero
            if ($total > 0 ) {
                $pageStep  = intval($filter['queryStep']);

                if ($filter['queryFrom'] == 'last') {
                    //For the last page, we need to calculate since the queryStep is unpredictable
                    $remainder = intval($total % $pageStep);
                    $pageFrom  = $total - ($remainder ?  $remainder : $pageStep) + 1;
                } else {
                    $pageFrom  = intval($filter['queryFrom']);
                }

                $reportQuery->skip($pageFrom-1)
                    ->take($pageStep);
            }
        }

		$reports = $reportQuery->get();
		$reportIDList = $reportQuery->lists('ReportID');
		
		$tmpAttachments = array();
		if (count($reportIDList)) {
			$attachmentList = File::getListByEntities($reportIDList, 'report');
			//Add from query result to temp array
			if (count($attachmentList)) {
				foreach ($attachmentList as $attachment) {
					$tmpAttachments[$attachment->EntityID][] = $attachment;
				}
			}
		}
		
		if (count($reports)) {
            foreach ($reports as $k => $report) {
                //Quypv 2014-11-11:
                //In case of display rejected report for approver. Use backup record instead (if possible)
                if (Report::isRejected($report->ReportID) && $report->Approver == $userID) {
                    $rejectedRecord = ReportRejected::getRejectedRecord($report->ReportID);

                    if ($rejectedRecord) {
                        $reports[$k] = $rejectedRecord;
                        $reports[$k]->IsRejectedRecord = true;
                    }
                }
            }

			foreach ($reports as $report) {
                if (isset($report->Trips)) unset($report->Trips);
                //if (isset($report->LastRejected)) unset($report->LastRejected);

                $report->Status      = self::staticSetStatus($report, $userID);
                $report->CreatedTime = date('Y-m-d\TH:i:s.B\Z', $report->CreatedTime);

                if (empty($report->IsRejectedRecord)) {
                    $report->Date    = date('Y-m-d\TH:i:s.B\Z', $report->Date);

                    //Temporarily calculate amount for report by this way
                    $trips = Trip::getList($userID, array(
                        'reportID' => $report->ReportID
                    ));
                    $report->Amount = 0;
                    if (count($trips)) {
                        foreach ($trips as $trip) {
                            Trip::staticGetAmount($trip);
                            $report->Amount += $trip->Amount;
                        }
                    }
                }

                $report->ReportType = self::staticSetStatusReport($report, $userID);
                if ($report->Submitter == $userID && ($report->Approver != $userID || (! $report->IsSubmitted && $report->Approver == $userID))) {
                    $report->IsSubmitter = 1;
                } else {
                    $report->IsSubmitter = 0;
                }

                if (isset($tmpAttachments[$report->ReportID])) {
                    $report->Attachments = $tmpAttachments[$report->ReportID];
                } else {
                    $report->Attachments = array();
                }

                $report->IsChecked = false;
			}
		}
		
		return $reports;
	}

    /**
     * Count number of reports by type
     *
     * @param  $userID      int       User ID
     * @param  $types       array     Array of type name
     * @param  $role        string    User role: submitter/approver
     * @param  $dateFrom    string    Start date range
     * @param  $dateTo      string    End date range
     *
     * @return array    List of array and count number
     */
    public static function count ($userID, $types, $role, $dateFrom='', $dateTo='')
    {
        $result = array();

        foreach ($types as $type) {
            $reportQuery = DB::table('Report AS r')
                ->leftJoin('ReportApprover AS ra', 'ra.ReportID', '=', 'r.ReportID')
                ->where(function($query) use ($userID, $type, $role) {
                    if ($role == 'submitter') {
                        $query->where(function($query) use ($userID) {
                            $query->where('Submitter', $userID);
                            $query->where('r.IsArchived', 0);

                        });
                    }

                    if ($role == 'approver') {
                        $query->where(function($query) use ($userID) {
                            $query->where('Approver', $userID)
                                ->where('IsSubmitted', 1)
                                ->where('IsDeleted', 0);
                            $query->where('ra.IsArchived', 0);

                        });
                    }
                });

            $found = true;
            switch ($type) {
                case 'draft':
                    $reportQuery->where('IsSubmitted', 0)
                        ->where('Submitter', $userID);
                    break;
                case 'submitted':
                    $reportQuery->where('Submitter', $userID)
                        ->where('ra.Approver', '<>', $userID)
                        ->where('IsSubmitted', 1)
                        ->where('IsApproved', 0);
                    break;
                case 'pending':
                    $reportQuery->where('ra.Approver', $userID)
                        ->where('ra.IsArchived', 0)
                        ->where('IsSubmitted', 1)
                        ->where('IsApproved', 0)
                        ->where('IsDeleted', 0);
                    break;
                case 'approved':
                    $reportQuery->where('IsApproved', 1);
                    break;
                case 'rejected':
                    $reportQuery->where('IsApproved', 2);
                    break;
                case 'all':
                    break;
                default:
                    $found = false;
                    break;
            }

            //Additional date filters
            if ($found) {
                $tmpQuery = clone $reportQuery;
                $filterCount = $countAll = $tmpQuery->count();

                if ($dateFrom && $dateTo) {
                    $dateFrom = strtotime($dateFrom);
                    $dateTo   = strtotime($dateTo);

                    $reportQuery->where('r.Date', '>=', $dateFrom)
                        ->where('r.Date', '<=', $dateTo);

                    $filterCount = $reportQuery->count();

                    if ($type == 'all') {
                        //dd($filterCount);
                    }

                }
            }

            //Count trips
            if ($found) $result[] = array('type' => $type, 'count' => $countAll, 'filterCount' => $filterCount);
        }

        return $result;
    }

    public static function getDetail($reportID, $userID, $options = array())
	{
        isset($options['pdfItemType'])? : $options['pdfItemType'] = '';

        //Quypv 2014-11-11:
        //In case of display rejected report for approver. Return backup record instead (if possible)
        if (Report::isRejected($reportID) && ReportApprover::isApprover($userID, $reportID) &&
            empty($options['skipRejectedRecord'])) {

            if (!empty($options['pdfItemType'])) {
                $report = ReportRejected::getRejectedRecord($reportID, $options['pdfItemType']);
            } else {
                $report = ReportRejected::getRejectedRecord($reportID);
            }

            if ($report) return $report;
        }

        //Else: parsing report data as normal
		$report = DB::table('Report AS r')
				->select('r.*',  'ra.Approver', 'ra.IsArchived AS ApproverIsArchived', 'ra.CreatedTime', 
						'ua.Email AS ApproverEmail', 'us.Email AS SubmitterEmail', 
						'pa.CompanyName AS ApproverCompanyName', 'ps.CompanyName AS SubmitterCompanyName', 
						'ps.FirstName AS SubmitterFirstName', 'ps.LastName AS SubmitterLastName',
						'pa.FirstName AS ApproverFirstName', 'pa.LastName AS ApproverLastName')
				->leftJoin('ReportApprover AS ra', 'ra.ReportID', '=', 'r.ReportID')
				->leftJoin('User AS ua', 'ua.UserID', '=', 'ra.Approver')
				->leftJoin('Profile AS pa', 'pa.UserID', '=', 'ua.UserID')
				->leftJoin('User AS us', 'us.UserID', '=', 'r.Submitter')
				->leftJoin('Profile AS ps', 'ps.UserID', '=', 'us.UserID')
				->where('r.ReportID', $reportID)
				->where(function($query) use ($userID) {
					$query->where('Submitter', $userID)
						->orWhere('Approver', $userID);
				})->first();
				
		if ($report) {
			$report->Status = self::staticSetStatus($report, $userID);
			
			if ($report->Submitter == $userID && ($report->Approver != $userID || (! $report->IsSubmitted && $report->Approver == $userID))) {
				$report->IsSubmitter = 1;
			} else {
				$report->IsSubmitter = 0;
			}
		
			//Get trips
			$report->Trips = Trip::getList($userID, array(
				'reportID' => $reportID,
                'order'    => 'reportTripAsc'
			));

			$report->Attachments = File::getListByEntities($reportID, 'report');
			$reportMemos = ReportMemo::getList($report->ReportID);
			$tmpMemos = array();
			if (count($reportMemos)) {
				foreach ($reportMemos as $memo) {
					$memo->CreatedDate = date('d-M-Y', $memo->CreatedTime);
					$memo->CreatedTime = date('h:i A', $memo->CreatedTime);
					if ($report->Approver != $memo->UserID) {
						$memo->SenderType = 'Submitter';
					} else {
						$memo->SenderType = 'Approver';
					}
					$tmpMemos[$memo->ItemID][] = $memo;
				}
			}
			
			$report->Amount = 0;
			
			if (! empty($options['pdfItemType'])) {
				$report->Categories = array();
				$report->HasImagesOrEmails = false;
			}
			
			if (count($report->Trips)) {
				foreach ($report->Trips as $trip) {
					Trip::staticSetState($trip);

					$trip->Amount = 0;
					$trip->Items = Trip::getTripItems($trip->TripID, $options['pdfItemType']);
					$countReceipts = 1;
					$uniqueReceipts = array();
					
					foreach ($trip->Items as $item) {
						if (! empty($options['pdfItemType'])) {
							$receiptData = Receipt::getReceiptImageAndRawData($item->ItemID);
							
							$item->ReceiptImage = null;
							$item->RawData = null;
							
							if ($receiptData) {
								if ($receiptData->ReceiptType != 2 && $receiptData->FilePath) {
									$report->HasImagesOrEmails = true;
									$item->ReceiptImage = $receiptData;
									if ($item->ReceiptImage) {
										$item->ReceiptImage->FileExtension = substr($item->ReceiptImage->FilePath, -3);
									}
									
									if (! in_array($receiptData->FilePath, $uniqueReceipts)) {
										$uniqueReceipts[$countReceipts] = $receiptData->FilePath;
										$item->ReceiptImage->Used = false;
										$item->ReceiptImage->Number = $countReceipts;
										$countReceipts++;
									} else {
										$item->ReceiptImage->Number = array_search($item->ReceiptImage->FilePath, $uniqueReceipts);
										$item->ReceiptImage->Used = true;
									}
									
									unset($item->ReceiptImage->RawData);
								}
								else if ($receiptData->RawData) {
									$report->HasImagesOrEmails = true;
									$item->RawData = $receiptData;
									
									if (! in_array($receiptData->RawData, $uniqueReceipts)) {
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
								if (isset($report->Categories[$item->CategoryID])) {
									$report->Categories[$item->CategoryID]->Amount += $item->Amount;
								} else {
									$report->Categories[$item->CategoryID] = new stdClass();
									$report->Categories[$item->CategoryID]->Name = $item->CategoryName;
									$report->Categories[$item->CategoryID]->Amount = $item->Amount;
								}
							}
						}
						
						if (isset($tmpMemos[$item->ItemID])) {
							$item->ReportMemos = $tmpMemos[$item->ItemID];
						} else {
							$item->ReportMemos = array();
						}
						
						$trip->Amount += $item->Amount;
					}
					
					$report->Amount += $trip->Amount;
					
					$trip->IsApproved = (bool) $trip->IsApproved;
					$trip->IsClaimed = (bool) $trip->IsClaimed;
				}
			}
			
			$report->Date = date('Y-m-d\TH:i:s.B\Z', $report->Date);
//			$report->Amount = number_format($report->Amount, 2, '.', '');
			$report->Claimed = number_format($report->Claimed, 2, '.', '');
			$report->Approved = number_format($report->Approved, 2, '.', '');
			//$report->IsApproved = (bool) $report->IsApproved;
            $report->IsApproved = $report->IsApproved;
			$report->IsAllApproved = (bool) $report->IsAllApproved;
			$report->IsClaimed = (bool) $report->IsClaimed;
		}
		
		return $report;
	}
	
	public static function archiveList($reportIDs, $userID, $archived = 1) 
	{
		if (! is_array($reportIDs)) {
			$reportIDs = array($reportIDs);
		}
		
		$reports = DB::table('Report As r')
				->leftJoin('ReportApprover AS ra', 'ra.ReportID', '=', 'r.ReportID')
				->select('r.ReportID', 'Submitter', 'Approver')
				->whereIn('r.ReportID', $reportIDs)
				->where(function($query) use ($userID) {
					$query->where('r.Submitter', $userID)
						->orWhere('ra.Approver', $userID);
				})->get();
		
		$submittedReportIDs = array();
		$approvedReportIDs = array();
		foreach ($reports as $report) {
			if ($report->Submitter == $userID) {
				$submittedReportIDs[] = $report->ReportID;
			} 
            if ($report->Approver == $userID) {
				$approvedReportIDs[] = $report->ReportID;
			}
		}
		
		if (count($submittedReportIDs)) {
			DB::table('Report')
					->whereIn('ReportID', $submittedReportIDs)
					->update(array('IsArchived' => $archived));
		}
		
		if (count($approvedReportIDs)) {
			DB::table('ReportApprover')
					->whereIn('ReportID', $approvedReportIDs)
					->update(array('IsArchived' => $archived));
		}
	}
	
	public static function deleteList($reportIDs) 
	{
		if (! is_array($reportIDs)) {
			$reportIDs = array($reportIDs);
		}
		
		DB::table('Report')
				->whereIn('ReportID', $reportIDs)
				->delete();
	}
	
	public static function addTripRelationships($reportID, $trips)
	{
		if (! is_array($trips)) {
			$trips = array($trips);
		}
		
		$insert = array();
		if (count($trips)) {
			foreach ($trips as $trip) {
				$insert[] = array(
						'ReportID' => $reportID,
						'TripID' => $trip->TripID,
						'Claimed' => $trip->Claimed,
						'IsClaimed' => $trip->IsClaimed,
						'CreatedTime' => $_SERVER['REQUEST_TIME']
					);
			}
		}
		
		if (count($insert)) {
			DB::table('ReportTrip')
					->insert($insert);
		}
	}
	
	public static function removeTripRelationships($reportIDs, $tripIDs = array())
	{
		if (! is_array($tripIDs)) {
			$tripIDs = array($tripIDs);
		}
		
		if (! is_array($reportIDs)) {
			$reportIDs = array($reportIDs);
		}
		
		$query = DB::table('ReportTrip')
				->whereIn('ReportID', $reportIDs);
		
		if (count($tripIDs)) {
			$query->whereIn('TripID', $tripIDs);
		} else {
			$tripIDs = $query->lists('TripID');
		}
				
		$query->delete();
		
		if (count($tripIDs)) {
			DB::table('TripItem')
					->whereIn('TripID', $tripIDs)
					->update(array(
						'IsClaimed' => 0,
						'IsApproved' => 0,
						'Claimed'=> 0,
						'Approved' => 0,
					));
		}
	}
	
	public static function staticSetStatus($report, $userID)
	{
        $isApproved = isset ($report->IsReportApproved) ?
            $report->IsReportApproved :
            $report->IsApproved;

        if ($isApproved == 1) {
            return 'Approved';
        } else if ($isApproved == 2) {
            return 'Rejected';
        }

		if (isset($report->IsSubmitted)) {
			if (! $report->IsSubmitted) {
				return 'Draft';
			} else if (isset($report->Submitter) && $report->Submitter == $userID && $report->Approver != $userID) {
				return 'Submitted';
			} else {
				return 'Pending';
			}
		}
		
		return false;
	}
    
    public static function staticSetStatusReport($report, $userID)
	{
		if (isset($report->IsApproved)) {
			if ($report->IsApproved == 1) {
				return 'Approved Reports';
			} else if ($report->IsApproved == 2) {
				return 'Rejected Reports';
			}
		}
		
		if (isset($report->IsSubmitted)) {
			if (! $report->IsSubmitted) {
				return 'Draft Reports';
			} else if (isset($report->Submitter) && $report->Submitter == $userID && $report->Approver != $userID) {
				return 'Pending Approval';
			} else {
				return 'Pending Reports';
			}
		}
		
		return false;
	}
	
	public static function classifyReportIDs($reportIDs, $userID)
	{
		$reportIDs = is_array($reportIDs) ? $reportIDs : array($reportIDs);
		
		$return = array(
			'submitted' => array(),
			'approved' => array(),
		);
		
		if (count($reportIDs)) {
			$return['submitted'] = DB::table('Report')
					->whereIn('ReportID', $reportIDs)
					->where('Submitter', $userID)
					->lists('ReportID');
			
			$return['approved'] = DB::table('ReportApprover')
					->whereIn('ReportID', $reportIDs)
					->where('Approver', $userID)
					->lists('ReportID');
		}
		
		return $return;
	}
	
	public static function checkRef($ref, $userID)
	{
		$lastRef = DB::table('Report')->select('Reference')
				->where('Reference', 'LIKE', $ref . '%')
				->where('Submitter', $userID)
				->orderBy('Reference', 'DESC')
				->orderBy('ReportID', 'DESC')->take(1)
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
	
	public function setStatus($userID)
	{
		return self::staticSetStatus($this, $userID);
	}
    
    public static function onPreQuery(\Illuminate\Database\Query\Builder $query, &$where = null)
    {
        $query->select(DB::raw('CONCAT(proa.FirstName, " ", proa.LastName) AS ApproverName, CONCAT(pros.FirstName, " ", pros.LastName) AS SubmitterName, r.*, ra.Approver, us.Email AS SubmitterEmail, ua.Email AS ApproverEmail, rr.Json AS LatestRejected'));

        $query->leftJoin('ReportApprover AS ra', 'ra.ReportID', '=', 'r.ReportID')
            ->leftJoin('User AS us', 'us.UserID', '=', 'r.Submitter')
            ->leftJoin('User AS ua', 'ua.UserID', '=', 'ra.Approver')
            ->leftJoin('Profile AS pros', 'pros.UserID', '=', 'us.UserID')
            ->leftJoin('Profile AS proa', 'proa.UserID', '=', 'ua.UserID')
            ->leftJoin('ReportRejected as rr', function($join) {
                $join->on('r.ReportID', '=', 'rr.ReportID')
                    ->on('rr.Usage', '=', DB::raw('"report"'));
            });

        if ($where != null) {
            
            if (isset($where['arrayNotReportID'])) {
                $query->whereNotIn('r.ReportID', $where['arrayNotReportID']);
                unset($where['arrayNotReportID']);
            }
            
            if (isset($where['tripCount'])) {
                $query->leftJoin('ReportTrip AS rt', 'r.ReportID', '=', 'rt.ReportID')
                    ->havingRaw(DB::raw('COUNT(rt.TripID) <= ' . (int)$where['tripCount']));
                $query->groupBy('r.ReportID');
                unset($where['tripCount']);
            }
            // tripitems?:: rIds <=> arrayReceiptID
            if (isset($where['rIds'])) {
                $query->leftJoin('ReportTrip AS ret', 'r.ReportID', '=', 'ret.ReportID')
                    ->leftJoin('TripItem AS ti', 'ret.TripID', '=', 'ti.TripID')
                    ->leftJoin('Item AS it', 'ti.TripItemID', '=', 'it.ItemID')
                    ->leftJoin('Receipt AS rec', 'it.ReceiptID', '=', 'rec.ReceiptID');
                $query->whereIn('rec.ReceiptID', $where['rIds']);
                $query->distinct();
                unset($where['rIds']);
                if (isset($where['UserID'])) {
                    unset($where['UserID']);
                }
            }
            
            if (isset($where['UserID'])) {
                $userID = $where['UserID'];
                $query->where(function($query) use ($userID) {
                    $query->where('r.Submitter', $userID)
                        ->orWhere(function($query) use ($userID) {
                        $query->where('ra.Approver', $userID)
                            ->where('IsSubmitted', 1)
                            ->where('IsDeleted', 0);
                    });
                });
                
                unset($where['UserID']);
            }
            
        }
    }
    
    public static function getAll(array $where = array(), array $sort = array(), $limit = 10, $offset = 0) 
    {
        $userID = $where['UserID'];
        
        $reports = parent::getAll($where, $sort, $limit, $offset);
        
        $reportIDList = array();
        foreach ($reports as $report) {
            $reportIDList[] = $report->ReportID;
        }
        
		$tmpAttachments = array();
		if (count($reportIDList)) {
			$attachmentList = File::getListByEntities($reportIDList, 'report');
			//Add from query result to temp array
			if (count($attachmentList)) {
				foreach ($attachmentList as $attachment) {
					$tmpAttachments[$attachment->EntityID][] = $attachment;
				}
			}
		}
		
		if (count($reports)) {
			foreach ($reports as $report) {
				$report->ReportType = self::staticSetStatusReport($report, $userID);
				$report->Date = date('Y-m-d\TH:i:s.B\Z', $report->Date);
				$report->CreatedTime = date('Y-m-d\TH:i:s.B\Z', $report->CreatedTime);
				
				//Temporarily calculate amount for report by this way
				$trips = Trip::getList($userID, array(
					'reportID' => $report->ReportID
				));
				$report->Amount = 0;
				if (count ($trips)) {
					foreach ($trips as $trip) {
						Trip::staticGetAmount($trip);
						$report->Amount += $trip->Amount;
					}
				}
				
				if ($report->Submitter == $userID && ($report->Approver != $userID || (! $report->IsSubmitted && $report->Approver == $userID))) {
					$report->IsSubmitter = 1;
				} else {
					$report->IsSubmitter = 0;
				}
				
				if (isset($tmpAttachments[$report->ReportID])) {
					$report->Attachments = $tmpAttachments[$report->ReportID];
				} else {
					$report->Attachments = array();
				}
				
				$report->IsChecked = false;
			}
		}
        
        return $reports;
    }
    
    public static function getByIdAndUser($reportId, $user) 
    {
        $report = parent::getById($reportId);
        $userID = $user->UserID;
        
		if($report) {
            $reportIDList = array();
            $reportIDList[] = $report->ReportID;

            $tmpAttachments = array();
            if (count($reportIDList)) {
                $attachmentList = File::getListByEntities($reportIDList, 'report');
                //Add from query result to temp array
                if (count($attachmentList)) {
                    foreach ($attachmentList as $attachment) {
                        $tmpAttachments[$attachment->EntityID][] = $attachment;
                    }
                }
            }
		
            $report->ReportType = self::staticSetStatusReport($report, $userID);
            $report->Date = date('Y-m-d\TH:i:s.B\Z', $report->Date);
            $report->CreatedTime = date('Y-m-d\TH:i:s.B\Z', $report->CreatedTime);

            //Temporarily calculate amount for report by this way
            $trips = Trip::getList($userID, array(
                'reportID' => $report->ReportID
            ));
            $report->Amount = 0;
            if (count ($trips)) {
                foreach ($trips as $trip) {
                    Trip::staticGetAmount($trip);
                    $report->Amount += $trip->Amount;
                }
            }

            if ($report->Submitter == $userID && ($report->Approver != $userID || (! $report->IsSubmitted && $report->Approver == $userID))) {
                $report->IsSubmitter = 1;
            } else {
                $report->IsSubmitter = 0;
            }

            if (isset($tmpAttachments[$report->ReportID])) {
                $report->Attachments = $tmpAttachments[$report->ReportID];
            } else {
                $report->Attachments = array();
            }

            $report->IsChecked = false;
		}

        return $report;
    }
    
    public static function onPreCount(\Illuminate\Database\Query\Builder &$query)
    {
        $query->leftJoin('ReportApprover AS ra', 'ra.ReportID', '=', 'r.ReportID')
            ->leftJoin('User AS us', 'us.UserID', '=', 'r.Submitter')
            ->leftJoin('User AS ua', 'ua.UserID', '=', 'ra.Approver')
            ->select('r.*', 'ra.Approver', 'us.Email AS SubmitterEmail', 'ua.Email AS ApproverEmail');
    }
    
    public static function countAllKind($user, $reportType) 
    {
        $result = array();
        
        // Count All Reports
        $query = DB::table(static::$_table . ' as r');
        $where['UserID'] = $user->UserID;
        self::onPreQuery($query, $where);
        $result[0]['name'] = 'All Reports';
        $result[0]['total'] = 0;

        if(count($reportType)) {
            foreach($reportType as $index=>$type) {
                switch ($type) {
                    case 'draft':
                        // Count Draft Reports
                        $query = DB::table(static::$_table . ' as r');
                        static::onPreCount($query);
                        $query->where('Submitter', $user->UserID)
                            ->where('IsSubmitted', 0);
                        $result[$index+1]['name'] = 'Draft Reports';
                        $result[$index+1]['total'] = $query->count();
                        break;
                    case 'submitted':
                        // Count Submitted Reports
                        $query = DB::table(static::$_table . ' as r');
                        static::onPreCount($query);
                        $query->where('Submitter', $user->UserID)
                            ->where('IsSubmitted', 1)    
                            ->where('r.IsApproved', 0);
                        $result[$index+1]['name'] = 'Submitted Reports';
                        $result[$index+1]['total'] = $query->count();
                        break;
                    case 'pending':
                        // Count Pending Reports
                        $query = DB::table(static::$_table . ' as r');
                        static::onPreCount($query);
                        $query->where('Approver', $user->UserID)
                            ->where('ra.IsArchived', 0)
                            ->where('IsSubmitted', 1)
                            ->where('IsDeleted', 0)
                            ->where('r.IsApproved', 0);
                        $result[$index+1]['name'] = 'Pending Reports';
                        $result[$index+1]['total'] = $query->count();
                        break;
                    case 'approved':
                        // Count Approved Report
                        $query = DB::table(static::$_table . ' as r');
                        static::onPreCount($query);
                        $query->where(function($qr) use($user){
                            $qr->where('Approver', $user->UserID)
                                ->orWhere('Submitter', $user->UserID);
                        });
                        $query->where('r.IsApproved', 1);  
                        $result[$index+1]['name'] = 'Approved Reports';
                        $result[$index+1]['total'] = $query->count();
                        break;
                    case 'rejected':
                        // Count Rejected Reports
                        $query = DB::table(static::$_table . ' as r');
                        static::onPreCount($query);
                        $query->where(function($qr) use($user){
                            $qr->where('Approver', $user->UserID)
                                ->orWhere('Submitter', $user->UserID);
                        });
                        $query->where('r.IsApproved', 2);  
                        $result[$index+1]['name'] = 'Rejected Reports';
                        $result[$index+1]['total'] = $query->count();
                        break;
                    case 'archived':
                        // Count Archived Reports
                        $query = DB::table(static::$_table . ' as r');
                        static::onPreCount($query);
                        $query->where(function($qr) use($user){
                            $qr->where('Approver', $user->UserID)
                                ->orWhere('Submitter', $user->UserID);
                        });
                        $query->where(function($qr){
                            $qr->where('r.IsArchived', 1)
                                ->orWhere('ra.IsArchived', 1);
                        });
                        $result[$index+1]['name'] = 'Archived Reports';
                        $result[$index+1]['total'] = $query->count();
                        break;
                }
                // Count All Reports
                $result[0]['name'] = 'All Reports';
                $result[0]['total'] = $result[0]['total'] + $result[$index+1]['total'];
            }
        } else {
            // Count Draft Reports
            $query = DB::table(static::$_table . ' as r');
            static::onPreCount($query);
            $query->where('Submitter', $user->UserID)
                ->where('IsSubmitted', 0);
            $result[1]['name'] = 'Draft Reports';
            $result[1]['total'] = $query->count();
            
            // Count Submitted Reports
            $query = DB::table(static::$_table . ' as r');
            static::onPreCount($query);
            $query->where('Submitter', $user->UserID)
                ->where('IsSubmitted', 1)    
                ->where('r.IsApproved', 0);
            $result[2]['name'] = 'Submitted Reports';
            $result[2]['total'] = $query->count();
            
            // Count Pending Reports
            $query = DB::table(static::$_table . ' as r');
            static::onPreCount($query);
            $query->where('Approver', $user->UserID)
                ->where('ra.IsArchived', 0)
                ->where('IsSubmitted', 1)
                ->where('IsDeleted', 0)
                ->where('r.IsApproved', 0);
            $result[3]['name'] = 'Pending Reports';
            $result[3]['total'] = $query->count();
            
            // Count Approved Report
            $query = DB::table(static::$_table . ' as r');
            static::onPreCount($query);
            $query->where(function($qr) use($user){
                $qr->where('Approver', $user->UserID)
                    ->orWhere('Submitter', $user->UserID);
            });
            $query->where('r.IsApproved', 1);  
            $result[4]['name'] = 'Approved Reports';
            $result[4]['total'] = $query->count();
            
            // Count Rejected Reports
            $query = DB::table(static::$_table . ' as r');
            static::onPreCount($query);
            $query->where(function($qr) use($user){
                $qr->where('Approver', $user->UserID)
                    ->orWhere('Submitter', $user->UserID);
            });
            $query->where('r.IsApproved', 2);  
            $result[5]['name'] = 'Rejected Reports';
            $result[5]['total'] = $query->count();
            
            // Count Archived Reports
            $query = DB::table(static::$_table . ' as r');
            static::onPreCount($query);
            $query->where(function($qr) use($user){
                $qr->where('Approver', $user->UserID)
                    ->orWhere('Submitter', $user->UserID);
            });
            $query->where(function($qr){
                $qr->where('r.IsArchived', 1)
                    ->orWhere('ra.IsArchived', 1);
            });
            $result[6]['name'] = 'Archived Reports';
            $result[6]['total'] = $query->count();
            
            // Count All Reports
            $result[0]['name'] = 'All Reports';
            $result[0]['total'] = $result[0]['total'] + $result[1]['total'] + $result[2]['total']
                    + $result[3]['total'] + $result[4]['total'] + $result[5]['total'] + $result[6]['total'];
        }
        return $result;
    }
    
    /**
     * Use this method to build vaidator for both creating and updating report
     */
    public static function validateModel($inputs, $user, $report = null) 
    {
        $rules = array(
            'Title' => array('required', 'max:255'),
            'Date' => array('required', 'date'),
            'Reference' => array('required', 'max:45'),
            'IsSubmitted' => array('required'),
            'Trips' => array('required_if:IsSubmitted,1', 'trips_obj_not_added'),
            'ApproverEmail' => array('required_if:IsSubmitted,1', 'email', 'exists:User,Email'),
        );
        
        if($report != null) {
            $inputs['ReportID'] = $report->ReportID;
            $rules['ReportID'] = array('required', 'reports_submitted_by_mb:' . $user->UserID, 'reports_not_submitted_mb:' . $user->UserID);
            $rules['RemovedTrips'] = array('trips_belong_to:' . $user->UserID);
        }
        
        $customMessages = array(
            'Title.required' => 'Please enter the name of Travel Report.',
            'Date.required' => 'Please enter the date of Travel Report',
            'Trips.required_if' => 'This report does not have any trip. Please add at least 1 trip to the report.',
            'ApproverEmail.required_if' => 'Please enter the approver.',
            'ReportID.reports_not_submitted_mb' => 'This report was submitted. You cannot modify or delete it.',
            'ApproverEmail.exists' => "Approver's email does not exist in ReceiptClub",
            'RemovedTrips.trips_belong_to' => 'This trip contains an error. Please check your trip.'
        );
        
        if($report != null) {
            foreach ($rules as $key => $value) {
                if(!isset($inputs[$key])) {
                    unset($rules[$key]);
                }
            }
        }
        
        $validator = Validator::make($inputs, $rules, $customMessages);
		
        if ($validator->fails()) {
            return array('message' => $validator->messages()->all());
        }
        
        return array();
    }
    
    public static function checkUser($user, $object) 
    {
        $query = DB::table('Report AS r')
			->where('r.ReportID', $object->ReportID)
			->where('Submitter', $user->UserID)
			->get();
	
        if (count($query)) {
            return "submitter";
        }
        
        $query = DB::table('Report AS r')
			->leftJoin('ReportApprover AS ra', 'ra.ReportID', '=', 'r.ReportID')
			->where('r.ReportID', $object->ReportID)
			->where('Approver', $user->UserID)
			->get();
        
        if (count($query)) {
            return "approver";
        }

        return false;
    }
    
    /**
     * Use this method to build vaidator for approve report
     */
    public static function validateApprove($put, $user, $object) 
    {  
        $put['ReportID'] = $object->ReportID;
        
        $rules = array(
            'Approved' => array('required_with:ReportID'),
            'IsApproved' => array('required_with:ReportID', 'in:0,1,2'),
            'IsAllApproved' => array('in:0,1'),
        );
        
        if (isset($put['IsApproved']) && $put['IsApproved'] > 0) {
            $rules['ReportID'] = array('reports_approved_by:' . $user->UserID);
        }
        $validator = Validator::make($put, $rules);
		
        if ($validator->fails()) {
            return array('message' => $validator->messages()->all());
        }
        
        return array();
    }
    
    public static function processApprove($put, $user, $report)
    {
        $report->Approved = $put['Approved'];
        $report->IsApproved = $put['IsApproved'];
        if (isset($put['IsAllApproved'])) {
            $report->IsAllApproved = $put['IsAllApproved'];
        }
        $report->save();

        if (isset($put['Trips'])) {
            if (count($put['Trips'])) {
                foreach ($put['Trips'] as $trip) {
                    DB::table('ReportTrip')
                        ->where('ReportID', $put['ReportID'])
                        ->where('TripID', $trip['TripID'])
                        ->update(array(
                            'Approved' => $trip['Approved'],
                            'IsApproved' => $trip['IsApproved'],
                    ));

                    if (isset($trip['Items']) && count($trip['Items'])) {
                        foreach ($trip['Items'] as $item) {
                            if (isset($item['Approved']) && $item['Approved']) {
                                DB::table('TripItem')
                                    ->where('TripID', $trip['TripID'])
                                    ->where('TripItemID', $item['ItemID'])
                                    ->update(array(
                                        'Approved' => $item['Approved'],
                                        'IsApproved' => $item['IsApproved'],
                                ));
                            }
                        }
                    }
                }
            }
        }
        
        //Send push message to submitter's devices
        PushBackground::send($user->UserID, 'report', 'approve', $report->ReportID);
    }
    
    public static function processStore($post, $user) 
    {
    	if (isset($post['CreatedTime'])) {
        	$createdTime = $post['CreatedTime'];
        } else {
        	$createdTime = $_SERVER['REQUEST_TIME'];
        }

		if (isset($post['ApproverEmail'])) {
			$reportApprover = new ReportApprover();
			$reportApprover->Approver = User::where('Email', $post['ApproverEmail'])->first()->UserID;
		}
		
		$report = new Report();
		$report->Title = $post['Title'];
		$report->Reference = Report::checkRef($post['Reference'], $user->UserID);
        $report->CreatedTime = $createdTime;

		if (! isset($post['Date'])) {
			$report->Date = $report->CreatedTime;
		} else {
			if (strpos($post['Date'], 'T') !== false) {
				$post['Date'] = substr(str_replace('T', ' ', $post['Date']), 0, -5);
			}
			
			$report->Date = strtotime($post['Date']);
		}
		
		if (isset($post['Claimed'])) {
			$report->Claimed = $post['Claimed'];
		} else {
			$report->Claimed = 0;
		}
		
		$report->Submitter = $user->UserID;
		
		if (isset($post['IsSubmitted']) && $post['IsSubmitted']) {
			$report->IsSubmitted = 1;
		} else {
			$report->IsSubmitted = 0;
		}
		
		if (isset($post['IsClaimed'])) {
			$report->IsClaimed = $post['IsClaimed'];
		}
        
        if (isset($post['MobileSync']) && $post['MobileSync']) {
            $report->MobileSync = $post['MobileSync'];
        }
		
		$report->save();
		
		if (isset($reportApprover)) {
			$reportApprover->ReportID = $report->ReportID;
			$reportApprover->CreatedTime = $createdTime;
			$reportApprover->save();
		}
		
		if (isset($post['Attachments'])) {
			if (count($post['Attachments'])) {
				$fileIDs = array();
				foreach ($post['Attachments'] as $attachment) {
					$fileIDs[] = $attachment['FileID'];
				}

				File::updateList($fileIDs, array(
					'Permanent' => 1, 
					'EntityID' => $report->ReportID,
				));
			}
		}
		
		if (isset($post['DeletedFileIDs']) && count($post['DeletedFileIDs'])) {
			File::deleteList(File::getList($post['DeletedFileIDs']));
		}
		
		//Handle trips for report
		if (isset($post['Trips'])) {
			if (count($post['Trips'])) {
				$insert = array();
				foreach ($post['Trips'] as $trip) {
					$insert[] = array(
						'ReportID' => $report->ReportID,
						'TripID' => $trip['TripID'],
						'Claimed' => isset($trip['Claimed']) ? $trip['Claimed'] : 0,
						'IsClaimed' => isset($trip['IsClaimed']) ? $trip['IsClaimed'] : 0,
						'CreatedTime' => $createdTime,
					);
					
					if (isset($trip['Items']) && count($trip['Items'])) {
						foreach ($trip['Items'] as $item) {
							if (isset($item['Claimed']) && isset($item['IsClaimed'])) {
								DB::table('TripItem')
										->where('TripID', $trip['TripID'])
										->where('TripItemID', $item['ItemID'])
										->update(array(
											'Claimed' => $item['Claimed'],
											'IsClaimed' => $item['IsClaimed'],
										));
							}
							
							if (isset($item['ReportMemos']) && count($item['ReportMemos'])) {
								$insertedMemos = array();
								foreach ($item['ReportMemos'] as $memo) {
									$insertedMemos[] = array(
										'ReportID' => $report->ReportID,
										'ItemID' => $memo['ItemID'],
										'UserID' => $user->UserID,
										'Message' => $memo['Message'],
										'CreatedTime' => $createdTime,
									);
								}
								
								if (count($insertedMemos)) {
									DB::table('ReportMemo')
											->insert($insertedMemos);
								}
							}
						}
					}
				}
				
				if (count($insert)) {
					DB::table('ReportTrip')
						->insert($insert);
				}
			}
		}
		
		if ($report->IsSubmitted) {
			$submitter = DB::table('User AS u')
					->select('p.FirstName', 'p.LastName', 'u.Email')
					->join('Profile AS p', 'p.UserID', '=', 'u.UserID')
					->where('u.UserID', $user->UserID)
					->first();

			//Send notification email to approver
			Mail::send(
				'emails.report.notify_approval', array(
					'title' => $report->Title,
					'name' => $submitter->FirstName . ' ' . $submitter->LastName,
					'email' => $submitter->Email,
				), function($message) use ($post) {
					$message->to($post['ApproverEmail'])->subject('Please approve an expense report');
				});
		}
		
        $createdReport = Report::getByIdAndUser($report->ReportID, $user);
        
		return $createdReport;
    }
    
    public static function processUpdate($put, $user, $report) 
    {
        
        if (!empty($put['ApproverEmail'])) {
			$approverID = User::where('Email', $put['ApproverEmail'])->pluck('UserID');
		}
		
		$refreshTripList = false;

		if (isset($put['Title']) && $put['Title'] != $report->Title) {
			$report->Title = $put['Title'];
		}
		if (isset($put['Reference']) && $put['Reference'] != $report->Reference) {
			$report->Reference = Report::checkRef($put['Reference'], $user->UserID);
			$refreshTripList = true;
		}
		
		$report->ModifiedTime = $_SERVER['REQUEST_TIME'];
        if (isset($put['Date'])) {
            $put['Date'] = strtotime($put['Date']);
            if ($put['Date'] != $report->Date) {
                $report->Date = $put['Date'];
            }
        }
		
		if (isset($put['Claimed']) && $put['Claimed'] != $report->Claimed) {
			$report->Claimed = $put['Claimed'];
		}
		
		$submitted = false;
		if (isset($put['IsSubmitted']) && $put['IsSubmitted'] != $report->IsSubmitted) {
			$report->IsSubmitted = $put['IsSubmitted'];
			if ($report->IsSubmitted) {
				$submitted = true;
			}
		}
        
        $approved = false;
        if (isset($put['IsApproved']) && $put['IsApproved'] != $report->IsApproved) {
            $report->IsApproved = $put['IsApproved'];
            $approved = true;
        }
		
		if (isset($put['IsClaimed']) && $put['IsClaimed'] != $report->IsClaimed) {
			$report->IsClaimed = $put['IsClaimed'];
		}
		
		$report->save();
		
		if (isset($approverID)) {
			$reportApprover = ReportApprover::where('ReportID', $report->ReportID)->first();
			if ($reportApprover) {
				$reportApprover->ReportID = $report->ReportID;
				$reportApprover->Approver = $approverID;
				$reportApprover->ModifiedTime = $_SERVER['REQUEST_TIME'];
				$reportApprover->save();
			} else {
				$reportApprover = new ReportApprover();
				$reportApprover->ReportID = $report->ReportID;
				$reportApprover->Approver = $approverID;
				$reportApprover->CreatedTime = $_SERVER['REQUEST_TIME'];
				$reportApprover->save();
			}
		}
		
		if (isset($put['Attachments']) && count($put['Attachments'])) {
			$fileIDs = array();
			foreach ($put['Attachments'] as $attachment) {
				$fileIDs[] = $attachment['FileID'];
			}
			
			File::updateList($fileIDs, array(
				'Permanent' => 1,
				'EntityID' => $report->ReportID,
			));
		}
		
		if (isset($put['DeletedFileIDs']) && count($put['DeletedFileIDs'])) {
			File::deleteList(File::getList($put['DeletedFileIDs']));
		}
		
		//Handle trips for report
		if (isset($put['Trips'])) {
			$insert = array();
			if (count($put['Trips'])) {
				$refreshTripList = true;
				foreach ($put['Trips'] as $trip) {
					$relationshipExists = DB::table('ReportTrip')
                        ->where('TripID', $trip['TripID'])
                        ->first();
					
					if (! $relationshipExists) {
						$insert[] = array(
							'ReportID' => $report->ReportID,
							'TripID' => $trip['TripID'],
							'Claimed' => isset($trip['Claimed']) ? $trip['Claimed'] : 0,
							'IsClaimed' => isset($trip['Claimed']) ? $trip['IsClaimed'] : 0,
							'CreatedTime' => $_SERVER['REQUEST_TIME'],
						);
					} else {
						DB::table('ReportTrip')
                            ->where('ReportID', $report->ReportID)
                            ->where('TripID', $trip['TripID'])
                            ->update(array(
                                'Claimed' => $trip['Claimed'],
                                'IsClaimed' => $trip['IsClaimed'],
                            ));
					}
					
					if (isset($trip['Items']) && count($trip['Items'])) {
						foreach ($trip['Items'] as $item) {
							if (isset($item['Claimed']) && $item['Claimed']) {
								DB::table('TripItem')
                                    ->where('TripID', $trip['TripID'])
                                    ->where('TripItemID', $item['ItemID'])
                                    ->update(array(
                                        'Claimed' => $item['Claimed'],
                                        'IsClaimed' => $item['IsClaimed'],
                                    ));
							}
						}
					}
				}
				
				if (count($insert)) {
					DB::table('ReportTrip')
						->insert($insert);
				}
			}
		}
		
		if (isset($put['RemovedTrips'])) {
			if (count($put['RemovedTrips'])) {
				$refreshTripList = true;
				Report::removeTripRelationships($report->ReportID, $put['RemovedTrips']);
			}
		}
		
		if ($submitted) {
			$submitter = DB::table('User AS u')
					->select('p.FirstName', 'p.LastName', 'u.Email')
					->join('Profile AS p', 'p.UserID', '=', 'u.UserID')
					->where('u.UserID', $user->UserID)
					->first();
			//Send push message to approver's devices
            PushBackground::send($user->UserID, 'report', 'submit', $report->ReportID);
            
			//Send notification email to approver
			Mail::send(
				'emails.report.notify_approval', array(
                    'title' => $report->Title,
                    'name' => $submitter->FirstName . ' ' . $submitter->LastName,
                    'email' => $submitter->Email,
				), function($message) use ($put) {
					$message->to($put['ApproverEmail'])->subject('Please approve an expense report');
				});
		}
        
        if ($approved) {
            //Send push message to submitter's devices
            PushBackground::send($user->UserID, 'report', 'approve', $report->ReportID);

            //If report is rejected
            if ($put['IsApproved'] == 2) {
                ReportRejected::createRejectedRecord($report->ReportID, $user->UserID);
            }
        }
    }
    
    public static function validateDestroy($reportIDs, $user)
    {
        if (! is_array($reportIDs)) {
			$reportIDs = array($reportIDs);
		}
        
		$customMessages = array('ReportID' => array('required', 'reports_not_submitted_mb:' . $user->UserID, 'reports_belong_to:' . $user->UserID));
		if (count($reportIDs) === 1) {
			$customMessages['ReportID.reports_not_submitted_mb'] = 'This report was submitted. You cannot modify or delete it.';
		}
		$validator = Validator::make(array('ReportID' => $reportIDs), $customMessages);
		
		if ($validator->fails()) {
			return array('message' => $validator->messages()->all());
		}
        
        return array();
    }    
    
    
    public static function processDestroy($reportIDs, $user) 
    {
        $classifiedReportIDs = Report::classifyReportIDs($reportIDs, $user->UserID);
		if (count($classifiedReportIDs['submitted'])) {
			//Delete attachments of the selected reports
			File::deleteList(File::getListByEntities($classifiedReportIDs['submitted'], 'report', true));

			//Delete the report approver records
			ReportApprover::deleteList($classifiedReportIDs['submitted']);

			//Delete the report trip relationships
			Report::removeTripRelationships($classifiedReportIDs['submitted']);

			//Delete all report item memos
			ReportMemo::deleteByReports($classifiedReportIDs['submitted']);

			//Delete the reports themselves
			Report::deleteList($classifiedReportIDs['submitted']);	
		}
		
		if (count($classifiedReportIDs['approved'])) {
			DB::table('ReportApprover')
					->whereIn('ReportID', $classifiedReportIDs['approved'])
					->update(array('IsDeleted' => 1));
		}
    }
    
    /**
     * Check if user have permission on report
     */
    public static function checkUserPermission($reportID, $userID) 
    {
        $query = DB::table('Report as rp')
            ->leftJoin('ReportApprover as ra', 'ra.ReportID', '=', 'rp.ReportID')
            ->where('rp.ReportID', $reportID)
            ->where(function ($query) use ($userID) {
                $query->where('rp.Submitter', $userID)
                    ->orWhere('ra.Approver', $userID);
            })
            ->count();
        
        if ($query) {
            return true;
        }
        
        return false;
    }
    
    public static function createReceiptImageUrlForReport($filePath) {
        if (!self::$s3) {
            self::$s3 = App::make('aws')->get('s3');
        }
        $request = self::$s3->get($filePath);
        return self::$s3->createPresignedUrl($request, '+ 1 hour');
    }
    
    /*
     * get Image object from 
     */
    
    public static function createObjectImage($filePath) {
        if (!self::$s3) {
            self::$s3 = App::make('aws')->get('s3');
        }         
       $request = self::$s3->getObject(array(
         'Bucket' => Config::get('aws::config.bucketFile'),
          'Key'   => $filePath
        ));        
       return $request->Body;
    }

    /**
     * Check to see report is rejected report
     *
     * @param    $reportID  int  ReportID
     * @return   boolean
     */
    public static function isRejected ($reportID)
    {
        $report = DB::table('Report')
            ->where('ReportID', $reportID)
            ->select('ReportID', 'IsSubmitted', 'IsApproved')
            ->first();

        if (!$report) return false;

        return ($report->IsSubmitted == 1 && $report->IsApproved == 2);
    }
}
