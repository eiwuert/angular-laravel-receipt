<?php
/**
 * Controller for items
 */
class ExportController extends BaseController 
{
	
	public function getIndex()
	{
		//Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
		
		$get = Input::all();
		$validator = Validator::make(
				$get, 
				array(
					'app' => array('required', 'is_app'),
					'type' => array('required', 'in:xls,csv,qif,qfx'),
					'date' => array('required', 'date'),
				)
			);
		
		if ($validator->fails()) {
			return Response::json(array('message' => $validator->messages()->all()), 500);
		}
		
		$user = User::find($userToken->UserID);
		if ($get['type'] == 'xls') {
			$excelObj = new PHPExcel();
			$excelObj->getProperties()->setCreator($user->Username)
					->setLastModifiedBy($user->Username)
					->setTitle('Office 2007 XLSX Test Document')
					->setSubject('Office 2007 XLSX Test Document')
					->setDescription('Test document for Office 2007 XLSX, generated using PHP classes.')
					->setKeywords('office 2007 openxml php')
					->setCategory('Personal Expense');
			
			$excelObj->setActiveSheetIndex(0)
					->setCellValue('A1', 'Expense export: ' . date('Y-M-d'));
			
			//Add table header row
			$excelObj->setActiveSheetIndex(0)
					->setCellValue('A2', 'Category Name')
					->setCellValue('B2', 'Amount')
					->setCellValue('C2', 'Item Name')
					->setCellValue('D2', 'Merchant')
					->setCellValue('E2', 'Date')
					->setCellValue('F2', 'Memo')
					->setCellValue('G2', 'Tag');
			
			$items = Item::getExportedList($userToken->UserID, strtotime(date('Y-m-01', strtotime($get['date']))));
			if (count($items)) {
				foreach ($items as $key => $item) {
					$excelObj->setActiveSheetIndex(0)
							->setCellValue('A' . ($key + 3), $item->CategoryName)
							->setCellValue('B' . ($key + 3), $item->Amount)
							->setCellValue('C' . ($key + 3), $item->ItemName)
							->setCellValue('D' . ($key + 3), $item->MerchantName)
							->setCellValue('E' . ($key + 3), $item->Date)
							->setCellValue('F' . ($key + 3), $item->Memo)
							->setCellValue('G' . ($key + 3), $item->Tags);
				}
			}
			
			$excelObj->getActiveSheet()->getStyle('A2:G2')->getFont()->setBold(true);
			$excelObj->getActiveSheet()->getStyle('A1')->getFont()->getColor()->setARGB(PHPExcel_Style_Color::COLOR_BLUE);
			$excelObj->getActiveSheet()->setTitle('Personal Expense');
			$excelObj->setActiveSheetIndex(0);
			
			$file = new File();
			$file->FileName = $user->Username . '_PersonalExpense_' . date('Y-m-d') . '.xls';
			$file->FilePath = 'export/' . $file->FileName;
			$file->Timestamp = $_SERVER['REQUEST_TIME'];
			$file->EntityID = 0;
			$file->EntityName = 'export';
			$file->save();
			
			PHPExcel_IOFactory::createWriter($excelObj, 'Excel5')
					->save('files/' . $file->FilePath);
			
			return Response::json(array(
					'FileName' => $file->FileName
				));
		}
		
		return Response::make('', 204);
	}
	
