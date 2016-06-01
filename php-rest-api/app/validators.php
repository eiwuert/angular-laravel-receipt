<?php
/*
|--------------------------------------------------------------------------
| Custom Validators for Inputs
|--------------------------------------------------------------------------
*/

/**
 * Check whether the password input is matched with password value from database
 */
Validator::extend('password_matched', function($attribute, $value, $parameters)
{
	return Hash::check($value, $parameters[0]);
});

/**
 * Check to require at least one item when submitting from Receipt Detail screen
 */
Validator::extend('item_required', function($attribute, $value, $parameters)
{
	if (count($value)) {
		if (isset($value[0]['Name']) && isset($value[0]['Amount'])) {
			return true;
		}
	}
	
	return false;
});

/**
 * Check to limit reset password maximum 2 times per day
 */
Validator::extend('limit_reset_pass', function($attribute, $value, $parameters)
{
    $beginOfDay = strtotime("midnight", $_SERVER['REQUEST_TIME']);
    $endOfDay   = strtotime("tomorrow", $beginOfDay) - 1;
    
    $query = DB::table('User')
        ->where('Email', $value)
        ->first();
	$userId = $query->UserID;
 
    $query1 = DB::table('UserActivity')
        ->where('UserID', $userId)
        ->where('ActivityType', 'reset password')    
        ->where('Timestamp', '>=' , $beginOfDay)
        ->where('Timestamp', '<=' , $endOfDay) 
        ->get();
    
    if(count($query1) >= 2) {
        return false;
    }
	
	return true;
});

/**
 * Check whether a receipt or an array of receipts belong to a user
 */
Validator::extend('receipts_belong_to', function($attribute, $value, $parameters)
{
	$query = DB::table('Receipt')
			->where('UserID', $parameters[0]);
	
	if (is_array($value)) {
		$query->whereIn('ReceiptID', $value);
	} else {
		$query->where('ReceiptID', $value);
	}
	
	if ($query->first()) {
		return true;
	}
	
	return false;
});

/**
 * Check whether a merchant has already existed
 */
Validator::extend('merchant_existed', function($attribute, $value, $parameters)
{
    $userID = $parameters[0];
    $mcAddress = $parameters[1];
    $mcName = $value;
    
    $query = DB::table('Merchant')
        ->whereIn('UserID', array($userID, 0))
        ->where('Searchable', 1)
        ->where('Name', $mcName);
    if (!empty($mcAddress)) {
        $query->where('Address', $mcAddress);
    }

    $merchant = $query->first();
    if ($merchant) {
        return false;
    }

    return true;
});

/**
 * Check whether a tripitem has already existed
 */
Validator::extend('tripitem_existed', function($attribute, $value, $parameters)
{
    $query = DB::table('TripItem')
        ->where('TripItemID', $value);
    
    $tripitem = $query->first();
    if ($tripitem) {
        return false;
    }

    return true;
});

/**
 * Check whether a tripitem has already existed
 */
Validator::extend('approve_not_submitter', function($attribute, $value, $parameters)
{
    return ($value == $parameters[0] || $parameters[0] == '') ? false : true;
});

/**
 * Check whether an item or an array of items belong to a receipt of a user
 */
Validator::extend('items_belong_to', function($attribute, $value, $parameters)
{
	$query = DB::table('Item AS i')
			->join('Receipt AS r', 'r.ReceiptID', '=', 'i.ReceiptID')
			->where('UserID', $parameters[0]);
	
	if (is_array($value)) {
		$query->whereIn('ItemID', $value);
	} else {
		$query->where('ItemID', $value);
	}
	
	if ($query->first()) {
		return true;
	}
	
	return false;
});

/**
 * Check whether an item or an array of items belong to a receipt of a user
 */
