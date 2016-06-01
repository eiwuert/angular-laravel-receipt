<?php

class BaseV2Controller extends Controller 
{
    public function __construct() {
		if (isset($_SERVER['HTTP_AUTH_TOKEN'])) {
			$userTimezone = DB::table('UserApiToken AS uat')
					->join('Profile AS pro', 'uat.UserID', '=', 'pro.UserID')
					->select('Timezone')
					->where('ApiToken', $_SERVER['HTTP_AUTH_TOKEN'])
					->where('Action', 'login')
					->pluck('Timezone');
			
			if ($userTimezone) {
				date_default_timezone_set($userTimezone);
			}
		}
	}
	
}