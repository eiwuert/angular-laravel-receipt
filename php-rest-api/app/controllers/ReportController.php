<?php

class ReportController extends BaseController {

    public function getIndex() {
        // Need to check authentication
        if (empty($_SERVER['HTTP_AUTH_TOKEN']) || !$userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
            return Response::json(array('message' => 'The authentication is failed.'), 401);
        }

        $reportID = Input::get('reportID', '');
        if (empty($reportID)) {
            return Response::json(Report::getList($userToken->UserID, Input::all()));
        } else {
            return Response::json(Report::getDetail($reportID, $userToken->UserID));
        }
    }

    /**
     * API count number of report by types
     *
     * @return array Contain type and count number
     */
    public function getCount() {
        // Need to check authentication
        if (empty($_SERVER['HTTP_AUTH_TOKEN']) || !$userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
            return Response::json(array('message' => 'The authentication is failed.'), 401);
        }

        $types = @explode(',', Input::get('type', ''));
        $types = is_array($types) ? $types : array();

        $role = Input::get('role', '');
        $dateFrom = Input::get('dateFrom', '');
        $dateTo = Input::get('dateTo', '');

        return Response::json(Report::count($userToken->UserID, $types, $role, $dateFrom, $dateTo), 200);
    }

    public function postIndex() {
        // Need to check authentication
        if (empty($_SERVER['HTTP_AUTH_TOKEN']) || !$userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
            return Response::json(array('message' => 'The authentication is failed.'), 401);
        }

        $post = Input::all();
        $validator = Validator::make(
                        $post, array(
                    'Title' => array('required', 'max:255'),
                    'Date' => array('required', 'date'),
                    'Reference' => array('required', 'max:45'),
                    'IsSubmitted' => array('required'),
                    'Trips' => array('required_if:IsSubmitted,1', 'trips_obj_not_added'),
                    'ApproverEmail' => array('required_if:IsSubmitted,1', 'exists:User,Email'),
                    'ApproverEmail' => array('approve_not_submitter:' . (isset($post['SubmitterEmail']) ? $post['SubmitterEmail'] : '')),
                        ), array(
                    'Title.required' => 'Please enter the name of Travel Report.',
                    'Date.required' => 'Please enter the date of Travel Report',
                    'Trips.required_if' => 'This report does not have any trip. Please add at least 1 trip to the report.',
                    'ApproverEmail.required_if' => 'Please enter the approver.',
                    'ApproverEmail.exists' => 'This email address does not belong to anyone in ReceiptClub.',
                    'ApproverEmail.approve_not_submitter' => 'Invalid email - Submitter cannot be Approver.'
                        )
        );

        if ($validator->fails()) {
            return Response::json(array('message' => $validator->messages()->all()), 500);
        }

        if (isset($post['ApproverEmail'])) {
            $reportApprover = new ReportApprover();
            $reportApprover->Approver = User::where('Email', $post['ApproverEmail'])->first()->UserID;
//			if ($reportApprover->Approver == $userToken->UserID) {
//				return Response::json(array('message' => array('Approver and submitter cannot be the same person.')), 500);
//			}
        }

        $report = new Report();
        $report->Title = $post['Title'];
        $report->Reference = Report::checkRef($post['Reference'], $userToken->UserID);
        $report->CreatedTime = $_SERVER['REQUEST_TIME_FLOAT'];

        if (!isset($post['Date'])) {
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

        $report->Submitter = $userToken->UserID;

        if (isset($post['IsSubmitted']) && $post['IsSubmitted']) {
            $report->IsSubmitted = 1;
        } else {
            $report->IsSubmitted = 0;
        }

        if (isset($post['IsClaimed'])) {
            $report->IsClaimed = $post['IsClaimed'];
        }

        $report->save();

        if (isset($reportApprover)) {
            $reportApprover->ReportID = $report->ReportID;
            $reportApprover->CreatedTime = $_SERVER['REQUEST_TIME_FLOAT'];
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
                        'CreatedTime' => $_SERVER['REQUEST_TIME_FLOAT'],
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

                            if (count($item['ReportMemos'])) {
                                $insertedMemos = array();
                                foreach ($item['ReportMemos'] as $memo) {
                                    $insertedMemos[] = array(
                                        'ReportID' => $report->ReportID,
                                        'ItemID' => $memo['ItemID'],
                                        'UserID' => $userToken->UserID,
                                        'Message' => $memo['Message'],
                                        'CreatedTime' => $_SERVER['REQUEST_TIME_FLOAT'],
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
                    ->where('u.UserID', $userToken->UserID)
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

        //Push server
        PushBackground::send($userToken->UserID, 'report', 'post', $report->ReportID);

        return Response::json(array(
                    'ReportID' => $report->ReportID,
                    'Status' => $report->setStatus($userToken->UserID),
        ));
    }

    public function puatIndex() {
        // Need to check authentication
        if (empty($_SERVER['HTTP_AUTH_TOKEN']) || !$userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
            return Response::json(array('message' => 'The authentication is failed.'), 401);
        }

        $put = Input::all();

        $customMessages = array(
            'Title.required' => 'Please enter the name of Travel Report.',
            'Date.required' => 'Please enter the date of Travel Report',
            'Trips.required_if' => 'This report does not have any trip. Please add at least 1 trip to the report.',
            'ApproverEmail.required_if' => 'Please enter the approver.'
        );

        if (isset($put['ReportID']) && !is_array($put['ReportID'])) {
            $customMessages['ReportID.reports_not_submitted'] = 'This report was submitted. You cannot modify or delete it.';
        }
        $validator = Validator::make(
                        $put, array(
                    'ReportID' => array('required', 'reports_submitted_by:' . $userToken->UserID, 'reports_not_submitted'),
                    'Title' => array('required', 'max:255'),
                    'Date' => array('required', 'date'),
                    'Reference' => array('required', 'max:45'),
                    'IsSubmitted' => array('required'),
                    'Trips' => array('required_if:IsSubmitted,1', 'trips_obj_not_added:' . $put['ReportID']),
                    'ApproverEmail' => array('required_if:IsSubmitted,1', 'email', 'exists:User,Email'),
                    'RemovedTrips' => array('trips_belong_to:' . $userToken->UserID),
                        ), $customMessages
        );

        if ($validator->fails()) {
            return Response::json(array('message' => $validator->messages()->all()), 500);
        }

        if (isset($put['ApproverEmail'])) {
            $approverID = User::where('Email', $put['ApproverEmail'])->first()->UserID;

//			if ($approverID == $userToken->UserID) {
//				return Response::json(array('message' => array('Approver and submitter cannot be the same person.')), 500);
//			}
        }

        $report = Report::find($put['ReportID']);
        $refreshTripList = false;

        if ($put['Title'] != $report->Title) {
            $report->Title = $put['Title'];
        }
        if ($put['Reference'] != $report->Reference) {
            $report->Reference = Report::checkRef($put['Reference'], $userToken->UserID);
            $refreshTripList = true;
        }

        $report->ModifiedTime = $_SERVER['REQUEST_TIME_FLOAT'];
        $put['Date'] = strtotime($put['Date']);
        if ($put['Date'] != $report->Date) {
            $report->Date = $put['Date'];
        }

        if (isset($put['Claimed']) && $put['Claimed'] != $report->Claimed) {
            $report->Claimed = $put['Claimed'];
        }

        $submitted = false;
        if ($put['IsSubmitted'] != $report->IsSubmitted) {
            $report->IsSubmitted = $put['IsSubmitted'];
            if ($report->IsSubmitted) {
                $submitted = true;
            }
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
                $reportApprover->ModifiedTime = $_SERVER['REQUEST_TIME_FLOAT'];
                $reportApprover->save();
            } else {
                $reportApprover = new ReportApprover();
                $reportApprover->ReportID = $report->ReportID;
                $reportApprover->Approver = $approverID;
                $reportApprover->CreatedTime = $_SERVER['REQUEST_TIME_FLOAT'];
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

                    if (!$relationshipExists) {
                        $insert[] = array(
                            'ReportID' => $report->ReportID,
                            'TripID' => $trip['TripID'],
                            'Claimed' => isset($trip['Claimed']) ? $trip['Claimed'] : 0,
                            'IsClaimed' => isset($trip['Claimed']) ? $trip['IsClaimed'] : 0,
                            'CreatedTime' => $_SERVER['REQUEST_TIME_FLOAT'],
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
                    ->where('u.UserID', $userToken->UserID)
                    ->first();

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

        return Response::json(array(
                    'ReportID' => $report->ReportID,
                    'Status' => $report->setStatus($userToken->UserID),
                    'RefreshTripList' => $refreshTripList
        ));
    }

    public function putList() {
        // Need to check authentication
        if (empty($_SERVER['HTTP_AUTH_TOKEN']) || !$userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
            return Response::json(array('message' => 'The authentication is failed.'), 401);
        }

        $data = Input::get('data', array());
        if (!count($data)) {
            return Response::json(array('message' => array('The request data is empty.')), 500);
        }

        $response = array();
        $refreshTripList = false;
        //Push server
//        $pushService = App::make('pushService');
//        $submitter   = User::find($userToken->UserID);                        

        foreach ($data as $key => $put) {
            $validator = Validator::make(
                            $put, array(
                        'ReportID' => array('required', 'reports_not_submitted', 'reports_submitted_by:' . $userToken->UserID, 'report_can_be_submitted'),
                        'Date' => array('required', 'date'),
                        'Reference' => array('required', 'max:45'),
                        'IsSubmitted' => array('required'),
                        'ApproverEmail' => array('required_if:IsSubmitted,1', 'email', 'exists:User,Email'),
                        'IsClaimed' => array('required'),
                        'IsApproved' => array('required')
                            ), array(
                        'ReportID.reports_not_submitted' => 'This report was submitted. You cannot modify or delete it.',
                        'Date.required' => 'Please enter the date of Travel Report',
                        'ApproverEmail.required_if' => 'Please enter the approver.'
                            )
            );

            if ($validator->fails()) {
                return Response::json(array(
                            'reportRow' => $key,
                            'message' => $validator->messages()->all()
                                ), 500);
            }

            $pushSubmit = false;
            $pushApprove = false;
            $report = Report::find($put['ReportID']);
            if ($put['Reference'] != $report->Reference) {
                $refreshTripList = true;
                $report->Reference = Report::checkRef($put['Reference'], $userToken->UserID);
            }

            $report->ModifiedTime = $_SERVER['REQUEST_TIME_FLOAT'];
            if (strpos($put['Date'], 'T') !== false) {
                $put['Date'] = strtotime(substr(str_replace('T', ' ', $put['Date']), 0, -5));
            }

            if ($put['Date'] != $report->Date) {
                $report->Date = $put['Date'];
            }

            if ($put['IsSubmitted'] != $report->IsSubmitted) {
                $report->IsSubmitted = $put['IsSubmitted'];
                $pushSubmit = true;
            }

            if ($put['IsClaimed'] != $report->IsClaimed) {
                $report->IsClaimed = $put['IsClaimed'];
            }

            if ($put['IsApproved'] != $report->IsApproved) {
                ($report->IsApproved == 2 && $put['IsApproved'] == 0) ? $pushSubmit = true : $pushApprove = true;
                $report->IsApproved = $put['IsApproved'];
            }

            $report->save();

            if (isset($put['ApproverEmail']) && !empty($put['ApproverEmail'])) {
                $reportApprover = ReportApprover::where('ReportID', $report->ReportID)->first();
                if ($reportApprover) {
                    $reportApprover->ReportID = $report->ReportID;
                    $reportApprover->Approver = User::where('Email', $put['ApproverEmail'])->first()->UserID;
                    $reportApprover->ModifiedTime = $_SERVER['REQUEST_TIME_FLOAT'];
                    $reportApprover->save();
                } else {
                    $reportApprover = new ReportApprover();
                    $reportApprover->ReportID = $report->ReportID;
                    $reportApprover->Approver = User::where('Email', $put['ApproverEmail'])->first()->UserID;
                    $reportApprover->CreatedTime = $_SERVER['REQUEST_TIME_FLOAT'];
                    $reportApprover->save();
                }
            }

            $response[] = array(
                'Report' => $report->ReportID,
                'Status' => $report->setStatus($userToken->UserID),
            );

            //Push server
            PushBackground::send($userToken->UserID, 'report', 'put,submit', $report->ReportID);
        }

        if (!empty($pushSubmit))
            $refreshTripList = true;

        return Response::json(array(
                    'ReportList' => $response,
                    'RefreshTripList' => $refreshTripList
        ));
    }

    /**
     * This callback is to save a field of a report quickly
     */
    public function putQuickSave() {
        // Need to check authentication
        if (empty($_SERVER['HTTP_AUTH_TOKEN']) || !$userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
            return Response::json(array('message' => 'The authentication is failed.'), 401);
        }

        $put = Input::all();

        $rules = array(
            'ReportID' => array('required', 'reports_belong_to:' . $userToken->UserID, 'reports_not_submitted'),
            'Field' => array('required', 'in:Title,Date,ApproverEmail,Reference,IsSubmitted,IsClaimed,IsApproved,Claimed,Approved,RemovedTrips,TripIDs')
        );

        $messages = array(
            'ReportID.required' => 'You need to specify a trip.',
            'ReportID.reports_belong_to' => 'Specified report does not belong to the user who sent this request.',
            'ReportID.reports_not_submitted' => 'This report was submitted. You cannot modify or delete it.',
            'Field.required' => 'You need to specified a field to be saved.',
            'Field.in' => 'Your specified field is invalid.',
            'Value.required' => 'You need to specified a value to be saved'
        );

        if (isset($put['Field'])) {
            switch ($put['Field']) {
                case 'Title':
                    $rules['Value'] = array('required', 'max:255');
                    $messages['Value.max'] = 'Report title is limited to 255 characters';
                    break;
                case 'Date':
                    $rules['Value'] = array('required', 'date');
                    $messages['Value.max'] = 'Please enter the date of Travel Report';
                    break;
                case 'ApproverEmail':
                    $rules['Value'] = array('email', 'exists:User,Email');
                    $messages['Value.email'] = 'Approver email is invalid.';
                    $messages['Value.exists'] = 'This email address does not belong to anyone in ReceiptClub.';
                    break;
                case 'Reference':
                    $rules['Value'] = array('required', 'max:10', 'quick_report_ref_exist:' . $userToken->UserID);
                    $messages['Value.max'] = 'Reference is limited to 10 characters.';
                    $messages['Value.quick_report_ref_exist'] = 'This Report# is used by another report. Please select other Report# or just use existing one.';
                    break;
                case 'IsSubmitted':
                    $rules['Value'] = array('required', 'in:0,1');
                    $rules['ReportID'][] = 'report_can_be_submitted';
                    break;
                case 'IsClaimed':
                    $rules['Value'] = array('required', 'in:0,1');
                    break;
                case 'IsApproved':
                    $rules['Value'] = array('required', 'in:0,1');
                    break;
                case 'RemoveTrips':
                    $rules['Value'] = array('trips_belong_to:' . $userToken->UserID);
                    break;
                case 'TripIDs':
                    $rules['Value'] = array('required_if:IsSubmitted,1', 'trips_belong_to:' . $userToken->UserID);
                    break;
                case 'Approved':
                    unset($rules['ReportID'][2]);
                    break;
                case 'IsApproved':
                    unset($rules['ReportID'][2]);
                    break;
                default:
                    break;
            }
        }

        $validator = Validator::make($put, $rules, $messages);

        if ($validator->fails()) {
            return Response::json(array('message' => $validator->messages()->all()), 500);
        }

        $report = Report::find($put['ReportID']);
        $ref = $report->Reference;
        $refreshTripList = false;
        switch ($put['Field']) {
            case 'RemovedTrips':
                if (count($put['Value'])) {
                    $refreshTripList = true;
                    Report::removeTripRelationships($put['ReportID'], $put['Value']);
                }
                break;
            case 'TripIDs':
                if (count($put['Value'])) {
                    //We need to refresh the trip list
                    $report->IsClaimed = 1;
                    $refreshTripList = true;
                    $insert = array();
                    foreach ($put['Value'] as $tripID) {
                        $trip = Trip::find($tripID);
                        $_insert = array(
                            'ReportID' => $report->ReportID,
                            'TripID' => $tripID,
                            'IsClaimed' => 1,
                            'Claimed' => 0,
                            'CreatedTime' => $_SERVER['REQUEST_TIME_FLOAT'],
                        );

                        $trip->Items = DB::table('Item AS i')
                                ->join('TripItem AS ti', 'ti.TripItemID', '=', 'i.ItemID')
                                ->select('ItemID', 'Amount')
                                ->where('ti.TripID', $tripID)
                                ->where('IsJoined', 0)
                                ->get();

                        if (count($trip->Items)) {
                            foreach ($trip->Items as $item) {
                                $report->Claimed += $item->Amount;
                                $_insert['Claimed'] += $item->Amount;
                                DB::table('TripItem')
                                        ->where('TripID', $tripID)
                                        ->where('TripItemID', $item->ItemID)
                                        ->update(array(
                                            'Claimed' => $item->Amount,
                                            'IsClaimed' => 1,
                                ));
                            }
                        }

                        $insert[] = $_insert;
                    }

                    if (count($insert)) {
                        DB::table('ReportTrip')
                                ->insert($insert);
                    }
                }
                break;
            case 'Date':
                $report->Date = strtotime($put['Value']);
                $value_pieces = explode('T', $put['Value']);
                $dateRef = 'R' . str_replace('-', '', $value_pieces[0]);
                $ref = Report::checkRef($dateRef, $userToken->UserID);
                break;
            case 'ApproverEmail':
                $approverID = DB::table('User')
                        ->select('UserID')
                        ->where('Email', $put['Value'])
                        ->pluck('UserID');

                $reportApprover = ReportApprover::where('ReportID', $report->ReportID)->first();
                if ($reportApprover) {
                    $reportApprover->ReportID = $report->ReportID;
                    $reportApprover->Approver = $approverID;
                    $reportApprover->ModifiedTime = $_SERVER['REQUEST_TIME_FLOAT'];
                    $reportApprover->save();
                } else {
                    $reportApprover = new ReportApprover();
                    $reportApprover->ReportID = $report->ReportID;
                    $reportApprover->Approver = $approverID;
                    $reportApprover->CreatedTime = $_SERVER['REQUEST_TIME_FLOAT'];
                    $reportApprover->save();
                }
                break;
            case 'Claimed':
                $report->Claimed = $put['Value']['Claimed'];
                $report->IsClaimed = $put['Value']['IsClaimed'];
                if (count($put['Value']['Trips'])) {
                    foreach ($put['Value']['Trips'] as $trip) {
                        $query = DB::table('ReportTrip')
                                ->where('ReportID', $put['ReportID'])
                                ->where('TripID', $trip['TripID'])
                                ->update(array(
                            'Claimed' => $trip['Claimed'],
                            'IsClaimed' => $trip['IsClaimed']
                        ));

                        if ($query && count($trip['Items'])) {
                            foreach ($trip['Items'] as $item) {
                                $query = DB::table('TripItem')
                                        ->where('TripID', $trip['TripID'])
                                        ->where('TripItemID', $item['ItemID'])
                                        ->update(array(
                                    'Claimed' => $item['Claimed'],
                                    'IsClaimed' => $item['IsClaimed']
                                ));
                            }
                        }
                    }
                }
                break;
            case 'Approved':
                $report->Approved = $put['Value']['Approved'];
                $report->IsApproved = $put['Value']['IsApproved'];
                $report->IsAllApproved = $put['Value']['IsAllApproved'];
                $report->IsApproverEdited = 1;
                if (count($put['Value']['Trips'])) {
                    foreach ($put['Value']['Trips'] as $trip) {
                        $query = DB::table('ReportTrip')
                                ->where('ReportID', $put['ReportID'])
                                ->where('TripID', $trip['TripID'])
                                ->update(array(
                            'Approved' => $trip['Approved'],
                            'IsApproved' => $trip['IsApproved']
                        ));

                        if ($query && count($trip['Items'])) {
                            foreach ($trip['Items'] as $item) {
                                $query = DB::table('TripItem')
                                        ->where('TripID', $trip['TripID'])
                                        ->where('TripItemID', $item['ItemID'])
                                        ->update(array(
                                    'Approved' => $item['Approved'],
                                    'IsApproved' => $item['IsApproved']
                                ));
                            }
                        }
                    }
                }
                break;
            case 'IsApproved':
                $report->IsApproverEdited = 1;
                break;
            case 'Reference':
                $ref = strtoupper($put['Value']);
                break;
            case 'IsSubmitted':
                $report->IsSubmitted = 1;
                $report->IsApproved = 0;
                $report->IsApproverEdited = 0;
                $pushSubmit = true;
                $refreshTripList = true;
                break;
            default:
                $report->{$put['Field']} = $put['Value'];
                break;
        }

        if ($ref != $report->Reference) {
            $refreshTripList = true;
            $report->Reference = $ref;
        }
        $report->ModifiedTime = $_SERVER['REQUEST_TIME_FLOAT'];
        $report->save();

        //Push server
        if ($put['Field'] == 'RemovedTrips' || $put['Field'] == 'TripIDs') {
            $tripIds = is_array($put['Value']) ? implode(",", $put['Value']) : $put['Value'];
            PushBackground::send($userToken->UserID, 'trip', 'put', $tripIds);
        }
        if (!empty($pushSubmit)) {
            $refreshTripList = true;
            PushBackground::send($userToken->UserID, 'report', 'put,submit', $report->ReportID);
        } else {
            PushBackground::send($userToken->UserID, 'report', 'put', $report->ReportID);
        }

        return Response::json(array(
                    'Reference' => $report->Reference,
                    'Status' => $report->setStatus($userToken->UserID),
                    'RefreshTripList' => $refreshTripList
        ));
    }

    public function deleteIndex() {
        // Need to check authentication
        if (empty($_SERVER['HTTP_AUTH_TOKEN']) || !$userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
            return Response::json(array('message' => 'The authentication is failed.'), 401);
        }

        $reportIDs = explode(',', Input::get('ReportIDs', 0));
        $customMessages = array('ReportID' => array('required', 'reports_not_submitted', 'reports_belong_to:' . $userToken->UserID));
        if (count($reportIDs) === 1) {
            $customMessages['ReportID.reports_not_submitted'] = 'This report was submitted. You cannot modify or delete it.';
        }
        $validator = Validator::make(array('ReportID' => $reportIDs), $customMessages);

        if ($validator->fails()) {
            return Response::json(array('message' => $validator->messages()->all()), 500);
        }

        $tripIdsToUpdate = array();
        foreach ($reportIDs as $rid) {
            $tripIdsToUpdate = array_merge($tripIdsToUpdate, Trip::getIdsByReport($rid));
        }

        $classifiedReportIDs = Report::classifyReportIDs($reportIDs, $userToken->UserID);
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

        //Push server
        if (count($tripIdsToUpdate) > 0) {
            PushBackground::send($userToken->UserID, 'trip', 'put', implode(",", $tripIdsToUpdate));
        }
        PushBackground::send($userToken->UserID, 'report', 'delete', Input::get('ReportIDs', 0));

        return Response::make('', 204);
    }

    /**
     * Approve or reject a report in report detail screen
     */
    public function putApprove() {
        // Need to check authentication
        if (empty($_SERVER['HTTP_AUTH_TOKEN']) || !$userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
            return Response::json(array('message' => 'The authentication is failed.'), 401);
        }

        $put = Input::all();

        $valSet = array(
            'Approved' => array('required_with:ReportID'),
            'IsApproved' => array('required_with:ReportID'),
        );
        if (isset($put['IsApproved']) && $put['IsApproved'] > 0) {
            $valSet['ReportID'] = array('required_without:ReportIDs', 'reports_approved_by:' . $userToken->UserID);
            $valSet['ReportIDs'] = array('required_without:ReportID', 'reports_approved_by:' . $userToken->UserID);
        }
        $validator = Validator::make($put, $valSet);

        if ($validator->fails()) {
            return Response::json(array('message' => $validator->messages()->all()), 500);
        }

        if (isset($put['ReportIDs'])) {
            if (!isset($put['IsApproved'])) {
                $put['IsApproved'] = 1;
            }

            DB::table('Report')
                    ->whereIn('ReportID', $put['ReportIDs'])
                    ->update(array(
                        'IsApproved' => $put['IsApproved']
            ));

            if ($put['IsApproved'] == 1) {
                $responseStatus = 'Approved';
            } else if ($put['IsApproved'] == 2) {
                $responseStatus = 'Rejected';
            } else {
                $responseStatus = 'Pending';
            }

            $response = array();
            foreach ($put['ReportIDs'] as $reportID) {
                $response[] = array(
                    'ReportID' => $reportID,
                    'Status' => $responseStatus,
                );
            }

            //Push server
            PushBackground::send($userToken->UserID, 'report', 'put,approve', implode(",", $put['ReportIDs']));

            //Make report rejected records
            if ($put['IsApproved'] == 2) {
                ReportRejected::createRejectedRecord($put['ReportIDs'], $userToken->UserID);
            }

            return Response::json($response);
            
        } else {
            $report = Report::find($put['ReportID']);
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

            //Push server
            if ($report->IsApproved == 0) {
                PushBackground::send($userToken->UserID, 'report', 'put,submit', $report->ReportID);
            } else {
                PushBackground::send($userToken->UserID, 'report', 'put,approve', $report->ReportID);
            }

            //Make report rejected records
            if ($put['IsApproved'] == 2) {
                ReportRejected::createRejectedRecord(array($put['ReportID']), $userToken->UserID);
            }

            return Response::json(array(
                        'Status' => $report->setStatus($userToken->UserID)
            ));
        }
    }

    public function putArchive() {
        // Need to check authentication
        if (empty($_SERVER['HTTP_AUTH_TOKEN']) || !$userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
            return Response::json(array('message' => 'The authentication is failed.'), 401);
        }

        // Validate to be sure that all specified trips belongs to the user who send this request
        $puts = Input::all();
        $validator = Validator::make($puts, array('ReportIDs' => array('required', 'reports_belong_to:' . $userToken->UserID),
                    'Archived' => array('required', 'in:0,1')));

        if ($validator->fails()) {
            return Response::json(array('message' => $validator->messages()->all()), 500);
        }

        //Archive the selected trips
        Report::archiveList($puts['ReportIDs'], $userToken->UserID, $puts['Archived']);

        return Response::make('', 204);
    }

    public function postMemo() {
        // Need to check authentication
        if (empty($_SERVER['HTTP_AUTH_TOKEN']) || !$userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
            return Response::json(array('message' => 'The authentication is failed.'), 401);
        }

        $post = Input::all();
        $validator = Validator::make(
                        $post, array(
                    'ReportID' => array('required', 'reports_belong_to:' . $userToken->UserID),
                    'ItemID' => array('required'),
                    'Message' => array('required', 'max:255'),
                        )
        );

        if ($validator->fails()) {
            return Response::json(array('message' => $validator->messages()->all()), 500);
        }

        $reportMemo = new ReportMemo();
        $reportMemo->ReportID = $post['ReportID'];
        $reportMemo->ItemID = $post['ItemID'];
        $reportMemo->UserID = $userToken->UserID;
        $reportMemo->Message = $post['Message'];
        $reportMemo->CreatedTime = $_SERVER['REQUEST_TIME_FLOAT'];
        $reportMemo->save();

        return Response::json(array(
                    'CreatedDate' => date('d-M-Y', $reportMemo->CreatedTime),
                    'CreatedTime' => date('h:i A', $reportMemo->CreatedTime),
                    'Sender' => User::find($userToken->UserID)->Email,
        ));
    }

    public function getPrint() {
        //Need to check authentication
        if (empty($_SERVER['HTTP_AUTH_TOKEN']) || !$userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
            return Response::json(array('message' => 'The authentication is failed.'), 401);
        }


        $reportID = Input::get('reportID', '');
        $itemType = Input::get('itemType', 'all');
        $validator = Validator::make(
                        array(
                    'ReportID' => $reportID,
                    'ItemType' => $itemType
                        ), array(
                    'ReportID' => array('required', 'numeric', 'reports_belong_to:' . $userToken->UserID),
                    'ItemType' => array('in:all,claimed,approved')
                        ), array('ReportID.required' => 'You must specified a report.')
        );

        if ($validator->fails()) {
            return Response::json(array('message' => $validator->messages()->all()), 500);
        }

        $report = Report::getDetail($reportID, $userToken->UserID, array('pdfItemType' => $itemType));

        if ($report) {
            $arrPdfFile = array();
            $profile = Profile::find($userToken->UserID);
            $itemTypeText = 'Full report';
            if ($itemType == 'claimed') {
                $itemTypeText = 'Claimed Items Only';
            }
            if ($itemType == 'approved') {
                $itemTypeText = 'Approved Items Only';
            }

            //Initialize the pdf creator object
            $pdf = new MyPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, TRUE, 'UTF-8', FALSE);
            $pdf->SetCreator("Receipt Club");
            $pdf->SetAuthor('Receipt Club');
            $pdf->SetTitle('Travel Expense Report');
            $pdf->SetKeywords('TCPDF, PDF, receipt, receiptclub, travelexpense, report');
            //Set default configs and values            
            $pdf->setPageOrientation('L');
            $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
            $pdf->SetMargins(PDF_MARGIN_LEFT, 10, PDF_MARGIN_RIGHT);
            $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
            $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
            $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
            $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
            $pdf->setDisplayMode(70);

            $pdf->AddPage();
            $pdf->setFontSubsetting(true);
            $pdf->SetFont('helvetica', '', 9);
            $view = View::make('pdfs.report', array(
                        'report' => $report,
                        'currency' => $profile ? $profile->CurrencyCode : '',
                        'itemTypeText' => $itemTypeText,
            ));

            $pdf->writeHTML($view->render(), TRUE, FALSE, FALSE, FALSE, '');
            if (count($report->Trips)) {
                $pdf->SetMargins(PDF_MARGIN_LEFT, 20, PDF_MARGIN_RIGHT);
//                dd($report->Trips);
                foreach ($report->Trips as $key => $trip) {
                    $trip->key = $key + 1;
                    $pdf->AddPage();
                    $pdf->setFontSubsetting(true);
                    $pdf->SetFont('helvetica', '', 9);
                    $view = View::make('pdfs.report_trip', array('trip' => $trip));
                    $pdf->writeHTML($view->render(), TRUE, FALSE, FALSE, FALSE, '');
                }
            }

            $fileBasePath = Config::get('app.fileBasePath');
            //Remove old file and old folder of previous generation                                    
            exec('rm -R ' . $fileBasePath . '/report_pdf/*');
            $fileNameUsername = substr($profile->FirstName . ' ' . $profile->LastName, 0, 40);
            $fileNameTitle = substr($report->Title, 0, 60);
            $dirName = 'Travel Expense Report ' . date('Y-m-d') . ' ' . $fileNameUsername . ' - ' . $fileNameTitle . '(' . $itemType . ' item) - ' . date('H:i:s');
            ;
            $dirName = preg_replace('/[^A-Za-z0-9 _\-]/', '_', $dirName);
            $pdfFileName = $dirName . '.pdf';
            $tmppdfFilePath = $pdfFileName;
            if ($report->HasImagesOrEmails) {
                $tmpDirStorePdf = $fileBasePath . 'tmpDirPdf';
                $pdfDirPath = $fileBasePath . 'report_pdf/' . $dirName;
                if (!file_exists($pdfDirPath)) {
                    mkdir($pdfDirPath);
                    exec('chmod 777 -R "' . $pdfDirPath . '"');
                    exec('chmod 777 -R "' . $tmpDirStorePdf . '"');
                }
                $arrPdf = array();
                foreach ($report->Trips as $trip) {
                    if (count($trip->Items)) {
                        foreach ($trip->Items as $item) {
                            if ($item->ReceiptImage) {
                                if (!$item->ReceiptImage->Used) {
                                    $item->ReceiptImage->Number = str_pad($item->ReceiptImage->Number, 2, '0', STR_PAD_LEFT);
//                                    dd($item->ReceiptImage->FileBucket .'/'. $item->ReceiptImage->FilePath);
//                                    dd(Receipt::createReceiptImageUrl($item->ReceiptImage->FileBucket .'/'. $item->ReceiptImage->FilePath));
                                    $data = file_get_contents(File::getS3PreSignedUrl($item->ReceiptImage->FileBucket, $item->ReceiptImage->FilePath));
                                    $tmpPdfPath = $tmpDirStorePdf . '/' . $trip->Reference . ' - Receipt' . $item->ReceiptImage->Number . '.' . $item->ReceiptImage->FileExtension;
                                    file_put_contents($tmpPdfPath, $data);
                                    array_push($arrPdf, $tmpPdfPath);
                                }
                            } else if ($item->RawData) {
                                $item->RawData->Number = str_pad($item->RawData->Number, 2, '0', STR_PAD_LEFT);
                                $htmlEmail = $item->RawData->RawData;                                        //                                
                                $pdf->AddPage();
                                // output the HTML content
                                $pdf->writeHTML(html_entity_decode($htmlEmail, ENT_QUOTES), true, false, false, false, ''); //                                
                            }
                        }
                    }
                }
                //output file pdf
                $arrConcatPdfFile = array();
                $pdf->Output($tmpDirStorePdf . '/' . $pdfFileName, 'F');
                $arrConcatPdf = array($tmpDirStorePdf . '/' . $pdfFileName);
                $arrConcatPdfFile = array_merge($arrConcatPdf, $arrPdf);
                //Concat 2 pdf  
                $pdf = new PdfConcat();

                // set default header data
                $pdf->setHtmlHeader('<table style="font-size: 6px; color: #666">
                                        <tr>
                                            <td colspan="2" style="text-align: center"><b>' . $report->Title . ' No. ' . $report->Reference . '</b></td>
                                        </tr>
                                    </table>');

                $pdf->setHtmlExtraHeader('<table style="font-size: 6px; color: #666">
                                        <tr>
                                            <td style="text-align: left">Submitted by: ' . $report->SubmitterFirstName . ' ' . $report->SubmitterLastName . '<br/>' . $report->SubmitterCompanyName . '</td>
                                            <td style="text-align: right">Approver: ' . $report->ApproverFirstName . ' ' . $report->ApproverLastName . '<br/>' . $report->ApproverCompanyName . '</td>
                                        </tr>
                                    </table>');

                //set default footer data
                $pdf->setHtmlFooter('<table  style="font-size: 6px; color: #666">
                                        <tr>
                                             <td style="text-align: left"><img src="' . Config::get('app.fileBaseUrl') . 'logo-small.png"></td>
                                             <td style="text-align: center; line-height:30px;">' . date('d-M-Y') . '</td>
                                             <td style="text-align: right; line-height:30px;">Page ' . $pdf->getAliasNumPage() . ' of ' . $pdf->getAliasNbPages() . '</td>
                                        </tr>
                                   </table>');

                $pdf->SetMargins(PDF_MARGIN_LEFT, 5, PDF_MARGIN_RIGHT);
                $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
                $pdf->SetFooterMargin(10);
                $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
                //set all file to concat
                $pdf->setFiles($arrConcatPdfFile);
                $pdf->concat();
                $pdf->Output($pdfDirPath . '/' . $pdfFileName, "F");

                $file = new File();
                $file->FileName = $tmppdfFilePath;
                $file->FilePath = 'report_pdf/' . $file->FileName;
                $file->Timestamp = $_SERVER['REQUEST_TIME_FLOAT'];
                $file->EntityID = $report->ReportID;
                $file->EntityName = 'report_pdf';
                $file->save();
            } else {
                $file = new File();
                $file->FileName = $pdfFileName;
                $file->FilePath = 'report_pdf/' . $file->FileName;
                $file->Timestamp = $_SERVER['REQUEST_TIME_FLOAT'];
                $file->EntityID = $report->ReportID;
                $file->EntityName = 'report_pdf';
                $file->save();
                $pdf->Output('files/' . $file->FilePath, 'F');
            }

            $tmpPdfDir = ($report->HasImagesOrEmails) ? 'report_pdf/' . $dirName . '/' . $tmppdfFilePath : 'report_pdf/' . $tmppdfFilePath;

            return Response::json(array(
                        'FilePath' => $tmpPdfDir
            ));
        }
        return Response::json(array('message' => array('Cannot find the specified receipt(s).')), 500);
    }

    public function getDownloadPdf() {
        $filePath = Input::get('filePath', '');
        $file = Config::get('app.fileBasePath') . $filePath;

        if (!is_file($file)) {
            return Response::make('Cannot find the specified file:' . $file, 500);
        }
        $filePathPieces = explode('/', $filePath);
        $fileName = $filePathPieces[1];
        $dirName = str_replace('.pdf', '', $fileName);
        header('Content-Description: File Transfer');
        if (strpos('pdf', $fileName) !== -1) {
            header('Content-Type: application/pdf');
        } else {
            header('Content-Type: application/octet-stream');
        }
        header('Content-Disposition: attachment; filename=' . $fileName);
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        ob_clean();
        flush();
        readfile($file);
        exit;
    }

    /**
     * Secret function to generate report automatically (for testing only)
     *
     * @return mixed
     */
    public function postDumpReport() {
        // Need to check authentication
        if (empty($_SERVER['HTTP_AUTH_TOKEN']) || !$userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
            return Response::json(array('message' => 'The authentication is failed.'), 401);
        }

        $uid = $userToken->UserID;
        $num = Input::get('amount', 0);
        $sdate = Input::get('from', 0);
        if (!$sdate)
            return;

        $stime = strtotime($sdate);
        $unq = uniqid();
        $unq = substr($unq, strlen($unq) - 4);
        $oneDay = 60 * 60 * 24;

        $latestReport = DB::table('Report')->orderBy('Date', 'desc')->first();
        //dd('T' . date('Ymd', $latestTrip->EndDate));

        for ($i = 1; $i <= $num; $i++) {
            $date = $stime + $oneDay * $i;
            $reportRef = date('Ymd', $date);
            DB::table('Report')
                    ->insert(array(
                        'Title' => 'Report ' . $unq . ' ' . $i,
                        'Date' => $date,
                        'Claimed' => 0,
                        'Approved' => 0,
                        'IsSubmitted' => 0,
                        'IsClaimed' => 0,
                        'Submitter' => $uid,
                        'IsArchived' => 0,
                        'Reference' => 'R' . $reportRef,
                        'CreatedTime' => time(),
                        'ModifiedTime' => time(),
                        'IsAllApproved' => 0,
                        'IsApproverEdited' => 0
            ));
        }

        return Response::json(array(), 200);
    }

}
