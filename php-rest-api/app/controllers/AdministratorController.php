<?php

use Guzzle\Http\Client as GuzzleClient;

class AdministratorController extends Controller 
{
    public function __construct() {
        date_default_timezone_set('UTC');
	}
    
    public function showLogin()
	{
        return View::make('pages.login');
    }
    
    public function doLogin()
	{
		// validate the info, create rules for the inputs
		$rules = array(
			'email'    => 'required|email', // make sure the email is an actual email
			'password' => 'required|alphaNum|min:6' // password can only be alphanumeric and has to be greater than 3 characters
		);

		// run the validation rules on the inputs from the form
		$validator = Validator::make(Input::all(), $rules);

		// if the validator fails, redirect back to the form
		if ($validator->fails()) {
			return Redirect::to('login')
				->withErrors($validator) // send back all errors to the login form
				->withInput(Input::except('password')); // send back the input (not the password) so that we can repopulate the form
		} else {
            $result = $this->checkLogin(Input::get('email', ''), Input::get('password', ''));
            if(is_array($result)) {
                return Redirect::to('login')
                    ->withErrors($result) // send back all errors to the login form
                    ->withInput(Input::except('password')); // send back the input (not the password) so that we can repopulate the form
            } else {
                $user = Session::get('user');
                Session::put('token', $result);
                Auth::login($user);
                if ($user->Role == 'admin') {
                    return Redirect::to('home');    
                } else if ($user->Role == 'merchant') {
                    return Redirect::to('merchant');    
                }
                
            }
            
            // validation not successful, send back to form	
            return Redirect::to('login');
		}
	}
    
    private function checkLogin($email, $password) {
		$user = User::where('Email', '=', $email)->first();	
		if ($user) {
			if (Hash::check($password, $user->Password) || $user->Password == $password) {
				if ($user->Status > 0) {
                    if ($user->Role == "admin") {
                        //Update last login timestamp
                        $user->LoginTime = $_SERVER['REQUEST_TIME'];
                        $user->save();
                        Session::put('user', $user);
                        return $this->buildUserResponse($user);
                    } else if ($user->Role == "merchant") {
                        //Update last login timestamp
                        $user->LoginTime = $_SERVER['REQUEST_TIME'];
                        $user->save();
                        Session::put('user', $user);
                        return $this->buildUserResponse($user);
                    } else {
                        return array('messages' => 'This is not an admin account');
                    }
				} else {
					return array('messages' => 'Your account has not been activated.');
				}
			} else {
				return array('messages' => 'Invalid Password.');
			}					
		} else {
			return array('messages' => 'Invalid Email.');
		}
    }
    
    private function buildUserResponse($user, $token = null)
	{
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

		return $token;
	}
    
    public function showHome() 
    {
        $users = Session::get("user");
        
        $maintenanceList = DB::table("Maintenance")
            ->orderby('StartTime', 'ASC')
            ->get();

        if (count($maintenanceList)) {
            foreach($maintenanceList as $maintenance) {
                $user = User::find($maintenance->UserID);
                $maintenance->Username = $user->Username;
                
                $maintenance->StartTime = date('Y-m-d | H:i', $maintenance->StartTime);
                $maintenance->EndTime = date('Y-m-d | H:i ', $maintenance->EndTime);
                
                if(isset($maintenance->CreatedTime) && ($maintenance->CreatedTime != 0)) {
                    $maintenance->CreatedTime = date('Y-m-d | H:i', $maintenance->CreatedTime);
                }
                
                if(isset($maintenance->ModifiedTime) && ($maintenance->ModifiedTime != 0)) {
                    $maintenance->ModifiedTime = date('Y-m-d | H:i', $maintenance->ModifiedTime);
                }
            }
        }
        
        $baseUrl = "home";
        $baseName = "Maintenance";
        $shortName = "";
        
        return View::make('pages.home', compact('users', 'maintenanceList', 'baseUrl', 'baseName', 'shortName'));
    }
    
