<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Authentication route
Route::post('auth/login', 'JwtAuthenticateController@authenticate');
Route::post('auth/admin-login', 'JwtAuthenticateController@authenticateAdmin');
Route::post('auth/reset-password', 'PasswordController@sendResetPasswordEmail');
Route::post('auth/signup', 'JwtAuthenticateController@createUser');

// Express Transaction...m
Route::post('transactions/express', 'TransactionController@requestExpressTransaction');

Route::get('clients/document/{dir}/{file_name}', 'ClientController@getDocument');

// API route group that we need to protect for only system-admins...
Route::group(['middleware' => ['role:systems-admin']], function () {

	// Route to create a new role
	Route::post('role', 'JwtAuthenticateController@createRole');
	// Route to create a new permission
	Route::post('permission', 'JwtAuthenticateController@createPermission');
	// Route to assign role to user
	Route::post('assign-role', 'JwtAuthenticateController@assignRole');
	// Route to attache permission to a role
	Route::post('attach-permission', 'JwtAuthenticateController@attachPermission');
	// Route to get all users
	Route::get('users', 'JwtAuthenticateController@index');

});


// API route group that we need to protect...
Route::group(['middleware' => ['role:systems-admin|fx-ops|fx-ops|fx-ops-manager|treasury-ops|client']], function () {

	// Client Routes...
	Route::get('clients/search/{term}', 'ClientController@search');
	Route::get('clients/{client_id}/accounts', 'ClientController@accounts');
	Route::get('clients/{id}', 'ClientController@show');
	Route::get('clients', 'ClientController@index');

	Route::post('clients/{client_id}/account', 'ClientController@addAccount');
	Route::post('clients/{client_id}/avatar', 'ClientController@updateAvatar');
	Route::post('clients/{client_id}/identity', 'ClientController@updateIdentity');
	Route::post('clients/{client_id}/validate_kyc', 'ClientController@validateKYC');
	Route::post('clients/cooperate', 'ClientController@storecooperate');
	Route::post('clients/individual', 'ClientController@storeIndividual');
	Route::post('clients/{id}/upload', 'ClientController@updateAvatar');
	Route::post('clients/{id}', 'ClientController@update');


	// Transaction Routes...
	Route::put('transactions/{id}/treat', 'TransactionController@treatTransaction');
	Route::put('transactions/{id}/approve', 'TransactionController@approveTransaction');
	Route::put('transactions/{id}/fulfil', 'TransactionController@fulfilTransaction');
	Route::patch('transactions/{id}/cancel', 'TransactionController@cancelTransaction');
	Route::patch('transactions/{id}/reject', 'TransactionController@rejectTransaction');
	Route::patch('transactions/{id}/update', 'TransactionController@updateTransaction');
	Route::get('transactions/{id}', 'TransactionController@show');
	Route::post('transactions', 'TransactionController@requestTransaction');
	Route::get('transactions', 'TransactionController@index');


	// Account Routes...
	Route::resource('accounts', 'AccountController')->only('index');

	// Product Rates...
	Route::get('products/rates', 'ProductController@getRates');


	// Dashboard Routes...
	Route::get('dashboard/counts', 'DashboardController@counts');
	Route::get('dashboard/figures', 'DashboardController@figures');
	Route::get('dashboard/buckets', 'DashboardController@bucketBalance');
	Route::get('dashboard/timeline/wacc', 'DashboardController@WACCTimeline');
	Route::get('dashboard/timeline/rate', 'DashboardController@rateTimeline');
	Route::get('dashboard/recent_transactions', 'TransactionController@recentTransactions');
	Route::get('dashboard/recent_events', 'DashboardController@recentEvents');

});


// Public Routes for fill-ables...
Route::resource('products', 'ProductController')->only('index');
