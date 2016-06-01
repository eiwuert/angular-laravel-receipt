<?php
class ReportRejected extends Eloquent
{
	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'ReportRejected';
	
	/**
	 * The primary key for the model.
	 *
	 * @var string
	 */
	protected $primaryKey = 'ReportRejectedID';
	
	/**
	 * Indicates if the model should be timestamped.
	 *
	 * @var bool
	 */
	public $timestamps = false;

    /**
     * Make a copy of report from last rejected status. Display as read-only for approver.
     *
     * @param $reportIDs  array   List of report ids
     * @param $userID     int     User ID
     */
    public static function createRejectedRecord($reportIDs, $userID)
    {
        if (!is_array($reportIDs)) return false;

        foreach ($reportIDs as $rid) {
            DB::table('ReportRejected')
                ->where('ReportID', $rid)
                ->delete();

            //Create report record
            $report = Report::getDetail($rid, $userID, array('skipRejectedRecord' => true));

            $report->Amount = 0;
            if (count($report->Trips)) {
                foreach ($report->Trips as $trip) {
                    Trip::staticGetAmount($trip);
                    $report->Amount += $trip->Amount;
                }
            }

            //Create Print usage records
            $printAll = Report::getDetail($rid, $userID, array('skipRejectedRecord' => true, 'pdfItemType' => 'all'));
            $printClm = Report::getDetail($rid, $userID, array('skipRejectedRecord' => true, 'pdfItemType' => 'claimed'));
            $printApv = Report::getDetail($rid, $userID, array('skipRejectedRecord' => true, 'pdfItemType' => 'approved'));

            //Save to DB
            DB::table('ReportRejected')
                ->insert(array(
                    array('ReportID' => $rid, 'Usage' => 'report', 'Json' => json_encode($report)),
                    array('ReportID' => $rid, 'Usage' => 'print_all', 'Json' => json_encode($printAll)),
                    array('ReportID' => $rid, 'Usage' => 'print_claimed', 'Json' => json_encode($printClm)),
                    array('ReportID' => $rid, 'Usage' => 'print_approved', 'Json' => json_encode($printApv)),
                ));
        }
    }

    /**
     * Return last rejected record of report
     *
     * @param    $reportID  int      ReportID
     * @param    $usage     string   Usage of record: report/ print_all/
     *                               print_claimed/ print_approved
     * @return   stdClass
     */
    public static function getRejectedRecord ($reportID, $usage = 'report')
    {
        switch($usage) {
            case 'all':
                $usage = 'print_all';
                break;
            case 'claimed':
                $usage = 'print_claimed';
                break;
            case 'approved':
                $usage = 'print_approved';
                break;
        }
        
        $record = DB::table('ReportRejected')
            ->where('ReportID', $reportID)
            ->where('Usage', $usage)
            ->select('Json')
            ->first();

        if ($record) return json_decode($record->Json);

        return null;
    }
}