Validator::extend('tripitem_belong_to', function($attribute, $value, $parameters)
{
	$userID = $parameters[0];

	$query = DB::table('TripItem AS r')
			->leftJoin('Trip AS tr', 'r.TripID', '=', 'tr.TripID')
			->leftJoin('ReportTrip AS rt', 'tr.TripID', '=', 'rt.TripID')
			->leftJoin('Report AS re', 're.ReportID', '=', 'rt.ReportID')
			->leftJoin('ReportApprover AS ra', 'ra.ReportID', '=', 're.ReportID');

	$query->where(function($query) use ($userID) {
	        $query->where('re.Submitter', $userID);
	    })
	    ->orWhere(function($query) use ($userID) {
	        $query->where('ra.Approver', $userID);
	    });

	if (is_array($value)) {
		$query->whereIn('r.TripItemID', $value);
	} else {
		$query->where('r.TripItemID', $value);
	}
	
	if ($query->first()) {
		return true;
	}
	
	return false;
});

/**
 * Check whether an deviceapitoken belongs to a user
 */
Validator::extend('deviceapitoken_belongs_to_user', function($attribute, $value, $parameters)
{
	$query = DB::table('DeviceApiToken AS de')
			->where('de.DeviceToken', $value)
            ->where('de.UserID', $parameters[0]);
	
	if ($query->first()) {
		return true;
	}
	
	return false;
});

/**
 * Check whether merchant belongs to a user
 */
Validator::extend('merchant_belongs_to_user', function($attribute, $value, $parameters)
{
	$query = DB::table('Merchant AS me')
			->where('me.MerchantID', $value)
            ->where('me.UserID', $parameters[0]);
	
	if ($query->first()) {
		return true;
	}
	
	return false;
});

/**
 * Check whether trips belong to an user
 */
Validator::extend('trips_belong_to', function($attribute, $value, $parameters) 
{
	if (! is_array($value)) {
		$value = array($value);
	}
	
	if (! count($value)) {
		return true;
	}
	
	$value = array_unique($value);
	
	$query = DB::table('Trip')
			->where('UserID', $parameters[0])
			->whereIn('TripID', $value)
			->get();
	
	if (count($query) == count($value)) {
		return true;
	}
	
	return false;
});

/**
 * Check whether trips belong to an user
 */
Validator::extend('trips_belong_to_sa', function($attribute, $value, $parameters) 
{
	if (! is_array($value)) {
		$value = array($value);
	}
	
	if (! count($value)) {
		return true;
	}
	
	$value = array_unique($value);
	
	$query = DB::table('Trip')
			->where('UserID', $parameters[0])
			->whereIn('TripID', $value)
			->get();
	
	if (count($query) == count($value)) {
		return true;
	}

	$query1 = DB::table('Trip as r')
			->leftJoin('ReportTrip AS rt', 'r.TripID', '=', 'rt.TripID')
			->leftJoin('Report AS re', 're.ReportID', '=', 'rt.ReportID')
			->leftJoin('ReportApprover AS ra', 'ra.ReportID', '=', 're.ReportID')
			->where('ra.Approver', $parameters[0])
			->whereIn('r.TripID', $value)
			->get();
	
	if (count($query1) == count($value)) {
		return true;
	}
	
	return false;
});

/**
 * Check whether reports belong to an user
 */
Validator::extend('reports_belong_to', function($attribute, $value, $parameters) 
{
	$value = ! is_array($value) ? array($value) : $value;
	
	$query = DB::table('Report AS r')
			->leftJoin('ReportApprover AS ra', 'ra.ReportID', '=', 'r.ReportID')
			->whereIn('r.ReportID', $value)
			->where(function($query) use ($parameters) {
				$query->where('Submitter', $parameters[0])
					->orWhere('Approver', $parameters[0]);
			})->get();
		
	if (count($query) == count($value)) {
		return true;
	}
	
	return false;
});

/**
 * Check whether deviceapitoken exists for create, if exists return false; otherwise return true
 */
Validator::extend('deviceapitoken_exists', function($attribute, $value, $parameters) 
{
	$devicetokens = DB::table('DeviceApiToken AS r')
			->where('DeviceToken', $value)
			->get();

	if (count($devicetokens)) {
        foreach ($devicetokens as $devicetoken) {
           	if($devicetoken->UserID == $parameters[0]) {
                return false;                
            }
        }
	}
	
	return true;
});

