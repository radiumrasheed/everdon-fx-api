<?php

namespace App\Observers;

use App\Transaction;
use Illuminate\Support\Facades\Log;

class TransactionObserver
{
	/**
	 * Handle to the transaction "created" event.
	 *
	 * @param  \App\Transaction $transaction
	 *
	 * @return void
	 */
	public function created(Transaction $transaction)
	{
		//
	}


	/**
	 * Handle the transaction "updated" event.
	 *
	 * @param  \App\Transaction $transaction
	 *
	 * @return void
	 */
	public function updated(Transaction $transaction)
	{
		if ($transaction->transaction_status_id === 6 || $transaction->transaction_status_id === 8) {
			if ($transaction->funds_received == true && $transaction->funds_paid == true) {
				$transaction->transaction_status_id = 8;
				$transaction->unsetEventDispatcher();
				$transaction->save();
			} else {
				$transaction->transaction_status_id = 6;
				$transaction->unsetEventDispatcher();
				$transaction->save();
			}
		}
	}


	/**
	 * Handle the transaction "deleted" event.
	 *
	 * @param  \App\Transaction $transaction
	 *
	 * @return void
	 */
	public function deleted(Transaction $transaction)
	{
		//
	}
}
