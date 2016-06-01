<?php
class Maintenance extends BaseModel
{

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'Maintenance';
	
	/**
	 * The primary key for the model.
	 *
	 * @var string
	 */
	protected $primaryKey = 'MaintenanceID';
	
	/**
	 * Indicates if the model should be timestamped.
	 *
	 * @var bool
	 */
	public $timestamps = false;
	
    /**
     * Use this method to build vaidator for creating maintenance
     */
    public static function validateStore($inputs, $user) 
    {
        $todayTimestamp = strtotime(date('Y-m-d\TH:i:s.B\Z', $_SERVER['REQUEST_TIME']));  
        
        $rules = array(
            'StartTime' => array('required', 'date_after_current:' . $todayTimestamp, 'date_no_duplicate:' . $inputs['EndTime']),
            'EndTime' => array('required', 'date_after_start:' . $inputs['StartTime'])
        );

        //Validate all inputs for maintenance
        $validator = Validator::make($inputs, $rules, array(
            'StartTime.required' => 'Please enter StartTime',
            'StartTime.date_after_current' => 'StartTime must be greater than CurrentTime',
            'EndTime.required' => 'Please enter EndTime',
            'EndTime.date_after_start' => 'EndTime must be greater than StartTime.',
            'StartTime.date_no_duplicate' => 'This maintenance time rage conflicts with other maintenance time rage, please choose different time'
        ));

        if ($validator->fails()) {
            return array('message' => $validator->messages()->all());
        }
        
        return array();
    }
    
    /**
     * Use this method to build vaidator for updating maintenance
     */
    public static function validateUpdate($inputs, $user, $maintenanceObject) 
    {
        $todayTimestamp = strtotime(date('Y-m-d\TH:i:s.B\Z', $_SERVER['REQUEST_TIME']));  
        
        $rules = array(
            'StartTime' => array('required', 'date_after_current:' . $todayTimestamp, 'date_no_duplicate:' . (isset($inputs['EndTime']) ? $inputs['EndTime'] : null) . ',' . $maintenanceObject->MaintenanceID),
            'EndTime' => array('required', 'date_after_start:' . (isset($inputs['StartTime']) ? $inputs['StartTime'] : null))
        );

        foreach ($rules as $key => $value) {
            if(!isset($inputs[$key])) {
                unset($rules[$key]);
            }
        }
        
        //Validate all inputs for maintenance
        $validator = Validator::make($inputs, $rules, array(
            'StartTime.required' => 'Please enter StartTime',
            'StartTime.date_after_current' => 'StartTime must be greater than CurrentTime',
            'EndTime.required' => 'Please enter EndTime',
            'EndTime.date_after_start' => 'EndTime must be greater than StartTime.',
            'StartTime.date_no_duplicate' => 'This maintenance time rage conflicts with other maintenance time rage, please choose different time'
        ));
        
        if ($validator->fails()) {
            return array('message' => $validator->messages()->all());
        }
        
        return array();
    }
    
    /**
     * Use this method to build vaidator for deleting maintenance
     */
    public static function validateDestroy($inputs, $user, $maintenanceObject) 
    {
        $todayTimestamp = strtotime(date('Y-m-d\TH:i:s.B\Z', $_SERVER['REQUEST_TIME']));  
        
        $rules = array(
            'MaintenanceID' => array('required', 'date_notin_current:' . $todayTimestamp . ',' . $maintenanceObject->StartTime . ',' . $maintenanceObject->EndTime),
        );
        
        //Validate all inputs for maintenance
        $validator = Validator::make($inputs, $rules, array(
            'MaintenanceID.required' => 'Please enter MaintenanceID',
            'MaintenanceID.date_notin_current' => 'Cannot delete maintenance record that is in progress!',
        ));
        
        if ($validator->fails()) {
            return array('message' => $validator->messages()->all());
        }
        
        return array();
    }
    
    public static function processStore($post, $user) 
    {
        $maintenance = new Maintenance();
         
        $maintenance->UserID = $user->UserID;
        $maintenance->StartTime = strtotime($post['StartTime']);
        $maintenance->EndTime = strtotime($post['EndTime']);
        $todayTimestamp = strtotime(date('Y-m-d\TH:i:s.B\Z', $_SERVER['REQUEST_TIME']));  
        
        $maintenance->CreatedTime = $todayTimestamp;
        
        if (isset($post['Reason']) && !empty($post['Reason'])) {
            $maintenance->Reason = $post['Reason'];
        } 

        //Save the new Maintenance
        $maintenance->save();

        $currentMaintenance = DB::table('Maintenance')
            ->where('StartTime', strtotime($post['StartTime']))
            ->first();
        
        $currentMaintenance->Username = $user->Username;

        $currentMaintenance->StartTime = date('Y-m-d\TH:i:s.B\Z', $currentMaintenance->StartTime);
        $currentMaintenance->EndTime = date('Y-m-d\TH:i:s.B\Z', $currentMaintenance->EndTime);

        if(isset($currentMaintenance->CreatedTime) && ($currentMaintenance->CreatedTime != 0)) {
            $currentMaintenance->CreatedTime = date('Y-m-d\TH:i:s.B\Z', $currentMaintenance->CreatedTime);
        }

        if(isset($currentMaintenance->ModifiedTime) && ($currentMaintenance->ModifiedTime != 0)) {
            $currentMaintenance->ModifiedTime = date('Y-m-d\TH:i:s.B\Z', $currentMaintenance->ModifiedTime);
        }
                
        return $currentMaintenance;
    }
    
    public static function processUpdate($post, $user, $maintenance) 
    {
        if (isset($post['StartTime']) && !empty($post['StartTime'])) {
            $maintenance->StartTime = strtotime($post['StartTime']);
        } 
        
        if (isset($post['EndTime']) && !empty($post['EndTime'])) {
            $maintenance->EndTime = strtotime($post['EndTime']);
        } 
        $maintenance->UserID = $user->UserID;

        $todayTimestamp = strtotime(date('Y-m-d\TH:i:s.B\Z', $_SERVER['REQUEST_TIME']));  
        
        $maintenance->ModifiedTime = $todayTimestamp;
        
        if (isset($post['Reason']) && !empty($post['Reason'])) {
            $maintenance->Reason = $post['Reason'];
        } 

        //Save the new Maintenance
        $maintenance->save();

        $currentMaintenance = DB::table('Maintenance')
            ->where('MaintenanceID', $maintenance->MaintenanceID)
            ->first();
        
        $currentMaintenance->Username = $user->Username;

        $currentMaintenance->StartTime = date('Y-m-d\TH:i:s.B\Z', $currentMaintenance->StartTime);
        $currentMaintenance->EndTime = date('Y-m-d\TH:i:s.B\Z', $currentMaintenance->EndTime);

        if(isset($currentMaintenance->CreatedTime) && ($currentMaintenance->CreatedTime != 0)) {
            $currentMaintenance->CreatedTime = date('Y-m-d\TH:i:s.B\Z', $currentMaintenance->CreatedTime);
        }

        if(isset($currentMaintenance->ModifiedTime) && ($currentMaintenance->ModifiedTime != 0)) {
            $currentMaintenance->ModifiedTime = date('Y-m-d\TH:i:s.B\Z', $currentMaintenance->ModifiedTime);
        }
        
        return $currentMaintenance;
    }
    
    public static function processDestroy($post, $user) 
    {
        DB::table('Maintenance')
			->where('MaintenanceID', $post['MaintenanceID'])
            ->delete();
    }

}