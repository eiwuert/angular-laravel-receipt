<?php

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Controller for receipt images
 */
class ReceiptImageController extends BaseController
{
    public function postIndex()
    {        
      //  dd(round(microtime(true) * 1000));
        //Need to check authentication
		if (! $userToken = UserToken::checkAuth(Input::get('AUTH_TOKEN', ''))) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
        
        // it may runs longer
        //set_time_limit(120);
		
        // more validation rules
		$rules = array(
			'Filedata' => array('required'),
		);
		
		$validator = Validator::make(Input::all(), $rules);
		
		if ($validator->fails()) {
			return Response::json(array('message' => $validator->messages()->all()), 500);
		}
		
		$fileData = Input::file('Filedata');
        $oriFileName = urlencode($fileData->getClientOriginalName());
        
		//Move image to tmp folder to prevent removing on session closed
		$fileName = Input::get('uid') . '_' . time() . '_' . uniqid() . '_' . pathinfo($oriFileName, PATHINFO_BASENAME);
        $newFilePath = 'files/tmp_upload/' . $fileName;
		rename($fileData->getRealPath(), $newFilePath);

        $ftype = (strtolower(pathinfo($oriFileName, PATHINFO_EXTENSION)) == 'pdf')? 'PDF' : 'IMG';
        $uploadType = "upload";
        
        //Run background script
        $command = 'php ../gearman_background.php ' . $userToken->UserID . ' "' . $ftype .  '" "' . $uploadType . '" "' . $newFilePath . '" "' . $oriFileName . '"';
        if ($_ENV['STAGE'] != STAGE_DEV) {
            $command .= ' > /dev/null 2>/dev/null &';
        }

        shell_exec($command);
        
        return Response::json(array(), 200);
    }
    
    /**
     * Request OCR worker to progress images which are uploaded to s3 already
     */
    public function postOcrWorkRequest()
    {   
        //Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
        
        $input = Input::all();
        
        if ($input['fileName']) {
            $ftype = (strtolower(pathinfo($input['orgName'], PATHINFO_EXTENSION)) == 'pdf')? 'PDF' : 'IMG';
            $uploadType = "upload";
            
            $command = 'php ../scripts/gearman_OCR_Image.php ' . $userToken->UserID . ' "' . $ftype .  '" "' . $uploadType . '" "' . $input['fileName'] . '" "' . $input['orgName'] . '"';
            
            if ($_ENV['STAGE'] != STAGE_DEV) {
                $command .= ' > /dev/null 2>/dev/null &';
            }
            
            shell_exec($command);            
        }
    }

    /**
     * API for PushGun server
     * Request OCR worker to progress images
     */
    public function postOcrRequest()
    {
        $token       = Input::json('token', '');
        $imgName     = Input::json('fileName', '');
        $orgName     = Input::json('orgName', '')?:'receipt_upload.jpg';
        $msg         = '';

        if (!empty($token)) {
            $user = (UserToken::checkAuth($token))?: UserApiToken::checkAuth($token);

            if ($user) {
                if (!empty($imgName)) {
                    $requesterIP = Input::json('internalAddress', '');

                    $ftype       = (strtolower(pathinfo($imgName, PATHINFO_EXTENSION)) == 'pdf')? 'PDF' : 'IMG';
                    $uploadType  = "upload";
                    $command     = 'php ../scripts/pushgun_OCR_Image.php ' . $user->UserID . ' "' . $ftype .  '" "' .
                                  $uploadType . '" "' . $imgName . '" "' . $orgName . '" "' . $requesterIP . '"';

                    if ($_ENV['STAGE'] != STAGE_DEV) {
                        $command .= ' > /dev/null 2>/dev/null &';
                    }

                    shell_exec($command);

                    return Response::json(array('Success' => true), 200);
                } else {
                    $msg = 'File name and original image name are required';
                }
            } else {
                $msg = 'Authentication failed';
            }
        }

        return Response::json(array('Success' => false, 'error' => $msg), 400);
    }
    
    /**
     * Check health for OCR server
     */
    public function getServerStatus ()
    {            
        if ($_ENV['STAGE'] == STAGE_DEV) {
            $domain = $_ENV['GLB_OCR_SERVER_URL'];
            $port   = $_ENV['GLB_OCR_SERVER_PORT'];
            $ping   = @fsockopen ($domain, $port, $errno, $errstr, 3); //ping in 3 seconds
        } else {
            $client = new GearmanClient();
            $client->addServer($_ENV['GLB_OCR_SERVER_URL'], $_ENV['GLB_OCR_SERVER_PORT']);
            $ping = $client->ping('ding dong');
        }

        if (!$ping) {
            // Site is down
            return Response::json(array('message' => '0'), 200);
        }
        return Response::json(array('message' => '1'), 200);
    }
}
