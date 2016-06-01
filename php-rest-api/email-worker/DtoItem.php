<?php
/**
 * Data transfer object of Receipt Item
 *
 * @author khanhdn
 */
class DtoItem {
    public $ItemID;
    public $ReceiptID;
    public $CategoryID = 0;
    public $Name;
    public $CreatedTime = 0;
    public $ModifiedTime = 0;
    public $ExpensePeriodFrom = 0;
    public $ExpensePeriodTo = 0;
    public $Quantity = 1;
    public $Price = 0;
    public $Amount = 0;
    public $Total = 0;
	public $Spender;
	public $Memo;
	public $CategorizeStatus = 0;
	public $UnspscCode;
	public $GpcCode;
	public $GtinCode;
	public $TaxDeductible;
	public $TaxDeductibleCategory;
	public $Manufacturer;
	public $ServiceProviderInfo;
	public $MaintenanceTerm;
	public $ReturnExpirationDate;
	public $WarrantyRegistration;
	public $WarrantyExpirationDate;
	public $WarrantyDocUri;
	public $InsuranceDocUri;
	public $UnitPrice;
	public $UnitCostBeforeTax;
	public $UnitCostAfterTax;
	public $Rate;
	public $Review;
}