/**
 * Check whether a date have format yyyy-mm-dd
 */
Validator::extend('date_true_format', function($attribute, $value, $parameters) 
{
	if (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $value)) {
        return true;
    } else {
        return false;
    }
});

/**
 * Check whether deviceapitoken exists for delete, if exists return true; otherwise return false
 */
Validator::extend('deviceapitoken_exists_delete', function($attribute, $value, $parameters) 
{
	$query = DB::table('DeviceApiToken AS r')
			->where('DeviceToken', $value)
			->get();
	
	if (count($query)) {
		return true;
	}
	
	return false;
});

/**
 * Check whether reports submitted by an user
 */
Validator::extend('reports_submitted_by_mb', function($attribute, $value, $parameters) 
{
	if (! is_array($value)) {
		$value = array($value);
	}
	
	$value = array_unique($value);
	$query = DB::table('Report AS r')
			->whereIn('r.ReportID', is_array($value) ? $value : array($value))
			->where('Submitter', $parameters[0])
			->get();
	
	if (count($query) == count($value)) {
		return true;
	}
    
    $query1 = DB::table('Report AS r')
        ->leftJoin('ReportApprover AS ra', 'ra.ReportID', '=', 'r.ReportID')
        ->whereIn('r.ReportID', is_array($value) ? $value : array($value))
        ->where('Approver', $parameters[0])
        ->get();
	
	if (count($query1) == count($value)) {
		return true;
	}
	
	return false;
});

/**
 * Check whether reports submitted by an user
 */
Validator::extend('reports_submitted_by', function($attribute, $value, $parameters) 
{
	if (! is_array($value)) {
		$value = array($value);
	}
	
	$value = array_unique($value);
	$query = DB::table('Report AS r')
			->whereIn('r.ReportID', is_array($value) ? $value : array($value))
			->where('Submitter', $parameters[0])
			->get();
	
	if (count($query) == count($value)) {
		return true;
	}
	
	return false;
});

/**
 * Check whether reports need to be approved by an user
 */
Validator::extend('reports_approved_by', function($attribute, $value, $parameters) 
{
	if (! is_array($value)) {
		$value = array($value);
	}
	
	$value = array_unique($value);
	$query = DB::table('Report AS r')
			->leftJoin('ReportApprover AS ra', 'ra.ReportID', '=', 'r.ReportID')
			->whereIn('r.ReportID', is_array($value) ? $value : array($value))
			->where('Approver', $parameters[0])
			->get();
	
	if (count($query) == count($value)) {
		return true;
	}
	
	return false;
});

/**
 * Check if (a) trip(s) is not added to a report
 */
Validator::extend('trips_not_added', function($attribute, $value, $parameters)
{
	if (! is_array($value)) {
		$value = array($value);
	}
	$reportTripRelationships = DB::table('ReportTrip')
			->whereIn('TripID', is_array($value) ? $value : array($value))
			->where('ReportID', $parameters[0])
			->get();
	
	if (count($reportTripRelationships)) {
		return false;
	}
	
	return true;
});

/**
 * Check if trips is not added to a certain report, we pass an array of Trip objects to this one
 */
Validator::extend('trips_obj_not_added', function($attribute, $value, $parameters)
{
	$tripIDs = array();
	foreach ($value as $v) {
		$tripIDs[] = $v['TripID'];
	}
	if (count($tripIDs)) {
		$reportTripRelationships = DB::table('ReportTrip')
			->whereIn('TripID', $tripIDs);
		
		if (isset($parameters[0])) {
			$reportTripRelationships->where('ReportID', '!=', $parameters[0]);
		}
		
		if (count($reportTripRelationships->get())) {
			return false;
		}
	}
		
	return true;
});

/**
 * Check if start date and end date of a trip input are invalid
 */
