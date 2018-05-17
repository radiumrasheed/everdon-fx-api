<?php

use Illuminate\Http\Request;
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

Route::middleware('auth:api')->get('/user', function (Request $request) {
	return $request->user();
});

// Route to create a new role
Route::post('role', 'JwtAuthenticateController@createRole');
// Route to create a new permission
Route::post('permission', 'JwtAuthenticateController@createPermission');
// Route to assign role to user
Route::post('assign-role', 'JwtAuthenticateController@assignRole');
// Route to attache permission to a role
Route::post('attach-permission', 'JwtAuthenticateController@attachPermission');
// Authentication route
Route::post('login', 'JwtAuthenticateController@authenticate');


// API route group that we need to protect
Route::group(['middleware' => ['role:systems-admin']], function () {
	// Protected route
	Route::get('users', 'JwtAuthenticateController@index');


	// Client Routes
	Route::post('clients/individual', 'ClientController@storeIndividual');
	Route::post('clients/cooperate', 'ClientController@storecooperate');
	Route::get('clients/{id}', 'ClientController@show');

	Route::resource('clients', 'ClientController')->only([
		'index', 'update', 'destroy'
	]);

	// Transaction Routes
	Route::post('transactions', 'TransactionController@requestTransaction');
	Route::put('transactions/{id}/treat', 'TransactionController@treatTransaction');
	Route::put('transactions/{id}/approve', 'TransactionController@approveTransaction');
	Route::put('transactions/{id}/fulfil', 'TransactionController@fulfilTransaction');
	Route::patch('transactions/{id}/cancel', 'TransactionController@cancelTransaction');
	Route::get('transactions/{id}', 'TransactionController@show');

	Route::resource('transactions', 'TransactionController')->only([
		'index', 'update', 'destroy'
	]);


});
