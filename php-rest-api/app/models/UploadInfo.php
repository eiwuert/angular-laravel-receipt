<?php
class UploadInfo extends Eloquent
{

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'UploadInfo';
	
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
     * Function to add new record
     *
     * @param   $data   array   Contains data of record
     */
    public static function add ($data)
    {
        if (!isset($data['UserID'], $data['SocketID'], $data['PushServerIP']))
            return false;

        $uploadInfo = UploadInfo::find($data['UserID']);

        if (!$uploadInfo)
            $uploadInfo = new UploadInfo();

        $uploadInfo->UserID       = $data['UserID'];
        $uploadInfo->SocketID     = $data['SocketID'];
        $uploadInfo->PushServerIP = $data['PushServerIP'];
        $uploadInfo->Created      = time();

        $uploadInfo->save();
    }

}