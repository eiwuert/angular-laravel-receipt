<?php
class ContactController extends BaseController 
{
	public function postIndex()
	{
		// Build the validator for inputs
		$validator = Validator::make(
			Input::all(),
			array(
				'fullname' => array('required'),
				'email' => array('required', 'email'),
				'message' => array('required')
			),
			array('email.required' => 'Your email address is invalid.'));
		
		// Check if the validator fails
		if ($validator->fails()) {
			$messages = $validator->messages();
			return Response::json(array('message' => $messages->all()), 500);
		}else{
			
		
		//$users = User::getFullNamesByEmails(Input::get('emails'));
		Mail::send(
			'emails.contact.contact', array(
				//'name' => $user->Name
				'name' => 'support@receiptclub.com',
				'myname' => Input::get('fullname'),
				'email' => Input::get('email'),
				'phone' => Input::get('phone'),
				'mymessage' => Input::get('message'),
			), function($message) {
				$message->to('support@receiptclub.com')->subject('Email contact');
			});
			return Response::make('', 204);
		}
	}

    public function postEmulatePush ()
    {
        $uid = Input::get('uid', 0);

        $msg = '{"event":"file-processed","content":"{\"uploadType\":\"email\",\"obReceipt\":{\"ReceiptID\":41605,\"MerchantName\":\"AMAZON\",\"OriginalTotal\":\"155.84\",\"DigitalTotal\":\"155.84\",\"IsNew\":1,\"CurrencyCode\":\"USD\",\"VerifyStatus\":0,\"ReceiptType\":2,\"PaymentType\":4,\"PurchaseTime\":\"2014-12-31T00:00:00.750Z\",\"Tax\":\"0.0000\",\"Discount\":\"0.0000\",\"CreatedTime\":\"2014-12-31T18:11:29.507Z\",\"IsArchived\":0,\"IsOpened\":0,\"ItemCount\":0,\"MerchantReview\":null,\"Memo\":null,\"CouponCode\":null,\"RebateAmount\":null,\"EmailSender\":null,\"MerchantID\":5,\"MerchantLogo\":\"https:\\/\\/www.receiptclub.com\\/api\\/v1\\/files\\/merchant_logo\\/amazon.jpg\",\"MerchantPhone\":null,\"MerchantAddress\":null,\"MerchantCountry\":null,\"MerchantCity\":null,\"MerchantState\":null,\"MerchantCode\":null,\"MerchantNaicsCode\":null,\"MerchantSicCode\":null,\"MerchantMccCode\":null,\"More\":{\"Memo\":null,\"ItemCount\":0,\"MerchantReview\":null,\"Tags\":[],\"IsEmpty\":true},\"Items\":[],\"IsReported\":0,\"Attachments\":[],\"App\":null,\"Category\":null,\"ExpensePeriod\":null,\"IsChecked\":false,\"IsCollapsed\":true,\"DeletedFileIDs\":[]},\"processTime\":-1}"}';

        Push::toWeb($msg, 'file-processed', $uid);

        return Response::json('push sent', 200);
    }
}
