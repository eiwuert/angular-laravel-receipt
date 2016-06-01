<?php

/**
 * Controller for receipts
 */
class TripV2Controller extends BaseApiController 
{
    public function __construct() 
    {
        return parent::__construct();
    }
    
    protected $model = 'Trip';
    
    /*
     * GET /objects/{id}
     * 
     * Get specific object by id
     */

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
    
}
