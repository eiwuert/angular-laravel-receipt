<?php
class MaintenanceController extends Controller 
{
    protected $model = 'Maintenance';
    
    protected $user;
    
    protected $primaryKey = 'MaintenanceID';
    
    protected function getUser() 
    {
        return $this->user;
    }

    protected function setUser($user) 
    {
        $this->user = $user;
    }
    
    // Set default timezone of server to UTC time
    public function __construct() {
        date_default_timezone_set('UTC');
	}
    
    public function getStatus() {
        // API to get maintenance status, if there is a maintenance record, return it
        $model = $this->model;
        
        $todayTimestamp = strtotime(date('Y-m-d\TH:i:s.B\Z', $_SERVER['REQUEST_TIME']));  
        
        //Maintenance Object
        $maintenance = DB::table($model)
            ->where('StartTime', '<=' , $todayTimestamp)
            ->where('EndTime', '>=' , $todayTimestamp)
            ->first();

        if (empty($maintenance)) {
            //Get the nearest maintenance record if exist
            $upcoming = DB::table($model)
                ->where('StartTime', '>=' , $todayTimestamp)
                ->orderBy('StartTime', 'ASC')
                ->first();

            return Response::json(array(
                'message'  => 'Maintenance record not found',
                'status'   => 'error',
                'upcoming' => (array) $upcoming
            ), 404);
        } else {
            $user = User::find($maintenance->UserID);
            $maintenance->Username = $user->Username;
            
            $maintenance->TimeLeft = $maintenance->EndTime - $todayTimestamp;
            $maintenance->StartTime = date('Y-m-d\TH:i:s.B\Z', $maintenance->StartTime);
            $maintenance->EndTime = date('Y-m-d\TH:i:s.B\Z', $maintenance->EndTime);

            if(isset($maintenance->CreatedTime) && ($maintenance->CreatedTime != 0)) {
                $maintenance->CreatedTime = date('Y-m-d\TH:i:s.B\Z', $maintenance->CreatedTime);
            }

            if(isset($maintenance->ModifiedTime) && ($maintenance->ModifiedTime != 0)) {
                $maintenance->ModifiedTime = date('Y-m-d\TH:i:s.B\Z', $maintenance->ModifiedTime);
            }
            $jsend = JSend\JSendResponse::success((array) $maintenance);
            return $jsend->respond();
        }        
    }
    public function getIndex() {
        $model = $this->model;
        
        //Check authentication
        $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN']);

        if (empty($_SERVER['HTTP_AUTH_TOKEN']) || !$userToken) {
            $jsend = JSend\JSendResponse::error('The authentication is failed.', 401);
            return $jsend->respond();
        }
        
        $this->setUser(User::where('UserID', $userToken->UserID)->first());
        $user = $this->getUser();
        
        //Check authorization
        $userRole = $user->Role;
        if(empty($userRole) || $userRole != "admin") {
            $jsend = JSend\JSendResponse::error("Donot have permission to find resource", 403);
            return $jsend->respond();
        }
        
        $maintenanceList = DB::table($model)
            ->orderby('StartTime', 'ASC')
            ->get();

        if (count($maintenanceList)) {
            foreach($maintenanceList as $maintenance) {
                $user = User::find($maintenance->UserID);
                $maintenance->Username = $user->Username;
                
                $maintenance->StartTime = date('Y-m-d\TH:i:s.B\Z', $maintenance->StartTime);
                $maintenance->EndTime = date('Y-m-d\TH:i:s.B\Z', $maintenance->EndTime);
                
                if(isset($maintenance->CreatedTime) && ($maintenance->CreatedTime != 0)) {
                    $maintenance->CreatedTime = date('Y-m-d\TH:i:s.B\Z', $maintenance->CreatedTime);
                }
                
                if(isset($maintenance->ModifiedTime) && ($maintenance->ModifiedTime != 0)) {
                    $maintenance->ModifiedTime = date('Y-m-d\TH:i:s.B\Z', $maintenance->ModifiedTime);
                }
            }
        }
        $jsend = JSend\JSendResponse::success((array) $maintenanceList);
        return $jsend->respond();
    }
	public function postIndex()
	{
        $model = $this->model;
        
        //Check authentication
        $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN']);

        if (empty($_SERVER['HTTP_AUTH_TOKEN']) || !$userToken) {
            $jsend = JSend\JSendResponse::error('The authentication is failed.', 401);
            return $jsend->respond();
        }
        
        $this->setUser(User::where('UserID', $userToken->UserID)->first());
        $user = $this->getUser();
        
        //Check authorization
        $userRole = $user->Role;
        if(empty($userRole) || $userRole != "admin") {
            $jsend = JSend\JSendResponse::error("Donot have permission to find resource", 403);
            return $jsend->respond();
        }
        
        $post = Input::all(); 

        //Validate store
        $messages = $model::validateStore($post, $this->getUser());

        if (count($messages)) {
            $jsend = JSend\JSendResponse::fail($messages);
            return $jsend->respond();
        }

        //Process store
        $newMaintenanceObject = $model::processStore($post, $this->getUser());

        $jsend = JSend\JSendResponse::success((array) $newMaintenanceObject);
        return $jsend->respond();
	}
    