    public function getExport()
	{
		//Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
		
		$get = Input::all();
		$validator = Validator::make(
				$get, 
				array(
					'app' => array('required', 'is_app'),
					'type' => array('required', 'in:xls,csv,qif,qfx'),
					'dateFrom' => array('required', 'date'),
				)
			);
		
		if ($validator->fails()) {
			return Response::json(array('message' => $validator->messages()->all()), 500);
		}
		
		$app = Input::get('app', '');

		$appName = 'PersonalExpense';

		if ($app == 'personal_expense') {
			$appName = 'PersonalExpense';
		} else if ($app == 'business_expense') {
			$appName = 'BusinessExpense';
		} else if ($app == 'education_expense') {
			$appName = 'EducationExpense';
		} else if ($app == 'personal_assets') {
			$appName = 'PersonalAssets';
		} else if ($app == 'business_assets') {
			$appName = 'BusinessAssets';
		}
		
		$dateFrom = Input::get('dateFrom', '');
		$dateTo = Input::get('dateTo', '');

		$user = User::find($userToken->UserID);
		
        $excelObj = new PHPExcel();
        $excelObj->getProperties()->setCreator($user->Username)
                ->setLastModifiedBy($user->Username)
                ->setTitle('Office 2007 XLSX Test Document')
                ->setSubject('Office 2007 XLSX Test Document')
                ->setDescription('Test document for Office 2007 XLSX, generated using PHP classes.')
                ->setKeywords('office 2007 openxml php')
                ->setCategory($appName);

        $excelObj->getDefaultStyle()->getFont()->setSize(12);

        //Add table header row
        $excelObj->setActiveSheetIndex(0)
                ->setCellValue('A1', Input::get('title', ''))
                ->setCellValue('A3', 'Category')
                ->setCellValue('B3', 'Amount')
                ->setCellValue('C3', 'Item')
                ->setCellValue('D3', 'Merchant')
                ->setCellValue('E3', 'Purchase')
                ->setCellValue('F3', 'Period')
                ->setCellValue('G3', 'Memo');

        $excelObj->getActiveSheet()->getColumnDimension('A')->setWidth(30);
        $excelObj->getActiveSheet()->getColumnDimension('B')->setWidth(15);
        $excelObj->getActiveSheet()->getColumnDimension('C')->setWidth(40);
        $excelObj->getActiveSheet()->getColumnDimension('D')->setWidth(25);
        $excelObj->getActiveSheet()->getColumnDimension('E')->setWidth(18);
        $excelObj->getActiveSheet()->getColumnDimension('F')->setWidth(18);
        $excelObj->getActiveSheet()->getColumnDimension('G')->setWidth(20);

        if (! $dateTo || strtotime($dateTo) <= strtotime($dateFrom)) {
            $dateTo = date('Y-m-d', strtotime('+1 day', strtotime($dateFrom)));
        } else {
            $dateTo = date('Y-m-d', strtotime('+1 day', strtotime($dateTo)));
        }

        $categoryReturnList = Category::getListByApp($app, $userToken->UserID, strtotime($dateFrom), strtotime($dateTo));

        $arrayItems = array();
        $totalAmount = 0;

        foreach($categoryReturnList as $category) {
            if(count($category->Items)) {
                $arrayItemsEachCat = $category->Items;
                foreach($arrayItemsEachCat as $singleItem) {
                    $singleItem->CategoryName = $category->Name;
                    $totalAmount = $totalAmount + $singleItem->Amount;
                }
                $arrayItems = array_merge($arrayItems, $arrayItemsEachCat);
            }
        }

        $lastKeyValue = 0;
        if (count($arrayItems)) {
            foreach ($arrayItems as $key => $item) {
                $more = $item->More;
                $excelObj->setActiveSheetIndex(0)
                        ->setCellValue('A' . ($key + 4), $item->CategoryName)
                        ->setCellValue('B' . ($key + 4), $item->Amount)
                        ->setCellValue('C' . ($key + 4), $item->Name);

                if(isset($item->MerchantName)) {
                    $excelObj->setActiveSheetIndex(0)->setCellValue('D' . ($key + 4), $item->MerchantName);
                } else {
                    $excelObj->setActiveSheetIndex(0)->setCellValue('D' . ($key + 4), 'Merchant Unrecognized');
                }
                $excelObj->setActiveSheetIndex(0)->setCellValue('E' . ($key + 4), date('d-M-Y', strtotime($item->PurchaseTime)))
                        ->setCellValue('F' . ($key + 4), date('M-Y', strtotime($item->ExpensePeriodFrom)))
                        ->setCellValue('G' . ($key + 4), $more->Memo);
                $lastKeyValue = $key;
            }
        }

        $settings = Settings::find($user->UserID);
        $currencyCode = $settings->CurrencyCode;
        
        $excelObj->setActiveSheetIndex(0)
                ->setCellValue('A' . ($lastKeyValue + 6), 'Summary')
                ->setCellValue('A' . ($lastKeyValue + 7), 'Amount')
                ->setCellValue('B' . ($lastKeyValue + 7), $totalAmount)
                ->setCellValue('A' . ($lastKeyValue + 8), 'Currency')
                ->setCellValue('B' . ($lastKeyValue + 8), $currencyCode)
                ->setCellValue('A' . ($lastKeyValue + 9), 'Records')
                ->setCellValue('B' . ($lastKeyValue + 9), count($arrayItems))
                ->setCellValue('A' . ($lastKeyValue + 10), 'Report Date')
                ->setCellValue('B' . ($lastKeyValue + 10), date('d-M-Y'));

        $excelObj->getActiveSheet()->getStyle('A' . ($lastKeyValue + 6) . ':A' . ($lastKeyValue + 10))->getFont()->setBold(true);

        $excelObj->getActiveSheet()->getStyle('A3:S3')->getFont()->setBold(true)->setSize(12);
        $excelObj->getActiveSheet()->getStyle('A1')->getFont()->setBold(true)->setSize(12);

        $excelObj->getActiveSheet()->setTitle($appName);
        $excelObj->setActiveSheetIndex(0);

        $file = new File();
        if ($get['type'] == 'xls') {
            $file->FileName = $user->Username . '_' . $appName . '_' . date('Y-m-d') . '.xls';
        } else if ($get['type'] == 'csv') {
            $file->FileName = $user->Username . '_' . $appName . '_' . date('Y-m-d') . '.csv';
        }
        $file->FilePath = 'export/' . $file->FileName;
        $file->Timestamp = $_SERVER['REQUEST_TIME'];
        $file->EntityID = 0;
        $file->EntityName = 'export';
        $file->save();

        if ($get['type'] == 'xls') {
            PHPExcel_IOFactory::createWriter($excelObj, 'Excel5')->save('files/' . $file->FilePath);
        } else if ($get['type'] == 'csv') {
            PHPExcel_IOFactory::createWriter($excelObj, 'CSV')->save('files/' . $file->FilePath);
        }

        return Response::json(array(
            'FileName' => $file->FileName
        ));
		
		
		return Response::make('', 204);
	}
	
	public function getDownload()
	{
		$fileName = Input::get('fileName', '');
		$file = Config::get('app.fileBasePath') . 'export/' . $fileName;
		
		if (! is_file($file)) {
			return Response::make('Cannot find the specified file.', 500);
		}
		
		header('Content-Description: File Transfer');
		header('Content-Type: application/pdf');
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
}