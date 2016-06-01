<?php
class MerchantAlgos extends BaseModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'MerchantAlgos';

    protected static $_table = 'MerchantAlgos';
    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'MerchantID';

    protected static $_primaryKey = 'MerchantID';
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

}