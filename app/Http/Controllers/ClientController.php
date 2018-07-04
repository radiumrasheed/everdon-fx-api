<?php

namespace App\Http\Controllers;

use App\Client;
use App\ClientType;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use JD\Cloudder\Facades\Cloudder;

class ClientController extends Controller
{

	/**
	 * Get a clients' accounts
	 *
	 * @param Request $req
	 * @param $client_id
	 * @return mixed
	 */
	public function accounts(Request $req, $client_id)
	{
		if (!$this->is_staff) {
			return response()->error('You don\'t have the rights to make this request');
		}

		$accounts = Client::with('accounts')->find($client_id)->accounts;

		return response()->success(compact('accounts'));
	}


	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index()
	{
		if ($this->is_client) {
			$client = Client::with('accounts', 'kyc')->findOrFail(Auth::user()->client->id);
		} elseif ($this->is_staff) {
			$clients = Client::with('kyc', 'accounts')->get();
		}

		return response()->success(compact('clients', 'client'));
	}


	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request $req
	 * @return \Illuminate\Http\Response
	 */
	public function storeIndividual(Request $req)
	{
		// Validate the request...
		$validator = Validator::make($req->all(), [
			'email' => 'required|unique:clients|email',
			'full_name' => 'required',
			'phone' => 'required|unique:clients'
		]);

		if ($validator->fails()) {
			return response()->error($validator->errors(), 422);
		}

		$_client = $req->only([
			'full_name', 'email', 'phone', 'bvn', 'office_address', 'marital_status', 'date_of_birth', 'residential_address', 'occupation',
			'nok_full_name', 'nok_phone', 'referee'
		]);
		/*$_account = $req->only([
			'account_name', 'account_number', 'bvn', 'bank_name', 'client_id'
		]);*/
		$client_type_id = ClientType::where('name', 'individual')->first()->id;


		// create client
		$client = new Client($_client);
		$client->client_type = $client_type_id;
		$client->save();

		/*$_account = [
			'name' => $req->account_name,
			'number' => $req->account_number,
			'bvn',
			'bank' => $req->bank_name,
			'client_id' => $client->id
		];*/

		// create account
//		$account = Account::firstOrCreate(['number' => $req->account_number], $_account);

		// attach account to client
//		$client->accounts()->save($account);

		return response()->success(compact('client'));
	}


	/**
	 * @param Request $req
	 * @return mixed
	 */
	public function storeCooperate(Request $req)
	{
		// Validate the request...
		$validator = Validator::make($req->all(), [
			'email' => 'required|unique:clients|email',
			'phone' => 'required|unique:clients',
			'rc_number' => 'required|unique',
			'full_name' => 'required',
		]);

		if ($validator->fails()) {
			return response()->error($validator->errors(), 422);
		}

		$inputs = $req->only(['full_name', 'email', 'phone', 'bvn', 'office_address', 'rc_number']);
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
		$client = Client::with('accounts', 'kyc')->findOrFail($client);

//		$client = Client::find($client);

		return response()->success(compact('client'));
	}


	/**
	 * Search for a client
	 *
	 * @param Request $req
	 * @param $term
	 * @return mixed
	 */
	public function search(Request $req, $term)
	{
		if (!$this->is_staff) {
			return response()->error('Unauthorised Access', 401);
		}

		$clients = Client::search($term)->get();

		return response()->success(compact('clients'));
	}


	/**
	 * Update the specified resource in storage.
	 *
	 * @param  \Illuminate\Http\Request $req
	 * @param  \App\Client $client_id
	 * @return \Illuminate\Http\Response
	 */
	public function update(Request $req, $client_id)
	{
		// Fetch the client...
		try {
			$client = Client::where('id', $client_id)->firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->error('Customer\'s Profile does not exist', 404);
		}

		// Validate the request...
		$validator = Validator::make($req->all(), [
//			'email' => 'required|exists:clients|email',
			'full_name' => 'required',
			'phone' => 'required',
//			'identification_document' => 'mimes:jpeg,bmp,jpg,png|between:1, 6000',
		]);

		if ($validator->fails()) {
			return response()->error($validator->errors(), 422);
		}

		// Get inputs based on client type...
		if ($client->client_type === 1 && $this->is_staff) {
			$inputs = $req->only(['full_name', 'phone', 'bvn', 'office_address', 'marital_status', 'identification', 'identification_number',
				'residential_address', 'occupation', 'nok_full_name', 'nok_phone', 'referee_1', 'referee_2', 'referee_3', 'referee_4', 'date_of_birth',
			]);
		} elseif ($client->client_type === 1 && $this->is_client) {
			$inputs = $req->only(['full_name', 'phone', 'bvn', 'office_address', 'marital_status', 'identification', 'identification_number',
				'residential_address', 'occupation', 'nok_full_name', 'nok_phone', 'date_of_birth']);
		} elseif ($client->client_type === 2) {
			$inputs = $req->only(['full_name', 'phone', 'bvn', 'office_address', 'rc_number']);
		}

		// Update Profile...
		try {
			// Set for review after update by client...
			if ($this->is_client) {
				$client->kyc->awaiting_review = 1;
				$client->kyc->save();
			}


			$client->update($inputs);
			if ($req->hasFile('identification_document')) {
				Storage::delete($client->identification_document);
				$client->identification_document = $req->identification_document->store('identification_files');
			}

			$client->save();
		} catch (\Exception $e) {
			return response()->error('Profile Update failed: ' . $e->getMessage());
		}

		return response()->success(compact('client'));
	}


	/**
	 * Update client avatar
	 *
	 * @param Request $req
	 * @param $client_id
	 * @return object
	 */
	public function updateAvatar(Request $req, $client_id)
	{
		// Fetch the client...
		try {
			$client = Client::where('id', $client_id)->firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->error('Client Profile does not exist');
		}

		$this->validate($req, [
			'avatar' => 'required|mimes:jpeg,bmp,jpg,png|between:1, 6000',
		]);

		if ($req->file('avatar')->isValid()) {
			Cloudder::upload($req->avatar, null);

			$image_url = Cloudder::show(Cloudder::getPublicId(), ["width" => 200, "height" => 200]);

			$client->update(['avatar' => $image_url]);
		}


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
