<?php
/**
 * Controller for user interactions with the system
 */
class CommonV2Controller extends BaseV2Controller 
{
    /**
	 * unarchived receipt
	 */
	public function postUnarchivedReceipts()
	{
        $query = DB::table('Receipt as r');
        $query->where('r.IsArchived', 1);
        
        $query->select('r.ReceiptID');
        $query->skip(0);
        $query->take(99999);
        
        $receiptArchived = $query->get();
        
        
		foreach ($receiptArchived as $receipt) {
            $receiptSmall = Receipt::find($receipt->ReceiptID);
            $receiptSmall->IsArchived = 0;
            $receiptSmall->save();
        }
		$jsend = JSend\JSendResponse::success();
        return $jsend->respond();
	}
    
    /**
	 * unarchived trip
	 */
	public function postUnarchivedTrips()
	{
        $query = DB::table('Trip as r');
        $query->where('r.IsArchived', 1);
        
        $query->select('r.TripID');
        $query->skip(0);
        $query->take(99999);
        
        $tripArchived = $query->get();
        
		foreach ($tripArchived as $trip) {
            $tripSmall = Trip::find($trip->TripID);
            $tripSmall->IsArchived = 0;
            $tripSmall->save();
        }
		$jsend = JSend\JSendResponse::success();
        return $jsend->respond();
	}
    
    /**
	 * unarchived report
	 */
	public function postUnarchivedReports()
	{
        $query = DB::table('Report as r');
        $query->where('r.IsArchived', 1);
        
        $query->select('r.ReportID');
        $query->skip(0);
        $query->take(99999);
        
        $reportArchived = $query->get();
        
		foreach ($reportArchived as $report) {
            $reportSmall = Report::find($report->ReportID);
            $reportSmall->IsArchived = 0;
            $reportSmall->save();
        }
		$jsend = JSend\JSendResponse::success();
        return $jsend->respond();
	}
    
}