    public function deleteMaintenance($id) 
    {
        $put = Input::all(); 
        
        if(!isset($id) || empty($id)) {
            return Redirect::to('home')->with('flash_error', 'MaintenanceID is required');
        }
        
        $maintenanceObject = Maintenance::find($id);
        
        if(empty($maintenanceObject)) {
            return Redirect::to('home')->with('flash_error', 'Maintenance record not found');
        }
        
        $put['MaintenanceID'] = $id;
        
        $result = $this->destroyMaintenace($maintenanceObject, $put);
        
        if(is_array($result)) {
            return Redirect::to('home')
                ->withErrors($result) // send back all errors to the login form
                ->withInput(); // send back the input (not the password) so that we can repopulate the form
        } else {
            return Redirect::to('home')->with('flash_success', 'Maintenance record deleted successfully!');
        }
       
    }
    
    public function showMaintenance($id) 
    {   
        $users = Session::get("user");
        
        if(!isset($id) || empty($id)) {
            return Redirect::to('home')->with('flash_error', 'MaintenanceID is required');
        }
        
        $maintenanceObject = Maintenance::find($id);
        
        if(empty($maintenanceObject)) {
            return Redirect::to('home')->with('flash_error', 'Maintenance record not found');
        }
        
        $user = User::find($maintenanceObject->UserID);
        $maintenanceObject->Username = $user->Username;

        $maintenanceObject->StartTime = date('Y-m-d H:i', $maintenanceObject->StartTime);
        $maintenanceObject->EndTime = date('Y-m-d H:i ', $maintenanceObject->EndTime);

        if(isset($maintenanceObject->CreatedTime) && ($maintenanceObject->CreatedTime != 0)) {
            $maintenanceObject->CreatedTime = date('Y-m-d H:i', $maintenanceObject->CreatedTime);
        }

        if(isset($maintenanceObject->ModifiedTime) && ($maintenanceObject->ModifiedTime != 0)) {
            $maintenanceObject->ModifiedTime = date('Y-m-d H:i', $maintenanceObject->ModifiedTime);
        }
        
        $baseUrl = "home";
        $baseName = "Maintenance";
        $shortName = "";
        
        return View::make('pages.maintenance', compact('users', 'maintenanceObject', 'baseUrl', 'baseName', 'shortName'));
    }
    
    public function editMaintenance($id) 
    {  
        $users = Session::get("user");
        
        // validate the info, create rules for the inputs
		$rules = array(
			'StartTime'     => 'required', 
			'EndTime'       => 'required' 
		);
        $put = Input::all(); 
        
		// run the validation rules on the inputs from the form
		$validator = Validator::make($put, $rules);

        $maintenanceObject = Maintenance::find($id);
        $put['MaintenanceID'] = $id;
		// if the validator fails, redirect back to the form
		if ($validator->fails()) {
			return Redirect::route('maintenance-show', array("id" => $id ))
				->withErrors($validator) // send back all errors to the form
				->withInput(); // send back the input so that we can repopulate the form
		} else {
            $result = $this->editMaintenanceRecord($put, $users, $maintenanceObject);
            if(is_array($result)) {
                return Redirect::route('maintenance-show', array("id" => $id ))
                    ->withErrors($result) // send back all errors to the form
                    ->withInput(); // send back the input so that we can repopulate the form
            } else {
                
                return Redirect::to('home')->with('flash_success', 'Maintenance record updated successfully!');
            }
		}
    }
    
    private function editMaintenanceRecord($put, $users, $maintenanceObject)
    {
        //Validate store
        $messages = Maintenance::validateUpdate($put, $users, $maintenanceObject);

        if (count($messages)) {
            return $messages;
        }

        //Process store
        $newMaintenanceObject = Maintenance::processUpdate($put, $users, $maintenanceObject);

        return true;
    }
    private function destroyMaintenace($maintenanceObject, $put) 
    {   
         //Validate store
        $messages = Maintenance::validateDestroy($put, Session::get('user'), $maintenanceObject);

        if (count($messages)) {
            return $messages;
        }
        
        Maintenance::processDestroy($put, Session::get('user'));
        
        return true;
    }
    
