<?php

/**
 * Controller for receipt item
 */
class ReceiptItemV2Controller extends BaseApiController 
{
    public function __construct() 
    {
        return parent::__construct();
    }
    
    protected $model = 'Item';

    protected $parentPrimaryKey = 'ReceiptID';
}
