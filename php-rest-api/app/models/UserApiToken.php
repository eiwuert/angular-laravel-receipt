<?php
class UserApiToken extends Eloquent
{

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'UserApiToken';
	
	/**
	 * The primary key for the model.
	 *
	 * @var string
	 */
	protected $primaryKey = 'UserApiTokenID';
	
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
	 * Generate a api token for the spcified authenticated user, and store in the database
	 * 
	 * @param User $user
	 * @param string $action
	 */
	public function generateApiToken($userId = 0, $action = 'login')
	{
		if (! $this->UserID) {
			$this->UserID = $userId;
		}
		
		//generate token
		$this->ApiToken = hash('sha256', uniqid());
		
		if ($action == 'login') {
			//Login api token expiration = current time + session lifetime config
			$this->Expired = $_SERVER['REQUEST_TIME'] + Config::get('session.lifetime') * 60;
		} else {
			//Api Token lifetime for other actions, such as account activation, password reset ... = 1 day (86400 seconds)
			$this->Expired = $_SERVER['REQUEST_TIME'] + 86400;
		}
		//Action that needs this api token. Default value is login
		$this->Action = $action;
		
		//Save the api token record
		$this->save();
		return $this;
	}
	
	/**
	 * Check whether an authentication is passed, based on the api token value
	 * 
	 * @param string $token
	 */
	public static function checkAuth($token)
	{
		return UserApiToken::whereRaw('ApiToken=:token AND Action=:action', array(':token' => $token, ':action' => 'login'))->first();
	}

}