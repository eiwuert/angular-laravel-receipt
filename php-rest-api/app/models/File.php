<?php
use Aws\Common\Aws;

class File extends Eloquent
{
	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'File';
	
	/**
	 * The primary key for the model.
	 *
	 * @var string
	 */
	protected $primaryKey = 'FileID';
	
    /**
     * Attributes will be mass-assignable. Support function firstOrNew
     * @var array
     */
    protected $fillable = array('EntityID', 'EntityName');
    
	/**
	 * Indicates if the model should be timestamped.
	 *
	 * @var bool
	 */
	public $timestamps = false;
	
	public static function getList($fileIDs)
	{
		$attachments = DB::table('File AS f')
				->select('FileID', 'FileBucket', 'FilePath', 'EntityID', 'FileName');
		if (! is_array($fileIDs)) {
			$attachments->where('FileID', $fileIDs);
		} else {
			$attachments->whereIn('FileID', $fileIDs);
		}
		
		return $attachments->get();
	}
	
	public static function getListByEntities($entityIds, $entityName = 'receipt', $to_delete = false)
	{
        $s3 = App::make('aws')->get('s3');

		$attachmentQuery = DB::table('File AS f')->select('FileID', 'FilePath', 'EntityID', 'FileName');
        /*
		if ($to_delete) {
			$attachmentQuery->select('FileID', 'FilePath', 'EntityID', 'FileName');
		} else {
			$attachmentQuery->select('FileID', DB::raw('CONCAT("' . Config::get('app.fileBaseUrl') . '", FilePath) AS FilePath'), 'EntityID', 'FileName');
		}
        */

		if ($entityName != 'trip') {
			$attachmentQuery->where('EntityName', $entityName);
		} else {
			$attachmentQuery->join('TripItem', 'TripItemID', '=', 'EntityID')
					->whereIn('TripID', $entityIds)
					->where('EntityName', 'receipt_item');
		}
		
		if (! is_array($entityIds)) {
			$attachmentQuery->where('EntityID', $entityIds);
		} else {
			$attachmentQuery->whereIn('EntityID', $entityIds);
		}

        $fileList = $attachmentQuery->get();

        foreach ($fileList as $file) {
            $request        = $s3->get(Config::get('aws::config.bucketFile') .'/' . $file->FilePath);
            $file->FilePath = $s3->createPresignedUrl($request, '+ 1 hour');
        }

		return $fileList;
	}
	
	public static function updateList($fileIDs, $fields)
	{
		if (! is_array($fileIDs)) {
			$fileIDs = array($fileIDs);
		}
		
		if (count($fileIDs) && count($fields)) {
			DB::table('File')
					->whereIn('FileID', $fileIDs)
					->update($fields);
		}
	}
	
	public static function deleteList($files)
	{
		// Get path of all attachments of the receipts to delete them physically
		// We also add file ids to an array, contains both receipt attachments
		// and item attachments, so we can delete all the database records later
		// in only one query
		$deletedFileIDs = array();
        $s3Objects      = array();
		if (count($files)) {
			foreach ($files as $file) {
                $s3Objects[]      = array('Key' => $file->FilePath);
				$deletedFileIDs[] = $file->FileID;
			}
		}

        //Remove file from S3 first
        if (count($s3Objects) > 0) {
            $s3 = App::make('aws')->get('s3');
            $s3->deleteObjects(array(
                'Bucket'  => $file->FileBucket,
                'Objects' => $s3Objects
            ));
        }

		if (count($deletedFileIDs)) {
			DB::table('File')
					->whereIn('FileID', $deletedFileIDs)
					->delete();
		}
	}
	
	public static function deleteListByEntities($entityIDs, $entityNames)
	{
		if (! is_array($entityIDs)) {
			$entityIDs = array($entityIDs);
		}
		if (! is_array($entityNames)) {
			$entityNames = array($entityNames);
		}
		
		DB::table('File')
				->whereIn('EntityID', $entityIDs)
				->whereIn('EntityName', $entityNames)
				->delete();
	}
	
	public static function getItemAttachmentListByReceipts($receiptIDs)
	{
		if (! is_array($receiptIDs)) {
			$receiptIDs = array($receiptIDs);
		}
		
		if (count($receiptIDs)) {
			return DB::table('File AS f')
					->join('Item AS i', function($join) {
						$join->on('f.EntityID', '=', 'i.ItemID')->on('f.EntityName', '=', DB::raw('"receipt_item"'));
					})
					->select('f.FileID', 'f.FilePath', 'f.FileBucket')
					->whereIn('i.ReceiptID', $receiptIDs)
					->get();
		}
		
		return array();
	}
	