    public function putIndex() {
        
        $model = $this->model;
        
        //Check authentication
        $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN']);

        if (empty($_SERVER['HTTP_AUTH_TOKEN']) || !$userToken) {
            $jsend = JSend\JSendResponse::error('The authentication is failed.', 401);
            return $jsend->respond();
        }
        
        $this->setUser(User::where('UserID', $userToken->UserID)->first());
        $user = $this->getUser();
        
        //Check authorization
        $userRole = $user->Role;
        if(empty($userRole) || $userRole != "admin") {
            $jsend = JSend\JSendResponse::error("Donot have permission to find resource", 403);
            return $jsend->respond();
        }
        
        $put = Input::all(); 

        if(!isset($put['MaintenanceID']) || empty($put['MaintenanceID'])) {
            $jsend = JSend\JSendResponse::error("MaintenanceID is required", 404);
            return $jsend->respond();
        }
        
        $maintenanceID = $put['MaintenanceID'];
        $maintenanceObject = $model::find($maintenanceID);
        
        if(empty($maintenanceObject)) {
            $jsend = JSend\JSendResponse::error("Maintenance record not found", 404);
            return $jsend->respond();
        }
        //Validate store
        $messages = $model::validateUpdate($put, $this->getUser(), $maintenanceObject);

        if (count($messages)) {
            $jsend = JSend\JSendResponse::fail($messages);
            return $jsend->respond();
        }

        //Process store
        $newMaintenanceObject = $model::processUpdate($put, $this->getUser(), $maintenanceObject);

        $jsend = JSend\JSendResponse::success((array) $newMaintenanceObject);
        return $jsend->respond();
    }
    
    public function deleteIndex()
	{
        $model = $this->model;
        
        //Check authentication
        $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN']);

        if (empty($_SERVER['HTTP_AUTH_TOKEN']) || !$userToken) {
            $jsend = JSend\JSendResponse::error('The authentication is failed.', 401);
            return $jsend->respond();
        }
        
        $this->setUser(User::where('UserID', $userToken->UserID)->first());
        $user = $this->getUser();
        
        //Check authorization
        $userRole = $user->Role;
        if(empty($userRole) || $userRole != "admin") {
            $jsend = JSend\JSendResponse::error("Donot have permission to find resource", 403);
            return $jsend->respond();
        }
        
        $put = Input::all(); 

        if(!isset($put['MaintenanceID']) || empty($put['MaintenanceID'])) {
            $jsend = JSend\JSendResponse::error("MaintenanceID is required", 404);
            return $jsend->respond();
        }
        
        $maintenanceID = $put['MaintenanceID'];
        $maintenanceObject = $model::find($maintenanceID);
        
        if(empty($maintenanceObject)) {
            $jsend = JSend\JSendResponse::error("Maintenance record not found", 404);
            return $jsend->respond();
        }
        
        //Validate store
        $messages = $model::validateDestroy($put, $this->getUser(), $maintenanceObject);

        if (count($messages)) {
            $jsend = JSend\JSendResponse::fail($messages);
            return $jsend->respond();
        }
        
        $model::processDestroy($put, $this->getUser());
        
        $jsend = JSend\JSendResponse::success();
        return $jsend->respond();
    }
}