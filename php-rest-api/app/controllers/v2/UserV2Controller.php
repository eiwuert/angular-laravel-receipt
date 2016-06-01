<?php
/**
 * Controller for user interactions with the system
 */
class UserV2Controller extends BaseV2Controller 
{
    /**
	 * Register new user
	 */
	public function postRegister()
	{

		$post = Input::all();
        $post['CurrencyCode'] = 'USD';
        
        $messages = $this->validateModel($post);

        if (count($messages)) {
            $jsend = JSend\JSendResponse::fail($messages);
            return $jsend->respond();
        }

		//If the validator passes, we created a new user
		$user = new User();
		$user->Username = $user->Email = $post['Email'];
		$user->Password = Hash::make($post['Password']);
		$user->CreatedTime = $_SERVER['REQUEST_TIME'];
		$user->Status = 0; // default status is 0, user must activate their account via email
		$user->save();
		
		//A new profile for the new user
		$profile = new Profile();
		$profile->UserID = $user->UserID;
		$profile->FirstName = $post['FirstName'];
		$profile->LastName = $post['LastName'];
        $profile->Timezone = $post['Timezone'];
		$profile->save();
		
		//A new settings for the new user
		$setting = new Settings();
		$setting->UserID = $user->UserID;
		$setting->CurrencyCode = 'USD';
        $setting->Timezone = $post['Timezone'];
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
		
		$jsend = JSend\JSendResponse::success();
        return $jsend->respond();
	}
    
    protected function validateModel($inputs, $user = null) {
        $rules = array(
            'FirstName' => array('required'),
            'LastName' => array('required'),
            'Email' => array('required', 'email', 'unique:User'),
            'Password' => array('required', 'min:6'),
            'PasswordConfirm' => array('required', 'same:Password'),
            'CurrencyCode' => array('required', 'size:3'),
        );
        
        if($user != null) {
            foreach ($rules as $key => $value) {
                if(!isset($inputs[$key])) {
                    unset($rules[$key]);
                }
            }
        }
        
        $message = array(
            'required' => 'Please enter your :attribute.',
            'PasswordConfirm.required' => 'Please enter your PasswordConfirm.',
            'Password.min' => 'Your password must consist at least :min characters.',
            'Email' => 'Your email address is invalid.',
            'Email.unique' => 'The e-mail address is already registered',
            'PasswordConfirm.same' => 'Please re-enter your confirmation password. It does not match with your first entry.',
            'CurrencyCode.required' => 'Please enter your Home Currency.',
        );
        
        $validator = Validator::make($inputs, $rules,$message);
        
        if ($validator->fails()) {
            return array('message' => $validator->messages()->all());
        }
        
        return array();
    }
    
    /**
	 * Update profile user
	 */
    public function putProfile()
	{
        $userToken = UserApiToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN']);
        
        if (empty($_SERVER['HTTP_AUTH_TOKEN']) || !$userToken) {
            $jsend = JSend\JSendResponse::error('The authentication is failed.', 401);
            return $jsend->respond();
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
        
        $messages = $this->validateUpdate($put, $user);

        if (count($messages)) {
            $jsend = JSend\JSendResponse::fail($messages);
            return $jsend->respond();
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
			
			if (!empty($put['FirstName']) && ($put['FirstName'] != $profile->FirstName)) {
				$profile->FirstName = $put['FirstName'];
			}
			
			if (!empty($put['LastName']) && ($put['LastName'] != $profile->LastName)) {
				$profile->LastName = $put['LastName'];
			}
			
			if (!empty($put['Email']) && ($put['Email'] != $user->Email)) {
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
			
			if (isset($put['Gender']) && ! empty($put['Gender'])) {
				$profile->Gender = $put['Gender'];
			}
			if (isset($put['CurrencyCode']) && ! empty($put['CurrencyCode'])) {
				$profile->CurrencyCode = $put['CurrencyCode'];
			}
			if (isset($put['MaritalStatus']) && ! empty($put['MaritalStatus'])) {
				$profile->MaritalStatus = $put['MaritalStatus'];
			}
			
			if (isset($put['BirthDate']) && ! empty($put['BirthDate'])) {
				$profile->BirthDate = $put['BirthDate'];
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
		
		$jsend = JSend\JSendResponse::success();
        return $jsend->respond();
    }
    
    protected function validateUpdate($put, $user) {
        //Build the validator for inputs
		if (empty($put['IsResetPassword'])) {
			$rules = array(
				'FirstName' => array('required'),
				'LastName' => array('required'),
				'Email' => array('required', 'email', 'profile_email:' . $user->UserID),
				'ExtraEmails' => array('extra_emails:' . $user->UserID),
				'Password' => array('required_with:NewPassword', 'password_matched:' . (isset($user->Password) ? $user->Password : null)),
				'NewPassword' => array('min:6'),
				'NewPasswordConfirm' => array('required_with:NewPassword', 'same:NewPassword'),
				//'Password' => array('password_matched:' . (isset($user->Password) ? $user->Password : null)),
				'CompanyName' => array('max:255'),
				'CompanyAddress' => array('max:255'),
				'AddressFirst' => array('max:255'),
				'AddressSecond' => array('max:255'),
				'MaritalStatus' => array('in:0,1'),
				'Gender' => array('in:0,1,2'),
				'BirthDate' => array('date'),
			);
            
            $rulesRequired = array(
				'FirstName' => array('required'),
				'LastName' => array('required'),
				'Email' => array('required', 'email', 'profile_email:' . $user->UserID),
            );
            
            foreach ($rulesRequired as $key => $value) {
                if(!isset($inputs[$key])) {
                    unset($rules[$key]);
                }
            }
		} else {
			$rules = array(
				'NewPassword' => array('required', 'min:6'),
				'NewPasswordConfirm' => array('required', 'same:NewPassword'),
			);
		}
        
        $message = array(
            'required' => 'Please enter your :attribute.',
            'Password.required_with' => 'Please enter your current password.',
            'NewPasswordConfirm.required_with' => 'Please confirm your new password.',
            'NewPassword.min' => 'Your password must consist at least :min characters.',
            'NewPasswordConfirm.same' => 'Please re-enter your confirmation password. It does not match with your first entry.',
        );
		
		$validator = Validator::make($put, $rules, $message);

		if ($validator->fails()) {
            return array('message' => $validator->messages()->all());
        }
        
        return array();
    }
    
    /**
	 * Request new password method
	 */
	public function postRequestPassword()
	{
        $input = Input::all();
        
        $messages = $this->validateRequestPassword($input);

        if (count($messages)) {
            $jsend = JSend\JSendResponse::fail($messages);
            return $jsend->respond();
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
		
		$jsend = JSend\JSendResponse::success();
        return $jsend->respond();
	}
    
    protected function validateRequestPassword($input) {
        $rules = array('Email' => array('required', 'exists:User'));
        $message = array(
            'Email' => 'Invalid email address.',
            'Email.exists' => 'The email address entered is not registered with RC, please try again.'
        );

		$validator = Validator::make($input, $rules, $message);

		if ($validator->fails()) {
            return array('message' => $validator->messages()->all());
        }
        
        return array();
    }
}
