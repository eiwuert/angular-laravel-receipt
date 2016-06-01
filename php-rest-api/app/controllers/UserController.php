<?php
/**
 * Controller for user interactions with the system
 */
$vendorDir = dirname(dirname(__FILE__));

require_once $vendorDir . '/libs/recaptchalib.php';
        
class UserController extends BaseController 
{
	
	/**
	 * Login method
	 */
	public function postAuth()
	{
		//Instead of using session, we generate a token and using it to interact with database directly
		$user = User::where('Email', '=', Input::get('Email', ''))->first();				
		if ($user) {
			$password = '';
			$rememberedUser = isset($_COOKIE['ls_lastLoginAccount']) ? json_decode($_COOKIE['ls_lastLoginAccount']) : null;
			
			// User use Remember Me feature
			if (isset($rememberedUser->password)) {			
				$encryptPassword = $rememberedUser->password;
				// prefix & suffix string are returned by SHA1 function so it has 40 characters
				$encodedPassword = substr($encryptPassword, 40, strlen($encryptPassword) - 80);
				$password = base64_decode($encodedPassword);
			} 
			
			if (Hash::check(Input::get('Password', ''), $user->Password) || $user->Password == $password) {
				if ($user->Status > 0) {
					//Update last login timestamp
					$user->LoginTime = $_SERVER['REQUEST_TIME'];
					$user->save();                                        
					return Response::json($this->buildUserJsonResponse($user));
				} else {
					return Response::json(array('message' => 'Your account has not been activated.'), 500);
				}
			} else {
				return Response::json(array('message' => 'Invalid Password.'), 500);
			}					
		} else {
			return Response::json(array('message' => 'Invalid Email.'), 500);
		}
	}
	
	/**
	 * Logout method
	 */
	public function getLogout()
	{	
		//Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
		
		//When authentication passes, we delete the token record
		$userToken = UserToken::where('Token', '=', $_SERVER['HTTP_AUTH_TOKEN'])->first();
		if ($userToken) {
			$userToken->delete();
		}
		
		return Response::make('', 204);
	}
	
	public function getGeoCurrency()
	{
		$geoInfo = unserialize(file_get_contents('http://www.geoplugin.net/php.gp?ip=' . $this->findIpAddress()));
		if (isset($geoInfo['geoplugin_currencyCode']) && ! empty($geoInfo['geoplugin_currencyCode'])) {
			return Response::json(array('currencyCode' => $geoInfo['geoplugin_currencyCode']));
		}
		
		return Response::json(array('message' => 'Cannot find a valid currency code.'), 500);
	}
	
	/**
	 * Register new user
	 */
	public function postRegister()
	{
		$post = Input::all();
		//Build the validator for inputs
		$validator = Validator::make(
			$post, 
			array(
				'FirstName' => array('required'),
				'LastName' => array('required'),
				'Email' => array('required', 'email', 'unique:User'),
				'Password' => array('required', 'min:6'),
				'PasswordConfirm' => array('required', 'same:Password'),
				'CurrencyCode' => array('required', 'size:3'),
			), 
			array(
				'required' => 'Please enter your :attribute.',
				'PasswordConfirm.required' => 'Please confirm your password.',
				'Password.min' => 'Your password must consist at least :min characters.',
				'Email' => 'Your email address is invalid.',
				'Email.unique' => 'The e-mail address is already registered',
				'PasswordConfirm.same' => 'Please re-enter your confirmation password. It does not match with your first entry.',
				'CurrencyCode.required' => 'Please enter your Home Currency',
			)
		);
		
		//Check if the validator fails
		if ($validator->fails()) {
			$messages = $validator->messages();
			if (isset($post['FullMessage']) && $post['FullMessage']) {
				return Response::json(array('message' => $messages->toArray()), 500);
			} else {
				return Response::json(array('message' => $messages->all()), 500);
			}
			
		}
		
		//If the validator passes, we created a new user
		$user = new User();
		$user->Username = $user->Email = $post['Email'];
		$user->Password = Hash::make($post['Password']);
		//$user->login = $user->created = $_SERVER['REQUEST_TIME']; -> User must activate their account so don't need to set login time
		$user->CreatedTime = $_SERVER['REQUEST_TIME'];
		$user->Status = 0; // default status is 0, user must activate their account via email
		$user->save();
		
		//A new profile for the new user
		$profile = new Profile();
		$profile->UserID = $user->UserID;
		$profile->FirstName = $post['FirstName'];
		$profile->LastName = $post['LastName'];
        $profile->CountryName = $post['CountryName'];
        $profile->Timezone = $post['Timezone'];
		//$profile->CurrencyCode = $post['CurrencyCode'];
		// Changed on 2014-0325 by KhanhDN: Default currency code is USD
		//$profile->CurrencyCode = 'USD';
		$profile->save();
		
		//A new settings for the new user
		$setting = new Settings();
		$setting->UserID = $user->UserID;
		$setting->CurrencyCode = 'USD';
        $setting->Timezone = $post['Timezone'];
		//$setting->CurrencyCode = 'USD';
		$setting->save();
		
		//Generate a hashed string to identify the activation
		$userToken = new UserToken();
		$userToken->generateToken($user->UserID, 'activate');
		
		//Send an activation email to the user
		Mail::send(
			'emails.user.activate', array(
				'name' => $profile->FirstName . ' ' . $profile->LastName,
				'url' => Config::get('app.clientBaseUrl') . '#!/profile/activate/' . $userToken->Token . '/' . $user->UserID,
				'loginUrl' => Config::get('app.clientBaseUrl') . '' . '#!/#login',
				'username' => $user->Email,
			), function($message) use ($user) {
				$message->to($user->Email)->subject('Account Activation – Please read!');
			});
		
		return Response::make('', 204);
	}
    
