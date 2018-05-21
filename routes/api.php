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


// Express Transaction...
Route::post('transactions/express', 'TransactionController@requestExpressTransaction');


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

	// Protected route...
	Route::get('users', 'JwtAuthenticateController@index');
});


// API route group that we need to protect...
Route::group(['middleware' => ['role:systems-admin|fx-ops|fx-ops-lead|fx-ops-manager|treasury-ops|client']], function () {

	// Client Routes...
	Route::post('clients/individual', 'ClientController@storeIndividual');
	Route::post('clients/cooperate', 'ClientController@storecooperate');
	Route::get('clients/{id}', 'ClientController@show');
	Route::resource('clients', 'ClientController')->only(['index']);

	// Transaction Routes...
	Route::get('transactions', 'TransactionController@index');
	Route::get('transactions/{id}', 'TransactionController@show');
	Route::post('transactions', 'TransactionController@requestTransaction');
	Route::put('transactions/{id}/treat', 'TransactionController@treatTransaction');
	Route::put('transactions/{id}/approve', 'TransactionController@approveTransaction');
	Route::put('transactions/{id}/fulfil', 'TransactionController@fulfilTransaction');
	Route::patch('transactions/{id}/cancel', 'TransactionController@cancelTransaction');

	// Account Routes...
	Route::resource('accounts', 'AccountController')->only('index');

});


// Public Routes for Fillables...
Route::resource('products', 'ProductController')->only('index');
