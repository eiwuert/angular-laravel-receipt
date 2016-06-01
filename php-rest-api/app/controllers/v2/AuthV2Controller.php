<?php
/**
 * Controller for user interactions with the system
 */
class AuthV2Controller extends BaseV2Controller 
{
    /*
     * POST /auth
     * 
     * Process login
     */
    public function postIndex() 
    {
        //Instead of using session, we generate a token and using it to interact with database directly
		$user = User::where('Email', '=', Input::get('Email', ''))->first();

		if ($user) {
			$password = '';
			
			if (Hash::check(Input::get('Password', ''), $user->Password) || $user->Password == $password) {
				if ($user->Status > 0) {
                    $object = $this->buildUserApiResponse($user);
                    $jsend = JSend\JSendResponse::success($object->toArray());
                    return $jsend->respond();
				} else {
                    $jsend = JSend\JSendResponse::fail(array('message' => 'Your account has not been activated.'));
                    return $jsend->respond();
				}
			} else {
                $jsend = JSend\JSendResponse::fail(array('message' => 'Invalid Password'));
                return $jsend->respond();
			}					
		} else {
            $jsend = JSend\JSendResponse::fail(array('message' => 'Invalid Email'));
            return $jsend->respond();
		}
    }
    
    private function buildUserApiResponse($user, $token = null)
	{
		//Initialize the object which will be returned
		$userResponse = new stdClass();
				
		// FIXME: improve password encrypt algorithm
		$pass 	 = base64_encode($user->Password);		
		$uid 	 = uniqid();
		$prefix  = sha1($uid);		
		$suffix  = sha1($pass);		
		$newPass = $prefix.$pass.$suffix;
		
		//Generate an user token or update expiration time if the user already has a token record
		if (! $token) {
//			if ($user->ApiToken) {
//				$userApiToken = $user->ApiToken->generateApiToken();
//			} else {
//				$userApiToken = new UserApiToken();
//				$userApiToken->generateApiToken($user->UserID);
//			}
			$userApiToken = new UserApiToken();
            $userApiToken->generateApiToken($user->UserID);
			//Add token to the returned object
			$token = $userApiToken->ApiToken;
		}

		//Add profile info to the returned object
		if ($user->Profile) {
			$userResponse = $user->Profile;
		} else {
			$userResponse->FirstName = $userResponse->LastName = null;
		}
		if($user->Settings){
			$userResponse->TimezoneOffset = date('Z');
			$userResponse->CurrencyCode = $user->Settings['CurrencyCode'];
			$userResponse->AutoArchive = $user->Settings['AutoArchive'];
		}
		
		$userResponse->UserID = $user->UserID;
		$userResponse->Email = $user->Email;
		$userResponse->Password = $newPass;
		$userResponse->ApiToken = $token;

        
		return $userResponse;
	}
    
    /*
     * GET /users/logout
     * 
     * Process logout
     */
	public function getIndex()
	{	
        $userToken = UserApiToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN']);
        
        if (empty($_SERVER['HTTP_AUTH_TOKEN']) || !$userToken) {
            $jsend = JSend\JSendResponse::error('The authentication is failed.', 401);
            return $jsend->respond();
        }
		
        $input = Input::all();
        if(!empty($input['DeviceToken'])) {
            DB::table('DeviceApiToken')
			->where('DeviceToken', $input['DeviceToken'])
            ->where('UserID', $userToken->UserID)->delete();
        }
        
		//When authentication passes, we delete the token record
		$userToken = UserApiToken::where('ApiToken', '=', $_SERVER['HTTP_AUTH_TOKEN'])->first();
		if ($userToken) {
			$userToken->delete();
		}
        		
        $jsend = JSend\JSendResponse::success();
        return $jsend->respond();
	}
}