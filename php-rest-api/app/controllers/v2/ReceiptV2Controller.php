<?php

/**
 * Controller for receipts
 */
class ReceiptV2Controller extends BaseApiController 
{
    public function __construct() 
    {
        return parent::__construct();
    }
    
    protected $model = 'Receipt';
    
}