    /**
	 * Register new user into newsletter
	 */
	public function postNewsletter()
	{
		$post = Input::all();
		//Build the validator for inputs
		$validator = Validator::make(
			$post, 
			array(
				'FirstName' => array('required'),
				'LastName' => array('required'),
				'Email' => array('required', 'email', 'unique:Newsletter'),
			), 
			array(
				'required' => 'Please enter your :attribute.',
				'Email' => 'Your email address is invalid.',
				'Email.unique' => 'The e-mail address is already registered',
			)
		);
		
		//Check if the validator fails
		if ($validator->fails()) {
			$messages = $validator->messages();
			if (isset($post['FullMessage']) && $post['FullMessage']) {
				return Response::json(array('message' => $messages->toArray()), 500);
			} else {
				return Response::json(array('message' => $messages->all()), 500);
			}
			
		}
		
		//If the validator passes, we created a new user
		$newsletter = new Newsletter();
		$newsletter->Email = $post['Email'];
        $newsletter->FirstName = $post['FirstName'];
		$newsletter->LastName = $post['LastName'];
		$newsletter->CreatedTime = $_SERVER['REQUEST_TIME'];
        
        $newsletter->save();
		
        //Send an activation email to the user
		Mail::send(
			'emails.user.newsletter', array(
				'name' => $newsletter->Email,
        ), function($message) use ($newsletter) {
            $message->to($newsletter->Email)->subject('THANK YOU FOR JOINING US!');
        });
        
		return Response::make('', 204);
	}
	
    public function getCheckActivate() {
        $user = User::find(Input::get('userid', ''));
        
        if($user->Status == '1') {
            return Response::json(array('message' => 'Redirect homepage'), 301);
        } else {
            $userToken = UserToken::whereRaw('Token=:token AND Action=:action', array(':token' => Input::get('token', ''), ':action' => 'activate'))->first();

            if ($userToken) {
                if ($userToken->Expired >= $_SERVER['REQUEST_TIME']) {
                    return Response::make('', 204);
                }
                return Response::json(array('message' => 'Your token was expired.'), 500);
            }

            return Response::json(array('message' => 'Invalid Token.'), 404);
        }
    }
    
    public function getCheckShowGuide() {
        //Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => array('The authentication is failed.')), 401);
		}
		$put = Input::all();
        $profile = Profile::find($userToken->UserID);
        
