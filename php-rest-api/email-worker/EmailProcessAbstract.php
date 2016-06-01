<?php
require_once 'DtoReceipt.php';
require_once 'DtoItem.php';

/**
 * An abstract class for analytics email
 *
 * @author khanhdn
 */
abstract class EmailProcessAbstract {
  /**
   * Variable contain order items
   * 
   * @var array object 
   */
  protected $_orders;
  
  /**
   * Patterns for extract information
   * 
   * @var array
   */
  protected $_patterns;
  
  /**
   * Message has attachment
   */
  protected $_hasAttachment = false;

  /**
   * Construct method
   */
  public function __construct() {
      $this->_orders = array();
  }

  /**
   * Set of patterns for analyzing email content
   * 
   * @param array $patterns
   */
  public function setPattern(array $patterns) {
    $this->_patterns = $patterns;
  }
  
  /**
   * Analyze email order notification of merchant
   * 
   * @param  string $content
   * @param  string $email
   * @return void
   */
  abstract public function analyze($content, $email);
  
  /**
   * Get order items
   * 
   * @return DtoReceipt
   */
  public function getOrders() {
    return $this->_orders;
  }

  /**
   * Formats currency, using PHP number_format with custom format string. 
   * 
   * @author khanhdn
   * @param string $number
   * @param array $attributes
   *    Possible values:
   *      array(
   *        'currency_sign' => '$',
   *        'position' => 'left', // Only accept 2 values: 'left' and 'right'
   *        'number_wrapper_prefix' => '',
   *        'number_wrapper_suffix' => '',
   *        'currency_wrapper_suffix' => '',
   *        'currency_wrapper_suffix' => '',
   *        'decimals' => 2,
   *        'dec_point' => '.',
   *        'thousands_sep' => ',',
   *      );
   * 
   * @return string 
   */
  public function formatCurrency($number = 0, $attributes = array()) {
    $default_attributes = array(
      'currency_sign' => '$',
      'position' => 'left',
      'number_wrapper_prefix' => '',
      'number_wrapper_suffix' => '',
      'currency_wrapper_prefix' => '',
      'currency_wrapper_suffix' => '',
      'decimals' => 2,
      'dec_point' => '.',
      'thousands_sep' => '',
    );
    
    $attributes = array_merge($default_attributes, $attributes);
    
    // To prevent the rounding that occurs when next digit after last significant decimal is 5
    if (($number * pow(10 , $attributes['decimals'] + 1) % 10 ) == 5) {
      $number -= pow(10 , -($attributes['decimals'] + 1));
    } else {
      $number = number_format($number, $attributes['decimals'], $attributes['dec_point'], $attributes['thousands_sep']);  
    }
    
    // @see http://113.160.50.82/dev/issues/5367
    // Temporary fix the number which less than 0 to 0
    if (strpos($number, '-') === 0) {
        $number = number_format(0, $attributes['decimals'], $attributes['dec_point'], $attributes['thousands_sep']);  
    }
    
    if ('right' == $attributes['position']) {
      return $attributes['number_wrapper_prefix']
            . $number
            . $attributes['number_wrapper_suffix']
            . $attributes['currency_wrapper_prefix']
            . $attributes['currency_sign']
            . $attributes['currency_wrapper_suffix'];
    }
    
    return $attributes['currency_wrapper_prefix']
            . $attributes['currency_sign']
            . $attributes['currency_wrapper_suffix']
            . $attributes['number_wrapper_prefix']
            . $number
            . $attributes['number_wrapper_suffix'];
            
  }

  /**
   * Check message has attachment
   * 
   * @param object imap_fetchstructure $part
   * @return boolean
   */
  public function hasAttachment($part) { 
    if (isset($part->parts)){ 
      foreach ($part->parts as $partOfPart){ 
        $this->_hasAttachment = $this->hasAttachment($partOfPart); 
      } 
    } else { 
        if (isset($part->disposition)){ 
          if (strtolower($part->disposition) == 'attachment'){ 
            return true; 
          } 
        } 
    }

    return $this->_hasAttachment;
  }

  /**
   * Fetch the body content
   * 
   * @param resource $imap_stream 
   * @param int      $msg_number
   */
  abstract public function fetchBody($imap_stream, $msg_number);

  /**
   * Extract information from string
   * 
   * @param string $content
   * @param array  $pattern
   * @return mixed
   */
  protected function _extract($content, $pattern) {    
    $result = null;
    $beginPos = strripos($content, $pattern['start_delimiter']);    
        
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