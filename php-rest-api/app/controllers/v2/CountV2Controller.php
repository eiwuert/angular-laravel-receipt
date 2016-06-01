<?php
/**
 * Controller for count
 */
class CountV2Controller extends BaseApiController 
{
    public function __construct()
    {
        return parent::__construct();
    }
    
    /*
     * GET /count
     * 
     * Process count
     */
	public function index($id = null)
	{
        $input = Input::all();
        
        if(isset($input['ReceiptType'])) {
            $receiptType = $input['ReceiptType'];
            if(!is_array($receiptType)) {
                $receiptType = array($receiptType);
            }
        } else {
            $receiptType = array();
        }
        
        if(isset($input['ReportType'])) {
            $reportType = $input['ReportType'];
            if(!is_array($reportType)) {
                $reportType = array($reportType);
            }
        } else {
            $reportType = array();
        }
        
        if(isset($input['TripType'])) {
            $tripType = $input['TripType'];
            if(!is_array($tripType)) {
                $tripType = array($tripType);
            }
        } else {
            $tripType = array();
        }
        
        $receipt = Receipt::countAllKind($this->getUser(), $receiptType);
        
        $trip = Trip::countAllKind($this->getUser(), $tripType);
        
        $report = Report::countAllKind($this->getUser(), $reportType);
        
        $result = array_merge($receipt, $trip, $report);
        
        
        $jsend = JSend\JSendResponse::success($result);
        return $jsend->respond();
	}
    
    /*
     * GET /count/receipts
     * 
     * Process count receipts
     */
	public function countReceipts()
	{	
        $input = Input::all();
        if(isset($input['ReceiptType'])) {
            $receiptType = $input['ReceiptType'];
            if(!is_array($receiptType)) {
                $receiptType = array($receiptType);
            }
        } else {
            $receiptType = array();
        }
        $receipt = Receipt::countAllKind($this->getUser(), $receiptType);
        
        $jsend = JSend\JSendResponse::success($receipt);
        return $jsend->respond();
	}
    
    /*
     * GET /count/reports
     * 
     * Process count reports
     */
	public function countReports()
	{	
        $input = Input::all();
        if(isset($input['ReportType'])) {
            $reportType = $input['ReportType'];
            if(!is_array($reportType)) {
                $reportType = array($reportType);
            }
        } else {
            $reportType = array();
        }
        
        $report = Report::countAllKind($this->getUser(), $reportType);
        
        $jsend = JSend\JSendResponse::success($report);
        return $jsend->respond();
	}
    
    /*
     * GET /count/trips
     * 
     * Process count trips
     */
	public function countTrips()
	{	
        $input = Input::all();
        if(isset($input['TripType'])) {
            $tripType = $input['TripType'];
            if(!is_array($tripType)) {
                $tripType = array($tripType);
            }
        } else {
            $tripType = array();
        }
        
        $trip = Trip::countAllKind($this->getUser(), $tripType);
        
        $jsend = JSend\JSendResponse::success($trip);
        return $jsend->respond();
	}
}
