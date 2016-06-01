<?php
/**
 * Amazon email order notification analytics
 *
 * @author khanhdn
 */
class AmazonMerchantParser extends EmailProcessAbstract {
  protected $_patterns = array(    
    '_getSubtotal' => array(
      array(
        'start_delimiter' => 'Total Before Tax',
        'end_delimiter'   => '</tr>',
        'pattern_content' => '/<p.*?>(.*?)<\/p>/is',
        'pattern_amount'  => '/[0-9\.]+/'
      ),
      array(
        'start_delimiter' => 'Subtotal',
        'end_delimiter'   => '</tr>',
        'pattern_content' => '/<p(.*)>(.*)<\/p>/is',
        'pattern_amount'  => '/\p{Sc}\s*[0-9\.]+/'
      )
    ),
    '_getTotalAmount' => array(
      array(
        'start_delimiter' => 'Total',
        'end_delimiter'   => '</tr>',
        //'pattern_content' => '/<p(.*)>(.*)<\/p>/is',
        'pattern_content' => '/<p.*?>(.*?)<\/p>/is',
        'pattern_amount'  => '/[0-9\.]+/'
      ),
      array(
        'start_delimiter' => 'Shipment Total',
        'end_delimiter'   => '</tr>',
        'pattern_content' => '/<p(.*)>(.*)<\/p>/is',
        'pattern_amount'  => '/\p{Sc}\s*[0-9\.]+/'
      )
    ),
    '_getShippingFee' => array(      
      array(
        'start_delimiter' => 'Shipping &',
        'end_delimiter'   => '</tr>',
        'pattern_content' => '/<p(.*)>(.*)<\/p>/is',
        'pattern_amount'  => '/\p{Sc}\s*[0-9\.]+/'
      ),
    ),
    '_getDiscountAmount' => array(
      array(
        'start_delimiter' => 'Discount',
        'end_delimiter'   => '</tr>',
        'pattern_content' => '/<p(.*)>(.*)<\/p>/is',
        'pattern_amount'  => '/\p{Sc}\s*[0-9\.]+/'
      )
    ),
    '_getTaxFee' => array(
      array(
        'start_delimiter' => 'Tax',
        'end_delimiter'   => '</tr>',
        'pattern_content' => '/<p(.*)>(.*)<\/p>/is',        
        'pattern_amount'  => '/\p{Sc}\s*[0-9\.]+/'
      ),
      array(
        'start_delimiter' => 'Vat',
        'end_delimiter'   => '</tr>',
        'pattern_content' => '/<p(.*)>(.*)<\/p>/is',
        'pattern_amount'  => '/\p{Sc}\s*[0-9\.]+/'
      )
    ),
    '_getItems' => array(	      
      array(
        'pattern_items_wrapper'       => '/(Delivery estimate:|Shipping estimate for these items:).*<table.*?>(<tr[^\>]?>.*?Sold by:.*?<\/tr>)<\/table>/is',
        'pattern_item_row_wrapper'    => '/<tr[^\>]?>(.*?)<\/tr>/is',
        'pattern_item_column_wrapper' => '/<td.*?>(.*?)<\/td>/is',
        'pattern_item_name_wrapper'   => '/<span.*?>(.*?)<\/span>/is',
        'pattern_item_price'          => '/\d+\.?\d*/',
        'col_item_qty'                => 1,
        'col_item_name'               => 2,
        'col_item_price'              => false,
      ),
      array(
        'pattern_items_wrapper'       => '/(Delivery estimate:|Shipping estimate for these items:).*<table[^\>]+>\s+(<tr[^\>]+>.*?Sold by:.*?<\/tr>)\s+<\/table>/is',
        'pattern_item_row_wrapper'    => '/<tr[^\>]+>(.*?)<\/tr>/is',
        'pattern_item_column_wrapper' => '/<td[^\>]+>(.*?)<\/td>/is',
        'pattern_item_name_wrapper'   => '/<span[^\>]+>(.*?)<\/span>/is',
        'pattern_item_price'          => '/\d+\.?\d*/',
        'col_item_qty'                => 1,
        'col_item_name'               => 2,
        'col_item_price'              => false,
      ),
      array(
        'pattern_items_wrapper'       => '/(Delivery estimate:|Shipping estimate for these items:).*<table.*?>(<tr[^\>]?>.*?Sold by:.*?<\/tr>)<\/tbody><\/table>/is',
        'pattern_item_row_wrapper'    => '/<tr[^\>]?>(.*?)<\/tr>/is',
        'pattern_item_column_wrapper' => '/<td.*?>(.*?)<\/td>/is',
        'pattern_item_name_wrapper'   => '/<span.*?>(.*?)<\/span>/is',
        'pattern_item_price'          => '/\d+\.?\d*/',
        'col_item_qty'                => 1,
        'col_item_name'               => 2,
        'col_item_price'              => false,
      ),
      array(
        'pattern_items_wrapper'       => '/(<table[^\>]+>.*(<tr[^\>]+>.*?Sold by.*?<\/div>.*?<\/td>.*?<\/tr>.*<td.*colspan=("3"|3).*>))/is',        
        'pattern_item_row_wrapper'    => '/<tr[^\>]+>(.*?<\/div>.*<\/td>.*)<\/tr>/is',
        'pattern_item_column_wrapper' => '/<td[^\>]+>(.*?)<\/td>/is',
        'pattern_item_name_wrapper'   => '/<span[^\>]+>(.*?)<\/span>/is',
        'pattern_item_price'          => '/\d+\.?\d*/',
        'col_item_qty'                => false,
        'col_item_name'               => 1,
        'col_item_price'              => 5,
      ), 	  
      array(
        'pattern_items_wrapper'       => '/<table.*?id=("itemDetails"|itemDetails).*?>(.*?Sold by.*?<\/span><\/strong>.*?)<\/table>/is',
        'pattern_item_row_wrapper'    => '/(<tr.*>.*<\/span><\/strong>.*<\/tr>)/is',
        'pattern_item_column_wrapper' => '/<td.*?>(.*?)<\/td>/is',
        'pattern_item_name_wrapper'   => '/<span.*?>(.*?)<\/span>/is',
        'pattern_item_price'          => '/\d+\.?\d*/',
        'col_item_qty'                => false,
        'col_item_name'               => 1,
        'col_item_price'              => 5,      
      )	 
    ),
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
   * @return void
   */
  public function analyze($content, $email) {
    //file_put_contents(__DIR__ . '/amazon.html', $content);
    // Get the purchase date
    $purchase = null;
    if (preg_match_all('/Sent:(.*?)To:/is', $content, $purchase)) {
      $purchase = strip_tags(array_pop($purchase[1]));
      $purchase = preg_replace('/[^a-zA-Z0-9,:]/', ' ', $purchase);
      $purchase = preg_replace('/\s+/s', ' ', $purchase);
      $purchase = trim($purchase);
    }
    
    // Get all orders in mail
    $orderPatterns = array(
      "/<span[^\>]+>Order\s*#:<\/span>.*?<\/table>/is",
      "/<span[^\>]+>Order\s*#.*?<a.*?>.*?<\/a>.*<\/span>.*?<\/table>/is",
      "/Shipment Details.*?<\/span>.*?<\/table>\s*?<p/is"
    );

    foreach ($orderPatterns as $pattern) {
      if (preg_match_all($pattern, $content, $matches)) {
        break;
      }
    }

    //var_dump($pattern);
    unset($pattern);
	  
    if (empty($matches[0])) return;

    // FIXME: Temporary set $orderId as numeric index. In future, it should be Amazon order
    foreach ($matches[0] as $orderId => $match) {
      // Extract order number
      preg_match('/<a[^\>]+>(.*?[0-9-]+.*?)<\/a>/is', $match, $m);		
	
      if (!empty($m[1])) {        
		    //$m[1] = strip_tags($m[1]);

        $this->_orders[$orderId] = new DtoReceipt;
        $this->_orders[$orderId]->Items = array();
        $this->_orders[$orderId]->PurchaseTime = !is_array($purchase) ? strtotime($purchase) : time();
        $this->_orders[$orderId]->ReceiptType  = 2; // Email Receipt
        $this->_orders[$orderId]->VerifyStatus = 0;
        $this->_orders[$orderId]->MerchantName = 'Amazon';
        //$this->_orders[$orderId]->EmailSender  = $email;
        $this->_orders[$orderId]->CreatedTime  = $this->_orders[$orderId]->ModifiedTime = time();

        // FIXME: Temporary solution, we only print the content after string 
        // 'Content-Transfer-Encoding: quoted-printable'
        $str = 'Content-Transfer-Encoding: quoted-printable';
        $tmpContent = $content;
        $tmpPos     = stripos($content, $str);
        if ($tmpPos !== false) {
          $tmpContent = substr($content, $tmpPos);
        }

        $this->_orders[$orderId]->RawData = htmlentities($tmpContent, ENT_QUOTES, 'UTF-8');

        foreach ($this->_patterns as $method => $pattern) {
          if (method_exists($this, $method)) {            
            $this->$method($match, $pattern, $this->_orders[$orderId]);
          }
        }
      }
    }
    
    return true;
  }

  /**
   * Get order grand total
   * 
   * @param string $content
   * @param array  $pattern
   */
  protected function _getSubtotal($content, array $pattern, $order) {
    foreach ($pattern as $pat) {      
      $subtotal = $this->_extract($content, $pat);     
      
      if ($subtotal !== null) {
        $order->Subtotal = sprintf('%01.2f', $subtotal);
        break;
      }
    }
  }
  
  /**
   * Get order grand total
   * 
   * @param string $content
   * @param array  $pattern
   */
  protected function _getTotalAmount($content, array $pattern, $order) {
    foreach ($pattern as $pat) {      
      $total = $this->_extract($content, $pat);     
      
      if ($total !== null) {
        $order->DigitalTotal = sprintf('%01.2f', $total);
        break;
      }
    }
  }

  /**
   * Get order shipping fee
   * 
   * @param string $content
   * @param array  $pattern
   */
  protected function _getShippingFee($content, array $pattern, $order) {
    foreach ($pattern as $pat) {
      $fee = $this->_extract($content, $pat);
      
      if ($fee !== null) { 
        $fee = preg_replace("/[^0-9.]/", "", $fee);       
        $order->ShippingFee = sprintf('%01.2f', $fee);        
        break;
      }
    }
  }

  /**
   * Get order discount
   * 
   * @param string $content
   * @param array  $pattern
   */
  protected function _getDiscountAmount($content, array $pattern, $order) {
    foreach ($pattern as $pat) {
      $discount = $this->_extract($content, $pat);
      
      if ($discount !== null) {
        $discount = preg_replace("/[^0-9.]/", "", $discount);
        $order->Discount = sprintf('%01.2f', $discount);
        break;
      }
    }
  }
  
  /**
   * Get order tax
   * 
   * @param string $content
   * @param array  $pattern
   */
  protected function _getTaxFee($content, array $pattern, $order) {
    foreach ($pattern as $pat) {
      $tax = $this->_extract($content, $pat);
      
      if ($tax !== null) {
        $tax = preg_replace("/[^0-9.]/", "", $tax);
        
        // Convert tax amount to tax percent
        //if (!empty($tax) && $tax > 0 && $order->total > 0) {
        //  $tax = (100 * $tax)/($order->total - $tax);
        //}

        $order->Tax = sprintf('%01.2f', $tax);
        break;
      }
    }
  }

  /**
   * Get order items
   * 
   * @param string $content
   * @param array  $pattern
   * @return void
   */
  protected function _getItems($content, array $pattern, $order) {    
    foreach ($pattern as $pat) {
      if ($this->_getItemsByPattern($content, $pat, $order)) {
        break;
      }
    }
  }
  
  /**
   * Extract order items
   * 
   * @param string $content
   * @param array  $pattern
   * @return void
   */
  protected function _getItemsByPattern($content, $pattern, $order) {    
    preg_match($pattern['pattern_items_wrapper'], $content, $match); 

    if (empty($match[2])) return;
   
    preg_match_all($pattern['pattern_item_row_wrapper'], $match[2], $rows);

    if (empty($rows[1])) return;
    
    foreach ($rows[1] as $row) {          
      preg_match_all($pattern['pattern_item_column_wrapper'], $row, $cols);

      if (empty($cols[1])) break;
      
      $item = new DtoItem;
      $item->Quantity = 1;
      foreach ($cols[1] as $i => $col) {        
        if (!$i) continue;        

        if ($i == $pattern['col_item_qty']) $item->Quantity = (int) strip_tags($col);

        // Contain product name & product price
        if ($i == $pattern['col_item_name']) {
          $strName = strip_tags($col, '<span>');
          preg_match_all($pattern['pattern_item_name_wrapper'], $strName, $itemName);

          // Product name			
          if (!empty($itemName[1][0])) {				
            // Replace quote symbol
            $item->Name = str_replace("&quot;", '', $itemName[1][0]);	
            
            // Replace unnecessary spaces
            $item->Name = preg_replace('/\s+/', ' ', $item->Name);
            
            // Strip all HTML tags
            $item->Name = strip_tags($item->Name);
          }

          // Product price
          if ($pattern['col_item_price'] === false) {
            if (!empty($itemName[1][1])) {            
              if (preg_match($pattern['pattern_item_price'], $itemName[1][1], $price)) {
                $item->Price  = $price[0];
                $item->Amount = sprintf('%01.2f', $item->Price * $item->Quantity);
                $item->Total  = $item->Amount;
              }  
            }
          }          
        }

        if ($i == $pattern['col_item_price']) {
          if (preg_match($pattern['pattern_item_price'], strip_tags($col), $price)) {
            $item->Price  = $price[0];
            $item->Amount = sprintf('%01.2f', $item->Price * $item->Quantity);
            $item->Total  = $item->Amount;
          }   
        }
      }

      $order->Items[] = $item;
    }

    // In order to display receipt on Receiptclub.com, we should convert shipping fee to an item
    // of receipt
    if (!empty($order->ShippingFee) && $order->ShippingFee > 0) {
      $shippingItem = new DtoItem;
      $shippingItem->Price = $shippingItem->Amount = $shippingItem->Total = $order->ShippingFee;
      $shippingItem->Quantity = 1;
      $shippingItem->Name = 'Shipping Fee';
      $order->Items[] = $shippingItem;
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
}
