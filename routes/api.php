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
Route::prefix('auth')->group(function () {
	Route::post('login', 'JwtAuthenticateController@authenticate');
	Route::post('admin-login', 'JwtAuthenticateController@authenticateAdmin');
	Route::post('reset-password', 'PasswordController@sendResetPasswordEmail');
	Route::post('signup', 'JwtAuthenticateController@createUser');
});

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

	Route::resource('products', 'ProductController')->only('update');
	Route::resource('staffs', 'StaffController');

});

// API route group that we need to protect...
Route::group(['middleware' => ['role:systems-admin|fx-ops|fx-ops|fx-ops-manager|treasury-ops|client']], function () {

	// Client Routes...
	Route::prefix('clients')->group(function () {
		Route::get('search/{term}', 'ClientController@search');
		Route::get('{client_id}/accounts', 'ClientController@accounts');
		Route::get('{id}', 'ClientController@show');
		Route::get('', 'ClientController@index');

		Route::post('{client_id}/account', 'ClientController@addAccount');
		Route::post('{client_id}/avatar', 'ClientController@updateAvatar');
		Route::post('{client_id}/identity', 'ClientController@updateIdentity');
		Route::post('{client_id}/validate_kyc', 'ClientController@validateKYC');
		Route::post('cooperate', 'ClientController@storecooperate');
		Route::post('individual', 'ClientController@storeIndividual');
		Route::post('{id}/upload', 'ClientController@updateAvatar');
		Route::post('{id}', 'ClientController@update');
	});

	// Transaction Routes...
	Route::prefix('transactions')->name('transaction.')->group(function () {
		Route::put('{id}/treat', 'TransactionController@treatTransaction')->name('treat');
		Route::put('{id}/approve', 'TransactionController@approveTransaction')->name('approve');
		Route::put('{id}/fulfil', 'TransactionController@fulfilTransaction')->name('fulfil');

		Route::patch('{id}/cancel', 'TransactionController@cancelTransaction')->name('cancel');
		Route::patch('{id}/reject', 'TransactionController@rejectTransaction')->name('reject');
		Route::patch('{id}/update', 'TransactionController@updateTransaction')->name('update');

		Route::post('', 'TransactionController@requestTransaction')->name('request');

		Route::get('paginate', 'TransactionController@paginate')->name('pagination');
		Route::get('{id}', 'TransactionController@show')->name('show');
		Route::get('', 'TransactionController@index')->name('all');
	});

	// Account Routes...
	Route::resource('accounts', 'AccountController')->only('index');

	// Product Rates...
	Route::get('products/rates', 'ProductController@getRates');

	// Dashboard Routes...
	Route::prefix('dashboard')->group(function () {
		Route::get('counts', 'DashboardController@counts');
		Route::get('figures', 'DashboardController@figures');
		Route::get('buckets', 'DashboardController@bucketBalance');
		Route::get('timeline/wacc', 'DashboardController@WACCTimeline');
		Route::get('timeline/rate', 'DashboardController@rateTimeline');
		Route::get('recent_transactions', 'TransactionController@recentTransactions');
		Route::get('recent_events', 'DashboardController@recentEvents');
	});
});

// Public Routes for fill-ables...
Route::resource('products', 'ProductController')->only('index');
