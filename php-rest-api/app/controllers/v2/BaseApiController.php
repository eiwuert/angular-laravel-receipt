<?php

/**
 * Controller for BaseApi
 */
abstract class BaseApiController extends BaseV2Controller 
{
    protected $model;
    
    protected $user;
    
    protected $parentPrimaryKey;

    protected function getUser() 
    {
        return $this->user;
    }

    protected function setUser($user) 
    {
        $this->user = $user;
    }

    public function __construct() 
    {
        
        $userToken = UserApiToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN']);

        if (empty($_SERVER['HTTP_AUTH_TOKEN']) || !$userToken) {
            $jsend = JSend\JSendResponse::error('The authentication is failed.', 401);
            return $jsend->respond();
        }
        
        $this->setUser(User::where('UserID', $userToken->UserID)->first());
        parent::__construct();
    }

    /*
     * GET /objects
     * 
     * Get object list
     */
    public function getall($id = null) 
    {
        $query = $this->processInput($id);
        
        $model = $this->model;

        $result = $model::getAll($query['where'], $query['sort'], $query['limit'], $query['offset']);
        
        // TODO: optimize
        foreach ($result as $id=>$object) {
            if(!empty($query['fields'])) {
                foreach ($object as $key=>$value) {
                    if(in_array($key, $query['fields'])) {
                        continue;
                    } else {
                        unset($object->$key);
                    }
                }
            }
        }
        $jsend = JSend\JSendResponse::success($result);
        return $jsend->respond();
    }
    
    /*
     * GET /objects
     * 
     * Get object list
     */
    public function index($id = null) 
    {
        $query = $this->processInput($id);
        
        $model = $this->model;

        $result = $model::getAll($query['where'], $query['sort'], $query['limit'], $query['offset']);
        
        // TODO: optimize
        foreach ($result as $id=>$object) {
            if(!empty($query['fields'])) {
                foreach ($object as $key=>$value) {
                    if(in_array($key, $query['fields'])) {
                        continue;
                    } else {
                        unset($object->$key);
                    }
                }
            }
        }
        $jsend = JSend\JSendResponse::success($result);
        return $jsend->respond();
    }

    /*
     * GET /objects/{id}
     * 
     * Get specific object by id
     */

    public function show($id) 
    {
        $model = $this->model;
        
        $object = $this->showObjectById($id);

        $this->validateObjectPermission($object);
        
        $object = $this->filterByFields($object);

        $jsend = JSend\JSendResponse::success((array) $object);
        $jsend->respond();
    }

    /*
     * POST /objects
     * 
     * Create new object
     */
    public function store() 
    {
        $model = $this->model;
        $post = Input::all();

        $messages = $model::validateModel($post, $this->getUser());

        if (count($messages)) {
            $jsend = JSend\JSendResponse::fail($messages);
            return $jsend->respond();
        }

        
        $object = $model::processStore($post, $this->getUser());

        $object = $this->filterByFields($object);
        
        $jsend = JSend\JSendResponse::success((array) $object);
        return $jsend->respond();
    }
    
    public function storeSingleObject($post) 
    {
        $model = $this->model;
        $primaryKey = $model::getPrimaryKey();
        
        $id = $post[$primaryKey];
        unset($post[$primaryKey]);
        
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
        $returnObject->$primaryKey = $object->$primaryKey;
        $returnObject->messages = array();
        return $returnObject;
    }

    /*
     * PUT /objects/{id}
     * 
     * Update object by id
     */

    public function update($id) 
    {
		$put = Input::all();
        
        $model = $this->model;
        
        $object = $model::find($id);

        $this->validateObjectPermission($object);

		$messages = $model::validateModel($put, $this->getUser(), $object);
		if (count($messages)) {
            $jsend = JSend\JSendResponse::fail($messages);
            return $jsend->respond();
        }		
		
        $model::processUpdate($put, $this->getUser(), $object);
		
		$jsend = JSend\JSendResponse::success();
        return $jsend->respond();
    }
    
