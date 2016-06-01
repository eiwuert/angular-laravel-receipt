<?php
/**
 * Century Car Service
 *
 * @author khanhdn
 */
class CenturyCarServiceParser extends EmailProcessAbstract {
	protected $_patterns = array(   
		'_getPurchaseTime' => array(
			array(			
				'start_delimiter' => 'Payments Received:',
				'end_delimiter'   => '</tr>',
				'pattern_content' => '/<td.*?>(.*)<\/td>/is',
				'pattern_amount'  => '/(\d{2}\/\d{2}\/\d{4})/'			
			)
		), 
   		'_getTotalAmount' => array(
			array(
				'start_delimiter' => 'Total</',
				'end_delimiter'   => '</tr>',
				'pattern_content' => '/<td.*?>(.*)<\/td>/is',
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
		// Is Century Car Service email template?
		if (!preg_match('/.*<table.*>CENTURY CAR SERVICE.*<\/p>/is', $content)) {
			return false;
		}

		
		$receipt = new DtoReceipt;
        $receipt->Items = array();        
        $receipt->ReceiptType  = 2; // Email Receipt
        $receipt->VerifyStatus = 0;
        $receipt->MerchantName = 'Century Car Service';
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
	 * Get payments received time
	 *
	 * @param string $content
     * @param array  $pattern
     * @param object $order
	 */
	protected function _getPurchaseTime($content, array $pattern, $order) {		
		foreach ($pattern as $pat) {
			$purchaseTime = $this->_extract($content, $pat);

			if ($purchaseTime !== null) {				
				$order->PurchaseTime = strtotime($purchaseTime);
				break;
			}
		}
	}

	/**
   	 * Get order grand total
     * 
     * @param string $content
     * @param array  $pattern
     * @param object $order
     */
	protected function _getTotalAmount($content, array $pattern, $order) {
		foreach ($pattern as $pat) {
			$total = $this->_extract($content, $pat);
      
			if ($total !== null) {
				$order->Subtotal = $order->DigitalTotal = sprintf('%01.2f', $total);
				break;
			}
		}
	}
}
