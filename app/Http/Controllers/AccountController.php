<?php

namespace App\Http\Controllers;

use App\Account;
use App\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountController extends Controller
{
	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index()
	{
		$auth = Auth::user();

		if ($auth->hasRole(['client'])) {
			$accounts = $auth->client->accounts;
		}

		return response()->success(compact('accounts'));
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function create()
	{
		//
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request $request
	 * @return \Illuminate\Http\Response
	 */
	public function store(Request $request)
	{
		//
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  \App\Account $account
	 * @return \Illuminate\Http\Response
	 */
	public function show(Account $account)
	{
		//
	}

	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  \App\Account $account
	 * @return \Illuminate\Http\Response
	 */
	public function edit(Account $account)
	{
		//
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  \Illuminate\Http\Request $request
	 * @param  \App\Account $account
	 * @return \Illuminate\Http\Response
	 */
	public function update(Request $request, Account $account)
	{
		//
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  \App\Account $account
	 * @return \Illuminate\Http\Response
	 */
	public function destroy(Account $account)
	{
		//
	}
}
