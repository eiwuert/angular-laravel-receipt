<?php
class ReportMemo extends Eloquent
{
	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'ReportMemo';
	
	/**
	 * The primary key for the model.
	 *
	 * @var string
	 */
	protected $primaryKey = 'ReportMemoID';
	
	/**
	 * Indicates if the model should be timestamped.
	 *
	 * @var bool
	 */
	public $timestamps = false;
	
	/**
	 * Get all memos of a report
	 */
	public static function getList($reportID)
	{
		return DB::table('ReportMemo AS rm')
				->select('ItemID', 'Email', 'rm.UserID', 'Message', 'rm.CreatedTime')
				->join('User AS u', 'u.UserID', '=', 'rm.UserID')
				->where('ReportID', $reportID)
				->orderBy('ItemID')
				->orderBy('rm.CreatedTime')
				->get();
	}
	
	public static function deleteByReports($reportIDs)
	{
		if (! is_array($reportIDs)) {
			$reportIDs = array($reportIDs);
		}
		
		DB::table('ReportMemo')
				->whereIn('ReportID', $reportIDs)
				->delete();
	}
}