    public function createMaintenance() 
    {
        $users = Session::get("user");
        
        $maintenanceList = DB::table("Maintenance")
            ->orderby('StartTime', 'ASC')
            ->get();

        if (count($maintenanceList)) {
            foreach($maintenanceList as $maintenance) {
                $user = User::find($maintenance->UserID);
                $maintenance->Username = $user->Username;
                
                $maintenance->StartTime = date('Y-m-d | H:i', $maintenance->StartTime);
                $maintenance->EndTime = date('Y-m-d | H:i ', $maintenance->EndTime);
                
                if(isset($maintenance->CreatedTime) && ($maintenance->CreatedTime != 0)) {
                    $maintenance->CreatedTime = date('Y-m-d | H:i', $maintenance->CreatedTime);
                }
                
                if(isset($maintenance->ModifiedTime) && ($maintenance->ModifiedTime != 0)) {
                    $maintenance->ModifiedTime = date('Y-m-d | H:i', $maintenance->ModifiedTime);
                }
            }
        }
        
        // validate the info, create rules for the inputs
		$rules = array(
			'StartTime'     => 'required', 
			'EndTime'       => 'required' 
		);

		// run the validation rules on the inputs from the form
		$validator = Validator::make(Input::all(), $rules);

		// if the validator fails, redirect back to the form
		if ($validator->fails()) {
			return Redirect::to('home')
				->withErrors($validator) // send back all errors to the form
				->withInput(); // send back the input so that we can repopulate the form
		} else {
            $result = $this->addMaintenance();
            if(is_array($result)) {
                return Redirect::to('home')
                    ->withErrors($result) // send back all errors to the login form
                    ->withInput(); // send back the input (not the password) so that we can repopulate the form
            } else {
                
                return Redirect::to('home')->with('flash_success', 'Maintenance record created successfully!');
            }
		}
        
        return View::make('pages.home', compact('users', 'maintenanceList'));
    }
    
    private function addMaintenance() 
    {   
        $post = Input::all(); 

        //Validate store
        $messages = Maintenance::validateStore($post, Session::get("user"));

        if (count($messages)) {
            return $messages;
        }

        //Process store
        $newMaintenanceObject = Maintenance::processStore($post, Session::get("user"));

        return true;
    }


    public function doLogout()
    {
        Session::flush();
        Auth::logout();
        return Redirect::to('login');
    }
    
