<?php

/**
 * Controller for merchant
 */
class MerchantV2Controller extends BaseApiController 
{
    public function __construct() 
    {
        return parent::__construct();
    }
    
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
    
    protected $model = 'Merchant';
    
}
