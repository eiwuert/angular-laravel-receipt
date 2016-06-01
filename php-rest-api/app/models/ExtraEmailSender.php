<?php
class ExtraEmailSender extends Eloquent
{

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'ExtraEmailSender';
	
	/**
	 * The primary key for the model.
	 *
	 * @var string
	 */
	protected $primaryKey = 'ExtraEmailSenderID';
	
	/**
	 * Indicates if the model should be timestamped.
	 *
	 * @var bool
	 */
	public $timestamps = false;
	
	public static function updateExtraEmailList($extraEmails, $userID)
	{
		$oldExtraEmails = self::getExtraEmailList($userID);
		$diffDelete = array_diff($oldExtraEmails, $extraEmails);
		$diffAdd = array_diff($extraEmails, $oldExtraEmails);
		
		if (count($diffDelete)) {
			DB::table('ExtraEmailSender')
					->where('UserID', $userID)
					->whereIn('Email', $diffDelete)
					->delete();
		}
		
		if (count($diffAdd)) {
			$insert = array();
			foreach ($diffAdd as $extraEmail) {
				$insert[] = array('UserID' => $userID, 'Email' => $extraEmail);
			}
			
			DB::table('ExtraEmailSender')
					->insert($insert);
		}
	}
	
	public static function getExtraEmailList($userID, $blankLines = false)
	{
		$extraEmails = DB::table('ExtraEmailSender')
				->select('Email')->where('UserID', $userID)->lists('Email');
		
		if ($blankLines) {
			$return = array();
			for ($i = 0; $i < 4; $i++) {
				if (isset($extraEmails[$i])) {
					$return[] = array(
						'Number' => $i + 1,
						'Email' => $extraEmails[$i]
					);
				} else {
					$return[] = array(
						'Number' => $i + 1,
						'Email' => null
					);
				}
			}
			
			return $return;
		}
		
		return $extraEmails;
	}
	
}