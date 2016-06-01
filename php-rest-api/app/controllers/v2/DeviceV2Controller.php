<?php

/**
 * Controller for device
 */
class DeviceV2Controller extends BaseApiController 
{
    public function __construct() 
    {
        return parent::__construct();
    }
    
    protected $model = 'DeviceApiToken';
    
    /*
     * POST /objects
     * 
     * Create new object
     */
    public function store() 
    {
        $model = $this->model;
        $post = Input::all();

        $messages = $model::validateStore($post, $this->getUser());

        if (count($messages)) {
            $jsend = JSend\JSendResponse::fail($messages);
            return $jsend->respond();
        }
        
        $object = $model::processStore($post, $this->getUser());

        $jsend = JSend\JSendResponse::success($object->toArray());
        return $jsend->respond();
    }
    
    /* DELETE /object
     * 
     * Delete object
     */
    public function delete() 
    {
        $model = $this->model;
        $post = Input::all();
        
        $messages = $model::validateDestroy($post, $this->getUser());
        
        if (count($messages)) {
            $jsend = JSend\JSendResponse::fail($messages);
            return $jsend->respond();
        }
        
        $model::processDestroy($post, $this->getUser());

        return $jsend = JSend\JSendResponse::success();
    }
}
