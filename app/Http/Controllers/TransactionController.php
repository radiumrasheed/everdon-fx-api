<?php

namespace App\Http\Controllers;

use App\Account;
use App\Client;
use App\Product;
use App\Transaction;
use App\TransactionEvent;
use App\TransactionMode;
use App\TransactionStatus;
use App\TransactionType;
use App\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
	const OPEN = 'open';
	const IN_PROGRESS = 'in-progress';
	const PENDING_APPROVAL = 'pending-approval';
	const PENDING_FULFILMENT = 'pending-fulfilment';
	const CANCELLED = 'cancelled';
	const CLOSED = 'closed';
	const RAISED = 'raised';

	/** Requesting user is a client */
	protected $can_treat = false;

	/** Requesting user is a staff */
	protected $can_approve = false;

	/**
	 * Controller constructor.
	 */
	public function __construct()
	{

		parent::__construct();

		$this->middleware(function ($request, $next) {
			try {
				if (Auth::user() !== null) {
					$this->can_approve = Auth::user()->can('can-approve');
					$this->can_treat = Auth::user()->can('can-treat');
				}
			} catch (\Exception $e) {
				Log::alert('Tried to validate roles');
			}

			return $next($request);
		});
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index()
	{
		$auth = Auth::user();

		$is_client = $auth->hasRole('client');
		$is_systems_admin = $auth->hasRole('systems-admin');
		$is_fx_ops = $auth->hasRole('fx-ops');
		$is_fx_ops_lead = $auth->hasRole('fx-ops-lead');
		$is_fx_ops_manager = $auth->hasRole('fx-ops-manager');
		$is_treasury_ops = $auth->hasRole('treasury-ops');

		switch (true) {
			case $is_client:
				$transactions = User::find($auth->id)->client->transactions;
				break;

			case $is_fx_ops:
				$transactions = Transaction::where('transaction_status_id', 1)->get();
				break;

			case $is_fx_ops_lead:
				$transactions = Transaction::where('transaction_status_id', 2)->get();
				break;

			case $is_fx_ops_manager:
				$transactions = Transaction::where('transaction_status_id', 3)->get();
				break;

			case $is_treasury_ops:
				$transactions = Transaction::where('transaction_status_id', 4)->get();
				break;

			case $is_systems_admin:
				$transactions = Transaction::all();
				break;

			default:
				$transactions = [];
		}

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
			'client_id' => 'exists:clients,id',
			'transaction_type_id' => 'required|exists:transaction_type,id',
			'transaction_mode_id' => 'required|exists:transaction_mode,id',
			'buying_product_id' => 'required|exists:products,id',
			'selling_product_id' => 'required|exists:products,id',
			'account_id' => 'required|exists:accounts,id',
			'amount' => 'required|numeric',
			'rate' => 'numeric',
			'wacc' => 'numeric',
		]);

		// todo validate account_id belongs to client_id

		if ($validator->fails()) {
			return response()->error($validator->errors(), 422);
		}

		$inputs = $request->only(['client_id', 'transaction_type_id', 'transaction_mode_id', 'buying_product_id', 'selling_product_id', 'account_id', 'amount', 'rate', 'wacc']);
		$transaction_status_id = TransactionStatus::where('name', 'open')->first()->id;

		$transaction = new Transaction($inputs);

		// Check if user is authenticated
		if (Auth::user()->client) {
			$transaction->client()->associate(Auth::user()->client);
		}

		$transaction->transaction_status_id = $transaction_status_id;
		$transaction->save();

		// trail Event...
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
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request $r
	 * @return \Illuminate\Http\Response
	 */
	public function requestExpressTransaction(Request $r)
	{
		// Validate the request...
		$validator = Validator::make($r->all(), [
			'full_name' => 'required',
			'email' => 'required',
			'phone' => 'required',
			'amount' => 'required|numeric',
			'i_have' => 'required',
			'i_want' => 'required',
			'account_number' => 'required',
			'account_name' => 'required',
			'bank_name' => 'required',
			'bvn' => 'required',
		]);

		// Determine transaction type...
		$have_foreign = in_array($r->i_have, ['usd', 'eur', 'gbp']);
		$want_foreign = in_array($r->i_want, ['usd', 'eur', 'gbp']);
		$have_local = in_array($r->i_have, ['ngn']);
		$want_local = in_array($r->i_want, ['ngn']);
		$same = $r->i_want == $r->i_have;
		switch (true) {
			case $have_foreign && $want_local:
				$type = 'purchase';
				break;
			case $have_local && $want_foreign:
				$type = 'sales';
				break;
			case $same:
				$type = 'swap';
				break;
			case !$same && $have_foreign && $want_foreign:
				$type = 'cross';
				break;
			default:
				return response()->error('Unkwon Transaction Type');
		}

		// Determine transaction mode...
		$mode = 'cash'; // todo - Review*


		// todo validate account_id belongs to client_id...
		if ($validator->fails()) {
			return response()->error($validator->errors(), 422);
		}

		// Get Transaction type, mode and product...
		try {
			$open = TransactionStatus::where('name', 'open')->firstOrFail()->id;
			$transaction_type_id = TransactionType::where('name', $type)->firstOrFail()->id;
			$transaction_mode_id = TransactionMode::where('name', $mode)->firstOrFail()->id;
			$buying_product_id = Product::where('name', $r->i_want)->firstOrFail()->id;
			$selling_product_id = Product::where('name', $r->i_have)->firstOrFail()->id;
		} catch (ModelNotFoundException $e) {
			return response()->error('An Error Occured: ' . $e->getMessage());
		}

		// Merge the above into the request...
		$r->merge([
			'transaction_type_id' => $transaction_type_id,
			'transaction_mode_id' => $transaction_mode_id,
			'client_type' => 3, // todo - Review*
			'buying_product_id' => $buying_product_id,
			'selling_product_id' => $selling_product_id
		]);
		$inputs = $r->only(['client_id', 'transaction_type_id', 'transaction_mode_id', 'buying_product_id', 'selling_product_id', 'account_id', 'amount']);

		// Get or Create Client...
		$client = Client::firstOrCreate($r->only('email'), $r->only(['email', 'full_name', 'phone', 'client_type']));

		// Get or Create Account...
		$account = Account::firstOrCreate(['number' => $r->account_number], ['client_id' => $client->id, 'number' => $r->account_number, 'name' => $r->account_name, 'bank' =>
			$r->bank_name, 'bvn' => $r->bvn]);

		// Save transaction
		$transaction = new Transaction($inputs);

		$transaction->client()->associate($client);
		$transaction->account()->associate($account);
		$transaction->transaction_status_id = $open;
		$transaction->save();

		// $transaction = Transaction::with('client', 'account', 'events')->findOrFail($transaction->id);

		return response()->success('Request Successful');
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
//			'account_id' => 'exists:accounts,id',
			'org_account_id' => 'exists:organizations,id',
			'condition' => 'string',
			'comment' => 'string',
			'amount' => 'numeric',
			'calculated_amount' => 'numeric',
			'rate' => 'numeric',
			'wacc' => 'numeric',
		]);

		// todo validate account_id belongs to client_id

		if ($validator->fails()) {
			return response()->error($validator->errors(), 422);
		}

		$inputs = $request->only(['account_id', 'amount', 'rate', 'wacc', 'org_account_id', 'condition', 'calculated_amount']);
		$pending_approval = TransactionStatus::where('name', $this::PENDING_APPROVAL)->first()->id;

		$transaction->transaction_status_id = $pending_approval;
		$transaction->reviewed_by = Auth::user()->id;
		$transaction->reviewed_at = Carbon::now();
		$transaction->fill($inputs);
		$transaction->update();

		// trail Event
		$audit = new TransactionEvent();

		$audit->transaction_id = $transaction->id;
		$audit->transaction_status_id = $transaction->transaction_status_id;
		$audit->action = 'Treated Transaction';
		$audit->comment = $request->comment;
		// todo add account_id
		$audit->calculated_amount = $transaction->calculated_amount;
		$audit->condition = $transaction->condition;
		$audit->org_account_id = $transaction->org_account_id;
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
			'comment' => 'string',
		]);

		// todo validate account_id belongs to client_id

		if ($validator->fails()) {
			return response()->error($validator->errors(), 422);
		}

		$inputs = $request->only(['comment']);
		$transaction_status_id = TransactionStatus::where('name', $this::PENDING_FULFILMENT)->first()->id;

		// Update transaction for FULFILMENT...
		$transaction->transaction_status_id = $transaction_status_id;
		$transaction->approved_by = Auth::user()->id;
		$transaction->approved_at = Carbon::now();
		$transaction->fill($inputs);
		$transaction->update();

		// trail Event
		$audit = new TransactionEvent();

		$audit->transaction_id = $transaction->id;
		$audit->transaction_status_id = $transaction->transaction_status_id;
		$audit->action = 'Approved Transaction';
		$audit->comment = $request->comment;
		// todo add account_id
		$audit->calculated_amount = $transaction->calculated_amount;
		$audit->condition = $transaction->condition;
		$audit->org_account_id = $transaction->org_account_id;
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
			'comment' => 'string',
		]);

		if ($validator->fails()) {
			return response()->error($validator->errors(), 422);
		}

		$inputs = $request->only(['comment']);
		$transaction_status_id = TransactionStatus::where('name', $this::CLOSED)->first()->id;

		// Update transaction for FULFILMENT...
		$transaction->transaction_status_id = $transaction_status_id;
		$transaction->closed_by = Auth::user()->id;
		$transaction->closed_at = Carbon::now();
		$transaction->fill($inputs);
		$transaction->update();

		// trail Event
		$audit = new TransactionEvent();

		$audit->transaction_id = $transaction->id;
		$audit->transaction_status_id = $transaction->transaction_status_id;
		$audit->action = 'Fulfilled and Closed Transaction';
		$audit->comment = $request->comment;
		// todo add account_id
		$audit->calculated_amount = $transaction->calculated_amount;
		$audit->condition = $transaction->condition;
		$audit->org_account_id = $transaction->org_account_id;
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
		$transaction->cancelled_by = Auth::user()->id;
		$transaction->cancelled_at = Carbon::now();
		$transaction->fill($inputs);
		$transaction->update();

		// trail Event
		$audit = new TransactionEvent();

		$audit->transaction_id = $transaction->id;
		$audit->transaction_status_id = $transaction->transaction_status_id;
		$audit->action = 'Cancelled Transaction';
		$audit->comment = $request->comment;
		// todo add account_id
		$audit->calculated_amount = $transaction->calculated_amount;
		$audit->condition = $transaction->condition;
		$audit->org_account_id = $transaction->org_account_id;
		$audit->rate = $transaction->rate;
		$audit->wacc = $transaction->wacc;

		$audit->done_by = Auth::user()->id;
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

		$transaction = Transaction::with('client', 'account', 'events', 'events.doneBy')->findOrFail($transaction);

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
