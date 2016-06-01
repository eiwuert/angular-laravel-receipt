<?php
require_once __DIR__ . '/EmailProcessAbstract.php';

/**
 * EmailProcessFactory class
 *
 * @author khanhdn
 */
class EmailProcessFactory {
  public static function create($type) {
    $type = strtolower($type);
    $type = (stripos($type, 'amazon') !== false) ? 'e' : $type;
    
    switch ($type) {
      // Email receipt, currently we have only Amazon merchant
      case 'e':
        require_once __DIR__ . '/adapter/AmazonMerchantParser.php';
        return new AmazonMerchantParser();
        
      // Virtual receipt
      case 'v':
        require_once __DIR__ . '/adapter/VirtualReceiptParser.php';
        return new VirtualReceiptParser();
        
      // Invoice Receipt, will implement in future
      case 'i':
        return;
        
      default:
        throw new Exception('Class for "{$type}" does not exist.');
    }
  }
}
