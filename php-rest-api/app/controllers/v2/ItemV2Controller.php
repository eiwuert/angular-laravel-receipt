<?php

/**
 * Controller for items
 */
class ItemV2Controller extends BaseApiController 
{
    public function __construct()
    {
        return parent::__construct();
    }
    
    protected $model = 'Item';
    
    public function validateObjectPermission($object)
    {
        if ($object == null) {
            $jsend = JSend\JSendResponse::error("Cannot find resource", 404);
            return $jsend->respond();
        }
    }
    
    public function validateSingleObjectPermission($object)
    {
        $message = '';
        if ($object == null) {
            $message = "Cannot find resource";
            return $message;
        }
        
        return $message;
    }
    
    /* GET /objects
     * 
     * Check item is free (not in any report or in only draft report)
     */
    public function checkfree() {
        $input = Input::all();
        
        if (!isset($input['TripID'])) {
            $jsend = JSend\JSendResponse::error("Please provide TripID", 404);
            return $jsend->respond();
        } 
        
        $trip = Trip::find($input['TripID']);
        
        if ($trip == null) {
            $jsend = JSend\JSendResponse::error("Cannot find resource", 404);
            return $jsend->respond();
        }
        
        $tripID = $input['TripID'];

        $model = $this->model;
        $itemInReport = $model::checkInReport($tripID);

        if(! $itemInReport) {
            $result['IsFree'] = true;

            $jsend = JSend\JSendResponse::success($result);
            return $jsend->respond();
        } else {
            // Item already in report, check that report is draft of not
            $itemInReportDraft = $model::checkInReportDraft($tripID);
            if($itemInReportDraft) {
                $result['IsFree'] = true;

                $jsend = JSend\JSendResponse::success($result);
                return $jsend->respond();
            } else {
                $result['IsFree'] = false;

                $jsend = JSend\JSendResponse::success($result);
                return $jsend->respond();
            }
        }
    }
}
