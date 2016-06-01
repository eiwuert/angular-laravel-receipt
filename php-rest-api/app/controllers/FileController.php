<?php
use \Aws\Sts\StsClient;

class FileController extends BaseController 
{
	
	public function postIndex()
	{

		//Need to check authentication
		if (! $userToken = UserToken::checkAuth(Input::get('AUTH_TOKEN', ''))) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}

		$input = Input::all();

		$rules = array(
			'Filedata' => array('required', 'valid_ext:' . implode(',', Config::get('app.attachmentRules.extensions')), 
					'file_size:' . Config::get('app.attachmentRules.fileSizeLimit')),
			'EntityName' => array('required', 'in:' . Config::get('app.attachmentRules.entities')),
			'EntityID' => array('required'),
		);
		
		$validator = Validator::make(Input::all(), $rules, array(
			'Filedata.file_size' => 'This file is bigger than the file size limit: ' . Config::get('app.attachmentRules.fileSizeLimit'),
			'Filedata.required' => 'Cannot find file data.',
			'EntityName.required' => 'You need to specify entity name.',
			'EntityID.required' => 'You need to specify entity ID.',
		));
		
		if ($validator->fails()) {
			return Response::json(array('message' => $validator->messages()->all()), 500);
		}
		
		$fileData = Input::file('Filedata');
		$entityID = Input::get('EntityID');
		$entityName = Input::get('EntityName');
		
		$filename = $fileData->getClientOriginalName();
		if ($fileData->move('files/attachments', $_SERVER['REQUEST_TIME'] . '_' . $filename)) {
			$file = new File();
			$file->FileName = $filename;
			$file->FilePath = 'attachments/' . $_SERVER['REQUEST_TIME'] . '_' .  $filename;
			$file->Timestamp = $_SERVER['REQUEST_TIME'];
			$file->EntityID = $entityID;
			$file->EntityName = $entityName;
			$file->Permanent = 0;
			$file->save();
			$file->FilePath = Config::get('app.fileBaseUrl') . $file->FilePath;
			return Response::json($file, 200);
		}
		
