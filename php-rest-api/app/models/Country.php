<?php
class Country extends Eloquent
{

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'Country';
	
	/**
	 * The primary key for the model.
	 *
	 * @var string
	 */
	protected $primaryKey = 'CountryCode';
	
	/**
	 * Indicates if the model should be timestamped.
	 *
	 * @var bool
	 */
	public $timestamps = false;

    public static function getList ()
    {
        return DB::table('Country')
            ->select('CountryCode AS code', 'CountryName AS name')
            ->orderBy('CountryName', 'asc')
            ->get();
    }
	
}