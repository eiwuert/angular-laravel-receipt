<?php
$startTime = time();

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/start.php';
$app->boot();

$uid     = (int) $argv[1];
$ctrl    = $argv[2];
$actions = explode(",", $argv[3]);
$arrIds  = explode(",", $argv[4]);
$user    = User::find($uid);

if (empty($user->UserID)) {
    die;
}

switch($ctrl) {
    case 'report':
        foreach($actions as $action ) reportCtrl($action, $arrIds);
        break;
    case 'trip':
        foreach($actions as $action ) tripCtrl($action, $arrIds);
        break;
    case 'receipt':
        foreach($actions as $action ) receiptCtrl($action, $arrIds);
        break;
    case 'item':
        foreach($actions as $action ) itemCtrl($action, $arrIds);
        break;
}

/**
 * Push event for report controller
 * 
 * @param string $action  Name of action
 * @param array  $arrIds  report ids
 */
function reportCtrl ($action, $arrIds)
{
    if (count($arrIds) == 0) return;
    
    global $user;
    $report  = Report::find($arrIds[0]);
    $profile = Profile::find($user->UserID);
    
    if ($action == 'post') {
        Push::toMobile('P:C:' . $report->ReportID, $user->UserID);
    }
    
    if ($action == 'put') {
        $reportIDs = implode(',', $arrIds);
        Push::toMobile('P:U:' . $reportIDs, $user->UserID);
    }

    if ($action == 'delete') {
        $reportIDs = implode(',', $arrIds);
        Push::toMobile('P:D:' . $reportIDs, $user->UserID);
    }
    
    if ($action == 'submit') {
        $reportApprover = ReportApprover::where('ReportID', $report->ReportID)->first();
        if ($reportApprover) {
            $approver = User::find($reportApprover->Approver);
            $trips    = DB::table('ReportTrip')->where('ReportID', $report->ReportID)->get();

            foreach ($trips as $trip) {
                //1 - Push Trips
                Push::toMobile('T:C:' . $trip->TripID, $approver->UserID);

                $items = DB::table('TripItem AS ti')
                    ->where('ti.TripID', $trip->TripID)
                    ->join('Item AS i', 'i.ItemID', '=', 'ti.TripItemID')
                    ->where('i.IsJoined', 0)
                    ->lists('ti.TripItemID');

                if (is_array($items) && count($items)) {
                    //2 - Push Receipts
                    $receiptIds = implode(",", Receipt::queryAllContainItems($items));
                    Push::toMobile('R:C:' . $receiptIds, $approver->UserID);


                    //3 - Push Items
                    $itemIds = implode(",", $items);
                    Push::toMobile('I:C:' . $itemIds, $approver->UserID);
                }
            }

            Push::toMobile('P:S:' . $report->ReportID, $approver->UserID);
            Push::toWeb($report->ReportID.'$:'.$profile->FirstName.' '.$profile->LastName.' submitted a report '.$report->Reference,'reportSubmit', $approver->UserID);
        }
    }
    
    if ($action == 'approve') {
        $reportIDs = implode(',', $arrIds);

        foreach($arrIds as $rid) {
            $tmpReport = Report::find($rid);
            $submitter = User::find($tmpReport->Submitter);
            $trips     = DB::table('ReportTrip')->where('ReportID', $rid)->get();

            foreach ($trips as $trip) {
                $items = DB::table('TripItem AS ti')
                    ->where('ti.TripID', $trip->TripID)
                    ->join('Item AS i', 'i.ItemID', '=', 'ti.TripItemID')
                    ->where('i.IsJoined', 0)
                    ->lists('ti.TripItemID');

                $itemIds = implode(",", $items);
                Push::toMobile('I:U:' . $itemIds, $submitter->UserID);
            }
            
            $code  = ($tmpReport->IsApproved == 1)? 'A' : 'R';
            Push::toMobile('P:' . $code . ':' . $rid, $submitter->UserID);

            $action = ($tmpReport->IsApproved == 1)? 'approved' : 'rejected';
            $event  = ($tmpReport->IsApproved == 1)? 'reportApproved' : 'reportRejected';
            Push::toWeb($rid.'$:'.$profile->FirstName.' '.$profile->LastName.' '.$action.' your report '.$tmpReport->Reference, $event, $submitter->UserID);
        }
    }
}

/**
 * Push event for trip controller
 * 
 * @param string $action  Name of action
 * @param array  $arrIds  trip ids
 */
function tripCtrl ($action, $arrIds)
{
    if (count($arrIds) == 0) return;
    
    global $user;
    $tripIDs = implode(",", $arrIds);
    
    if ($action == 'post') {
        Push::toMobile('T:C:' . $tripIDs, $user->UserID);
    }
    
    if ($action == 'put') {
        Push::toMobile('T:U:' . $tripIDs, $user->UserID);
    }

    if ($action == 'delete') {
       Push::toMobile('T:D:' . $tripIDs, $user->UserID);
    }
}

/**
 * Push event for receipt controller
 * 
 * @param string $action  Name of action
 * @param array  $arrIds  receipt ids
 */
function receiptCtrl ($action, $arrIds)
{
    if (count($arrIds) == 0) return;
    
    global $user;
    //$trip = Trip::find($arrIds[0]);
    $receiptIDs = implode(",", $arrIds);
    
    if ($action == 'post') {
        Push::toMobile('R:C:' . $receiptIDs, $user->UserID);
    }
    
    if ($action == 'put') {
        Push::toMobile('R:U:' . $receiptIDs, $user->UserID);
    }

    if ($action == 'delete') {
        Push::toMobile('R:D:' . $receiptIDs, $user->UserID);
    }
}

/**
 * Push event for receipt controller
 * 
 * @param string $action  Name of action
 * @param array  $arrIds  receipt ids
 */
function itemCtrl ($action, $arrIds)
{
    if (count($arrIds) == 0) return;
    
    global $user;
    $itemIDs = implode(",", $arrIds);
    
    if ($action == 'post') {
        Push::toMobile('I:C:' . $itemIDs, $user->UserID);
    }
    
    if ($action == 'put') {
        Push::toMobile('I:U:' . $itemIDs, $user->UserID);
    }

    if ($action == 'delete') {
        Push::toMobile('I:D:' . $itemIDs, $user->UserID);
    }
}