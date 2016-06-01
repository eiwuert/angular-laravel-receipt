<?php
/**
 * Grand Hyatt template parser
 *
 * @author khanhdn
 */
class GrandHyattParser extends EmailProcessAbstract {
	protected $_patterns = array(   	
   		'_getTotalAmount' => array(
			array(
				'start_delimiter' => 'NIGHTLY RATE PER ROOM:',
				'end_delimiter'   => '</div>',				
				'pattern_content' => '/<span.*?>(.*?)<\/span>/is',
				'pattern_amount'  => '/[^0-9\.]/'
			),
			array(
				'start_delimiter' => 'NIGHTLY RATE PER ROOM:',
				'end_delimiter'   => 'TYPE OF RATE:',				
				'pattern_content' => '/<div>(.*?)<\/div>/is',
				'pattern_amount'  => '/[^0-9\.]/'
			),
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
		// Is Grand Hyatt Washington email template?
		if (!preg_match('/<table.*?>.*?Grand Hyatt.*?/is', $content)) {
			return false;
		}
		
		$receipt = new DtoReceipt;
        $receipt->Items = array();        
        $receipt->ReceiptType  = 2; // Email Receipt
        $receipt->VerifyStatus = 0;
        $receipt->MerchantName = 'Grand Hyatt';
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
		$text = imap_fetchbody($imap_stream, $msg_number, "1.2");
		return imap_qprint($text);
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
				$order->Subtotal = $order->DigitalTotal = sprintf('%01.2f', $value);				
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

		preg_match_all($pattern['pattern_content'], $extractedContent, $match);

		if (!empty($match[1])) {
			if (!preg_match_all('/<b>(.*?)<\/b>/is', $match[1][0], $subMatches)) {
				return $result;
			}
			
			if (!empty($subMatches[1][1])) {
				$result = strip_tags($subMatches[1][1]);
				$result = preg_replace($pattern['pattern_amount'], '', $result);
				$result = sprintf('%01.2f', $result);
			}					
		}

		return $result;
	}
}
