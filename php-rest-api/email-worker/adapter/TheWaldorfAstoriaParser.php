<?php
/**
 * The Waldorf=Astoria template parser
 *
 * @author khanhdn
 */
class TheWaldorfAstoriaParser extends EmailProcessAbstract {
	protected $_patterns = array(   	
   		'_getAmount' => array(
			array(
				'start_delimiter' => 'Total for Stay per Room:',
				'end_delimiter'   => '</table>',				
				'pattern_content' => '/<tr.*?>.*?<td.*?>.*?<\/td>.*?<\/tr>/is',
				'pattern_amount'  => '/[^0-9\.]/'
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
		// Is The Waldorf=Astoria email template?
		if (!preg_match('/.*?<tr.*?>.*The Waldorf=Astoria would welcome the opportunity.*<\/tr>.*/is', $content,$match)) {
			return false;
		}

		
		$receipt = new DtoReceipt;
        $receipt->Items = array();        
        $receipt->ReceiptType  = 2; // Email Receipt
        $receipt->VerifyStatus = 0;
        $receipt->MerchantName = 'The Waldorf=Astoria';
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
   	 * Get order grand total
     * 
     * @param string $content
     * @param array  $pattern
     * @param object $order
     */
	protected function _getAmount($content, array $pattern, $order) {
		foreach ($pattern as $pat) {
			$result = $this->_extract($content, $pat);
      	
			if (!empty($result)) {	
				foreach ($result as $label => $value) {
					$order->$label = sprintf('%01.2f', $value);
				}		

				break;			
			}
		}			
	}

	/**
     * Override extract method
     * 
     * @param string $content
     * @param array  $pattern
     * @return array
    */
   	protected function _extract($content, $pattern) {
		$result   = array();
		$beginPos = strripos($content, $pattern['start_delimiter']);    

		if ($beginPos === FALSE) return $result;

		$extractedContent = substr($content, $beginPos);
		$endPos           = stripos($extractedContent, $pattern['end_delimiter']);
		$extractedContent = substr($extractedContent, 0, $endPos);

		preg_match_all($pattern['pattern_content'], $extractedContent, $matches);
		
		/*
		 * Extracted array structure:
		 * array(
		 *     0: Rate
		 *     1: Taxes
		 *     2: Total
		 *     4: Total for Stay 
		 * )
		 */
		if (!empty($matches[0])) {
			foreach ($matches[0] as $key => $match) {
				if (!in_array($key, array(0, 1, 2))) { 
					continue;
				}

				switch ($key) {
					case 0:
						$label = 'Subtotal';
						break;
					
					case 1:
						$label = 'Tax';
						break;

					default:
						$label = 'DigitalTotal';
						break;
				}

				preg_match_all('/<td.*?>.*?<\/td>/is', $match, $subMatches);

				if (!empty($subMatches[0])) {									
					if (!empty($subMatches[0][3])) {
						$amount = strip_tags($subMatches[0][3]);
						$amount = preg_replace($pattern['pattern_amount'], '', $amount);
						$amount = sprintf('%01.2f', $amount);
						$result[$label] = $amount;
					}					
				}
			}
		}	

		return $result;	
	}
}