		return Response::json(array('message' => array('Cannot upload this file.')), 500);
	}
    
    /**
     * Save db file record 
     */
    public function postSaveFile()
	{
        if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}

        $buckets = S3Region::getUserBucketList($userToken->UserID);

        $post = Input::all();
        $file = new File();
        $file->FileName   = $post['orgName'];
        $file->FileBucket = $buckets['attachment'];
        $file->FilePath   = $post['fileName'];
        $file->Timestamp  = $_SERVER['REQUEST_TIME'];
        $file->EntityID   = $post['entityID'];
        $file->EntityName = $post['entityName'];
        $file->Permanent  = 0;
        $file->save();
        
        //Return pre-signed url to view file in the browser
        $s3        = App::make('aws')->get('s3');
        $request   = $s3->get($file->FileBucket .'/' . $file->FilePath);
		$signedUrl = $s3->createPresignedUrl($request, '+ 1 hour');

        $fileAttachment = array(
            'EntityID'   => $file->EntityID,
            'FileID'     => $file->FileID,
            'FileBucket' => $file->FileBucket,
            'FileName'   => $file->FileName,
            'FilePath'   => $signedUrl
        );

        return Response::json($fileAttachment, 200);
    }
	
	public function putPath()
	{
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
		if ($userToken->UserID != 1) {
			return;
		}
		
		$files = File::whereRaw('EntityName = "receipt_image" AND FilePath LIKE "receipts%"')->get();
		foreach ($files as $file) {
			$file->FilePath = str_replace('receipts/', '', $file->FilePath);
			$file->save();
		}
	}
    
    /**
     * Provide signature key and required data for uploading to s3 service
     */
    public function getS3UploadTicket()
    {
        if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
        
        //Generate AWS Security Token Service from configured aws sdk
        //$sts         = App::make('aws')->get('sts');
        $sts         = App::make('awsWebClient')->get('sts');
        $credentials = $sts->createCredentials($sts->getSessionToken());

        return Response::json(json_decode($credentials->serialize()), 200);
    }
    
    /**
     * Received manual image processing request and return processed file link in s3
     */
    public function postManualProcess()
    {
        if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}

        $post   = Input::all();
        if (empty($post['receiptID'])) {
            return Response::json(array('message' => 'Could not find receipt.'), 500);
        }
        
        //Request image conversion via Gearman
        if ($_ENV['STAGE'] == STAGE_DEV) {
            //Local Gearman lib
            $client = new \Net\Gearman\Client();
            $client->addServer($_ENV['GLB_OCR_SERVER_URL'], $_ENV['GLB_OCR_SERVER_PORT']);
        } else {
            //Server Gearman lib
            $client = new GearmanClient();
            $client->addServer($_ENV['GLB_OCR_SERVER_URL'], $_ENV['GLB_OCR_SERVER_PORT']);
        }

        $profile = Profile::find($userToken->UserID);
        $country = Country::find($profile->CountryName);
        $arguments = array(
            'imageName'   => $post['fileName'],
            'regionCode'  => strtolower($country->S3RegionCode),
            'countryCode' => strtolower($country->CountryCode),
            'userID'      => strval($userToken->UserID),
            'receiptID'   => strval($post['receiptID']),
            'oriName'     => $post['oriFileName']
        );

        $msg    = $client->doNormal('ManualProcessing_IMG', json_encode($arguments));
        $object = @json_decode($msg);
        
        //Save a receipt_image file record attached to a receipt
        $file = File::firstOrNew(array('EntityID' => $post['receiptID'], 'EntityName' => 'receipt_image'));
        $oldFile = clone $file;

        $file->FileName   = $post['oriFileName'];
        $file->FilePath   = $object->imageName;
        $file->FileBucket = $object->bucket;
        $file->Timestamp  = time();
        $file->EntityID   = $post['receiptID'];
        $file->EntityName = "receipt_image";
        $file->Permanent  = 1;
        $file->save();
        
        //Delete old image file of receipt if exist
        if ($oldFile->FilePath) {
            $s3 = App::make('aws')->get('s3');
            $s3->deleteObject(array(
                'Bucket' => $oldFile->FileBucket,
                'Key'    => $oldFile->FilePath
            ));
        }
        
        return Response::json(array(
            'keyName' => $object->imageName,
            'bucket'  => $object->bucket
        ), 200);
    }
    
    /**
     * Received manual image processing request and return processed file link in s3
     */
    public function deleteManualImage()
    {
        if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}

        $input = Input::all();
        if (empty($input['receiptID'])) {
            return Response::json(array('message' => 'Could not find receipt.'), 500);
        }
        
        $file = File::where('EntityID', $input['receiptID'])->first();
        if ($file && $file->FilePath) {
            //Delete file in S3 bucket
            $s3 = App::make('aws')->get('s3');
            $s3->deleteObject(array(
                'Bucket' => $file->FileBucket,
                'Key'    => $file->FilePath
            ));

            //Delete file record in db
            File::destroy($file->FileID);
        }
        
        return Response::json(array(), 200);
    }

    /**
     * Stress test trigger
     */
	/*
    public function postStressTest ()
    {
        $img_per_set  = 10;
        $size_of_set  = 1;
        $img_ext      = '.jpg';
        //$folder_root  = '../scripts/stresstest/';
        $folder_light = 'img/light/';
        $folder_heavy = 'img/heavy/';
        $folder_mixed = 'img/mixed/';

        $input = Input::all();
        $token = DB:: table('UserToken')->where('UserID', $input['uid'])->pluck('Token');

        if (isset($input['sizeOfSet'])) $size_of_set = intval($input['sizeOfSet']);

        if ($token && $input['set']) {
            switch ($input['set']) {
                case 'light':
                    $folder = $folder_light; break;
                case 'heavy':
                    $folder = $folder_heavy; break;
                default:
                    $folder = $folder_mixed;
            }

            $testID = time();
            for ($i=0; $i<$size_of_set; $i++) {
                $toLog = '-';
                //if ($i == 0) $toLog = '[TestID: ' . $testID . '] Start stress test of set ' . $input['set'] . ' : ' . $size_of_set . ' files';
                //if ($i == $size_of_set -1) $toLog = '[TestID: ' . $testID . '] End test of set ' . $input['set'] . ' : ' . $size_of_set . ' files';

                $filePath = $folder . ($i%$img_per_set) . $img_ext;

                $command = 'php ../scripts/client_upload.php ' . $input['uid'] . ' "' . $token .  '" "' .
                    $input['sockId'] . '" "' . $filePath . '" "' . $toLog . '"';

                if ($_ENV['STAGE'] != STAGE_DEV) {
                    $command .= ' > /dev/null 2>/dev/null &';
                }

                shell_exec($command);
            }
        }
    }
	*/
}
