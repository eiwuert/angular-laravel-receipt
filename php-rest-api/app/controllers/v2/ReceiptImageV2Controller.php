<?php

/**
 * Controller for receipts
 */
class ReceiptImageV2Controller extends BaseV2Controller 
{
    /*
     * POST /receipt-images
     * 
     * Upload receipt images
     */
    public function store() {
        $userToken = UserApiToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN']);
        
        if (empty($_SERVER['HTTP_AUTH_TOKEN']) || !$userToken) {
            $jsend = JSend\JSendResponse::error('The authentication is failed.', 401);
            return $jsend->respond();
        }
        
        $input = Input::all();
        $messages = $this->validateStore($input);
        
        if (count($messages)) {
            $jsend = JSend\JSendResponse::fail($messages);
            return $jsend->respond();
        }
		
		$fileData = Input::file('Filedata');
       
        $oriFileName = urlencode($fileData->getClientOriginalName());
        
		//Move image to tmp folder to prevent removing on session closed
		$fileName = $userToken->UserID . '_' . time() . '_' . uniqid() . '_' . pathinfo($oriFileName, PATHINFO_BASENAME);
        
        $newFilePath = 'files/tmp_upload/' . $fileName;
        
		rename($fileData->getRealPath(), $newFilePath);
		
        $ftype = (strtolower(pathinfo($oriFileName, PATHINFO_EXTENSION)) == 'pdf')? 'PDF' : 'IMG';
        $uploadType = "app";
        
        //Run background script
        //shell_exec('php ../gearman_background.php ' . $userToken->UserID . ' "' . $ftype .  '" "' . $uploadType . '" "' . $newFilePath . '" "' . $oriFileName . '" > /dev/null 2>/dev/null &');
        shell_exec('php ../gearman_background.php ' . $userToken->UserID . ' "' . $ftype .  '" "' . $uploadType . '" "' . $newFilePath . '" "' . $oriFileName . '"');

        $jsend = JSend\JSendResponse::success();
        return $jsend->respond();
    }
    
    protected function validateStore($input) 
    {
        // more validation rules
		$rules = array(
			'Filedata' => array('required'),
		);
		
		$validator = Validator::make($input, $rules);
		
		if ($validator->fails()) {
			return array('message' => $validator->messages()->all());
		}
        
        return array();
    }
        
    /*
     * POST /receipt-images
     * 
     * Upload receipt images
     */
    public function show($id) {
        $userToken = UserApiToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN']);
        if (empty($_SERVER['HTTP_AUTH_TOKEN']) || !$userToken) {
            $jsend = JSend\JSendResponse::error('The authentication is failed.', 401);
            return $jsend->respond();
        }
        $s3Link = '';
        
        if ($id) {
            $receiptID = $id;
            
            // set receipt_image
            $receiptImage = DB::table('File')
                ->select('FileID', 'FileBucket', 'FilePath')
                ->where('EntityID', $receiptID)
                ->where('EntityName', 'receipt_image')
                ->first();
            
            if($receiptImage) {
                //$s3Link = Receipt::createReceiptImageUrl($receiptImage->FilePath);
                $s3Link = File::getS3PreSignedUrl($receiptImage->FileBucket, $receiptImage->FilePath);
            }
        }
        
        $jsend = JSend\JSendResponse::success(array('image' => $s3Link));
        return $jsend->respond();
    }

    /**
     * Request OCR worker to progress images which are uploaded to s3 already
     */
    public function ocr()
    {        
        //Need to check authentication
        $userToken = UserApiToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN']);
        
        if (empty($_SERVER['HTTP_AUTH_TOKEN']) || !$userToken) {
            $jsend = JSend\JSendResponse::error('The authentication is failed.', 401);
            return $jsend->respond();
        }
        
        $input = Input::all();
        //$fileName = Input::get('fileName', '');
        
        if(! isset($input['fileName'])) {
            $messages['fileName'] = "Please provide fileName";
            $jsend = JSend\JSendResponse::fail($messages);
            return $jsend->respond();
        }

        $file_extension = substr(strtolower($input['fileName']), strrpos(strtolower($input['fileName']), '.') + 1);

        $supported_extension = array(
            'gif',
            'jpg',
            'jpeg',
            'png',
            'pdf'
        );

        if (!in_array($file_extension, $supported_extension)) {
            $messages['fileName'] = "Please provide valid fileName with a valid file extension";
            $jsend = JSend\JSendResponse::fail($messages);
            return $jsend->respond();
        } 

        $originalName = "mobile_upload_" . date("Ymd") . $file_extension;

        if ($input['fileName']) {
            $ftype = ($file_extension == 'pdf')? 'PDF' : 'IMG';
            $uploadType = "android";
            
            shell_exec('php ../scripts/gearman_OCR_Image.php ' . $userToken->UserID . ' "' . $ftype .  '" "' . $uploadType . '" "' . $input['fileName'] . '" "' . $originalName . '"');
        }

        $jsend = JSend\JSendResponse::success();
        return $jsend->respond();
    }
}
