<?php

namespace App\Http\Controllers;

use App\Product;
use App\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
	/**
	 * Get stat figures of transactions
	 *
	 * @return mixed
	 */
	public function figures()
	{
		switch (true) {
			case $this->is_client:
				$transactions = Auth::user()->client->transactions->count();
				$accounts = Auth::user()->client->accounts->count();
				break;

			case $this->is_staff:
				$open = Transaction::where('transaction_status_id', 1)->count();
				$in_progress = Transaction::where('transaction_status_id', 2)->count();
				$pending_approval = Transaction::where('transaction_status_id', 3)->count();
				$pending_fulfilment = Transaction::where('transaction_status_id', 4)->count();
				$closed = Transaction::where('transaction_status_id', 5)->count();
				$transactions = Transaction::all()->count();
				break;

			default:
				$transactions = 0;
		}

		return response()->success(compact('transactions', 'accounts', 'open', 'in_progress', 'pending_approval', 'pending_fulfilment', 'closed'));
	}

	/**
	 * Get last 3 transactions
	 *
	 * @return mixed
	 */
	public function recentTransactions()
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

	/**
	 * Get bucket balance for all currencies
	 *
	 * @return mixed
	 */
	public function bucketBalance()
	{
		if ($this->is_staff) {
			$products = Product::all();
		}

		return response()->success(compact('products'));

	}

	/**
	 * Get WACC rate of foreign currencies
	 *
	 * @return
	 */
	public function WACCTimeline()
	{
		if (!$this->is_staff) {
			return response()->error('Restricted Access');
		}

		$usd = Product::findOrFail(1)->dailyRates()->get();
		$gbp = Product::findOrFail(2)->dailyRates()->get();
		$eur = Product::findOrFail(3)->dailyRates()->get();

		return response()->success(compact('usd', 'eur', 'gbp'));
	}

	public function counts()
	{
		$open = Transaction::open()->count();
		$in_progress = Transaction::inProgress()->count();
		$pending_approval = Transaction::pendingApproval()->count();
		$pending_fulfilment = Transaction::pendingFulfilment()->count();
		$cancelled = Transaction::cancelled()->count();
		$closed = Transaction::closed()->count();

		return response()->success(compact('open', 'in_progress', 'pending_approval', 'pending_fulfilment', 'cancelled', 'closed'));
	}
}