Validator::extend('trip_date', function($attribute, $value, $parameters)
{
	$value = strtotime($value);
	$query = DB::table('Trip')
			->select('TripID')
			->where('UserID', $parameters[0]);
	
	if ($parameters[1]) {
		$query->where('TripID', '!=', $parameters[1]);
	}
	
	if (! $parameters[2]) {
		$query->where('StartDate', '<=', $value)
			->where('EndDate', '>=', $value);
	} else {
		$parameters[2] = strtotime($parameters[2]);
		if ($value == $parameters[2]) {
			$query->where('StartDate', '<', $value)
				->where('EndDate', '>', $value);
		} else {
			$query->where(function($query) use ($value, $parameters) {
				$query->where(function($query) use ($value) {
					$query->where('StartDate', '<=', $value)
						->where('EndDate', '>', $value)
						->whereRaw('StartDate != EndDate');
				})->orWhere(function($query) use ($value, $parameters) {
					$query->where('StartDate', '>', $value)
						->where('StartDate', '<', $parameters[2]);
				});
			});
		}
	}
	
	if (count($query->get())) {
		return false;
	}
	
	return true;
});

/**
 * Check if start date and end date of a trip input are invalid
 */
Validator::extend('quick_trip_date', function($attribute, $value, $parameters)
{
	if (! isset($parameters[0]) || ($parameters[0] !== 'start' && $parameters[0] !== 'end')) {
		return false;
	}
	
	if ($parameters[0] == 'start') {
		$startDate = strtotime($value);
		$endDate = Trip::find($parameters[2])->EndDate;
	} else if ($parameters[0] == 'end') {
		$endDate = strtotime($value);
		$startDate = Trip::find($parameters[2])->StartDate;
	}
	
	if ($startDate > $endDate) {
		return false;
	}
	
	$query = DB::table('Trip')
			->select('TripID')
			->where('UserID', $parameters[1])
			->where('TripID', '!=', $parameters[2]);
	
	if (! $endDate) {
		$query->where('StartDate', '<=', $startDate)
			->where('EndDate', '>=', $startDate);
	} else {
		if ($startDate == $endDate) {
			$query->where('StartDate', '<', $startDate)
				->where('EndDate', '>', $startDate);
		} else {
			$query->where(function($query) use ($startDate, $endDate) {
				$query->where(function($query) use ($startDate) {
					$query->where('StartDate', '<=', $startDate)
						->where('EndDate', '>', $startDate)
						->whereRaw('StartDate != EndDate');
				})->orWhere(function($query) use ($startDate, $endDate) {
					$query->where('StartDate', '>', $startDate)
						->where('StartDate', '<', $endDate);
				});
			});
		}
	}
	
	if (count($query->get())) {
		return false;
	}
	
	return true;
});

Validator::extend('not_assigned_to_report', function($attribute, $value, $parameters) {
	if (! $parameters[0]) return true;
	
	$query = DB::table('ReportTrip')
			->select('ReportTripID')
			->where('TripID', $value)
			->first();
	
	if (! $query) return true;
	return false;
});

Validator::extend('not_assigned_to_report_mb', function($attribute, $value, $parameters) {
	if (! $parameters[0]) return true;
	
	$query = DB::table('ReportTrip')
			->select('ReportTripID', 'ReportID')
			->where('TripID', $value)
			->first();
	
	if (! $query) return true;
    if ($query->ReportID == $parameters[0]) return true;
	return false;
});

Validator::extend('date_notin_current', function($attribute, $value, $parameters) {
    $todayTimestamp = $parameters[0];
    $startTime = $parameters[1];
	$endTime = $parameters[2];
    
    if ($startTime <= $todayTimestamp && $todayTimestamp <= $endTime) {
        return false;
    } else {
        return true;
    }
});

Validator::extend('date_after_current', function($attribute, $value, $parameters) {
    $startTime = strtotime($value);
	$currentTime = $parameters[0];
    if ($startTime <= $currentTime) {
        return false;
    } else {
        return true;
    }
});

