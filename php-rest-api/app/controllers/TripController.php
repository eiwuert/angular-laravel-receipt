<?php
/**
 * Controller for trips
 */
class TripController extends BaseController 
{
	
	public function getIndex()
	{
		// Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
		
		$tripID = Input::get('tripID', '');
		if (empty($tripID)) {
			$dropdown = Input::get('dropdown', '');
			if ($dropdown) {
				return Response::json(Trip::getList($userToken->UserID, array('type' => 'all', 'dropdown' => true)));
			} 
			
			return Response::json(Trip::getList($userToken->UserID, Input::all()));
		} else {
			$response = Trip::getDetail($tripID, $userToken->UserID);
			//Add trip list to the response
			$response->List = Trip::getList($userToken->UserID, array());			
			return Response::json($response);
		}
	}

    /**
     * API count number of trip by types
     *
     * @return array Contain type and count number
     */
    public function getCount()
    {
        // Need to check authentication
        if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
            return Response::json(array('message' => 'The authentication is failed.'), 401);
        }

        $types = @explode(',', Input::get('type', ''));
        $types = is_array($types) ? $types : array();

        $dateFrom = Input::get('dateFrom', '');
        $dateTo = Input::get('dateTo', '');

        return Response::json(Trip::count($userToken->UserID, $types, $dateFrom, $dateTo), 200);
    }
        
	public function postIndex()
	{		
		// Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
		
		$post = Input::all();
//		if (isset($post['StartDate']) && strpos($post['StartDate'], 'T')) {
//			$post['StartDate'] = substr($post['StartDate'], 0, 10);
//		}
//		if (isset($post['EndDate']) && strpos($post['EndDate'], 'T')) {
//			$post['EndDate'] = substr($post['EndDate'], 0, 10);
//		}
		if (isset($post['StartDate']) && strpos($post['StartDate'], 'T') !== false) {
			$post['StartDate'] = substr(str_replace('T', ' ', $post['StartDate']), 0, -5);
		}
		
		if (isset($post['EndDate']) && strpos($post['EndDate'], 'T') !== false) {
			$post['EndDate'] = substr(str_replace('T', ' ', $post['EndDate']), 0, -5);
		}
		
		$messages = $this->validateTrip($post, $userToken->UserID);
		if (count($messages)) {
			return Response::json($messages, 500);
		}
		
		$trip = new Trip();
		$trip->Name = $post['Name'];
		$trip->Departure = $post['Departure'];
		$trip->Arrival = $post['Arrival'];
		$trip->StartDate = strtotime($post['StartDate']);
		$trip->EndDate = strtotime($post['EndDate']);
		
		if (isset($post['Leg'])) {
			$trip->Leg = $post['Leg'];
		}
		
		if (isset($post['Reference'])) {
			$trip->Reference = Trip::checkRef($post['Reference'], $userToken->UserID);
		}
		if (isset($post['Memo'])) {
			$trip->Memo = $post['Memo'];
		}
		
		$trip->CreatedTime = $_SERVER['REQUEST_TIME'] * 1000;
		$trip->UserID = $userToken->UserID;
		$trip->save();
		
		if (isset($post['Tags']) && is_array($post['Tags']) && count($post['Tags'])) {
			Tag::saveTags($trip->TripID, 'trip', $post['Tags']);
		}
		
        //Push server
        PushBackground::send($userToken->UserID, 'trip', 'post', $trip->TripID);
        
		return Response::json(array(
			//Set state for the trip and return it
			'TripID' => $trip->TripID,
			'State' => $trip->setState()
		), 200);
	}
	
	public function putIndex()
	{
		// Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}		
		
		$data = Input::get('data', array());
		if (! count($data)) {
			return Response::json(array('message' => array('The request data is empty.')), 500);
		}
		
		$response = array();
		foreach ($data as $key => $put) {
			if (isset($put['StartDate'])) {
				if (strpos($put['StartDate'], 'T') !== false) {
					$put['StartDate'] = substr(str_replace('T', ' ', $put['StartDate']), 0, -5);
				}
			}

			if (isset($put['EndDate'])) {
				if (strpos($put['EndDate'], 'T') !== false) {
					$put['EndDate'] = substr(str_replace('T', ' ', $put['EndDate']), 0, -5);
				}
			}
			
			$messages = $this->validateTrip($put, $userToken->UserID);
			if (count($messages)) {
				$messages['tripRow'] = $key;
				return Response::json($messages, 500);
			}

			// Update this trip
			$trip = Trip::find($put['TripID']);

			if (isset($put['Name']) && $put['Name'] != $trip->Name) {
				$trip->Name = $put['Name'];
			}

			if (isset($put['Departure']) && $put['Departure'] != $trip->Departure) {
				$trip->Departure = $put['Departure'];
			}

			if (isset($put['Arrival']) && $put['Arrival'] != $trip->Arrival) {
				$trip->Arrival = $put['Arrival'];
			}
			
			if (isset($put['Leg']) && $put['Leg'] != $trip->Leg) {
				$trip->Leg = $put['Leg'];
			}

			if (isset($put['StartDate'])) {
				if (strtotime($put['StartDate']) != $trip->StartDate) {
					$trip->StartDate = strtotime($put['StartDate']);
				}
			}

			if (isset($put['EndDate'])) {
				if (strtotime($put['EndDate']) != $trip->EndDate) {
					$trip->EndDate = strtotime($put['EndDate']);
				}
			}

			if (isset($put['Reference']) && $put['Reference'] != $trip->Reference) {
				$trip->Reference = Trip::checkRef($put['Reference']);
			}

			if (isset($put['Memo']) && $put['Memo'] != $trip->Memo) {
				$trip->Memo = $put['Memo'];
			}

			if (isset($put['Tags']) && is_array($put['Tags']) && count($put['Tags'])) {
				Tag::saveTags($trip->TripID, 'trip', $put['Tags'], Tag::getList($trip->TripID, 'trip', true));
			}

			$trip->ModifiedTime = $_SERVER['REQUEST_TIME'] * 1000;
			$trip->save();
			
			$response[] = array(
				'TripID' => $trip->TripID,
				'State' => $trip->setState(),
			);
		}
        
        //Push server
        PushBackground::send($userToken->UserID, 'trip', 'put', $trip->TripID);
		
		return Response::json($response, 200);
	}
	
	/**
	 * This callback is to save a field of a trip quickly
	 */
	public function putQuickSave()
	{
		// Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
		
		$put = Input::all();
		
		$rules = array(
				'TripID' => array('required', 'trips_belong_to:' . $userToken->UserID, 'trips_not_reported'),
				'Field' => array('required', 'in:Name,Departure,Arrival,StartDate,EndDate,Leg,Reference')
			);
		
		$messages = array(
				'TripID.required' => 'You need to specify a trip.',
				'TripID.trips_belong_to' => 'Specified trip does not belong to the user who sent this request.',
				'TripID.trips_not_reported' => 'This trip is reported. You cannot modify or delete it.',
				'Field.required' => 'You need to specified a field to be saved.',
				'Field.in' => 'Your specified field is invalid.',
				'Value.required' => 'You need to specified a value to be saved'
			);
		
		if (isset($put['Field'])) {
			if ($put['Field'] == 'Name') {
				$rules['Value'] = array('required', 'max:255');
				$messages['Value.max'] = 'Trip name is limited to 255 characters';
			}
			
			if ($put['Field'] == 'Departure') {
				$rules['Value'] = array('required', 'max:128');
				$messages['Value.max'] = 'Departure is limited to 128 characters';
			}
			
			if ($put['Field'] == 'Arrival') {
				$rules['Value'] = array('required', 'max:128');
				$messages['Value.max'] = 'Arrival is limited to 128 characters';
			}
			
			if ($put['Field'] == 'StartDate') {
				if (strpos($put['Field'], 'T') !== false) {
					$put['Field'] = substr(str_replace('T', ' ', $put['Field']), 0, -5);
				}
		
				$rules['Value'] = array('required', 'date', 'quick_trip_date:start,' . $userToken['UserID'] . ',' . $put['TripID']);
				$messages['Value.date'] = 'Start date does not have valid date format.';
			}
			
			if ($put['Field'] == 'EndDate') {
				if (strpos($put['Field'], 'T') !== false) {
					$put['Field'] = substr(str_replace('T', ' ', $put['Field']), 0, -5);
				}
				
				$rules['Value'] = array('required', 'date', 'quick_trip_date:end,' . $userToken['UserID'] . ',' . $put['TripID']);
				$messages['Value.date'] = 'End date does not have valid date format.';
			}
			
			if ($put['Field'] == 'Reference') {
				$rules['Value'] = array('required', 'max:10', 'quick_trip_ref_exist:' . $userToken['UserID']);
				$messages['Value.max'] = 'Reference is limited to 10 characters.';
				$messages['Value.quick_trip_ref_exist'] = 'This Trip# is used by another trip. Please select other Trip# or just use existing one.';
			}
			
			if ($put['Field'] == 'Leg') {
				$rules['Value'] = array('integer');
				$messages['Value.integer'] = 'Leg needs to be an integer value.';
			}
		}
		
		$validator = Validator::make($put, $rules, $messages);
		
		if ($validator->fails()) {
			return Response::json(array('message' => $validator->messages()->all()), 500);
		}
		
		$trip = Trip::find($put['TripID']);
		if ($put['Field'] == 'StartDate' || $put['Field'] == 'EndDate') {
			
			
			$trip->{$put['Field']} = strtotime($put['Value']);
			
			if ($put['Field'] == 'StartDate') {
				$value_pieces = explode('T', $put['Value']);
				$trip->Reference = 'T' . str_replace('-', '', $value_pieces[0]);
			}
		} else {
			$trip->{$put['Field']} = $put['Value'];
		}
		
		// Do not generate Trip Reference in case UPDATE
		// $trip->Reference = Trip::checkRef($trip->Reference, $userToken->UserID);
		if ($put['Field'] == 'Reference') {
			$trip->Reference = strtoupper($put['Value']);
		}
		  
		$trip->ModifiedTime = $_SERVER['REQUEST_TIME'] * 1000;
		$trip->save();
		
        //Push server
        PushBackground::send($userToken->UserID, 'trip', 'put', $trip->TripID);
        
		return Response::json(array(
			'State' => $trip->setState(),
			'Reference' => $trip->Reference
		));
	}
	
	public function putArchive()
	{
		// Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
		
		// Validate to be sure that all specified trips belongs to the user who send this request
		$puts = Input::all();
		$validator = Validator::make(
				$puts, 
				array(
					'TripIDs' => array('required', 'trips_belong_to:' . $userToken->UserID),
					'Archived' => array('required', 'in:0,1'),
				));
		
		if ($validator->fails()) {
			return Response::json(array('message' => $validator->messages()->all()), 500);
		}
		
		//Archive the selected trips
		Trip::archiveList($puts['TripIDs'], $puts['Archived']);
		
		return Response::make('', 204);
	}
	
	public function deleteIndex()
	{
		// Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
		
		$tripIDs = explode(',', Input::get('TripIDs', ''));
		$customMessages = array();
		if (count($tripIDs) === 1) {
			$customMessages['trips_not_reported'] = 'This trip is reported. You cannot modify or delete it.';
		}
		
		// Validate to be sure that all specified trips belongs to the user who send this request
		$validator = Validator::make(
				array('TripIDs' => $tripIDs),
				array('TripIDs' => array('required', 'trips_belong_to:' . $userToken->UserID, 'trips_not_reported')),
				$customMessages);
		
		if ($validator->fails()) {
			return Response::json(array('message' => $validator->messages()->all()), 500);
		}
		
		$itemIDs = DB::table('TripItem')
				->select('TripItemID')
				->whereIn('TripID', $tripIDs)
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
			
			//14/01/2014: Need to update receipts which contain these items to have the verify status = 1
			$receiptIDs = DB::table('Item')
					->select('ReceiptID')
					->whereIn('ItemID', $itemIDs)
					->lists('ReceiptID');
			
			DB::table('Receipt')
					->whereIn('ReceiptID', $receiptIDs)
					->where('VerifyStatus', 2)
					->update(array('VerifyStatus' => 1));
		}
		
		//Delete trips
		DB::table('Trip')
				->whereIn('TripID', $tripIDs)
				->delete();
        
        //Push server
        PushBackground::send($userToken->UserID, 'trip', 'delete', Input::get('TripIDs', ''));
		
		return Response::make('', 204);
	}
	
	public function postAddToReport()
	{
		// Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
		
		$post = Input::all();
		$validator = Validator::make(
				$post, 
				array(
					'TripID' => array(
						'required', 
						'trips_belong_to:' . $userToken->UserID, 
						'not_assigned_to_report:' . (isset($post['ReportID']) ? $post['ReportID'] : null)
					),
					'ReportID' => array(
						'required', 
						'reports_belong_to:' . $userToken->UserID
					),
				)
			);
		
		if ($validator->fails()) {
			return Response::json(array('message' => $validator->messages()->all()), 500);
		}
		
		DB::table('ReportTrip')
				->insert(array(
					'ReportID' => $post['ReportID'],
					'TripID' => $post['TripID'],
					'CreatedTime' => $_SERVER['REQUEST_TIME'] * 1000,
				));
		
		$report = Report::getDetail($post['ReportID'], $userToken->UserID);
        //Push server
        PushBackground::send($userToken->UserID, 'trip', 'put', $post['TripID']);
        PushBackground::send($userToken->UserID, 'report', 'put', $report->ReportID);
		
		return Response::json(array(
			'Report' => $report->Reference,
            'ReportDetail' => $report,
		));
	}
	
	public function getItems()
	{
		// Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
		
		$tripIDs = explode(',', Input::get('tripIDs', ''));
		// Validate to be sure that all specified trips belongs to the user who send this request
		$validator = Validator::make(
				array('tripIDs' => $tripIDs), 
				array('tripIDs' => array('required', 'trips_belong_to:' . $userToken->UserID)));
		
		if ($validator->fails()) {
			return Response::json(array('message' => $validator->messages()->all()), 500);
		}
		
		$trips = Trip::getList($userToken->UserID, array(
			'tripIDs' => $tripIDs
		));
		
		if (count($trips)) {
			foreach ($trips as $trip) {
				Trip::staticSetState($trip);
				Trip::staticGetAmount($trip);
				
				$trip->Items = Trip::getTripItems($trip->TripID);
			}
		}
		
		return $trips;
	}
	
	public function putUpdateTime()
	{
		// Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
		
		if ($userToken->UserID == 1) {
			$trips = Trip::where('TripID', '>', 0)->get();
			foreach ($trips as $trip) {
				$profile = Profile::where('UserID', $trip->UserID)->first();
				if ($profile->Timezone) {
					date_default_timezone_set($profile->Timezone);
				} else {
					date_default_timezone_set('UTC');
				}
				
				$trip->StartDate = strtotime(date('Y-m-d', $trip->StartDate));
				$trip->EndDate = strtotime(date('Y-m-d', $trip->EndDate));
				
				$trip->save();
			}
		}
	}
	
	private function validateTrip($inputs, $userID)
	{
		$rules = array(
				'Name' => array('required', 'max:255'),
				'Departure' => array('required', 'max:128'),
				'Arrival' => array('required', 'max:128'),
				'StartDate' => array('required', 'date', 'trip_date:' . $userID . ',0,' . (isset($inputs['EndDate']) ? $inputs['EndDate'] : null)),
				'EndDate' => array('date'),
				'Reference' => array('required', 'max:45'),
				'Leg' => array('integer'),
			);
		
		if (isset($inputs['TripID'])) {
			$rules['TripID'] = array('required', 'trips_belong_to:' . $userID, 'trips_not_reported');
			$rules['StartDate'] = array('required', 'date', 'trip_date:' . $userID . ',' . $inputs['TripID'] . ',' . (isset($inputs['EndDate']) ? $inputs['EndDate'] : null));
		}
		
		if (isset($inputs['StartDate'])) {
			$rules['EndDate'][] = 'not_before:' . $inputs['StartDate'];
		}
		
		$customMessages = array(
			'TripID.trips_belong_to' => 'Specified trip does not belong to the user who sent this request.',
			'Name.required' => 'Please enter trip name.',
			'Departure.required' => 'Please enter your departure.',
			'Arrival.required' => 'Please enter your arrival.',
			'StartDate.required' => 'Please choose a start date.',
			'Reference.required' => 'Please enter a reference.',
			'Leg.integer' => 'Leg must be an integer.',
			'EndDate.not_before' => 'End date must be equal or greater than start date.',
		);
		
		if (isset($inputs['TripID']) && ! is_array($inputs['TripID'])) {
			$customMessages['TripID.trips_not_reported'] = 'This trip is reported. You cannot modify or delete it.';
		}
		
		$validator = Validator::make($inputs, $rules, $customMessages);
		
		if ($validator->fails()) {
			return array('message' => $validator->messages()->all());
		}
		
		return array();
	}
        
    public function getPrint() {
        //Need to check authentication
        if (empty($_SERVER['HTTP_AUTH_TOKEN']) || !$userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
            return Response::json(array('message' => 'The authentication is failed.'), 401);
        }

        //Get parameters
        $tripID = Input::get('tripID', '');
        $tripType = Input::get('tripType', 'items');

        //Validate param
        $validator = Validator::make(
                        array(
                    'tripID' => $tripID,
                    'tripType' => $tripType
                        ), array(
                    'tripID' => array('required', 'numeric'),
                    'tripType' => array('in:items, folder')
                        ), array('tripID.required' => 'You must specified a trip.')
        );
        
        //if Validate  failed
        if ($validator->fails()) {
            return Response::json(array('message' => $validator->messages()->all()), 500);
        }
        
        //get Trip by ID
        $trip = Trip::getDetail($tripID, $userToken->UserID, $tripType);                        
        
        if ($trip) {
            $profile = Profile::find($userToken->UserID);
            
            //Initialize the pdf creator object
            $pdf = new MyPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, TRUE, 'UTF-8', FALSE);
            $pdf->SetCreator("Receipt Club");
            $pdf->SetAuthor('Receipt Club');
            $pdf->SetTitle('Travel Expense Trip');
            $pdf->SetKeywords('TCPDF, PDF, receipt, receiptclub, travelexpense, report');

            //Set default configs and values            
            $pdf->setPageOrientation('L');
            $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);                            
            $pdf->SetMargins(PDF_MARGIN_LEFT, 10, PDF_MARGIN_RIGHT);
            $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
            $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
            $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
            $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
            $pdf->setDisplayMode(70);
            $pdf->SetMargins(PDF_MARGIN_LEFT, 20, PDF_MARGIN_RIGHT);

            //Genareate first part of Trip report
            $pdf->AddPage();
            $pdf->setFontSubsetting(true);
            $pdf->SetFont('helvetica', '', 9);            
            $view = View::make('pdfs.trip', array(
                'trip' => $trip,
                'currency' => $profile ? $profile->CurrencyCode : '',
                'startDate'=> $trip->StartDate ? date('d-M-Y',strtotime($trip->StartDate)) : '',
                'endDate'  => $trip->EndDate ? date('d-M-Y',strtotime($trip->EndDate)) : ''
                 ));
            $pdf->writeHTML($view->render(), TRUE, FALSE, FALSE, FALSE, '');

            $fileBasePath = Config::get('app.fileBasePath');
            //Remove old file and old folder of previous generation                                    
            exec('rm -R ' . $fileBasePath . '/report_pdf/*');
            $fileNameUsername = substr($profile->FirstName . ' ' . $profile->LastName, 0, 40);
            $fileNameTitle = substr($trip->Name, 0, 60);
            $dirName = 'Travel Expense Trip ' . date('Y-m-d') . ' ' . $fileNameUsername . ' - ' . $fileNameTitle . '(' . $tripType . ' item) - ' . date('H:i:s');
            $pdfFileName = $dirName . '.pdf';
            $tmppdfFilePath = $pdfFileName;

            if ($trip->HasImagesOrEmails) {
                $tmpDirStorePdf = $fileBasePath . 'tmpDirPdf';
                $pdfDirPath = $fileBasePath . 'trip_pdf/' . $dirName;
                if (!file_exists($pdfDirPath)) {
                    mkdir($pdfDirPath);
                    exec('chmod 777 -R "' . $pdfDirPath . '"');
                    exec('chmod 777 -R "' . $tmpDirStorePdf . '"');
                }

                $arrPdf = array();
                if (count($trip->Items)) {
                    foreach ($trip->Items as $item) {
                        if ($item->ReceiptImage) {
                            if (!$item->ReceiptImage->Used) {
                                $item->ReceiptImage->Number = str_pad($item->ReceiptImage->Number, 2, '0', STR_PAD_LEFT);                                                                
                                $data = file_get_contents(File::getS3PreSignedUrl($item->ReceiptImage->FileBucket, $item->ReceiptImage->FilePath));
                                $tmpPdfPath = $tmpDirStorePdf . '/' . $trip->Reference . ' - Receipt' . $item->ReceiptImage->Number . '.' . $item->ReceiptImage->FileExtension;
                                file_put_contents($tmpPdfPath, $data);
                                array_push($arrPdf, $tmpPdfPath);
                            }
                        } else if ($item->RawData) {
                            $item->RawData->Number = str_pad($item->RawData->Number, 2, '0', STR_PAD_LEFT);
                            $htmlEmail = $item->RawData->RawData;                                        //                                
                            $pdf->AddPage();
                            // output the HTML content
                            $pdf->writeHTML(html_entity_decode($htmlEmail, ENT_QUOTES), true, false, false, false, ''); //                                
                        }
                    }
                }

                //output file pdf
                $arrConcatPdfFile = array();
                $pdf->Output($tmpDirStorePdf . '/' . $pdfFileName, 'F');
                $arrConcatPdf = array($tmpDirStorePdf . '/' . $pdfFileName);
                $arrConcatPdfFile = array_merge($arrConcatPdf, $arrPdf);

                //Concat 2 pdf  
                $pdf = new PdfConcat($trip->Reference);

                // set default header data
                $pdf->setHtmlHeader('<table style="font-size: 6px; color: #666">
                                        <tr>
                                            <td colspan="2" style="text-align: center"><b>' . $trip->Name . ' No. ' . $trip->Reference . '</b></td>
                                        </tr>
                                    </table>');

                //set default footer data
                $pdf->setHtmlFooter('<table  style="font-size: 6px; color: #666">
                                        <tr>
                                             <td style="text-align: left;"><img src="' . Config::get('app.fileBaseUrl') . 'logo-small.png"></td>
                                             <td style="text-align: center;line-height:30px;">' . date('d-M-Y') . '</td>
                                             <td style="text-align: right; line-height:30px;">Page ' . $pdf->getAliasNumPage() . ' of ' . $pdf->getAliasNbPages() . '</td>
                                        </tr>
                                   </table>');

                $pdf->SetMargins(PDF_MARGIN_LEFT, 5, PDF_MARGIN_RIGHT);
                $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
                $pdf->SetFooterMargin(10);
                $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
                //set all file to concat
                $pdf->setFiles($arrConcatPdfFile);
                $pdf->concat();
                $pdf->Output($pdfDirPath . '/' . $pdfFileName, "F");

                $file = new File();
                $file->FileName = $tmppdfFilePath;
                $file->FilePath = 'trip_pdf/' . $file->FileName;
                $file->Timestamp = $_SERVER['REQUEST_TIME_FLOAT'];
                $file->EntityID = $trip->TripID;
                $file->EntityName = 'trip_pdf';
                $file->save();
//                exec('rm "' . $tmpDirStorePdf . '"/*');
            } else {
                $file = new File();
                $file->FileName = $pdfFileName;
                $file->FilePath = 'trip_pdf/' . $file->FileName;
                $file->Timestamp = $_SERVER['REQUEST_TIME_FLOAT'];
                $file->EntityID = $trip->TripID;
                $file->EntityName = 'trip_pdf';
                $file->save();
                $pdf->Output('files/' . $file->FilePath, 'F');
            }

            $tmpPdfDir = ($trip->HasImagesOrEmails) ? 'trip_pdf/' . $dirName . '/' . $tmppdfFilePath : 'trip_pdf/' . $tmppdfFilePath;
            return Response::json(array(
                        'FilePath' => $tmpPdfDir
            ));
        }
        return Response::json(array('message' => array('Cannot find the specified trip(s).')), 500);
    }

    public function getDownloadPdf()
    {
            $filePath = Input::get('filePath', '');
            $file = Config::get('app.fileBasePath') . $filePath;

            if (! is_file($file)) {
                    return Response::make('Cannot find the specified file:' . $file, 500);
            }		
            $filePathPieces = explode('/', $filePath);
            $fileName = $filePathPieces[1];
            $dirName = str_replace('.pdf', '', $fileName);                   		
            header('Content-Description: File Transfer');
            if (strpos('pdf', $fileName) !== -1) {
                    header('Content-Type: application/pdf');
            } else {
                    header('Content-Type: application/octet-stream');
            }		
            header('Content-Disposition: attachment; filename=' . $fileName);
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));
            ob_clean();
            flush();
            readfile($file);
            exit;                
    }


    /**
     * Secret function to generate trip automatically (for testing only)
     *
     * @return mixed
     */
    public function postDumpTrip () {
        // Need to check authentication
        if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
            return Response::json(array('message' => 'The authentication is failed.'), 401);
        }

        $uid = $userToken->UserID;
        $num = Input::get('amount', 0);
        $unq = uniqid();
        $unq = substr($unq, strlen($unq) - 4);
        $oneDay = 60 * 60 * 24;

        $latestTrip = DB::table('Trip')->where('UserID', $uid)->orderBy('EndDate', 'desc')->first();
        //dd('T' . date('Ymd', $latestTrip->EndDate));

        for ($i=1; $i<=$num; $i++) {
            $tripStart = $latestTrip->EndDate + $oneDay * $i;
            $tripEnd   = $tripStart;
            $tripRef   = date('Ymd', $tripStart);
            DB::table('Trip')
                ->insert(array(
                    'Name' => 'Trip ' . $unq . ' ' . $i,
                    'Departure' => 'a',
                    'Arrival' => 'z',
                    'StartDate' => $tripStart,
                    'EndDate' => $tripEnd,
                    'CreatedTime' => time(),
                    'ModifiedTime' => time(),
                    'Reference' => 'T' . $tripRef,
                    'UserID' => $uid
                ));
        }

        return Response::json(array(), 200);
    }
}