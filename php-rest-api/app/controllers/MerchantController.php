<?php
/**
 * Controller for receipts
 */
class MerchantController extends BaseController 
{
	
	public function getIndex()
	{
		// Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
        
        $type = Input::get('list', '');
        if ($type == 'autocombine') {
            return Response::json(Merchant::getAutoCombineList($userToken->UserID));
        }
		
		return Response::json(Merchant::getAutoCompleteList($userToken->UserID));
	}
    
    public function postIndex()
    {
        // Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! $userToken = UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
        
        $post             = Input::all();
        $merchant         = new Merchant();
        $merchant->Name   = $post['Name'];
        $merchant->UserID = $userToken->UserID;
        
        isset($post['Logo']) && $merchant->Logo               = $post['Logo'];
        isset($post['Address']) && $merchant->Address         = $post['Address'];
        isset($post['City']) && $merchant->City               = $post['City'];
        isset($post['State']) && $merchant->State             = $post['State'];
        isset($post['ZipCode']) && $merchant->ZipCode         = $post['ZipCode'];
        isset($post['CountryCode']) && $merchant->CountryCode = $post['CountryCode'];
        isset($post['PhoneNumber']) && $merchant->PhoneNumber = $post['PhoneNumber'];
        isset($post['NaicsCode']) && $merchant->NaicsCode     = $post['NaicsCode'];
        isset($post['SicCode']) && $merchant->SicCode         = $post['SicCode'];
        isset($post['MccCode']) && $merchant->MccCode         = $post['MccCode'];
        
        $merchant->save();
        
        return Response::json(array('MerchantID' => $merchant->MerchantID), 200);
    }
    
    public function putIndex () 
    {
        // Need to check authentication
		if (empty($_SERVER['HTTP_AUTH_TOKEN']) || ! UserToken::checkAuth($_SERVER['HTTP_AUTH_TOKEN'])) {
			return Response::json(array('message' => 'The authentication is failed.'), 401);
		}
        
        $put = Input::all();
        
    }
    
    /*
	public function getAddNewMerchantSafely ()
    {
        $mcName = Input::get('mc', '');
        //dd($mcName);
        //DB:: table('Merchant')->insert(array('Name' => $mcName, 'UserID' => 0));
        $newMc = new Merchant();
        $newMc->Name = $mcName;
        $newMc->UserID = 0;
        $newMc->save();
        $mid = $newMc->MerchantID;

        $merchants = DB::table('Merchant')
            ->where('Name', $mcName)
			->where('MerchantID', '<>', $mid)
            ->lists('MerchantID');
		if (count($merchants)) {
			DB::table('Receipt')->whereIn('MerchantID', $merchants)->update(array('MerchantID' => $mid));
		}
		DB::table('Receipt')->where('MerchantID', 0)
			->where('MerchantName', $mcName)
			->update(array('MerchantID' => $mid));

		if (count($merchants)) {
			DB::table('Merchant')->whereIn('MerchantID', $merchants)->delete();
		}
    }
    */
}