Validator::extend('date_no_duplicate', function($attribute, $value, $parameters) {
    $startTime = strtotime($value);
	$endTime = strtotime($parameters[0]);
    
    $query = DB::table('Maintenance')->select('MaintenanceID');
    
    $query->where(function($query) use ($startTime, $parameters) {
        $query->where('StartTime', '<=', $startTime)
            ->where('EndTime', '>=', $startTime);
        if(isset($parameters[1])) {
            $query->where('MaintenanceID', '!=', $parameters[1]);
        }
    })->orWhere(function($query) use ($endTime, $parameters) {
        $query->where('StartTime', '<=', $endTime)
            ->where('Endtime', '>=', $endTime);
        if(isset($parameters[1])) {
            $query->where('MaintenanceID', '!=', $parameters[1]);
        }
    });

    if (count($query->get())) {
		return false;
	}
	
	return true;
});

Validator::extend('date_after_start', function($attribute, $value, $parameters) {
    $endTime = strtotime($value);
	$startTime = strtotime($parameters[0]);
    if ($endTime <= $startTime) {
        return false;
    } else {
        return true;
    }
});

/**
 * Check whether an array of email addresses is valid
 */
Validator::extend('multiple_emails', function($attribute, $value, $parameters)
{
	return filter_var_array($value, FILTER_VALIDATE_EMAIL) !== false ? true : false;
});

/**
 * Check whether an uploaded file has the valid extension
 */
Validator::extend('valid_ext', function($attribute, $value, $parameters) 
{
	return in_array($value->getClientOriginalExtension(), $parameters);
});

/**
 * Check whether the size of an uploaded file is not bigger than the file size limit 
 */
Validator::extend('file_size', function($attribute, $value, $parameters) 
{
	return $value->getClientSize() < $parameters[0];
});

/**
 * Check whether an input is an application of our system
 */
Validator::extend('is_app', function($attribute, $value, $parameters) 
{
	return in_array($value, array_keys(Config::get('app.appList')));
});

/**
 * Check whether a category belongs to an application
 */
Validator::extend('belongs_to_app', function($attribute, $value, $parameters) 
{
	return DB::table('Category')
			->where('CategoryID', $value)
			->where('App', $parameters[0])
			->count();
});

/**
 * If the user email is requested to be changed, we need to check whether it doesn't belong to other people
 */
Validator::extend('profile_email', function($attribute, $value, $parameters) 
{
	$query = DB::table('User')
			->where('UserID', '!=', $parameters[0])
			->where('Email', $value)
			->first();
	
	if (! $query) {
		return true;
	}
	
	return false;
});

/**
 * Extra emails have to be available or used currently by the specified user
 */
Validator::extend('extra_emails', function($attribute, $value, $parameters) 
{
	if (! count($value)) {
		return true;
	}
	
	foreach ($value as $v) {
		if (! filter_var($v, FILTER_VALIDATE_EMAIL)) {
			return false;
		}
	}
	
	$query = DB::table('ExtraEmailSender')
			->where('UserID', '!=', $parameters[0])
			->whereIn('Email', $value)
			->get();
	
	if (! count($query)) {
		return true;
	}
	
	return false;
});

/**
 * Check if a date is not before another date
 */
Validator::extend('not_before', function($attribute, $value, $parameters) 
{
	return strtotime(date('Y-m-d', strtotime($value))) >= strtotime(date('Y-m-d', strtotime($parameters[0])));
});

/**
 * Check if receipts were used for some submitted reports
 */
Validator::extend('receipts_for_submitted_report', function($attribute, $value, $parameters) 
{
	if (! is_array($value)) {
		$value = array($value);
	}
	
	$query = DB::table('Item AS i')
			->join('TripItem AS ti', 'ti.TripItemID', '=', 'i.ItemID')
			->join('ReportTrip AS rt', 'rt.TripID', '=', 'ti.TripID')
			->join('Report AS r', 'r.ReportID', '=', 'rt.ReportID')
			->whereIn('ReceiptID', $value)
			->where('r.IsSubmitted', 1)
			->where('r.IsApproved', '<', 2)
			->get();
	
	if (! count($query)) {
		return true;
	}
	
	return false;
});

/**
 * Check if items were used for some submitted reports
 */
