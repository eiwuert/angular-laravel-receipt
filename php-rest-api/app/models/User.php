<?php

use Illuminate\Auth\UserInterface;
use Illuminate\Auth\Reminders\RemindableInterface;

class User extends Eloquent implements UserInterface, RemindableInterface 
{
    const DEVICE_ANDROID = 'ANDROID';
    
	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'User';

	/**
	 * The attributes excluded from the model's JSON form.
	 *
	 * @var array
	 */
	protected $hidden = array('Password');
	
	/**
	 * The primary key for the model.
	 *
	 * @var string
	 */
	protected $primaryKey = 'UserID';
	
	/**
	 * Indicates if the model should be timestamped.
	 *
	 * @var bool
	 */
	public $timestamps = false;
	
	/**
	 * Define the One-to-One relationship between users and user_tokens
	 * NOTE: Restrict the use of this feature, because it will cost us two queries
	 * Instead, using the query builder to make a proper SQL statement
	 */
	public function token()
	{
		return $this->hasOne('UserToken', 'UserID');
	}
    
    /**
	 * Define the One-to-One relationship between users and user_api_tokens
	 * NOTE: Restrict the use of this feature, because it will cost us two queries
	 * Instead, using the query builder to make a proper SQL statement
	 */
	public function ApiToken()
	{
		return $this->hasOne('UserApiToken', 'UserID');
	}
	
	/**
	 * Define the One-to-One relationship between users and profiles
	 * NOTE: Restrict the use of this feature, because it will cost us two queries
	 * Instead, using the query builder to make a proper SQL statement
	 */
	public function Profile()
	{
		return $this->hasOne('Profile', 'UserID');
	}
	
	/**
	 * Define the One-to-One relationship between users and settings
	 * NOTE: Restrict the use of this feature, because it will cost us two queries
	 * Instead, using the query builder to make a proper SQL statement
	 */
	public function Settings()
	{
		return $this->hasOne('Settings', 'UserID');
	}
	/**
	 * Get the unique identifier for the user.
	 *
	 * @return mixed
	 */
	public function getAuthIdentifier()
	{
		return $this->getKey();
	}

	/**
	 * Get the password for the user.
	 *
	 * @return string
	 */
	public function getAuthPassword()
	{
		return $this->Password;
	}

	/**
	 * Get the e-mail address where password reminders are sent.
	 *
	 * @return string
	 */
	public function getReminderEmail()
	{
		return $this->Email;
	}

    public static function getAndroidDeviceTokens ($userID)
    {
        $deviceTokens = DB::table('DeviceApiToken')
			->where('UserID', $userID)
            ->where('DeviceType', static::DEVICE_ANDROID)
			->get();
        
        foreach ($deviceTokens as $index => $value) {
            $deviceTokens[$index] = $value->DeviceToken;
        }
        
        return $deviceTokens;
    }
	
	public static function updateById($userId, $fields)
	{
		return DB::table('User')
				->where('UserID', $userId)
				->update($fields);
	}
	
	public static function getFullNamesByEmails($emails)
	{
		return DB::table('User AS u')
				->join('Profile AS p', 'p.UserID', '=', 'u.UserID')
				->select(DB::raw('CONCAT(FirstName, LastName, " ") AS Name, Email'))
				->whereIn('Email', $emails)
				->get();
	}
	
	/**
	 * Return UserID, allow to use input as an extra email
	 */
	public static function getUserIDFromEmail($email)
	{
		$user = User::where('Email', $email)->first();
		if (! $user) {
			$user = ExtraEmailSender::where('Email', $email)->first();
		}
		
		return isset($user->UserID) ? $user->UserID : 0;
	}
    
    /**
     * Return First Name of user by email
     */
    public static function getFirstNameByEmail($email)
	{
		return DB::table('User AS u')
				->join('Profile AS p', 'p.UserID', '=', 'u.UserID')
				->where('Email', $email)
				->pluck('FirstName');
	}
	public function getRememberToken()
    {
        return $this->remember_token;
    }

    public function setRememberToken($value)
    {
        $this->remember_token = $value;
    }

    public function getRememberTokenName()
    {
        return 'remember_token';
    }
}