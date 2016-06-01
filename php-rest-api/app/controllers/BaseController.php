<?php

class BaseController extends Controller 
{
	
	const RC_PDF_HEADER_LOGO = '';
	const RC_PDF_HEADER_TITLE = '';
	const RC_PDF_HEADER_STRING = '';
	
	public function __construct() {
		if (isset($_SERVER['HTTP_AUTH_TOKEN'])) {
			$userTimezone = DB::table('UserToken AS ut')
					->join('Profile AS pro', 'ut.UserID', '=', 'pro.UserID')
					->select('Timezone')
					->where('Token', $_SERVER['HTTP_AUTH_TOKEN'])
					->where('Action', 'login')
					->pluck('Timezone');
			
			if ($userTimezone) {
				date_default_timezone_set($userTimezone);
			}
		}
	}
	
}