<?php
class ReportTrip extends BaseModel
{

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'ReportTrip';
	
    protected static $_table = 'ReportTrip';
	/**
	 * The primary key for the model.
	 *
	 * @var string
	 */
	protected $primaryKey = 'ReportTripID';
    
    protected static $_primaryKey = 'ReportTripID';
	
	/**
	 * Indicates if the model should be timestamped.
	 *
	 * @var bool
	 */
	public $timestamps = false;
	
}