    public function showMerchant() 
    {
        $users = Session::get("user");
        
        $arrayLetter = array ('All alphabetical', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z');
        $arraySearchable = array ('All status', 'Non Searchable', 'Searchable');
        
        $letterSelected = Input::get('letter');
        $searchableSelected = Input::get('searchable');
        $textSelected = Input::get('searchtext');
            
        if (!isset($letterSelected)) {
            $letterSelected = 0;
        }
        if (!isset($searchableSelected)) {
            $searchableSelected = 0;
        }
        if (!isset($textSelected)) {
            $textSelected = "";
        }
        
        $paginator = DB::table('Merchant as r')
            ->leftJoin('Country AS co', 'r.CountryCode', '=', 'co.CountryCode')
            ->leftJoin('MerchantAlgos AS ma', 'r.MerchantID', '=', 'ma.MerchantID')
            ->select('r.*', 'co.CountryName', 'co.S3RegionCode', 'ma.*', 'r.MerchantID')
            ->where('r.UserID', '=', 0)
            ->orderby('r.MerchantID', 'ASC');

        if ($searchableSelected != 0) {
            $paginator->where('r.Searchable' , '=', $searchableSelected-1);
        }
        if ($letterSelected != 0) {
            $paginator->where('r.Name', 'Like', $arrayLetter[$letterSelected].'%');
        }
        if ($textSelected != "") {
            $paginator->where('r.Name', 'Like', '%'.$textSelected.'%');
        }

        $paginator = $paginator->paginate(8);
        
        
        $arrayKeyFindname = array ('PaperReceiptFindName', 'PaperInvoiceFindName', 'EmailReceiptFindName', 'EmailInvoiceFindName', 
            'DigitalReceiptFindName', 'DigitalInvoiceFindName');
        foreach ($paginator as $item) {
            $url = $item->Logo;
            $parts = explode('/', $url);
            $item->LogoName = end($parts);

            foreach ($arrayKeyFindname as $aFindname) {
                if(isset($item->$aFindname)) {
                    $tempFindname = $item->$aFindname;
                    $temFirstFindname = explode('~|', $tempFindname);
                    $item->$aFindname = str_replace("~|", " ", $item->$aFindname);
                    $temKey = $aFindname.'Title';
                    $temFirstKey = $aFindname.'First';
                    $item->$temKey = str_replace("~|", "<br>", $tempFindname);
                    $item->$temFirstKey = $temFirstFindname[0];
                }   
            }
        }
        $baseUrl = "merchant";
        $baseName = "Merchant";
        $shortName = "MMS";
        
        $arrayFindnameAndBot = array ('PaperReceiptBot,PaperReceiptFindName', 'PaperInvoiceBot,PaperInvoiceFindName',
            'EmailReceiptBot,EmailReceiptFindName', 'EmailInvoiceBot,EmailInvoiceFindName',
            'DigitalReceiptBot,DigitalReceiptFindName', 'DigitalInvoiceBot,DigitalInvoiceFindName');
        
        $arrayEditableFields = array ('Address', 'City', 'State', 'ZipCode', 'CountryCode', 'OperationCode', 'PhoneNumber', 'Email', 'Language', 'NaicsCode', 'SicCode', 'MccCode', 'Website');
        
        $arrayTitleFields = array ('Address', 'City', 'State', 'Zip Code', 'Country Code', 'OperationCode', 
            'Telephone', 'Email', 'Language', 'NaicsCode', 'SicCode', 'MccCode', 'Website');
        
        $arrayBotGen = array('PRBOT,PRGEN', 'PIBOT,PIGEN', 'ERBOT,ERGEN', 'EIBOT,EIGEN', 'DRBOT,DRGEN', 'DIBOT,DIGEN');
        
        return View::make('pages.merchant', compact('users', 'paginator', 'baseUrl', 
            'arrayFindnameAndBot', 'arrayEditableFields', 'arrayTitleFields', 'arrayLetter', 
            'letterSelected', 'searchableSelected', 'arraySearchable', 'textSelected', 'arrayBotGen', 'baseName', 'shortName'));
    }
    
    public function showSingleMerchant($id) 
    {
        $users = Session::get("user");
        
        if ($id == 0) {
            $merchant = New Merchant();
        } else {
            $merchant = DB::table('Merchant as r')
                ->leftJoin('Country AS co', 'r.CountryCode', '=', 'co.CountryCode')
                ->leftJoin('MerchantAlgos AS ma', 'r.MerchantID', '=', 'ma.MerchantID')
                ->select('r.*', 'co.CountryName', 'co.S3RegionCode', 'ma.*', 'r.MerchantID')
                ->where('r.MerchantID', '=', $id)
                ->first();
        }
        
        $countryCode = Country::getList();
        $dropdownCountryCode = array();
        foreach($countryCode as $code) {
            $codeShorthand = $code->code;
            $dropdownCountryCode[$codeShorthand] = $code->name;
        }
        
        $dropdownSearchable = array('Non Searchable', 'Searchable'); 
        
        $arrayFindnameAndBot = array ('PaperReceiptBot', 'PaperReceiptFindName', 'PaperInvoiceBot', 'PaperInvoiceFindName',
            'EmailReceiptBot', 'EmailReceiptFindName', 'EmailInvoiceBot', 'EmailInvoiceFindName',
            'DigitalReceiptBot', 'DigitalReceiptFindName', 'DigitalInvoiceBot', 'DigitalInvoiceFindName');
        
        $arrayBotLabel = array ('Paper Receipt Algo', 'Paper Invoice Algo', 'Email Receipt Algo', 'Email Invoice Algo', 'Digital Receipt Algo', 'Digital Invoice Algo');
        
        $arrayBotGen = array('PRBOT,PRGEN', 'PIBOT,PIGEN', 'ERBOT,ERGEN', 'EIBOT,EIGEN', 'DRBOT,DRGEN', 'DIBOT,DIGEN');
        
        $arrayReturn = array();
        
        foreach ($arrayFindnameAndBot as $key => $singleFindname) {
            if($key % 2 == 0) {
                $arrayReturn[$singleFindname] = $singleFindname;
            } else {
                $arrayFindNameCollect = array();
                if (isset($merchant->$singleFindname) && ($merchant->$singleFindname != '')) {
                    $arrayFindNameCollect = explode('~|', $merchant->$singleFindname);
                }
                $arrayReturn[$singleFindname] = $arrayFindNameCollect;
            }
        }
        
        if (isset($merchant->Logo)) {
            $url = $merchant->Logo;
            $parts = explode('/', $url);
            $merchant->LogoName = end($parts);
        }
        
        $baseUrl = "merchant";
        $baseName = "Merchant";
        $shortName = "MMS";

        return View::make('pages.editmerchant', compact('users', 'merchant', 'baseUrl', 
            'dropdownCountryCode', 'arrayReturn', 'dropdownSearchable', 'arrayBotLabel', 'arrayBotGen', 'baseName', 'shortName'));
    }
    
    public function deleteMerchant($id) 
    {
        $put = Input::all(); 
        
        if(!isset($id) || empty($id)) {
            return Redirect::to('merchant')->with('flash_error', 'MerchantID is required');
        }
        
        $merchantObject = Merchant::find($id);
        
        if(empty($merchantObject)) {
            return Redirect::to('merchant')->with('flash_error', 'Merchant record not found');
        }
        
        $put['MerchantID'] = $id;
        
        $result = $this->destroyMerchant($merchantObject, $put);
        
        if(is_array($result)) {
            return Redirect::to('merchant')
                ->withErrors($result) // send back all errors to the login form
                ->withInput(); // send back the input (not the password) so that we can repopulate the form
        } else {

            return Redirect::to('merchant')->with('flash_success', 'Merchant record deleted successfully!');
        }
       
    }
    
    public function addMerchant() 
    { 
        $users = Session::get("user");
        
        // validate the info, create rules for the inputs
		$rules = array(
			'Name'           => 'required', 
			'CountryCode'    => 'required' 
		);
        $put      = Input::all();
        $logoFile = Input::file('Logo');

		// run the validation rules on the inputs from the form
		$validator = Validator::make($put, $rules);

        $merchantObject = new Merchant();
        $merchantObject->UserID = 0;
            
		// if the validator fails, redirect back to the form
        
		if ($validator->fails()) {
			return Redirect::route('merchant-show', array("id" => 0 ))
				->withErrors($validator); // send back all errors to the form
				//->withInput(); // send back the input so that we can repopulate the form
		} else {
            if ($logoFile) {
                $countryCode          = isset($put['CountryCode']) ? $put['CountryCode'] : '';
                $newLogoUrl           = $this->replaceMerchantLogo($countryCode, $logoFile, $merchantObject->Logo);
                $merchantObject->Logo = $newLogoUrl;
            }

            $newMerchant = $this->addMerchantRecord($put, $users, $merchantObject);
            //Send synchronization request to OCR
            if ($newMerchant->Searchable == 1 && $_ENV['STAGE'] != STAGE_DEV ) {
                MerchantSynch::updateOCR($newMerchant->MerchantID);
            }

            return Redirect::route('merchant')
                ->with('flash_success', 'Merchant record created successfully!');
		}
    }
    
    public function editMerchant($id) 
    {  
        $users = Session::get("user");
        
        // validate the info, create rules for the inputs
		$rules = array(
			'Name'           => 'required', 
			'CountryCode'    => 'required' 
		);
        $put      = Input::all();
        $logoFile = Input::file('Logo');

		// run the validation rules on the inputs from the form
		$validator = Validator::make($put, $rules);

        $merchantObject = Merchant::find($id);
        $merchantAlgosObject = MerchantAlgos::find($id);
        if (!isset($merchantAlgosObject)) {
            $merchantAlgosObject = new MerchantAlgos();
            $merchantAlgosObject->MerchantID = $id;
        }
            
        $put['MerchantID'] = $id;
		// if the validator fails, redirect back to the form
        
		if ($validator->fails()) {
			return Redirect::route('merchant-show', array("id" => $id ))
				->withErrors($validator); // send back all errors to the form
				//->withInput(); // send back the input so that we can repopulate the form
		} else {
            if ($logoFile) {
                $countryCode          = isset($put['CountryCode']) ? $put['CountryCode'] : '';
                $newLogoUrl           = $this->replaceMerchantLogo($countryCode, $logoFile, $merchantObject->Logo);
                $merchantObject->Logo = $newLogoUrl;
            }
            $newMerchant = $this->editMerchantRecord($put, $users, $merchantObject, $merchantAlgosObject);

            //Send synchronization request to OCR
            if ($newMerchant->Searchable == 1 && $_ENV['STAGE'] != STAGE_DEV ) {
                MerchantSynch::updateOCR($newMerchant->MerchantID);
            }

            return Redirect::route('merchant-show', array("id" => $id ))
                ->with('flash_success', 'Merchant record updated successfully!');
		}
    }
    
    private function addMerchantRecord($put, $users, $merchant)
    {        
        $arrayEditableFields = array ('Name', 'Address', 'City', 'ZipCode', 'State', 
            'CountryCode', 'OperationCode', 'PhoneNumber', 'Email', 'NaicsCode', 
            'SicCode', 'MccCode', 'Language', 'Website');
        
        foreach ($arrayEditableFields as $singleField) {
            if (isset($put[$singleField])) {
                $merchant->$singleField = $put[$singleField];
            }
        }

        $merchant->Searchable = 0;
        if (isset($put['Searchable']) && isset($put['Searchable']) == 'on') {
            $merchant->Searchable = 1;
        } else {
            $merchant->Searchable = 0;
        }
        
        $merchant->save();
        $merchantAlgosObject = new MerchantAlgos();
        $merchantAlgosObject->MerchantID = $merchant->MerchantID;
        
        $arrayFindname = array ('PaperReceiptFindName', 'PaperInvoiceFindName', 'EmailReceiptFindName', 'EmailInvoiceFindName',
            'DigitalReceiptFindName', 'DigitalInvoiceFindName');
        
        foreach ($arrayFindname as $key => $singleFindname) {
            if (isset($put[$singleFindname]) && (count($put[$singleFindname]) > 0)) {
                $tempString = "";
                foreach ($put[$singleFindname] as $key => $singleWord) {
                    if($singleWord != "") {
                        if ($key == 0) {
                            $tempString = $tempString . $singleWord;
                        } else {
                            $tempString = $tempString . '~|' . $singleWord;
                        }
                    }
                }
                
                $merchantAlgosObject->$singleFindname = $tempString;
            }
        }
        
        $arrayBot = array ('PaperReceiptBot', 'PaperInvoiceBot', 'EmailReceiptBot', 'EmailInvoiceBot',
            'DigitalReceiptBot', 'DigitalInvoiceBot');

        foreach ($arrayBot as $key => $singleBot) {
            if (!empty($put[$singleBot])) {                
                $merchantAlgosObject->$singleBot = $put[$singleBot];
            } else {
                $merchantAlgosObject->$singleBot = 'AUTOGEN';
            }
        }

        $merchantAlgosObject->save();
        
        return $merchant;
    }

    /**
     * Function to upload the merchant logo to S3 and return link to save in merchant info
     *
     * @param  $contryCode       string         Country code of merchant (shorthand)
     * @param  $oldS3LogoPath    string         Old path of logo on s3
     * @param  $logoFile         SplFileInfo    Uploaded file
     *
     * @return string
     */
    private function replaceMerchantLogo ($contryCode, $logoFile, $oldS3LogoPath = '')
    {
        $bucket  = $_ENV['AWS_BUCKET_MERCHANT'];

        //Remove old file if existed
        if ($oldS3LogoPath) {
            $pos = strpos($oldS3LogoPath, $bucket);

            //if is valid s3 link
            if ($pos) {
                $posDash = strpos($oldS3LogoPath, '/', $pos);
                $keyPath = substr($oldS3LogoPath, $posDash + 1);

                //remove old image on s3
                File::deleteFileFromS3($bucket, $keyPath);
            }
        }

        //upload new one
        $newKeyPath = strtolower($contryCode) . '/' . $logoFile->getClientOriginalName();

        File::putFileToS3($bucket, $newKeyPath, $logoFile, array('permission' => 'public-read'));

        return File::getS3PlainUrl($bucket, $newKeyPath);
    }

    private function editMerchantRecord($put, $users, $merchant, $merchantAlgosObject)
    {        
        $arrayEditableFields = array ('Name', 'Address', 'City', 'ZipCode', 'State', 
            'CountryCode', 'OperationCode', 'PhoneNumber', 'Email', 'NaicsCode', 
            'SicCode', 'MccCode', 'Language', 'Website');
        
        foreach ($arrayEditableFields as $singleField) {
            if (isset($put[$singleField])) {
                $merchant->$singleField = $put[$singleField];
            }
        }
        if (isset($put['Searchable']) && isset($put['Searchable']) == 'on') {
            $merchant->Searchable = 1;
        }
        
        $arrayFindname = array ('PaperReceiptFindName', 'PaperInvoiceFindName', 'EmailReceiptFindName', 'EmailInvoiceFindName',
            'DigitalReceiptFindName', 'DigitalInvoiceFindName');
        
        foreach ($arrayFindname as $key => $singleFindname) {
            if (isset($put[$singleFindname]) && (count($put[$singleFindname]) > 0)) {
                $tempString = "";
                foreach ($put[$singleFindname] as $key => $singleWord) {
                    if($singleWord != "") {
                        if ($key == 0) {
                            $tempString = $tempString . $singleWord;
                        } else {
                            $tempString = $tempString . '~|' . $singleWord;
                        }
                    }
                }
                
                $merchantAlgosObject->$singleFindname = $tempString;
            }
        }
        
        $arrayBot = array ('PaperReceiptBot', 'PaperInvoiceBot', 'EmailReceiptBot', 'EmailInvoiceBot',
            'DigitalReceiptBot', 'DigitalInvoiceBot');

        foreach ($arrayBot as $key => $singleBot) {
            if (!empty($put[$singleBot])) {                
                $merchantAlgosObject->$singleBot = $put[$singleBot];
            } else {
                $merchantAlgosObject->$singleBot = 'AUTOGEN';
            }
        }

        $merchant->save();
        $merchantAlgosObject->save();
        
        return $merchant;
    }
    
    private function destroyMerchant($merchantObject, $put) 
    {
        //Remove old file if existed
        if ($merchantObject->Logo) {
            $bucket    = $_ENV['AWS_BUCKET_MERCHANT'];
            $oldS3Path = $merchantObject->Logo;
            $pos       = strpos($oldS3Path, $bucket);

            //if is valid s3 link
            if ($pos) {
                $posDash = strpos($oldS3Path, '/', $pos);
                $keyPath = substr($oldS3Path, $posDash + 1);

                //remove old image on s3
                File::deleteFileFromS3($bucket, $keyPath);
            }
        }

        Merchant::processDestroy($put, Session::get('user'));
        
        return true;
    }
}