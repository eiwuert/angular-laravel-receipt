<?php

/**
 * Controller for receipt item
 */
class TripItemsV2Controller extends BaseApiController 
{
    public function __construct() 
    {
        return parent::__construct();
    }
    
    protected $model = 'TripItem';

    public function storeSingleObject($post) 
    {
        $model = $this->model;
        $primaryKey = $model::getPrimaryKey();
        
        $id = $post[$primaryKey];
        
        $messages = $model::validateModel($post, $this->getUser());
        $returnObject = new stdClass();
        
        if (count($messages)) {
            $returnObject->status = 'fail';
            $returnObject->$primaryKey = $id;
            $returnObject->messages = $messages['message'];
            return $returnObject;
        }

        $object = $model::processStore($post, $this->getUser());
        $returnObject->status = 'success';
        $returnObject->$primaryKey = $id;
        $returnObject->messages = array();
        return $returnObject;
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
    
    public function updateSingleObject($put) 
    {
		$model = $this->model;
        $primaryKey = $model::getPrimaryKey();
        
        $id = $put[$primaryKey];
        
        if (isset($put['MobileSync'])) {
            $objectFromMobileSync = DB::table($model)->where('MobileSync', $put['MobileSync'])->first();
            $object = $model::find($objectFromMobileSync->$primaryKey);
            unset($put['MobileSync']);
        } else {
            $object = $model::find($id);
        }
        
        $returnObject = new stdClass();
        
        $havePermission = $this->validateSingleObjectPermission($object);
        
        if(strlen($havePermission) > 0) {
            $returnObject->status = 'fail';
            $returnObject->$primaryKey = $id;
            $returnObject->messages = (array)$havePermission;
            return $returnObject;
        }

		$messages = $model::validateModel($put, $this->getUser(), $object);
        
        if (count($messages)) {
            $returnObject->status = 'fail';
            $returnObject->$primaryKey = $id;
            $returnObject->messages = $messages['message'];
            return $returnObject;
        }
		
        $model::processUpdate($put, $this->getUser(), $object);
		
        $returnObject->status = 'success';
        $returnObject->$primaryKey = $object->$primaryKey;
        $returnObject->messages = array();
        return $returnObject;
    }
}
