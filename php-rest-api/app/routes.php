<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the Closure to execute when that URI is requested.
|
*/

Route::get('/', function()
{
	return Redirect::to('http://receiptclub.com');
});

if (!defined('API_VERSION') || API_VERSION == '1') {
    Route::controller('users', 'UserController');
    Route::controller('contact', 'ContactController');
    Route::controller('attachments', 'FileController');
    Route::controller('categories', 'CategoryController');
    Route::controller('receipts', 'ReceiptController');
    Route::controller('receipt-images', 'ReceiptImageController');
    Route::controller('receipt-images-two', 'ReceiptImageTwoController');
    Route::controller('items', 'ItemController');
    Route::controller('merchants', 'MerchantController');
    Route::controller('trips', 'TripController');
    Route::controller('reports', 'ReportController');
    Route::controller('export', 'ExportController');
    Route::controller('maintenance', 'MaintenanceController');
} else if (!defined('API_VERSION') || API_VERSION == '2') {
    Route::controller('users', 'UserV2Controller');
    Route::controller('contact', 'ContactV2Controller');
    Route::controller('auth', 'AuthV2Controller');
    
    Route::get('count/receipts','CountV2Controller@countReceipts');
    Route::get('count/trips','CountV2Controller@countTrips');
    Route::get('count/reports','CountV2Controller@countReports');
    Route::resource('count','CountV2Controller');
    
    Route::resource('categories', 'CategoryV2Controller');
    
    Route::resource('tripitems', 'TripItemsV2Controller');
    Route::post('tripitems-multiple','TripItemsV2Controller@create');
    Route::put('tripitems-multiple','TripItemsV2Controller@edit');
    Route::delete('tripitems','TripItemsV2Controller@delete');
    Route::post('tripitems/getall', 'TripItemsV2Controller@getall');
    
    Route::resource('receipts/{id}/items', 'ReceiptItemV2Controller');
    Route::get('receipts/count', 'ReceiptV2Controller@count');
    Route::resource('receipts', 'ReceiptV2Controller');
    Route::delete('receipts','ReceiptV2Controller@delete');
    Route::post('receipts-multiple','ReceiptV2Controller@create');
    Route::put('receipts-multiple','ReceiptV2Controller@edit');
    Route::post('receipts/getall', 'ReceiptV2Controller@getall');
    
    Route::resource('trips/{id}/items', 'TripItemV2Controller');
    Route::get('trips/count', 'TripV2Controller@count');
    Route::resource('trips', 'TripV2Controller');
    Route::delete('trips','TripV2Controller@delete');
    Route::post('trips-multiple','TripV2Controller@create');
    Route::put('trips-multiple','TripV2Controller@edit');
    Route::post('trips/getall', 'TripV2Controller@getall');
    
    Route::get('reports/count', 'ReportV2Controller@count');
    Route::get('reports-custom', 'ReportV2Controller@getcustom');
    Route::resource('reports', 'ReportV2Controller');
    Route::delete('reports','ReportV2Controller@delete');
    Route::post('reports-multiple','ReportV2Controller@create');
    Route::put('reports-multiple','ReportV2Controller@edit');
    Route::put('reports-approve','ReportV2Controller@approve');
    Route::post('reports/getall', 'ReportV2Controller@getall');
    
    Route::get('merchants/count', 'MerchantV2Controller@count');
    Route::resource('merchants', 'MerchantV2Controller');
    Route::post('merchants-multiple','MerchantV2Controller@create');
    Route::put('merchants-multiple','MerchantV2Controller@edit');
    
    Route::resource('receipt-images', 'ReceiptImageV2Controller');
    Route::post('ocr-request','ReceiptImageV2Controller@ocr');

    Route::resource('items/count', 'ItemV2Controller@count');
    Route::resource('items', 'ItemV2Controller');
    Route::delete('items','ItemV2Controller@delete');
    Route::get('items-free','ItemV2Controller@checkfree');
    Route::post('items-multiple','ItemV2Controller@create');
    Route::put('items-multiple','ItemV2Controller@edit');
    Route::post('items/getall', 'ItemV2Controller@getall');
    
    Route::resource('devices', 'DeviceV2Controller');
    Route::delete('devices','DeviceV2Controller@delete');
    
    Route::controller('report-trip', 'ConvertReportTripV2Controller');
    Route::controller('currency', 'CurrencyV2Controller');
    
    Route::resource('settings', 'SettingsV2Controller');
    Route::controller('commons', 'CommonV2Controller');

//    Route::resource('attachments', 'V2\FileController');
//    Route::resource('merchants', 'V2\MerchantController');
//    Route::resource('export', 'V2\ExportController');
    
} else {
    Route::get('login', 'AdministratorController@showLogin')->before('guest');
    Route::post('login', 'AdministratorController@doLogin');
    
    Route::get('merchant', array('as' => 'merchant', 'uses' => 'AdministratorController@showMerchant'))->before('merchant');
    Route::post('merchant/{id}', array('as' => 'merchant-delete', 'uses' => 'AdministratorController@deleteMerchant'));
    Route::get('merchant/{id}', array('as' => 'merchant-show', 'uses' => 'AdministratorController@showSingleMerchant'))->before('merchant');
    Route::post('merchant/{id}', array('as' => 'merchant-edit', 'uses' => 'AdministratorController@editMerchant'));
    Route::post('merchant-add', array('as' => 'merchant-add', 'uses' => 'AdministratorController@addMerchant'));
    
    Route::get('home', array('as' => 'home', 'uses' => 'AdministratorController@showHome'))->before('admin');
    Route::post('home', 'AdministratorController@createMaintenance');
    Route::post('home/{id}', array('as' => 'maintenance-delete', 'uses' => 'AdministratorController@deleteMaintenance'));
    
    Route::get('maintenance/{id}', array('as' => 'maintenance-show', 'uses' => 'AdministratorController@showMaintenance'))->before('admin');
    Route::post('maintenance/{id}', array('as' => 'maintenance-edit', 'uses' => 'AdministratorController@editMaintenance'));
    
    Route::get('logout', array('as' => 'logout', 'uses' => 'AdministratorController@doLogout'))->before('auth');
}