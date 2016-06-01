<?php
class ReportApprover extends Eloquent
{
	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'ReportApprover';
	
	/**
	 * The primary key for the model.
	 *
	 * @var string
	 */
	protected $primaryKey = 'ReportApproverID';
	
	/**
	 * Indicates if the model should be timestamped.
	 *
	 * @var bool
	 */
	public $timestamps = false;
	
	public static function deleteList($reportIDs)
	{
		if (! is_array($reportIDs)) {
			$reportIDs = array($reportIDs);
		}
		
		DB::table('ReportApprover')
				->whereIn('ReportID', $reportIDs)
				->delete();
	}

    /**
     * Check to see report is belong to approver
     *
     * @param   $userID    int  Approver user id
     * @param   $reportID  int  ReportID
     * @return  boolean
     */
    public static function isApprover ($userID, $reportID)
    {
        $count = DB::table('ReportApprover')
            ->where('ReportID', $reportID)
            ->where('Approver', $userID)
            ->count();

        return ($count > 0);
    }
}