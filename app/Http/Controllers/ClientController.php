<?php

namespace App\Http\Controllers;

use App\Account;
use App\User;
use App\Client;
use App\ClientType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;

class ClientController extends Controller
{
	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index()
	{
		$clients = Client::all();

		return response()->success(compact('clients'));
	}


	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request $request
	 * @return \Illuminate\Http\Response
	 */
	public function storeIndividual(Request $request)
	{
		// Validate the request...
		$validator = Validator::make($request->all(), [
			'email' => 'required|unique:clients|email',
			'full_name' => 'required',
			'phone' => 'required',
		]);

		if ($validator->fails()) {
			return response()->error($validator->errors(), 422);
		}

		$_client = $request->only([
			'full_name', 'email', 'phone', 'bvn', 'office_address', 'marital_status', 'date_of_birth', 'residential_address', 'occupation',
			'nok_full_name', 'nok_phone', 'referee'
		]);
		/*$_account = $request->only([
			'account_name', 'account_number', 'bvn', 'bank_name', 'client_id'
		]);*/
		$client_type_id = ClientType::where('name', 'individual')->first()->id;


		// create client
		$client = new Client($_client);
		$client->client_type = $client_type_id;
		$client->save();

		/*$_account = [
			'name' => $request->account_name,
			'number' => $request->account_number,
			'bvn',
			'bank' => $request->bank_name,
			'client_id' => $client->id
		];*/

		// create account
//		$account = Account::firstOrCreate(['number' => $request->account_number], $_account);

		// attach account to client
//		$client->accounts()->save($account);

		return response()->success(compact('client'));
	}


	/**
	 * @param Request $request
	 * @return mixed
	 */
	public function storeCooperate(Request $request)
	{
		// Validate the request...
		$validator = Validator::make($request->all(), [
			'email' => 'required|unique:clients|email',
			'full_name' => 'required',
			'phone' => 'required',
			'rc_number' => 'required',
		]);

		if ($validator->fails()) {
			return response()->error($validator->errors(), 422);
		}

		$inputs = $request->only(['full_name', 'email', 'phone', 'bvn', 'office_address', 'rc_number']);
		$client_type_id = ClientType::where('name', 'cooperate')->first()->id;

		$client = new Client($inputs);
		$client->client_type = $client_type_id;
		$client->save();

		return response()->success(compact('client'));
	}


	/**
	 * Display the specified resource.
	 *
	 * @param $client
	 * @return \Illuminate\Http\Response
	 */
	public function show($client)
	{
		$client = Client::with('accounts')->findOrFail($client);

		return response()->success(compact('client'));
	}


	/**
	 * Update the specified resource in storage.
	 *
	 * @param  \Illuminate\Http\Request $request
	 * @param  \App\Client $client
	 * @return \Illuminate\Http\Response
	 */
	public function update(Request $request, Client $client)
	{
		// Validate the request...
		$validator = Validator::make($request->all(), [
			'email' => 'required|unique:clients|email',
			'full_name' => 'required',
			'phone' => 'required',
			'rc_number' => 'required',
		]);

		if ($validator->fails()) {
			return response()->error($validator->errors(), 422);
		}

		$inputs = $request->only(['full_name', 'email', 'phone', 'bvn', 'office_address', 'rc_number']);
		$client_type_id = ClientType::where('name', 'cooperate')->first()->id;

		$client = new Client($inputs);
		$client->client_type = $client_type_id;
		$client->save();

		return response()->success(compact('client'));
	}


	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  \App\Client $client
	 * @return \Illuminate\Http\Response
	 */
	public function destroy(Client $client)
	{
		//
	}
}
