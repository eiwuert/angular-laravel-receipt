<?php
/**
 * This is VirtualReceiptParser class which ported from receipt_email.module (anhct)
 *
 */
class VirtualReceiptParser extends EmailProcessAbstract {
  /**
   * Construct method
   */
  public function __construct() {
    parent::__construct();
  }
  
  /**
   * Analyze email order notification of merchant
   * 
   * @param  string $string
   * @return void
   */
  public function analyze($string, $email) {
    $rawData       = $string;
    $string        = strip_tags($string);
    $param_receipt = array('merchant', 'date', 'tax', 'discount', 'item');
    $param_item    = array('title', 'quantity',  'price');
    $delimiter     = "\n";
    $delimiter1    = ':';
    $receipt = new DtoReceipt;    
    $i       = 0;
    $total   = 0;
    $arr     = explode($delimiter, $string);
    $items   = array();
    $formatCurrencyOption = array('currency_sign' => '', 'thousands_sep' => '');

    foreach ($arr as $value) {
      if (empty($value)) continue;

      $each = explode($delimiter1, $value);
      if (isset($each[1])) {
        $key = strtolower(trim($each[0]));
        $val = strtolower(trim($each[1]));
        
        if (!in_array($key, $param_receipt)) continue; 
        
        if ($key == 'item') {
          $item = explode(',', $val);          
          for ($j = 0; $j <= 2; $j++) {
            $items[$i][$param_item[$j]] = trim($item[$j]);
          }

          $items[$i]['total'] = $this->formatCurrency($items[$i]['quantity'] * $items[$i]['price'], $formatCurrencyOption);
          $total += $items[$i]['total'];
          $i++;
        } else {
          if ($key == 'date') {
            $receipt->PurchaseTime = strtotime($val);
          } elseif ($key == 'merchant') {
            $receipt->MerchantName = $val;
          } else {
			$key = ucfirst($key);
            $receipt->$key = $val;
          }            
        }        
      }      
    }
    	
	if (!$items) {
		return false;
	}
	
    $receipt->VerifyStatus   = 0;      
    $receipt->ReceiptType    = 2;
    $receipt->Discount = isset($receipt->Discount) ? $receipt->Discount: 0;
    $receipt->ModifiedTime = $receipt->CreatedTime = time();
	$receipt->RawData = htmlentities($rawData, ENT_QUOTES, mb_detect_encoding($rawData));

    if (!empty($receipt->tax)) {
      preg_match('/([0-9\.]+)/', $receipt->Tax, $match);
      if (!empty($match[0])) {
        $receipt->Tax = $match[0];
      }
    }

    $receipt->Subtotal = ($total - $receipt->Discount)*(1 + $receipt->Tax/100);
    $receipt->Subtotal = $this->formatCurrency($receipt->Subtotal, $formatCurrencyOption);
    $receipt->DigitalTotal = $receipt->Subtotal;
    $receipt->EmailSender  = $email;

    $this->_orders[0] = $receipt;
    foreach ($items as $val) {
      $receiptItem = new DtoItem;            
      $receiptItem->Name     = $val['title'];
      $receiptItem->Quantity = $val['quantity'];
      $receiptItem->Price    = $this->formatCurrency($val['price'], $formatCurrencyOption);
      $receiptItem->Amount   = $val['total'];    
      $receiptItem->CategorizeStatus = 0;
      $receiptItem->Total = $this->formatCurrency($val['price']*$val['quantity']*(1 + $receipt->Tax/100), $formatCurrencyOption);
      
      $this->_orders[0]->Items[] = $receiptItem;
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
    $text = imap_fetchbody($imap_stream, $msg_number, 1);
    return imap_utf8($text);
  }
}
