<?php

namespace App\Http\Controllers;

use App\Product;
use App\Transaction;
use App\TransactionEvent;
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
				$transactions = Auth::user()->client->transactions()->count();
				$open_transactions = Auth::user()->client->transactions()->open()->count();
				$closed_transactions = Auth::user()->client->transactions()->closed()->count();
				$accounts = Auth::user()->client->accounts->count();
				break;

			case $this->is_staff:
				$open = Transaction::open()->count();
				$in_progress = Transaction::inProgress()->count();
				$pending_approval = Transaction::pendingApproval()->count();
				$pending_fulfilment = Transaction::pendingFulfilment()->count();
				$cancelled = Transaction::cancelled()->count();
				$closed = Transaction::closed()->count();
				$raised = Transaction::raised()->count();
				$transactions = Transaction::all()->count();
				break;

			default:
				$transactions = 0;
		}

		return response()->success(compact(
			'transactions', 'open_transactions', 'closed_transactions', 'accounts', 'open',
			'in_progress', 'pending_approval', 'pending_fulfilment', 'closed', 'raised', 'cancelled'
		));
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
		} else if ($this->is_client) {
			$products = Product::clientRates()->ignoreLocalProduct()->get();
		} else {
			return response()->error('You don\'t have the right permission to view this resource');
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

		$usd = Product::findOrFail(1)->dailyWacc()->get();
		$gbp = Product::findOrFail(2)->dailyWacc()->get();
		$eur = Product::findOrFail(3)->dailyWacc()->get();

		return response()->success(compact('usd', 'eur', 'gbp'));
	}


	/**
	 * Get WACC rate of foreign currencies
	 *
	 * @return
	 */
	public function rateTimeline()
	{

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
		$raised = Transaction::raised()->count();

		return response()->success(compact('open', 'in_progress', 'pending_approval', 'pending_fulfilment', 'cancelled', 'closed', 'raised'));
	}


	public function recentEvents()
	{
		if (!$this->is_staff) {
			return response()->error('You don\'t have permission to make this request', 403);
		}

		$events = TransactionEvent::orderBy('updated_at', 'desc')->limit(5)->get();
		$events->loadMissing('doneBy:id,name,email');

		return response()->success(compact('events'));
	}
}
