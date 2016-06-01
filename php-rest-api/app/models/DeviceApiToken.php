<?php
class DeviceApiToken extends Eloquent
{

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'DeviceApiToken';
	
	/**
	 * The primary key for the model.
	 *
	 * @var string
	 */
	protected $primaryKey = 'DeviceApiTokenID';
	
	/**
	 * Indicates if the model should be timestamped.
	 *
	 * @var bool
	 */
	public $timestamps = false;
	
	/**
	 * Define the One-to-One relationship between user_api_tokens and users (inversion)
	 * NOTE: Restrict the use of this features, because it will cost two queries
	 * Instead, using the query builder to make a proper SQL statement
	 */
	public function User()
	{
		return $this->belongsTo('User');
	}
    
    /**
     * Use this method to build vaidator for creating deviceapitoken
     */
    public static function validateStore($inputs, $user) 
    {
        $rules = array(
            'DeviceToken' => array('required', 'max:255', 'deviceapitoken_exists:'. $user->UserID),
            'DeviceId' => array('required', 'max:255'),
            'DeviceType' => array('required', 'max:255')
        );

        //Validate all inputs for deviceapitoken
        $validator = Validator::make($inputs, $rules, array(
            'DeviceToken.required' => 'Please enter DeviceToken',
            'DeviceToken.deviceapitoken_exists' => 'DeviceToken for this user already existed',
            'DeviceId.required' => 'Please enter DeviceId',
            'DeviceType.required' => 'Please enter DeviceType'
        ));

        if ($validator->fails()) {
            return array('message' => $validator->messages()->all());
        }
        
        return array();
    }
    
    /**
     * Use this method to build vaidator for deleting deviceapitoken
     */
    public static function validateDestroy($inputs, $user) 
    {
        $rules = array(
            'DeviceToken' => array('required', 'max:255', 
                'deviceapitoken_exists_delete', 'deviceapitoken_belongs_to_user:' . $user->UserID),
        );

        //Validate all inputs for deviceapitoken
        $validator = Validator::make($inputs, $rules, array(
            'DeviceToken.required' => 'Please enter DeviceToken',
            'DeviceToken.deviceapitoken_exists_delete' => 'There is no DeviceToken record',
            'DeviceToken.deviceapitoken_belongs_to_user' => 'This DeviceToken doesnot belong to user who sent the request'
        ));

        if ($validator->fails()) {
            return array('message' => $validator->messages()->all());
        }
        
        return array();
    }
    
    public static function processStore($post, $user) 
    {
        // Delete all existing DeviceApiToken
        DB::table('DeviceApiToken')
            ->where('DeviceId', $post['DeviceId'])
            ->where('DeviceType', $post['DeviceType'])
            ->delete();
        
        $deviceapitoken = new DeviceApiToken();
         
        $deviceapitoken->UserID = $user->UserID;
         
        if (isset($post['DeviceToken'])) {
            $deviceapitoken->DeviceToken = trim($post['DeviceToken']);
        } 
        
        if (isset($post['DeviceId'])) {
            $deviceapitoken->DeviceId = trim($post['DeviceId']);
        } 
        
        if (isset($post['DeviceType'])) {
            $deviceapitoken->DeviceType = trim($post['DeviceType']);
        } else {
            $deviceapitoken->DeviceType = "Android";
        }

        //Save the new DeviceApiToken
        $deviceapitoken->save();

        $currentApiToken = DB::table('UserApiToken')
            ->where('ApiToken', $_SERVER['HTTP_AUTH_TOKEN'])
            ->first();
        
        $currentApiTokenObject = UserApiToken::find($currentApiToken->UserApiTokenID);
        $currentApiTokenObject->DeviceApiTokenID = $deviceapitoken->DeviceApiTokenID;
        $currentApiTokenObject->save();
        
        return $deviceapitoken;
    }
    
    public static function processDestroy($post, $user) 
    {
        DB::table('DeviceApiToken')
			->where('DeviceToken', $post['DeviceToken'])
            ->where('UserID', $user->UserID)
            ->delete();
    }

}