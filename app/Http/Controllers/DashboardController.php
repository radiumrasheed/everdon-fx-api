<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
	//
	public function figures(Request $request)
	{
		if ($this->is_client) {
			$client = Auth::user()->client;

			$transactions = $client->transactions->count();
			$accounts = $client->accounts->count();
		}

		return response()->success(compact('transactions', 'accounts'));
	}

	public function recentTransactions(Request $request)
	{
		if ($this->is_client) {
			$client = Auth::user()->client;

			$transactions = $client->transactions()
				->select(['id', 'amount', 'transaction_status_id', 'buying_product_id', 'selling_product_id', 'client_id', 'account_id', 'updated_at'])
				->orderBy('updated_at', 'desc')
				->limit(3)->get();
			$transactions->loadMissing('events:id,done_by,transaction_id', 'events.doneBy:id,name,email', 'account:id,number');

		}

		return response()->success(compact('transactions'));

	}
}