	public static function getAttachmentListOfReceipts($receiptIDs)
	{
		if (! is_array($receiptIDs)) {
			$receiptIDs = array($receiptIDs);
		}
		
		if (count($receiptIDs)) {
			return DB::table('File AS f')
					->select('f.FileID', 'f.FilePath', 'f.FileBucket')
					->whereIn('f.EntityID', $receiptIDs)
					->orWhere(function($query) {
						$query->where('EntityName', '=', 'receipt_image')
								->where('EntityName', '=', 'receipt');
					})
					->get();
		}
		
		return array();
	}

    /**
     * Initial S3 PHP SDK service.
     * Region is detected dynamically by bucket naming convention if possible
     *
     * @param   $bucket    string   Bucket name
     * @param   $endPoint  string   Endpoint
     * @return  mixed
     */
    public static function getS3Service ($bucket, $endPoint = 'us-east-1')
    {
        $s3Regions = array('us-east-1', 'us-west-1', 'us-west-2', 'eu-west-1', 'eu-central-1', 'ap-southeast-1',
            'ap-southeast-2', 'ap-northeast-1', 'sa-east-1');

        foreach ($s3Regions as $r) {
            if (strpos($bucket, $r)) {
                $endPoint = $r;
                break;
            }
        }

        $s3 = Aws::factory(array(
            'key'    => Config::get('aws::config.key'),
            'secret' => Config::get('aws::config.secret'),
            'region' => $endPoint
        ))->get('s3');

        return $s3;
    }

    /**
     * Upload file to s3 bucket
     *
     * @param   $bucket     string    Bucket name
     * @param   $keyName    string    Key name
     * @param   $endPoint   string    Region Endpoint
     *
     * @return bool
     */
    public static function getS3PreSignedUrl ($bucket, $keyName, $endPoint = 'us-east-1')
    {
        $s3 = self::getS3Service($bucket, $endPoint);

        $request = $s3->get($bucket . '/' . $keyName);

        return $s3->createPresignedUrl($request, '+ 1 hour');
    }

    /**
     * Upload file to s3 bucket
     *
     * @param   $bucket    string    Bucket name
     * @param   $name      string    Key name
     * @param   $file      string    File data
     * @param   $options   array     extended options
     *
     * @return bool
     */
    public static function putFileToS3 ($bucket, $name, $file, $options = array())
    {
        $endPoint = isset ($options['endPoint']) ? $options['endPoint'] : 'us-east-1';
        $s3 = self::getS3Service($bucket, $endPoint);

        try {
            $params = array(
                'Bucket'       => $bucket,
                'Key'          => $name
            );

            if (method_exists($file, 'getRealPath')) {
                //$params['SourceFile'] = $file->getRealPath();
                $params['Body'] = fopen($file->getRealPath(), 'r+');
            } else {
                $params['Body'] = $file;
            }

            if (isset($options['permission'])) {
                switch ($options['permission']) {
                    case 'public-read' :
                        $params['ACL'] = 'public-read';
                        break;
                }
            }

            $s3->putObject($params);

            return true;
        } catch (S3Exception $e) {
            return false;
        }
    }

    /**
     * Delete file from s3 bucket
     *
     * @param   $bucket    string    Bucket name
     * @param   $name      string    Key name
     * @param   $endPoint  string    Region Endpoint
     *
     * @return bool
     */
    public static function deleteFileFromS3 ($bucket, $name, $endPoint = 'us-east-1')
    {
        $s3 = self::getS3Service($bucket, $endPoint);

        try {
            $s3->deleteObject(array(
                'Bucket' => $bucket,
                'Key'    => $name
            ));

            return true;
        } catch (S3Exception $e) {
            return false;
        }
    }

    /**
     * Return the static/plain Url of file which are set public for reading
     * @param   $name      string    Key name
     * @param   $endPoint  string    Region Endpoint
     *
     * @return  string
     */
    public static function getS3PlainUrl ($bucket, $name, $endPoint = 'us-east-1')
    {
        $s3 = self::getS3Service($bucket, $endPoint);

        return $s3->getObjectUrl($bucket, $name);
    }
}
