<?php

class ContactV2Controller extends BaseV2Controller 
{
    /*
     * POST /contact
     * 
     * Create new contact mail
     */
    public function postIndex() {
        // Build the validator for inputs
        $validator = Validator::make(Input::all(), array(
            'fullname' => array('required'),
            'email' => array('required', 'email'),
            'message' => array('required')), array('email.required' => 'Your email address is invalid.'));
        
        // Check if the validator fails
        if ($validator->fails()) {
            $messages = $validator->messages()->all();
            $jsend = JSend\JSendResponse::fail($messages);
            return $jsend->respond();
        }
        
        Mail::send(
            'emails.contact.contact', 
            array (
                'name' => 'support@receiptclub.com',
                'myname' => Input::get('fullname'),
                'email' => Input::get('email'),
                'phone' => Input::get('phone'),
                'mymessage' => Input::get('message')
            ), 
            function($message) {
                $subject = Input::get('subject') ? Input::get('subject') : 'Email contact';
                $message->to('support@receiptclub.com')->subject($subject);
            }
        );
        $jsend = JSend\JSendResponse::success();
        return $jsend->respond();
    }
}
