<?php
/**
 * Controller for currency
 */
class CurrencyV2Controller extends BaseV2Controller 
{
    protected $OPEN_EXCHANGE_API_URL = 'https://openexchangerates.org/api/';
    protected $APP_ID = '68f8c549dee745dd9582e254c3686438';
    /**
	 * Convert currency
	 */
	public function getCurrencyConverter()
	{
		$input = Input::all();
        
        $messages = $this->validateCurrencyInput($input);

        if (count($messages)) {
            $jsend = JSend\JSendResponse::fail($messages);
            return $jsend->respond();
        }
        
        $methodCall = '/latest';

        if ($input['purchaseDate'] < date("Y-m-d")) {
            $methodCall = '/historical/' . $input['purchaseDate'];
        }
        
        $url = $this->OPEN_EXCHANGE_API_URL . $methodCall . '.json' . '?app_id=' 
            . $this->APP_ID . '&base=' . $input['fromCode'];

        $result = file_get_contents($url);
        
        $objects = json_decode($result, true);
        
        $exchangeRate = array();
        foreach ($objects["rates"] as $key => $rate) {
            if($key == $input['toCode']) {
                $exchangeRate['exchangeRate'] = $rate;
            }
        }
        
		$jsend = JSend\JSendResponse::success($exchangeRate);
        return $jsend->respond();
	}
    
    protected function validateCurrencyInput($input) {
        $rules = array(
            'purchaseDate' => array('required', 'Date', 'date_true_format'),
            'fromCode' => array('required', 'min:3'),
            'toCode' => array('required', 'min:3')
        );
        $message = array(
            'purchaseDate.date_true_format' => 'Please enter a Date in yyyy-mm-dd format',
        );

		$validator = Validator::make($input, $rules, $message);

		if ($validator->fails()) {
            return array('message' => $validator->messages()->all());
        }
        
        return array();
    }
}
