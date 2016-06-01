<?php

class Settings extends BaseModel
{

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'Settings';
    
    protected static $_table = 'Settings';

	/**
	 * The primary key for the model.
	 *
	 * @var string
	 */
	protected $primaryKey = 'UserID';
    
    protected static $_primaryKey = 'UserID';
	
	/**
	 * Indicates if the model should be timestamped.
	 *
	 * @var bool
	 */
	public $timestamps = false;

	/**
	 * Define the One-to-One relationship between profiles and users (inversion)
	 * NOTE: Restrict the use of this features, because it will cost two queries
	 * Instead, using the query builder to make a proper SQL statement
	 */
	public function User()
	{
		return $this->belongsTo('User');
	}
    
    /**
     * Use this method to build vaidator for both creating and updating settings
     */
    public static function validateModel($inputs, $user, $settings = null) 
    {
        $rules = array(
            'Timezone' => array('required', 'max:128'),
            'CurrencyCode' => array('required', 'max:3', 'min:3'),
            'AutoArchive' => array('integer'),
        );

        if($settings != null) {
            foreach ($rules as $key => $value) {
                if(!isset($inputs[$key])) {
                    unset($rules[$key]);
                }
            }
        }

        //Validate all inputs for receipt (not receipt items)
        $validator = Validator::make($inputs, $rules, array());

        if ($validator->fails()) {
            return array('message' => $validator->messages()->all());
        }

        return array();
    }
    
    public static function processUpdate($put, $user, $settings) 
    {
		if (isset($put['Timezone']) && $put['Timezone'] != $settings->Timezone) {
			$settings->Timezone = $put['Timezone'];
		}
        
        if (isset($put['AutoArchive']) && $put['AutoArchive'] != $settings->AutoArchive) {
			$settings->AutoArchive = $put['AutoArchive'];
		}
		
		$settings->save();
    }
}