<?php

namespace App\Http\Controllers;

use App\Account;
use App\Client;
use App\ClientType;
use App\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use JD\Cloudder\Facades\Cloudder;

class ClientController extends Controller
{

	const INDIVIDUAL = 1;
	const COOPERATE = 2;
	const EXPRESS = 3;
	const PROXY_INDIVIDUAL = 4;
	const PROXY_COOPERATE = 5;


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
			'first_name' => 'required',
			'last_name' => 'required',
			'phone' => 'required|unique:clients'
		]);
		if ($validator->fails()) {
			return response()->error($validator->errors(), 422);
		}

		// Determine Client Type...
		if ($this->is_staff) {
			$client_type_id = ClientType::where('name', 'proxy_individual')->first()->id;
		} elseif ($this->is_client) {
			$client_type_id = ClientType::where('name', 'individual')->first()->id;
		} else {
			return response()->error('You are not eligible to make this request');
		}


		$_client = $req->only([
			'first_name', 'middle_name', 'last_name', 'email', 'phone', 'bvn', 'office_address', 'marital_status', 'date_of_birth', 'residential_address', 'occupation',
			'nok_full_name', 'nok_phone', 'referee'
		]);

		// create client
		$client = new Client($_client);
		$client->client_type = $client_type_id;
		$client->save();

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
			'rc_number' => 'required|unique:clients',
			'first_name' => 'required',
			'last_name' => 'required',
		]);
		if ($validator->fails()) {
			return response()->error($validator->errors(), 422);
		}

		// Determine Client Type...
		if ($this->is_staff) {
			$client_type_id = ClientType::where('name', 'proxy_cooperate')->first()->id;
		} elseif ($this->is_client) {
			$client_type_id = ClientType::where('name', 'cooperate')->first()->id;
		} else {
			return response()->error('You are not eligible to make this request');
		}

		$inputs = $req->only(['first_name', 'middle_name', 'last_name', 'email', 'phone', 'bvn', 'office_address', 'rc_number']);

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
			'first_name' => 'required',
			'last_name' => 'required',
			'phone' => 'required',
//			'identification_document' => 'mimes:jpeg,bmp,jpg,png|between:1, 6000',
		]);

		if ($validator->fails()) {
			return response()->error($validator->errors(), 422);
		}

		// Get inputs based on client type & user...
		switch (true) {
			case in_array($client->client_type, [self::INDIVIDUAL, self::PROXY_INDIVIDUAL]) && $this->is_staff:
				$inputs = $req->only(['first_name', 'middle_name', 'last_name', 'phone', 'bvn', 'office_address', 'marital_status', 'identification', 'identification_number',
					'residential_address', 'occupation', 'nok_full_name', 'nok_phone', 'referee_1', 'referee_2', 'referee_3', 'referee_4', 'date_of_birth',
				]);
				break;

			case in_array($client->client_type, [self::INDIVIDUAL, self::PROXY_INDIVIDUAL]) && $this->is_client:
				$inputs = $req->only(['first_name', 'middle_name', 'last_name', 'phone', 'bvn', 'office_address', 'marital_status', 'identification', 'identification_number',
					'residential_address', 'occupation', 'nok_full_name', 'nok_phone', 'date_of_birth']);
				break;

			case in_array($client->client_type, [self::COOPERATE, self::PROXY_COOPERATE]) && $this->is_client:
				$inputs = $req->only(['first_name', 'middle_name', 'last_name', 'phone', 'bvn', 'office_address', 'rc_number']);
				break;

			case in_array($client->client_type, [self::COOPERATE, self::PROXY_COOPERATE]) && $this->is_staff:
				$inputs = $req->only(['first_name', 'middle_name', 'last_name', 'phone', 'bvn', 'office_address', 'rc_number', 'referee_1', 'referee_2', 'referee_3', 'referee_4']);
				break;

			case in_array($client->client_type, [self::EXPRESS]):
				$inputs = $req->only(['first_name', 'middle_name', 'last_name', 'phone', 'bvn', 'office_address', 'marital_status', 'identification', 'identification_number',
					'residential_address', 'occupation', 'nok_full_name', 'nok_phone', 'referee_1', 'referee_2', 'referee_3', 'referee_4', 'date_of_birth',
				]);
				break;

			default:
				return response()->error('You are not eligible to make this action');
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
	 * Add an account to client profile
	 *
	 * @param Request $req
	 * @param $client_id
	 * @return object
	 */
	public function addAccount(Request $req, $client_id)
	{
		// Fetch the client...
		try {
			$client = Client::where('id', $client_id)->firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->error('Client Profile does not exist');
		}

		if ($this->is_client) {
			if (Auth::user()->client->id !== $client->id) {
				return response()->error('You\'re not authorised to make this request');
			}
		} else if (!$this->is_staff) {
			return response()->error('Unauthorised Request', 403);
		}

		$this->validate($req, [
			'number' => 'required|digits:10|unique:accounts',
			'name' => 'required|string',
			'bank' => 'required|string',
		]);

		$account = new Account(['name' => $req->name, 'number' => $req->number, 'bank' => $req->bank]);
		$client->accounts()->save($account);

		$accounts = $client->accounts;

		return response()->success(compact('accounts'));


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
