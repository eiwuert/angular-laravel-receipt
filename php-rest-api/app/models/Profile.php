<?php

class Profile extends Eloquent
{

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'Profile';

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
	 * Define the One-to-One relationship between profiles and users (inversion)
	 * NOTE: Restrict the use of this features, because it will cost two queries
	 * Instead, using the query builder to make a proper SQL statement
	 */
	public function User()
	{
		return $this->belongsTo('User');
	}
}