        if ($put['kind'] == 'rb') {
            return Response::json(array('ShowGuide' => $profile->ShowRBGuide), 200);
        } else if ($put['kind'] == 'pe') {
            return Response::json(array('ShowGuide' => $profile->ShowPEGuide), 200);
        } else if ($put['kind'] == 'te') {
            return Response::json(array('ShowGuide' => $profile->ShowTEGuide), 200);
        } else if ($put['kind'] == 'be') {
            return Response::json(array('ShowGuide' => $profile->ShowBEGuide), 200);
        } else if ($put['kind'] == 'ee') {
            return Response::json(array('ShowGuide' => $profile->ShowEEGuide), 200);
        } else if ($put['kind'] == 'pa') {
            return Response::json(array('ShowGuide' => $profile->ShowPAGuide), 200);
        } else if ($put['kind'] == 'ba') {
            return Response::json(array('ShowGuide' => $profile->ShowBAGuide), 200);
        }
    }
    
    public function putUpdateShowGuide() {
        //Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => array('The authentication is failed.')), 401);
		}
		$put = Input::all();
        $profile = Profile::find($userToken->UserID);
        
        if ($put['kind'] == 'rb') {
            $profile->ShowRBGuide = $put['value'];
        } else if ($put['kind'] == 'pe') {
            $profile->ShowPEGuide = $put['value'];
        } else if ($put['kind'] == 'te') {
            $profile->ShowTEGuide = $put['value'];
        } else if ($put['kind'] == 'be') {
            $profile->ShowBEGuide = $put['value'];
        } else if ($put['kind'] == 'ee') {
            $profile->ShowEEGuide = $put['value'];
        } else if ($put['kind'] == 'pa') {
            $profile->ShowPAGuide = $put['value'];
        } else if ($put['kind'] == 'ba') {
            $profile->ShowBAGuide = $put['value'];
        }
            
        $profile->save();
        
        return Response::make('', 204);
    }
    
    public function getCheckResetPassword() {
        $userToken = UserToken::whereRaw('Token=:token AND Action=:action', array(':token' => Input::get('token', ''), ':action' => 'reset password'))->first();

        if ($userToken) {
            if ($userToken->Expired >= $_SERVER['REQUEST_TIME']) {
                return Response::make('', 204);
            }
            return Response::json(array('message' => 'Your token was expired.'), 500);
        }

        return Response::json(array('message' => 'Invalid Token.'), 404);
    }
	public function getActivate()
	{
		$userToken = UserToken::whereRaw('Token=:token AND Action=:action', array(':token' => Input::get('token', ''), ':action' => 'activate'))->first();
		if ($userToken) {
			$userToken->delete();

            //Update status of the user
            User::updateById($userToken->UserID, array('Status' => 1));

            //Update last login timestamp
            $user = User::find($userToken->UserID);
            $user->LoginTime = $_SERVER['REQUEST_TIME'];
            $user->save();

            return Response::json($this->buildUserJsonResponse($user));
		}
		
		return Response::json(array('message' => 'Invalid Token.'), 500);
	}
	
	/**
	 * Update user and her / his profile from the form Edit Profile
	 */
	public function putIndex()
	{
		//Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => array('The authentication is failed.')), 401);
		}
		
		$put = Input::all();
		$tmpExtraEmails = array();
		if (isset($put['ExtraEmails']) && count($put['ExtraEmails'])) {
			foreach ($put['ExtraEmails'] as $extraEmail) {
				if (! empty($extraEmail['Email'])) {
					$tmpExtraEmails[] = $extraEmail['Email'];
				}
			}
			
			$put['ExtraEmails'] = $tmpExtraEmails;
		}
		
		$user = User::find($userToken->UserID);
		//Build the validator for inputs
		if (empty($put['IsResetPassword'])) {
			$rules = array(
				'FirstName' => array('required'),
				'LastName' => array('required'),
				'Email' => array('required', 'email', 'profile_email:' . $userToken->UserID),
				'ExtraEmails' => array('extra_emails:' . $userToken->UserID),
				'Password' => array('required_with:NewPassword', 'password_matched:' . (isset($user->Password) ? $user->Password : null)),
				'NewPassword' => array('min:6'),
				'NewPasswordConfirm' => array('required_with:NewPassword', 'same:NewPassword'),
				//'Password' => array('password_matched:' . (isset($user->Password) ? $user->Password : null)),
				'CompanyName' => array('max:255'),
				'CompanyAddress' => array('max:255'),
                'Mobile' => array('max:25'),
                'CityTown' => array('max:50'),
                'StateProvince' => array('max:50'),
                'ZipCode' => array('max:10'),
                'CountryName' => array('max:50'),
				'AddressFirst' => array('max:255'),
				'AddressSecond' => array('max:255'),
				'MaritalStatus' => array('in:0,1'),
				'Gender' => array('in:0,1,2'),
				'Birthdate' => array('date'),
			);
		} else {
			$rules = array(
				'NewPassword' => array('required', 'min:6'),
				'NewPasswordConfirm' => array('required', 'same:NewPassword'),
			);
		}
		
		$validator = Validator::make($put, $rules, array(
				'required' => 'Please enter your :attribute.',
				'Password.required_with' => 'Please enter your current password.',
				'NewPasswordConfirm.required_with' => 'Please confirm your new password.',
				'NewPassword.min' => 'Your password must consist at least :min characters.',
				'NewPasswordConfirm.same' => 'Please re-enter your confirmation password. It does not match with your first entry.',
			));

		//Check if the validator fails
		if ($validator->fails()) {
			$messages = $validator->messages();
			if (isset($put['FullMessage']) && $put['FullMessage']) {
				return Response::json(array('message' => $messages->toArray()), 500);
			} else {
				return Response::json(array('message' => $messages->all()), 500);
			}
		}
		
		//Save the new password
		if (! empty($put['NewPassword'])) {
			$user->password = Hash::make($put['NewPassword']);
			$user->save();
		}
		
		//If not for case password reset, we will have inputs for first name, last name
		//Updated (16 / 09 / 2013): Now user can update his primary email, and add extra
		//email for send email receipts
		if (empty($put['IsResetPassword'])) {
			$profile = Profile::find($user->UserID);
			
			if ($put['FirstName'] != $profile->FirstName) {
				$profile->FirstName = $put['FirstName'];
			}
			
			if ($put['LastName'] != $profile->LastName) {
				$profile->LastName = $put['LastName'];
			}
			
			if ($put['Email'] != $user->Email) {
				$user->EmailOnChange = $put['Email'];
				$changeEmailToken = UserToken::whereRaw('UserID=:userID AND action=:action', array(':userID' => $user->UserID, ':action' => 'change email'))->first();
				if (! $changeEmailToken) {
					$changeEmailToken = new UserToken();
					$changeEmailToken->generateToken($user->UserID, 'change email');
				} else {
					$changeEmailToken->generateToken(0, 'change email');
				}
				Mail::send(
					'emails.user.change_email', array(
						'name' => $profile['FirstName'] . ' ' . $profile['LastName'],
						'email' => $user->EmailOnChange,
						'url' => Config::get('app.clientBaseUrl') . '#!/profile/change-email/' . $changeEmailToken->Token,
					), function($message) use ($user) {
						$message->to($user->Email)->subject('ReceiptClub - Change Primary Email');
					});
			}
			
			if (isset($put['ExtraEmails']) && count($put['ExtraEmails'])) {
				ExtraEmailSender::updateExtraEmailList($put['ExtraEmails'], $userToken->UserID);
			}
			
			if (isset($put['CompanyName']) && ! empty($put['CompanyName'])) {
				$profile->CompanyName = $put['CompanyName'];
			}
			
			if (isset($put['CompanyAddress']) && ! empty($put['CompanyAddress'])) {
				$profile->CompanyAddress = $put['CompanyAddress'];
			}
			
			if (isset($put['AddressFirst']) && ! empty($put['AddressFirst'])) {
				$profile->AddressFirst = $put['AddressFirst'];
			}
			
			if (isset($put['AddressSecond']) && ! empty($put['AddressSecond'])) {
				$profile->AddressSecond = $put['AddressSecond'];
			}
            
            if (isset($put['StateProvince']) && ! empty($put['StateProvince'])) {
				$profile->StateProvince = $put['StateProvince'];
			}
			
			if (isset($put['Gender']) && ! empty($put['Gender'])) {
				$profile->Gender = $put['Gender'];
			}
			if (isset($put['CurrencyCode']) && ! empty($put['CurrencyCode'])) {
				$profile->CurrencyCode = $put['CurrencyCode'];
			}
			if (isset($put['MaritalStatus']) && ! empty($put['MaritalStatus'])) {
				$profile->MaritalStatus = $put['MaritalStatus'];
			}
            
			if (isset($put['Birthdate']) && ! empty($put['Birthdate'])) {
				$profile->Birthdate = $put['Birthdate'];
			}
			
            if (isset($put['Phone']) && ! empty($put['Phone'])) {
				$profile->Phone = $put['Phone'];
			}
            
            if (isset($put['PhoneExt']) && ! empty($put['PhoneExt'])) {
				$profile->PhoneExt = $put['PhoneExt'];
			}
            
            if (isset($put['AddressFirstCompany']) && ! empty($put['AddressFirstCompany'])) {
				$profile->AddressFirstCompany = $put['AddressFirstCompany'];
			}
            
            if (isset($put['AddressSecondCompany']) && ! empty($put['AddressSecondCompany'])) {
				$profile->AddressSecondCompany = $put['AddressSecondCompany'];
			}
            
            if (isset($put['AddressSecondCompany']) && ! empty($put['AddressSecondCompany'])) {
				$profile->AddressSecondCompany = $put['AddressSecondCompany'];
			}
            
            if (isset($put['CityTownCompany']) && ! empty($put['CityTownCompany'])) {
				$profile->CityTownCompany = $put['CityTownCompany'];
			}
            
            if (isset($put['StateProvinceCompany']) && ! empty($put['StateProvinceCompany'])) {
				$profile->StateProvinceCompany = $put['StateProvinceCompany'];
			}
            
            if (isset($put['ZipCodeCompany']) && ! empty($put['ZipCodeCompany'])) {
				$profile->ZipCodeCompany = $put['ZipCodeCompany'];
			}
            
            if (isset($put['CountryNameCompany']) && ! empty($put['CountryNameCompany'])) {
				$profile->CountryNameCompany = $put['CountryNameCompany'];
			}
            
			if (isset($put['CountryName']) && ! empty($put['CountryName'])) {
				$profile->CountryName = $put['CountryName'];
			}
			
			if (isset($put['Mobile']) && ! empty($put['Mobile'])) {
				$profile->Mobile = $put['Mobile'];
			}
			
			if (isset($put['ZipCode']) && ! empty($put['ZipCode'])) {
				$profile->ZipCode = $put['ZipCode'];
			}
			
			if (isset($put['CityTown']) && ! empty($put['CityTown'])) {
				$profile->CityTown = $put['CityTown'];
			}
			
			if (isset($put['PostCode']) && ! empty($put['PostCode'])) {
				$profile->PostCode = $put['PostCode'];
			}
			
			$user->save();
			$profile->save();
		}
		
		return Response::make('', 204);
	}
	
	public function deleteIndex()
	{
		$post = Input::all();

		//Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => array('The authentication is failed.')), 401);
		}
        // This is just a fake delete, which will deactivate the user account
		// Will be updated to be a real delete function soon
        
		$user = User::find($userToken->UserID);

        $profile = Profile::find($user->UserID);

        $user->Username = $user->Email = $_SERVER['REQUEST_TIME'] . '_' . $user->Email;
        $user->Status = 0;
        $user->save();
        
		//Send feedback message to admin
        Mail::send(
            'emails.contact.contact', 
            array (
                'name' => 'support@receiptclub.com',
                'myname' => $profile->FirstName . ' ' . $profile->LastName,
                'email' => $user->Email,
                'phone' => $profile->Mobile,
                'mymessage' => $post['FeedbackMessage']
            ), 
            function($message) {
                $subject = 'Delete Account Feedback';
                $message->to('support@receiptclub.com')->subject($subject);
            }
        );

        return Response::make(array('token' => $_SERVER['HTTP_AUTH_TOKEN']));
	}
	
	public function getChangeEmail()
	{
		$userToken = UserToken::whereRaw('Token=:token AND Action=:action', array(':token' => Input::get('token', ''), ':action' => 'change email'))->first();
		if ($userToken) {
			if ($userToken->Expired >= $_SERVER['REQUEST_TIME']) {
				//Update user information
				$user = User::find($userToken->UserID);
				$user->Username = $user->Email = $user->EmailOnChange;
				$user->EmailOnChange = null;
				$user->save();
				
				UserToken::whereRaw('UserID=:userID AND Action=:action', array(':userID' => $userToken->UserID, ':action' => 'login'))->delete();
				$userToken->Action = 'login';
				$userToken->save();
				
				return Response::json($this->buildUserJsonResponse($user, $userToken->Token));
			} 
			
			$userToken->delete();
			return Response::json(array('message' => 'Your token was expired.'), 500);
		}

		return Response::json(array('message' => 'Invalid Token.'), 500);
	}
    
    public function putResendActivation() {
        $put = Input::all();
        
        $userToken = UserToken::whereRaw('Token=:token AND Action=:action', array(':token' => Input::get('token', ''), ':action' => 'activate'))->first();
        $user = User::find($userToken->UserID);
        $profile = Profile::find($user->UserID);
        
        $userToken->delete();
        
        //Generate a hashed string to identify the activation
		$newUserToken = new UserToken();
		$newUserToken->generateToken($user->UserID, 'activate');
		
		//Send an activation email to the user
		Mail::send(
			'emails.user.activate', array(
				'name' => $profile->FirstName . ' ' . $profile->LastName,
				'url' => Config::get('app.clientBaseUrl') . '#!/profile/activate/' . $newUserToken->Token . '/' . $user->UserID,
				'loginUrl' => Config::get('app.clientBaseUrl') . '' . '#!/#login',
				'username' => $user->Email,
			), function($message) use ($user) {
				$message->to($user->Email)->subject('Account Activation – Please read!');
			});
		
		return Response::make('', 204);
    }
    public function putUpdateCurrency()
	{
		$put = Input::all();

        $user = User::find(Input::get('userid', ''));
		//Build the validator for inputs
        
        $profile = Profile::find($user->UserID);
        $settings = Settings::find($user->UserID);
        
        if (isset($put['currencty']) && ! empty($put['currencty'])) {
            $profile->CurrencyCode = $put['currencty'];
            $profile->save();
            
            $settings->CurrencyCode = $put['currencty'];
            $settings->save();
            
            return Response::make('', 200);
        }
		
        return Response::json(array('message' => "Please choose your currency"), 500);
	}
	
	/**
	 * Request new password method
	 */
	public function postRequestPassword()
	{
		//Build the validator for email input
		$validator = Validator::make(
			Input::all(),
			array('Email' => array('required', 'exists:User', 'limit_reset_pass:' . $_SERVER['REQUEST_TIME'])),
			array(
				'Email' => 'Invalid email address.',
				'Email.exists' => 'The email address entered is not registered with RC, please try again.',
                'Email.limit_reset_pass' => 'You can only reset your password no more than 2 times one day.'
			));
		
		//Check if the validator fails
		if ($validator->fails()) {
			$messages = $validator->messages();
			return Response::json(array('message' => $messages->all()), 500);
		}
		
		$email = Input::get('Email', '');
		//Generate a hashed string to identify the request
		$user = User::where('Email', '=', $email)->first();
		$userToken = UserToken::whereRaw('UserID=:userID AND action=:action', array(':userID' => $user->UserID, ':action' => 'reset password'))->first();
		if (! $userToken) {
			$userToken = new UserToken();
			$userToken->generateToken($user->UserID, 'reset password');
		} else {
			$userToken->generateToken(0, 'reset password');
		}
		
		$profile = $user->Profile;
		if ($profile) {
			//Send an activation email to the user
			Mail::send(
				'emails.user.reset_password', array(
					'name' => $profile->FirstName . ' ' . $profile->LastName,
					'supportEmail' => 'support@receiptclub.com',
					'url' => Config::get('app.clientBaseUrl') . '#!/profile/reset-password/' . $userToken->Token,
					'expiryDate' => date('m/d/Y', strtotime('tomorrow')),
				), function($message) use ($email) {
					$message->to($email)->subject('Your Password Reset Link');
				});
		}
		
        $userActivity = new UserActivity();
        $userActivity->UserID = $user->UserID;
        $userActivity->ActivityType = 'reset password'; 
        $userActivity->Timestamp = $_SERVER['REQUEST_TIME'];
        $userActivity->save();
        
		return Response::make('', 204);
	}
	
	public function getResetPassword()
	{
		$userToken = UserToken::whereRaw('Token=:token AND Action=:action', array(':token' => Input::get('token', ''), ':action' => 'reset password'))->first();
		if ($userToken) {
			if ($userToken->Expired >= $_SERVER['REQUEST_TIME']) {
				$userToken->Action = 'login';
				$userToken->save();
				return Response::json($this->buildUserJsonResponse(User::find($userToken->UserID), $userToken->Token));
			} 
			
			$userToken->delete();
			return Response::json(array('message' => 'Your token was expired.'), 500);
		}

		return Response::json(array('message' => 'Invalid Token.'), 500);
	}
    
    public function putSetNewPassword()
	{
		$userToken = UserToken::whereRaw('Token=:token AND Action=:action', array(':token' => Input::get('token', ''), ':action' => 'reset password'))->first();
		$password = Input::get('password', '');
        $confirmPassword = Input::get('passwordconfirm', '');
                
        if (strlen($password) >= 6) {
            if($password == $confirmPassword) {
                $userToken->Action = 'login';
                $userToken->save();

                $user = User::find($userToken->UserID);
                $user->password = Hash::make($password);
                $user->save();
                
                return Response::json($this->buildUserJsonResponse($user, $userToken->Token));
            }
            
            return Response::json(array('message' => 'Confirm Password does not match Password'), 500);
		}

		return Response::json(array('message' => 'Password must consist at least 6 characters.'), 500);
	}
	
	public function getTimezoneList()
	{
		//Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
		
		$o = array();
     
		$t_zones = timezone_identifiers_list();

		foreach($t_zones as $a)
		{
			if ($a == 'UTC') continue;
			try
			{
				//this throws exception for 'US/Pacific-New'
				$zone = new DateTimeZone($a);

				$seconds = $zone->getOffset(new DateTime("now" , $zone));
				$hours = intval($seconds / 3600);
				if ($hours < 0) {
					$hours = '-' . sprintf("%02d", abs($hours));
				} else {
					$hours = '+' . sprintf("%02d", $hours);
				}
				$minutes = sprintf("%02d", ($seconds % 3600) / 60);

				$o[] = array(
					'Zone' => $a,
					'Offset' => $hours . ':' . $minutes,
					'Label' => '[' . $hours . ':' . $minutes . '] ' . $a,
				);
			}

			//exceptions must be catched, else a blank page
			catch(Exception $e)
			{
				//die("Exception : " . $e->getMessage() . '<br />');
				//what to do in catch ? , nothing just relax
			}
		}

		ksort($o);

		return Response::json($o);
	}

    /**
     * Return list of country and region
     */
    public function getCountryList ()
    {
        return Response::json(Country::getList(), 200);
    }
	
	public function putSettings()
	{
		//Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
		
		$put = Input::all();
        
		$validator = Validator::make(
            $put,
            array(
                'Timezone' => array('required'),
            ),
            array(
                'Timezone.required' => 'Please enter your timezone',
            )
        );
		
		if ($validator->fails()) {
			return Response::json(array('message' => $validator->messages()->all()), 500);
		}
		
		$setting = Settings::find($userToken->UserID);

		if ($put['Timezone'] != $setting->Timezone) {
			$setting->Timezone = $put['Timezone'];
		}
		$setting->save();
		
        $profile = Profile::find($userToken->UserID);
        if ($put['Timezone'] != $profile->Timezone) {
			$profile->Timezone = $put['Timezone'];
		}
        $profile->save();
        
		return Response::make('', 204);
	}

    /**
     * API for PushGun server.
     * Authentication for both web and mobile client
     * Save PushGun socket session information to database in order to serve Siphon request later
     */
    public function postPushAuth () {
        if (Input::has('token')) {
            $token  = Input::get('token', '') ;
            $sockID = Input::get('socketId', '');
        } else {
            $token  = Input::json('token', '');
            $sockID = Input::json('socketId', '');
        }

        if (!empty($token)) {
            $user = (UserToken::checkAuth($token))?: UserApiToken::checkAuth($token);

            if ($user) {
                $uid = $user->UserID;
                $sessionType = isset($user->UserTokenID)? 'web' : 'mobile';

                //Save socket session information
                $PushIP  = Input::json('internalAddress', '');

                UploadInfo::add(array(
                    'UserID'       => $uid,
                    'SocketID'     => $sockID,
                    'PushServerIP' => $PushIP
                ));

                return Response::json(array('Success' => true, 'SessionType' => $sessionType), 200);
            }
        }

        return Response::json(array('Success' => false), 400);
    }

    /**
     * API for Siphon server.
     * Get socket session of user in PushGun server
     */
    public function getPushAuth () {
        $token  = Input::has('token') ? Input::get('token', '') : Input::json('token', '');

        if (!empty($token)) {
            $user = (UserToken::checkAuth($token))?: UserApiToken::checkAuth($token);

            if ($user) {
                $uid     = $user->UserID;
                $sessionType = isset($user->UserTokenID)? 'web' : 'mobile';

                if ($sessionType == 'web') {
                    $info = UploadInfo::find($uid);
                }

                return Response::json(array(
                    'Success'     => true,
                    'UserId'      => $uid,
                    'SocketId'    => !empty($info) ? $info->SocketID : '',
                    'PushIp'      => !empty($info) ? $info->PushServerIP : '',
                    'SessionType' => $sessionType
                ), 200);
            }
        }

        return Response::json(array('Success' => false), 400);
    }

    /**
     * API for Siphon server.
     * Get socket session of user in PushGun server
     * Transported from GET PushAuth to POST for security reason
     */
    public function postSiphonAuth () {
        $token  = Input::has('token') ? Input::get('token', '') : Input::json('token', '');

        if (!empty($token)) {
            $user = (UserToken::checkAuth($token))?: UserApiToken::checkAuth($token);

            if ($user) {
                $uid     = $user->UserID;
                $sessionType = isset($user->UserTokenID)? 'web' : 'mobile';

                if ($sessionType == 'web') {
                    $info = UploadInfo::find($uid);
                }

                return Response::json(array(
                    'Success'     => true,
                    'UserId'      => $uid,
                    'SocketId'    => !empty($info) ? $info->SocketID : '',
                    'PushIp'      => !empty($info) ? $info->PushServerIP : '',
                    'SessionType' => $sessionType
                ), 200);
            }
        }

        return Response::json(array('Success' => false), 400);
    }

	public function postCloneData()
	{
		// Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
		
		if ($userToken->UserID == 1) {
			$post = Input::all();
			$validator = Validator::make($post, array(
				'Master' => array('required', 'exists:User,UserID'),
				'Clone' => array('required', 'exists:User,UserID'),
			));
			
			if ($validator->fails()) {
				return Response::json(array('message' => $validator->messages()->all()), 500);
			}
			
			// Step 1: Clear all data of the clone account
			// Delete all receipts
			$oldReceiptIDs = DB::table('Receipt')
					->select('ReceiptID')
					->where('UserID', $post['Clone'])
					->lists('ReceiptID');
			
			if (count($oldReceiptIDs)) {
				File::deleteList(File::getAttachmentListOfReceipts($oldReceiptIDs));
				File::deleteList(File::getItemAttachmentListByReceipts($oldReceiptIDs));
				Receipt::deleteList($oldReceiptIDs);
				Tag::deleteIndexList($oldReceiptIDs, 'receipt');
				
				$oldItemIDs = Item::getItemIDsOfReceipts($oldReceiptIDs);
				if (count($oldItemIDs)) {
					Tag::deleteIndexList($oldItemIDs, 'receipt_item');
					Item::deleteItemTripRelationship($oldItemIDs);
				}
				
				CategoryAmount::updateAmountByReceipts($oldReceiptIDs, $post['Master']);
				Item::deleteListByReceipts($oldReceiptIDs);
			}
			
			$oldTripIDs = DB::table('Trip')
					->select('TripID')
					->where('UserID', $post['Clone'])
					->lists('TripID');
			
			if (count($oldTripIDs)) {
				$itemIDs = DB::table('TripItem')
						->select('TripItemID')
						->whereIn('TripID', $oldTripIDs)
						->lists('TripItemID');

				if (count($itemIDs)) {
					// Update category amount
					CategoryAmount::updateAmountByItemIDs($itemIDs, $userToken->UserID);

					// Update items to be uncategorized
					DB::table('Item')
							->whereIn('ItemID', $itemIDs)
							->update(array(
								'CategoryID' => 0,
								'ExpensePeriodFrom' => null
							));

					//Delete trip item relationship
					Item::deleteItemTripRelationship($itemIDs);
				}

				//Delete trips
				DB::table('Trip')
						->whereIn('TripID', $oldTripIDs)
						->delete();
			}
			
			$oldReportIDs = DB::table('Report')
					->select('ReportID')
					->where('Submitter', $post['Clone'])
					->lists('ReportID');
			
			if (count($oldReportIDs)) {
				//Delete attachments of the selected reports
				File::deleteList(File::getListByEntities($oldReportIDs, 'report', true));

				//Delete the report approver records
				ReportApprover::deleteList($oldReportIDs);

				//Delete the report trip relationships
				Report::removeTripRelationships($oldReportIDs);
				
				//Delete all report item memos
				ReportMemo::deleteByReports($oldReportIDs);

				//Delete the reports themselves
				Report::deleteList($oldReportIDs);
			}
			
			//Step 2: Clone data from master account to clone account
			//Get all receipts of the master account
			$receipts = Receipt::where('UserID', $post['Master'])->get();
			$itemMap = array();
			
			if (count($receipts)) {
				foreach ($receipts as $receipt) {
					//Clone each receipt
					$cloneReceipt = clone $receipt;
					unset($cloneReceipt->ReceiptID);
					$cloneReceipt->UserID = $post['Clone'];
					$cloneReceipt->exists = false;
					$cloneReceipt->save();
					
					//Clone each original receipt
					$originalReceipt = $receipt->ReceiptOriginal;
					if ($originalReceipt) {
						$cloneOriginalReceipt = clone $originalReceipt;
						$cloneOriginalReceipt->ReceiptID = $cloneReceipt->ReceiptID;
						$cloneOriginalReceipt->exists = false;
						$cloneOriginalReceipt->save();
					}
					
					//Clone receipt images and attachments
					$files = File::whereRaw(
							'EntityID = :ReceiptID AND Permanent > 0 AND (EntityName = "receipt" OR EntityName = "receipt_image")', 
							array(':ReceiptID' => $receipt->ReceiptID))->get();
					
					if (count($files)) {
						foreach ($files as $file) {
							//Clone file record in database
							$cloneFile = clone $file;
							unset($cloneFile->FileID);
							$cloneFile->EntityID = $cloneReceipt->ReceiptID;
							$cloneFile->FileName = 'c' . $post['Clone'] . '_' . $cloneFile->FileName;
							$cloneFile->FilePath = str_replace('/', '/c' . $post['Clone'] . '_', $cloneFile->FilePath);
							$cloneFile->exists = false;
							$cloneFile->save();
							
							//Clone the file physically
							$fileFullPath = Config::get('app.fileBasePath') . $file->FilePath;
							if (is_file($fileFullPath)) {
								$copy = copy($fileFullPath, Config::get('app.fileBasePath') . $cloneFile->FilePath);
								if (! $copy) {
									CLog::message('Cannot copy the file: ' . $fileFullPath);
								}
							}
						}
					}
					
					$items = Item::where('ReceiptID', $receipt->ReceiptID)->get();
					if (count($items)) {
						foreach ($items as $item) {
							//Clone each item
							$cloneItem = clone $item;
							unset($cloneItem->ItemID);
							$cloneItem->ReceiptID = $cloneReceipt->ReceiptID;
							$cloneItem->exists = false;
							$cloneItem->save();
							
							//Mapping old and cloned items
							$itemMap[$item->ItemID] = $cloneItem->ItemID;
							
							//Calculate amount per month
							if (! $item->IsJoined && $item->ExpensePeriodFrom) {
								CategoryAmount::updateAmount($post['Clone'], $cloneItem->CategoryID, $cloneItem->Amount, $cloneItem->ExpensePeriodFrom);
							}
							
							//Clone attachments
							$files = File::whereRaw(
									'EntityID = :ItemID AND Permanent > 0 AND EntityName = "receipt_item"', 
									array(':ItemID' => $item->ItemID))->get();

							if (count($files)) {
								foreach ($files as $file) {
									//Clone file record in database
									$cloneFile = clone $file;
									unset($cloneFile->FileID);
									$cloneFile->EntityID = $cloneItem->ItemID;
									$cloneFile->FileName = 'c_' . $cloneFile->FileName;
									$cloneFile->FilePath = str_replace('/', '/c_', $cloneFile->FilePath);
									$cloneFile->exists = false;
									$cloneFile->save();

									//Clone the file physically
									$fileFullPath = Config::get('app.fileBasePath') . $file->FilePath;
									if (is_file($fileFullPath)) {
										$copy = copy($fileFullPath, Config::get('app.fileBasePath') . $cloneFile->FilePath);
										if (! $copy) {
											CLog::message('Cannot copy the file: ' . $fileFullPath);
										}
									}
								}
							}
						}
					}
				}
			}
			
			$tripMap = array();
			//Get all trips of the master account
			$trips = Trip::where('UserID', $post['Master'])->get();
			if (count($trips)) {
				foreach ($trips as $trip) {
					//Clone each trip
					$cloneTrip = clone $trip;
					unset($cloneTrip->TripID);
					$cloneTrip->UserID = $post['Clone'];
					$cloneTrip->exists = false;
					$cloneTrip->save();
					
					$tripMap[$trip->TripID] = $cloneTrip->TripID;
					
					//Clone trip - item relationships
					$tripItemRelationships = DB::table('TripItem')->where('TripID', $trip->TripID)->get();
					if (count($tripItemRelationships)) {
						$insert = array();
						foreach ($tripItemRelationships as $relationship) {
							if (isset($itemMap[$relationship->TripItemID])) {
								$insert[] = array(
									'TripID' => $cloneTrip->TripID, 
									'TripItemID' => $itemMap[$relationship->TripItemID],
									'Claimed' => $relationship->Claimed,
									'Approved' => $relationship->Approved,
									'IsClaimed' => $relationship->IsClaimed,
									'IsApproved' => $relationship->IsApproved,
								);
							}
						}
						
						if (count($insert)) {
							DB::table('TripItem')
									->insert($insert);
						}
					}
					
				}
			}
			
			//Get all reports of the master account
			$reports = Report::where('Submitter', $post['Master'])->get();
			if (count($reports)) {
				foreach ($reports as $report) {
					//Clone each report
					$cloneReport = clone $report;
					unset($cloneReport->ReportID);
					$cloneReport->Submitter = $post['Clone'];
					$cloneReport->exists = false;
					$cloneReport->save();
					
					//Clone correspondent report approver
					$reportApprover = ReportApprover::where('ReportID', $report->ReportID)->first();
					if ($reportApprover) {
						$cloneReportApprover = clone $reportApprover;
						unset($cloneReportApprover->ReportApproverID);
						$cloneReportApprover->ReportID = $cloneReport->ReportID;
						$cloneReportApprover->exists = false;
						$cloneReportApprover->save();
					}
					
					//Clone report - trip relationships
					$reportTripRelationships = DB::table('ReportTrip')->where('ReportID', $report->ReportID)->get();
					if (count($reportTripRelationships)) {
						$insert = array();
						foreach ($reportTripRelationships as $relationship) {
							if (isset($tripMap[$relationship->TripID])) {
								$insert[] = array(
									'ReportID' => $cloneReport->ReportID,
									'TripID' => $tripMap[$relationship->TripID], 
									'Claimed' => $relationship->Claimed,
									'Approved' => $relationship->Approved,
									'IsClaimed' => $relationship->IsClaimed,
									'IsApproved' => $relationship->IsApproved,
									'CreatedTime' => $relationship->CreatedTime
								);
							}
							
						}
						
						if (count($insert)) {
							DB::table('ReportTrip')
									->insert($insert);
						}
					}
					
					
					//Clone report item memo
					$reportMemos = ReportMemo::where('ReportID', $report->ReportID)->get();
					if (count($reportMemos)) {
						foreach ($reportMemos as $memo) {
							if (isset($itemMap[$memo->ItemID])) {
								$cloneMemo = clone $memo;
								unset($cloneMemo->ReportMemoID);
								$cloneMemo->ReportID = $cloneReport->ReportID;
								$cloneMemo->ItemID = $itemMap[$memo->ItemID];
								$cloneMemo->UserID = $post['Clone'];
								$cloneMemo->exists = false;
								$cloneMemo->save();
							}
							
						}
					}
					
					//Clone attachments
					$files = File::whereRaw(
							'EntityID = :ReportID AND Permanent > 0 AND EntityName = "report"', 
							array(':ReportID' => $report->ReportID))->get();

					if (count($files)) {
						foreach ($files as $file) {
							//Clone file record in database
							$cloneFile = clone $file;
							unset($cloneFile->FileID);
							$cloneFile->EntityID = $cloneReport->ReportID;
							$cloneFile->FileName = 'c_' . $cloneFile->FileName;
							$cloneFile->FilePath = str_replace('/', '/c_', $cloneFile->FilePath);
							$cloneFile->exists = false;
							$cloneFile->save();

							//Clone the file physically
							$fileFullPath = Config::get('app.fileBasePath') . $file->FilePath;
							if (is_file($fileFullPath)) {
								$copy = copy($fileFullPath, Config::get('app.fileBasePath') . $cloneFile->FilePath);
								if (! $copy) {
									CLog::message('Cannot copy the file: ' . $fileFullPath);
								}
							}
						}
					}
				}
			}
		}
	}
	
	public function getIdFromMail()
	{
		return User::getUserIDFromEmail(Input::get('email', ''));
	}
	
	private function buildUserJsonResponse($user, $token = null)
	{
		//Initialize the object which will be returned
		$userJson = new stdClass();
					
		// FIXME: improve password encrypt algorithm
		$pass 	 = base64_encode($user->Password);		
		$uid 	 = uniqid();
		$prefix  = sha1($uid);		
		$suffix  = sha1($pass);		
		$newPass = $prefix.$pass.$suffix;
		
		//Generate an user token or update expiration time if the user already has a token record
		if (! $token) {
			if ($user->Token) {
				$userToken = $user->Token->generateToken();
			} else {
				$userToken = new UserToken();
				$userToken->generateToken($user->UserID);
			}
			
			//Add token to the returned object
			$token = $userToken->Token;
		}

		//Add profile info to the returned object
		if ($user->Profile) {
			$userJson = $user->Profile;
		} else {
			$userJson->FirstName = $userJson->LastName = null;
		}
		if($user->Settings){
			$userJson->TimezoneOffset = date('Z');
			$userJson->CurrencyCode = $user->Settings['CurrencyCode'];
			$userJson->AutoArchive = $user->Settings['AutoArchive'];
		}

		$userJson->UserID = $user->UserID;
		$userJson->Email = $user->Email;
		$userJson->Password = $newPass;
		$userJson->Token = $token;

        $country = Country::find($user->Profile->CountryName);

        $userJson->CountryCode = $country->CountryCode;
        $userJson->RegionCode  = $country->S3RegionCode;
        $userJson->BucketList  = S3Region::getUserBucketList(0, $country->S3RegionCode);
		$userJson->ExtraEmails = ExtraEmailSender::getExtraEmailList($user->UserID, true);

		return $userJson;
	}
	
	private function findIpAddress()
	{
		return getenv('HTTP_CLIENT_IP')?:
			getenv('HTTP_X_FORWARDED_FOR')?:
			getenv('HTTP_X_FORWARDED')?:
			getenv('HTTP_FORWARDED_FOR')?:
			getenv('HTTP_FORWARDED')?:
			getenv('REMOTE_ADDR');
	}
	public function getAutoArchive()
	{
		$userToken = UserToken::where('Token', '=', $_SERVER['HTTP_AUTH_TOKEN'])->first();
		//$profile = new Profile();
		$geoInfo = DB::table('Settings')
						->select('AutoArchive')
						->where('UserID', $userToken->UserID)
						->get();
		//$geoInfo = Profile::getAutoArchive($userToken->UserID);
		//var_dump($user->UserID);
		//var_dump($geoInfo);die;
		
		return Response::json(array('autoArchive' => $geoInfo[0]->AutoArchive));
		//return Response::json(array('autoArchive' => array('a'=>$userToken->UserID)));
	}
    public function putVerifyPassword()
	{
        $post = Input::all();
        $userToken = UserToken::where('Token', '=', $_SERVER['HTTP_AUTH_TOKEN'])->first();
        $user = User::find($userToken->UserID);
        $profile = Profile::find($user->UserID);
        
		//Build the validator for $post
		$validator = Validator::make(
			$post, 
			array(
				'Password' => array('required', 'min:6', 'password_matched:' . $user->Password),
				'PasswordConfirm' => array('required', 'same:Password'),
			), 
			array(
				'required' => 'Please enter your :attribute.',
				'PasswordConfirm.required' => 'Please confirm your password.',
				'Password.min' => 'Your password must consist at least :min characters.',
				'PasswordConfirm.same' => '"Confirm password" and "Password" do not match',
                "Password.password_matched" => "Your input for current password is incorrect.",
			)
		);
		
        
        //Check if the validator fails
		if ($validator->fails()) {
			$messages = $validator->messages();
            $message = $messages->all();
            return Response::json(array('message' => $message[0]), 500);
			
		}
       
        return Response::make('', 204);
	}
}