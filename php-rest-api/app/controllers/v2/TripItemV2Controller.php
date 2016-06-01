<?php

/**
 * Controller for receipt item
 */
class TripItemV2Controller extends BaseApiController 
{
    public function __construct() 
    {
        return parent::__construct();
    }
    
    protected $model = 'Item';

    protected $parentPrimaryKey = 'TripID';
}