Validator::extend('items_for_submitted_report', function($attribute, $value, $parameters) 
{
	$query = DB::table('Item AS i')
			->join('TripItem AS ti', 'ti.TripItemID', '=', 'i.ItemID')
			->join('ReportTrip AS rt', 'rt.TripID', '=', 'ti.TripID')
			->join('Report AS r', 'r.ReportID', '=', 'rt.ReportID')
			->whereIn('ItemID', $value)
			->where('r.IsSubmitted', 1)
			->get();
	
	if (! count($query)) {
		return true;
	}
	
	return false;
});

/**
 * Check if the reports were submitted or approved
 */
Validator::extend('reports_not_submitted_mb', function($attribute, $value, $parameters) 
{
	if (! is_array($value)) {
		$value = array($value);
	}
	
    $query = DB::table('Report AS r')
        ->leftJoin('ReportApprover AS ra', 'ra.ReportID', '=', 'r.ReportID')
        ->whereIn('r.ReportID', is_array($value) ? $value : array($value))
        ->where('Approver', $parameters[0])
        ->where('Submitter', $parameters[0])
        ->get();
        
    if (count($query) == count($value)) {
		return true;
	}
    
    $query1 = DB::table('Report AS r')
        ->leftJoin('ReportApprover AS ra', 'ra.ReportID', '=', 'r.ReportID')
        ->whereIn('r.ReportID', is_array($value) ? $value : array($value))
        ->where('Approver', $parameters[0])
        ->where('IsSubmitted', 1)
        ->where('IsApproved', '<', 2)
        ->get();
        
    if (count($query1) == count($value)) {
		return true;
	}
    
	$query = DB::table('Report AS r')
			->whereIn('ReportID', $value)
			->where('IsSubmitted', 1)
			->where('IsApproved', '<', 2)
			->get();
	
	if (! count($query)) {
		return true;
	}
	
	return false;
});

/**
 * Check if the reports were submitted or approved
 */
Validator::extend('reports_not_submitted', function($attribute, $value, $parameters) 
{
	if (! is_array($value)) {
		$value = array($value);
	}
	
	$query = DB::table('Report AS r')
			->whereIn('ReportID', $value)
			->where('IsSubmitted', 1)
			->where('IsApproved', '<', 2)
			->get();
	
	if (! count($query)) {
		return true;
	}
	
	return false;
});

/**
 * Check if the trips were reported
 */
Validator::extend('trips_not_reported', function($attribute, $value, $parameters) 
{
	if (! is_array($value)) {
		$value = array($value);
	}
	
	$query = DB::table('Trip AS t')
			->leftJoin('ReportTrip AS rt', 'rt.TripID', '=', 't.TripID')
			->leftJoin('Report AS r', 'r.ReportID', '=', 'rt.ReportID')
			->whereIn('t.TripID', $value)
			->where('r.IsSubmitted', 1)
			->where('r.IsApproved', '<', 2)
			->get();
	
	if (! count($query)) {
		return true;
	}
	
	return false;
});

Validator::extend('report_can_be_submitted', function($attribute, $value, $parameters) 
{
	$query = DB::table('Report AS r')
			->join('ReportTrip AS rt', 'rt.ReportID', '=', 'r.ReportID')
			->join('ReportApprover AS ra', 'ra.ReportID', '=', 'r.ReportID')
			->where('r.ReportID', $value)
			->get();
	
	if (count($query)) {
		return true;
	}
	
	return false;
});

/**
 * Check if Trip Reference is exist
 */
Validator::extend('quick_trip_ref_exist', function($attribute, $value, $parameters)
{
	$isRefExist = DB::table('Trip')
					->where('Reference', $value)
					->where('UserID', $parameters[0])
					->take(1)
					->pluck('Reference');
	
	if (! $isRefExist) {
		return true;
	}

	return false;
});

/**
 * Check if Report Reference is exist
 */
Validator::extend('quick_report_ref_exist', function($attribute, $value, $parameters)
{
	$isRefExist = DB::table('Report')
					->where('Reference', $value)
					->where('Submitter', $parameters[0])
					->take(1)
					->pluck('Reference');
	
	if (! $isRefExist) {
		return true;
	}

	return false;
});
