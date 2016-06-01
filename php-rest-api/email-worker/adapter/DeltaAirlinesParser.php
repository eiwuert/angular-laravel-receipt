<?php
/**
 * Delta template parser
 *
 * @author khanhdn
 */
class DeltaAirlinesParser extends EmailProcessAbstract {
	protected $_patterns = array(   	
   		'_getSubTotalAmount' => array(
			array(
				'start_delimiter' => 'FARE:',
				'end_delimiter'   => '</tr>',				
				'pattern_content' => '/<td.*?>(.*?)<\/td>/is',
				'pattern_amount'  => '/[0-9\.]+/'
			)
		),
		'_getTaxAmount' => array(			
			array(
				'start_delimiter' => 'Taxes/Carrier-imposed Fees:',
				'end_delimiter'   => '</tr>',				
				'pattern_content' => '/<td.*?>(.*?)<\/td>/is',
				'pattern_amount'  => '/[0-9\.]+/'
			)
		),
		'_getTotalAmount' => array(			
			array(
				'start_delimiter' => 'Total:',
				'end_delimiter'   => '</tr>',				
				'pattern_content' => '/<td.*?>(.*?)<\/td>/is',
				'pattern_amount'  => '/[0-9\.]+/'
			)
		)			
  	);

	/**
	 * Construct method
	 */
	public function __construct() {
    	parent::__construct();
	}

	/**
     * Analyze email order notification of merchant
     * 
     * @param  string $content
     * @param  string $email
     * @return void
    */
	public function analyze($content, $email) {			
		//file_put_contents(__DIR__ . '/deltaairlines.html', $content);

		/*
		$encoding = mb_detect_encoding($content);
		
		if (strtolower($encoding) != 'utf-8') {
			$content = mb_convert_encoding($content, 'UTF-8', "UCS-2LE");
		}
		*/
		
		// Is DeltaAirlines email template?
		if (stripos($content, 'print email now and scan at a Delta self-service kiosk') === false) {
			return false;
		}

		$receipt = new DtoReceipt;
        $receipt->Items = array();        
        $receipt->ReceiptType  = 2; // Email Receipt
        $receipt->VerifyStatus = 0;
        $receipt->MerchantName = 'Delta Air Lines';
        $receipt->EmailSender  = $email;
        $receipt->CreatedTime  = $receipt->ModifiedTime = time();
		$receipt->RawData = htmlentities($content, ENT_QUOTES, mb_detect_encoding($content));

		$this->_orders[0] = $receipt;

        foreach ($this->_patterns as $method => $pattern) {        	
          if (method_exists($this, $method)) {                                        
            $this->$method($content, $pattern, $this->_orders[0]);
          }
        }           

        return true;     
	}

	/**
	 * Fetch the body content
	 * 
	 * @param resource $imap_stream 
	 * @param int      $msg_number
	*/
	public function fetchBody($imap_stream, $msg_number) {
		$text = imap_fetchbody($imap_stream, $msg_number, 2);
		return imap_qprint($text);
	}

	/**
   	 * Get order subtotal total
     * 
     * @param string $content
     * @param array  $pattern
     * @param object $order
     */
	protected function _getSubTotalAmount($content, array $pattern, $order) {
		foreach ($pattern as $pat) {
			$value = $this->_extract($content, $pat);
      	
			if ($value) {					
				$order->Subtotal = sprintf('%01.2f', $value);				
				break;			
			}
		}			
	}	

	/**
   	 * Get tax amount
     * 
     * @param string $content
     * @param array  $pattern
     * @param object $order
     */
	protected function _getTaxAmount($content, array $pattern, $order) {
		foreach ($pattern as $pat) {
			$value = $this->_extract($content, $pat);
      	
			if ($value) {					
				$order->Tax = sprintf('%01.2f', $value);				
				break;			
			}
		}			
	}

	/**
   	 * Get order total
     * 
     * @param string $content
     * @param array  $pattern
     * @param object $order
     */
	protected function _getTotalAmount($content, array $pattern, $order) {
		foreach ($pattern as $pat) {
			$value = $this->_extract($content, $pat);
      	
			if ($value) {					
				$order->DigitalTotal = sprintf('%01.2f', $value);				
				break;			
			}
		}			
	}	

	/**
	 * Extract information from string
	 * 
	 * @param string $content
	 * @param array  $pattern
	 * @return mixed
	*/
	protected function _extract($content, $pattern) {    
		$result = null;
		$beginPos = stripos($content, $pattern['start_delimiter']);    

		if ($beginPos === FALSE) return $result;

		$extractedContent = substr($content, $beginPos);
		$endPos           = stripos($extractedContent, $pattern['end_delimiter']);
		$extractedContent = substr($extractedContent, 0, $endPos);

		preg_match($pattern['pattern_content'], $extractedContent, $match);

		if (!empty($match[1])) {
			$match[1] = strip_tags($match[1]);    

			preg_match($pattern['pattern_amount'], $match[1], $extractedNumber);

			if (!empty($extractedNumber[0])) {
				$result = $extractedNumber[0];
			}
		}

		return $result;
	}
}
