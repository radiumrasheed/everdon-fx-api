<?php

namespace App\Http\Controllers;

use App\Client;
use App\Transaction;
use App\TransactionEvent;
use App\TransactionStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\JWTAuth;

class TransactionController extends Controller
{
	const OPEN = 'open';
	const IN_PROGRESS = 'in-progress';
	const PENDING_APPROVAL = 'pending-approval';
	const PENDING_FULFILMENT = 'pending-fulfilment';
	const CANCELLED = 'cancelled';
	const CLOSED = 'closed';
	const RAISED = 'raised';

	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index()
	{
		$transactions = Auth::user()->client->transactions;

		return response()->success(compact('transactions'));
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
	public function requestTransaction(Request $request)
	{
		// Validate the request...
		$validator = Validator::make($request->all(), [
			'client_id' => 'required|exists:clients,id',
			'transaction_type_id' => 'required|exists:transaction_type,id',
			'transaction_mode_id' => 'required|exists:transaction_mode,id',
			'product_id' => 'required|exists:products,id',
			'account_id' => 'required|exists:accounts,id',
			'amount' => 'required|numeric',
			'rate' => 'required|numeric',
			'wacc' => 'numeric',
		]);

		// todo validate account_id belongs to client_id

		if ($validator->fails()) {
			return response()->error($validator->errors(), 422);
		}

		$inputs = $request->only(['client_id', 'transaction_type_id', 'transaction_mode_id', 'product_id', 'account_id', 'amount', 'rate', 'wacc']);
		$transaction_status_id = TransactionStatus::where('name', 'open')->first()->id;

		$transaction = new Transaction($inputs);
		$transaction->transaction_status_id = $transaction_status_id;
		$transaction->save();

		// trail Event
		$audit = new TransactionEvent();

		$audit->transaction_id = $transaction->id;
		$audit->transaction_status_id = $transaction->transaction_status_id;
		$audit->action = 'Requested Transaction';
		$audit->amount = $transaction->amount;
		$audit->rate = $transaction->rate;
		$audit->wacc = $transaction->wacc;

		$audit->done_by = Auth::User()->id;
		$audit->done_at = Carbon::now();
		$audit->save();

		$transaction = Transaction::with('client', 'account', 'events')->findOrFail($transaction->id);

		return response()->success(compact('transaction'));
	}

	/**
	 * Treat an open transaction
	 *
	 * @param  \Illuminate\Http\Request $request
	 * @param $transaction_id
	 * @return \Illuminate\Http\Response
	 */
	public function treatTransaction(Request $request, $transaction_id)
	{
		// Get the transaction...
		$transaction = Transaction::with('client')->findOrFail($transaction_id);

		$transaction_status_open = TransactionStatus::where('name', $this::OPEN)->first()->id;
		$transaction_status_in_progress = TransactionStatus::where('name', $this::IN_PROGRESS)->first()->id;

		// @throw error if transaction is not OPEN or IN PROGRESS...
		if (!in_array($transaction->transaction_status_id, [$transaction_status_open, $transaction_status_in_progress])) {
			return response()->error('Transaction is not OPEN or IN PROGRESS');
		}

		// Validate the request...
		$validator = Validator::make($request->all(), [
			'account_id' => 'exists:accounts,id',
			'amount' => 'numeric',
			'rate' => 'numeric',
			'wacc' => 'numeric',
		]);

		// todo validate account_id belongs to client_id

		if ($validator->fails()) {
			return response()->error($validator->errors(), 422);
		}

		$inputs = $request->only(['account_id', 'amount', 'rate', 'wacc']);
		$transaction_status_id = TransactionStatus::where('name', $this::PENDING_APPROVAL)->first()->id;

		$transaction->transaction_status_id = $transaction_status_id;
		$transaction->fill($inputs);
		$transaction->update();

		// trail Event
		$audit = new TransactionEvent();

		$audit->transaction_id = $transaction->id;
		$audit->transaction_status_id = $transaction->transaction_status_id;
		$audit->action = 'Treated Transaction';
		// todo add account_id
		$audit->amount = $transaction->amount;
		$audit->rate = $transaction->rate;
		$audit->wacc = $transaction->wacc;

		$audit->done_by = Auth::User()->id;
		$audit->done_at = Carbon::now();
		$audit->save();

		$transaction = Transaction::with('client', 'account', 'events')->findOrFail($transaction->id);

		return response()->success(compact('transaction'));
	}

	/**
	 * Approve a treated transaction
	 *
	 * @param  \Illuminate\Http\Request $request
	 * @param $transaction_id
	 * @return \Illuminate\Http\Response
	 */
	public function approveTransaction(Request $request, $transaction_id)
	{
		// Get the transaction...
		$transaction = Transaction::with('client')->findOrFail($transaction_id);

		$transaction_status_pending = TransactionStatus::where('name', $this::PENDING_APPROVAL)->first()->id;

		// @throw error if transaction is not OPEN
		if ($transaction->transaction_status_id != $transaction_status_pending) {
			// return response()->success(compact('transaction', 'transaction_status_pending'));
			return response()->error('Transaction is not PENDING APPROVAL');
		}

		// Validate the request...
		$validator = Validator::make($request->all(), [
			'account_id' => 'exists:accounts,id',
			'amount' => 'numeric',
			'rate' => 'numeric',
			'wacc' => 'numeric',
		]);

		// todo validate account_id belongs to client_id

		if ($validator->fails()) {
			return response()->error($validator->errors(), 422);
		}

		$inputs = $request->only(['account_id', 'amount', 'rate', 'wacc']);
		$transaction_status_id = TransactionStatus::where('name', $this::PENDING_FULFILMENT)->first()->id;

		// Update transaction for FULFILMENT...
		$transaction->transaction_status_id = $transaction_status_id;
		$transaction->fill($inputs);
		$transaction->update();

		// trail Event
		$audit = new TransactionEvent();

		$audit->transaction_id = $transaction->id;
		$audit->transaction_status_id = $transaction->transaction_status_id;
		$audit->action = 'Approved Transaction';
		// todo add account_id
		$audit->amount = $transaction->amount;
		$audit->rate = $transaction->rate;
		$audit->wacc = $transaction->wacc;

		$audit->done_by = Auth::User()->id;
		$audit->done_at = Carbon::now();
		$audit->save();

		$transaction = Transaction::with('client', 'account', 'events')->findOrFail($transaction->id);

		return response()->success(compact('transaction'));
	}

	/**
	 * Fulfil an approved transaction
	 *
	 * @param  \Illuminate\Http\Request $request
	 * @param $transaction_id
	 * @return \Illuminate\Http\Response
	 */
	public function fulfilTransaction(Request $request, $transaction_id)
	{
		// Get the transaction...
		$transaction = Transaction::with('client')->findOrFail($transaction_id);

		$transaction_status_pending_fulfilment = TransactionStatus::where('name', $this::PENDING_FULFILMENT)->first()->id;

		// @throw error if transaction is not OPEN
		if ($transaction->transaction_status_id != $transaction_status_pending_fulfilment) {
			return response()->error('Transaction is not PENDING FULFILMENT');
		}

		// Validate the request...
		$validator = Validator::make($request->all(), [
			'account_id' => 'exists:accounts,id',
			'amount' => 'numeric',
			'rate' => 'numeric',
			'wacc' => 'numeric',
		]);

		// todo validate account_id belongs to client_id

		if ($validator->fails()) {
			return response()->error($validator->errors(), 422);
		}

		$inputs = $request->only(['account_id', 'amount', 'rate', 'wacc']);
		$transaction_status_id = TransactionStatus::where('name', $this::CLOSED)->first()->id;

		// Update transaction for FULFILMENT...
		$transaction->transaction_status_id = $transaction_status_id;
//		$transaction->fulfilled_by = Auth::User()->id;
//		$transaction->fulfilled_at = Carbon::now();
		$transaction->fill($inputs);
		$transaction->update();

		// trail Event
		$audit = new TransactionEvent();

		$audit->transaction_id = $transaction->id;
		$audit->transaction_status_id = $transaction->transaction_status_id;
		$audit->action = 'Fulfilled and Closed Transaction';
		// todo include account_id
		$audit->amount = $transaction->amount;
		$audit->rate = $transaction->rate;
		$audit->wacc = $transaction->wacc;

		$audit->done_by = Auth::User()->id;
		$audit->done_at = Carbon::now();
		$audit->save();

		$transaction = Transaction::with('client', 'account', 'events')->findOrFail($transaction->id);

		return response()->success(compact('transaction'));
	}

	/**
	 * Cancel an un-closed transaction
	 *
	 * @param  \Illuminate\Http\Request $request
	 * @param $transaction_id
	 * @return \Illuminate\Http\Response
	 */
	public function cancelTransaction(Request $request, $transaction_id)
	{
		// Get the transaction...
		$transaction = Transaction::with('client')->findOrFail($transaction_id);

		$transaction_status_closed = TransactionStatus::where('name', $this::CLOSED)->first()->id;
		$transaction_status_cancelled = TransactionStatus::where('name', $this::CANCELLED)->first()->id;

		// @throw error if transaction is already CLOSED or CANCELLED
		if (in_array($transaction->transaction_status_id, [$transaction_status_closed, $transaction_status_cancelled])) {
			return response()->error('Transaction is already CLOSED or CANCELLED');
		}

		// Validate the request...
		$validator = Validator::make($request->all(), [
			'comment' => 'required|string'
		]);

		// todo validate account_id belongs to client_id

		if ($validator->fails()) {
			return response()->error($validator->errors(), 422);
		}

		$inputs = $request->only(['string']);
		$transaction_status_id = TransactionStatus::where('name', $this::CANCELLED)->first()->id;

		// Update transaction for FULFILMENT...
		$transaction->transaction_status_id = $transaction_status_id;
//		$transaction->cancelled_by = Auth::User()->id;
//		$transaction->cancelled_at = Carbon::now();
		$transaction->fill($inputs);
		$transaction->update();

		// trail Event
		$audit = new TransactionEvent();

		$audit->transaction_id = $transaction->id;
		$audit->transaction_status_id = $transaction->transaction_status_id;
		$audit->action = 'Cancelled Transaction';
		$audit->amount = $transaction->amount;
		$audit->rate = $transaction->rate;
		$audit->wacc = $transaction->wacc;

		// todo uncomment below line
		// $audit->comment = $request->comment;

		$audit->done_by = Auth::User()->id;
		$audit->done_at = Carbon::now();
		$audit->save();

		$transaction = Transaction::with('client', 'account', 'events')->findOrFail($transaction->id);

		return response()->success(compact('transaction'));
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
	 * @param  \App\Transaction $transaction
	 * @return \Illuminate\Http\Response
	 */
	public function show($transaction)
	{
		/*
		 * if (true) {
			return response()->success(
				Auth::user()->whereHas('roles', function ($query) {
					$query->where('name', 'systems-admin');
				})->get());
		}*/

		// todo - if transaction is OPEN, and Auth::User() is a staff, set it as IN_PROGRESS then track event as "Viewed Transaction"

		$transaction = Transaction::with('client', 'account', 'events')->findOrFail($transaction);

		return response()->success(compact('transaction'));
	}


	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  \App\Transaction $transaction
	 * @return \Illuminate\Http\Response
	 */
	public function edit(Transaction $transaction)
	{
		//
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  \Illuminate\Http\Request $request
	 * @param  \App\Transaction $transaction
	 * @return \Illuminate\Http\Response
	 */
	public function update(Request $request, Transaction $transaction)
	{
		//
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  \App\Transaction $transaction
	 * @return \Illuminate\Http\Response
	 */
	public function destroy(Transaction $transaction)
	{
		//
	}
}
