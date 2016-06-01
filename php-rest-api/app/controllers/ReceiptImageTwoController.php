<?php

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Controller for receipt images
 */
class ReceiptImageTwoController extends BaseController
{
    public function postIndex()
    {
        //Need to check authentication
		if (! $userToken = UserToken::checkAuth(Input::get('AUTH_TOKEN', ''))) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
        
        // it may runs longer
        set_time_limit(120);
		
        // more validation rules
		$rules = array(
			'Filedata' => array('required'),
		);
		
		$validator = Validator::make(Input::all(), $rules);
		
		if ($validator->fails()) {
			return Response::json(array('message' => $validator->messages()->all()), 500);
		}
		
		$fileData = Input::file('Filedata');
        
		//Move image to tmp folder to prevent removing on session closed
		$tmpFileName = Input::get('uid') . '_' . time() . '_' . uniqid() . '.' . pathinfo($fileData->getClientOriginalName(), PATHINFO_EXTENSION);
		$newFilePath = 'files/tmp_upload' . $tmpFileName;
		rename($fileData->getRealPath(), $newFilePath);
		//Run background script
        shell_exec('php ../gearman_background.php ' . Input::get('uid') . ' "' . $newFilePath . '" > /dev/null 2>/dev/null &');

        return Response::json(array(), 200);
    }
}
