<?php
class S3Region extends Eloquent
{

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'S3Region';
	
	/**
	 * The primary key for the model.
	 *
	 * @var string
	 */
	protected $primaryKey = 'S3RegionCode';
	
	/**
	 * Indicates if the model should be timestamped.
	 *
	 * @var bool
	 */
	public $timestamps = false;

    /**
     * Return s3 region code by name
     *
     * @param   $name   string    AWS region name
     * @return  int               AWS region code
     */
    public static function findByName ($name) {
        $id = DB::table('S3Region')->where('Name', $name)->pluck('S3RegionCode');

        return ($id) ? $id : 0;
    }

    /**
     * Return all buckets of user by region
     *
     * @param    $userID       int       Use userID or region code
     * @param    $regionCode   string    Use userID or region code
     * @return   array
     */
    public static function getUserBucketList ($userID = 0, $regionCode = '') {
        if (empty($regionCode)) {
            $profile = Profile::find($userID);
            $userID && $country = Country::find($profile->CountryName);
            $regionCode = ($country)? $country->S3RegionCode : '';
        } else {
            $regionCode = $regionCode;
        }

        $list = array(
            'manual'     => Config::get('aws::config.bucketManual'),
            'receipt'    => str_replace('{region}', $regionCode, Config::get('aws::config.bucket')),
            'attachment' => str_replace('{region}', $regionCode, Config::get('aws::config.bucketFile'))
        );

        return $list;
    }
}
