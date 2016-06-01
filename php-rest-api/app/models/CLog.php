<?php
class CLog extends Eloquent
{
	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'Log';
	
	/**
	 * The primary key for the model.
	 *
	 * @var string
	 */
	protected $primaryKey = 'LogID';
	
	/**
	 * Indicates if the model should be timestamped.
	 *
	 * @var bool
	 */
	public $timestamps = false;
	
	public static function message($message)
	{
		$log = new CLog();
		$log->Timestamp = $_SERVER['REQUEST_TIME'];
		$log->Message = $message;
		$log->save();
	}
}