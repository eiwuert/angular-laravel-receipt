<?php
/**
 * Data transfer object of Receipt
 *
 * @author khanhdn
 */
class DtoReceipt {
    public $ReceiptID;
    public $UserID;
    public $MerchantID;
    public $MerchantName;
    public $MerchantAddress;
    public $MerchantCountry;
    public $MerchantCity;
    public $MerchantState;
    public $MerchantCode;
    public $MerchantPhone;
    public $MerchantReview;
    public $OriginalTotal = 0;
    public $Discount = 0;
    public $Subtotal = 0;
    public $Tip = 0;
    public $Tax = 0;
    public $DigitalTotal = 0;
    public $CurrencyCode = 'USD';
    public $VerifyStatus = 0;
    public $ReceiptType;
    public $PaymentType = 4;
    public $PurchaseTime;
    public $CreatedTime;
	public $ModifiedTime;
	public $IsArchived = 0;
	public $IsOpened = 0;
	public $IsNew = 1;
	public $RawData;
	public $Memo;
	public $ItemCount = 0;
	public $CouponCode;
	public $RebateAmount;
	public $EmailSender;
}