    public function updateSingleObject($put) 
    {
		$model = $this->model;
        $primaryKey = $model::getPrimaryKey();
        
        $id = $put[$primaryKey];
        unset($put[$primaryKey]);
        
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

    /* DELETE /objects/{id}
     * 
     * Delete object by id
     */
    public function destroy($id) 
    {
        $model = $this->model;
        
        $messages = $model::validateDestroy($id, $this->getUser());

        if (count($messages)) {
            $jsend = JSend\JSendResponse::fail($messages);
            return $jsend->respond();
        }

        $model::processDestroy($id, $this->getUser());

        return $jsend = JSend\JSendResponse::success();
    }
    
    public function deleteSingleObject($id) 
    {
        $model = $this->model;
        
        $messages = $model::validateDestroy($id, $this->getUser());
        
        $returnObject = new stdClass();
        
        if (count($messages)) {
            $returnObject->status = 'fail';
            $returnObject->id = $id;
            $returnObject->messages = $messages['message'];
            return $returnObject;
        }

        $model::processDestroy($id, $this->getUser());

        $returnObject->status = 'success';
        $returnObject->id = $id;
        $returnObject->messages = array();
        return $returnObject;
    }
    
    /* DELETE /objects
     * 
     * Delete multiple objects
     */
    public function delete() 
    {
        $model = $this->model;
        $input = Input::all();  
       
        $arrayIdDelete = $input[$model::getPrimaryKey()];
        
        $result = array();
        foreach ($arrayIdDelete as $id) {
            $singleResult = $this->deleteSingleObject($id);
            $result[] = $singleResult;
        }
        
        return $jsend = JSend\JSendResponse::success($result);
    }
    
    /* GET /objects/count
     * 
     * count total number of objects
     */
    public function count() 
    {   
        $query = $this->processInput();
        
        $model = $this->model;
        
        $result['total'] = $model::countAll($query['where']);

        $jsend = JSend\JSendResponse::success($result);
        return $jsend->respond();
    }

    protected function processInput($id = null) 
    {
        $input = Input::all();
        
        $bodyArrayKey = $this->model . '-Array';
		if(isset($input[$bodyArrayKey]) && !empty($input[$bodyArrayKey])) {
            $arrayInput = $input[$bodyArrayKey];
			parse_str($arrayInput, $output);
            foreach($output as $key => $value) {
                $input[$key] = $value;
            }
            unset($input[$bodyArrayKey]);
        }
        
        $input['UserID'] = $this->getUser()->UserID;
        if($id != null) {
            $input[$this->parentPrimaryKey] = $id;
        }
        
        $result = array();
        
        $fields = array();
        if (array_key_exists('fields', $input)) {
            $fields = explode(',', $input['fields']);
            unset($input['fields']);
        }
        
        $sort = array();
        if (array_key_exists('sort', $input)) {
            foreach (explode(',', $input['sort']) as $sortValue) {
                if (substr($sortValue, 0, 1) == '-') {
                    $sort[substr($sortValue, 1)] = 'Desc';
                } else {
                    $sort[$sortValue] = 'Asc';
                }
            }

            unset($input['sort']);
        }
                
        $limit = 10;
        if (array_key_exists('limit', $input)) {
            $limit = $input['limit'];
            unset($input['limit']);
        }

        $offset = 0;
        if (array_key_exists('offset', $input)) {
            $offset = $input['offset'];
            unset($input['offset']);
        }
        
        $where = $input;
        
        $result['fields'] = $fields;
        $result['sort'] = $sort;
        $result['limit'] = $limit;
        $result['offset'] = $offset;
        $result['where'] = $where;
        
        return $result;
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
    
    public function showObjectById($id) {
        $model = $this->model;
        
        return $model::getById($id);
    }
    
    protected function filterByFields($object) {
        $input = Input::all();
        
        $fields = array();
        if (array_key_exists('fields', $input)) {
            $fields = explode(',', $input['fields']);
            unset($input['fields']);
        }
        
        // TODO: optimize
        if(!empty($fields)) {
            foreach ($object as $key=>$value) {
                if(in_array($key, $fields)) {
                    continue;
                } else {
                    unset($object->$key);
                }
            }
        }
        
        return $object;
    }
    
    /* POST /objects
     * 
     * Create multiple objects
     */
    public function create() 
    {
        $post = Input::all();
        
        $bodyArrayKey = $this->model . '-Array';
		if(isset($post[$bodyArrayKey]) && !empty($post[$bodyArrayKey])) {
            $arrayInput = $post[$bodyArrayKey];
			parse_str($arrayInput, $output);
            $post[$this->model] = $output[$this->model] ;
        }
        
        $jsonKey = $this->model . '-Multiple';
        
        if(isset($post[$jsonKey]) && !empty($post[$jsonKey])) {
            $jsonInput = $post[$jsonKey];
            $arrayObject = json_decode($jsonInput);
            foreach($arrayObject as $key => $object) {
                $arrayObject[$key] = get_object_vars($object);
            }
            
            $post[$this->model] = $arrayObject;
        }
        
        $result = array();
        foreach ($post[$this->model] as $singlePost) {
            $mobileSyncID = $singlePost['MobileSync'];
            
            // Check if there is an existing MobileSync
            $model = $this->model;
            $objectRecord = DB::table($model)->where('MobileSync', $mobileSyncID)->first();
            
            if(empty($objectRecord)) {
                // There is no existing MobileSync Record, create new
                $singleResult = $this->storeSingleObject($singlePost);
            } else {
                // There is an existing MobileSync Record, update it
                $singleResult = $this->updateSingleObject($singlePost);
            }
            $result[] = $singleResult;
        }
        
        return $jsend = JSend\JSendResponse::success($result);
    }
    
    /* PUT /objects
     * 
     * Update multiple objects
     */
    public function edit() 
    {
        $post = Input::all();
        
        $bodyArrayKey = $this->model . '-Array';
		if(isset($post[$bodyArrayKey]) && !empty($post[$bodyArrayKey])) {
            $arrayInput = $post[$bodyArrayKey];
			parse_str($arrayInput, $output);
            $post[$this->model] = $output[$this->model] ;
        }
        
        $jsonKey = $this->model . '-Multiple';
        
        if(isset($post[$jsonKey]) && !empty($post[$jsonKey])) {
            $jsonInput = $post[$jsonKey];
            $arrayObject = json_decode($jsonInput);
            foreach($arrayObject as $key => $object) {
                $arrayObject[$key] = get_object_vars($object);
            }
            
            $post[$this->model] = $arrayObject;
        }
        
        $result = array();
        foreach ($post[$this->model] as $singlePost) {
            $singleResult = $this->updateSingleObject($singlePost);
            $result[] = $singleResult;
        }
        
        return $jsend = JSend\JSendResponse::success($result);
    }
}
