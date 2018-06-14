<?php

namespace App\Http\Controllers;

use App\Account;
use App\Client;
use App\Notifications\NotifyClient;
use App\Notifications\NotifyStaff;
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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use SebastianBergmann\Environment\Console;

class TransactionController extends Controller
{
	const OPEN = 'open';
	const IN_PROGRESS = 'in-progress';
	const PENDING_APPROVAL = 'pending-approval';
	const PENDING_FULFILMENT = 'pending-fulfilment';
	const CANCELLED = 'cancelled';
	const CLOSED = 'closed';
	const RAISED = 'raised';

	protected $is_fx_ops;
	protected $is_fx_ops_lead;
	protected $is_treasury_ops;
	protected $is_fx_ops_manager;
	protected $is_systems_admin;


	/**
	 * Transaction Controller constructor.
	 */
	public function __construct()
	{

		parent::__construct();

		$this->middleware(
		/**
		 * @param $request
		 * @param $next
		 * @return mixed
		 */
			function ($request, $next) {
				try {
					if (Auth::user() !== null) {
						$auth = Auth::user();

						$this->is_client = $auth->hasRole('client');
						$this->is_systems_admin = $auth->hasRole('systems-admin');
						$this->is_fx_ops = $auth->hasRole('fx-ops');
						$this->is_fx_ops_lead = $auth->hasRole('fx-ops-lead');
						$this->is_fx_ops_manager = $auth->hasRole('fx-ops-manager');
						$this->is_treasury_ops = $auth->hasRole('treasury-ops');
					}
				} catch (\Exception $e) {
					Log::alert('Tried to validate auth roles');
				}

				return $next($request);
			});
	}

	/**
	 * Update buckets using transactions
	 *
	 * @param Transaction $transaction
	 * @return bool
	 */
	private function updateBucket(Transaction $transaction)
	{
		DB::beginTransaction();
		try {
			// Is Purchase or Sales Transaction...
			$buy = Product::findOrFail($transaction->buying_product_id);
			$sell = Product::findOrFail($transaction->selling_product_id);

			// Do calculation...
			$buy->prev_bucket = $buy->bucket;
			$buy->prev_bucket_local = $buy->bucket_local;
			$buy->bucket = $buy->bucket - $transaction->amount;
			$buy->bucket_local = $buy->wacc * $buy->bucket;
			$buy->save();

			$sell->prev_bucket = $sell->bucket;
			$sell->prev_bucket_local = $sell->bucket_local;
			$sell->bucket = $sell->bucket + $transaction->calculated_amount;
			$sell->bucket_local = $sell->wacc * $sell->bucket;
			$sell->save();

			DB::commit();
			$is_success = true;
		} catch (\Exception $e) {
			$is_success = false;
			DB::rollback();

			Log::emergency($e->getMessage());
		}

		return $is_success;
	}

	/**
	 * Update WACC
	 *
	 * @param int $product_id
	 * @param float $amount
	 * @param float $calculated_amount
	 * @return bool
	 */
	private function updateWACC(int $product_id, float $amount, float $calculated_amount)
	{
		DB::beginTransaction();
		try {
			$product = Product::findOrFail($product_id);

			// Store prev values...
			$product->prev_wacc = $product->wacc;
			$product->prev_seed_value = $product->seed_value;
			$product->prev_seed_value_local = $product->seed_value_local;

			// Calculate new values...
			$product->seed_value = $product->seed_value + $amount;
			$product->seed_value_local = $product->seed_value_local + $calculated_amount;
			$product->wacc = $product->seed_value_local / $product->seed_value;
			$product->save();

			DB::commit();
			$success = true;

			//Update timemline...
			Artisan::call('update:timeline');

		} catch (\Exception $e) {
			$success = false;
			DB::rollback();

			Log::emergency($e->getMessage());
		}

		return $success;
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index()
	{
		switch (true) {
			case $this->is_client:
				$transactions = User::find(Auth::user()->id)->client->transactions;
				break;

			case $this->is_fx_ops:
				$transactions = Transaction::where('transaction_status_id', 1)->orWhere('transaction_status_id', 2)->orderBy('updated_at', 'desc')->get();
				break;

			case $this->is_fx_ops_lead:
				$transactions = Transaction::where('transaction_status_id', 2)->orderBy('updated_at', 'desc')->get();
				break;

			case $this->is_fx_ops_manager:
				$transactions = Transaction::where('transaction_status_id', 3)->orderBy('updated_at', 'desc')->get();
				break;

			case $this->is_treasury_ops:
				$transactions = Transaction::where('transaction_status_id', 4)->orderBy('updated_at', 'desc')->get();
				break;

			case $this->is_systems_admin:
				$transactions = Transaction::orderBy('updated_at', 'desc')->get();
				break;

			default:
				$transactions = [];
		}

		// Load missing client data along with transaction
		$transactions->loadMissing('client:id,full_name,occupation');

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
	 * @param  \Illuminate\Http\Request $req
	 * @return \Illuminate\Http\Response
	 */
	public function requestTransaction(Request $req)
	{

		// Validate the request...
		$validator = Validator::make($req->all(), [
			'client_id' => 'exists:clients,id',
			'transaction_type_id' => 'exists:transaction_type,id',
			'transaction_mode_id' => 'exists:transaction_mode,id',
			'buying_product_id' => 'required|exists:products,id',
			'selling_product_id' => 'required|exists:products,id',
			'amount' => 'required|numeric',
			'account_id' => 'exists:accounts,id',
			'rate' => 'numeric',
			'account_number' => 'string',
			'account_name' => 'string',
			'bank_name' => 'string',
			'bvn' => 'string',
		]);

		if ($this->is_client) {
			// Determine transaction type...
			$have_foreign = in_array($req->selling_product_id, ['1', '2', '3']);
			$want_foreign = in_array($req->buying_product_id, ['1', '2', '3']);
			$have_local = in_array($req->selling_product_id, ['4']);
			$want_local = in_array($req->buying_product_id, ['4']);
			$same = $req->buying_product_id == $req->selling_product_id;
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
			$transaction_type_id = TransactionType::where('name', $type)->firstOrFail()->id;
			$transaction_mode_id = TransactionMode::where('name', $mode)->firstOrFail()->id;
			$req->merge(['transaction_type_id' => $transaction_type_id, 'transaction_mode_id' => $transaction_mode_id]);
		}

		if ($validator->fails()) {
			return response()->error($validator->errors(), 422);
		}

		$inputs = $req->only(['client_id', 'transaction_type_id', 'transaction_mode_id', 'buying_product_id', 'selling_product_id', 'account_id', 'amount', 'rate']);
		$transaction_status_id = TransactionStatus::where('name', 'open')->first()->id;


		try {
			// Get Client...
			if ($this->is_client) {
				$client = Auth::user()->client;
			} elseif ($this->is_staff) {
				$client = Client::find($req->client_id);
			} else {
				return response()->error('How did you get here!');
			}

			// Get or Create Account...
			if ($req->account_id) {
				$account = Account::find($req->account_id);
			} else {
				/*$owner_exists = Account::where('number', $r->account_number)->where('client_id', $client->id)->count();
				if ($owner_exists == 0) {
					return response()->success(compact('owner_exists'));
				}*/

				$account = Account::firstOrCreate(['number' => $req->account_number], ['number' => $req->account_number, 'name' => $req->account_name, 'bank' =>
					$req->bank_name, 'bvn' => $req->bvn, 'client_id' => $client->id]);

				// make sure account doesn't belong to a different client...
				if ($account->client_id !== $client->id) {
					return response()->error('account details provided belongs to a different customer');
				}

				// HACK >>>> get account again so it comes with id...
//				$account = Account::where('number', $r->account_number)->firstOrFail();
			}
		} catch (ModelNotFoundException $e) {
			return response()->error('No such client or account exists');
		}


		$transaction = new Transaction($inputs);

		// Check if user is a staff
		if ($this->is_staff) {
			$transaction->initiated_by = Auth::user()->id;
			$transaction->initiated_at = Carbon::now();
		}

		$transaction->transaction_status_id = $transaction_status_id;
		$transaction->client()->associate($client);
		$transaction->account()->associate($account);
		$transaction->save();

		if ($this->is_staff) {
			// trail Event...
			$audit = new TransactionEvent();

			$audit->transaction_id = $transaction->id;
			$audit->transaction_status_id = $transaction->transaction_status_id;
			$audit->action = 'Requested Transaction';
			$audit->amount = $transaction->amount;
			$audit->rate = $transaction->rate;

			$audit->done_by = Auth::User()->id;
			$audit->done_at = Carbon::now();
			$audit->save();
		}

		$transaction = Transaction::with('client', 'account', 'events')->findOrFail($transaction->id);

		// Notify all users in the next role...
		$recipients = User::withRole('fx-ops')->get();
		$emails = [];
		foreach ($recipients as $recipient) {
			$emails[] = $recipient->email;
		}
		Notification::route('mail', $emails)->notify(new NotifyStaff($transaction));

		return response()->success(compact('transaction'));
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request $req
	 * @return \Illuminate\Http\Response
	 */
	public function requestExpressTransaction(Request $req)
	{
		// Validate the request...
		$validator = Validator::make($req->all(), [
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
		$have_foreign = in_array($req->i_have, ['usd', 'eur', 'gbp']);
		$want_foreign = in_array($req->i_want, ['usd', 'eur', 'gbp']);
		$have_local = in_array($req->i_have, ['ngn']);
		$want_local = in_array($req->i_want, ['ngn']);
		$same = $req->i_want == $req->i_have;
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
				return response()->error('Unknown Transaction Type');
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
			$buying_product_id = Product::where('name', $req->i_want)->firstOrFail()->id;
			$selling_product_id = Product::where('name', $req->i_have)->firstOrFail()->id;
		} catch (ModelNotFoundException $e) {
			return response()->error('An Error Occured: ' . $e->getMessage());
		}

		// Merge the above into the request...
		$req->merge([
			'transaction_type_id' => $transaction_type_id,
			'transaction_mode_id' => $transaction_mode_id,
			'client_type' => 3, // todo - Review*
			'buying_product_id' => $buying_product_id,
			'selling_product_id' => $selling_product_id
		]);
		$inputs = $req->only(['client_id', 'transaction_type_id', 'transaction_mode_id', 'buying_product_id', 'selling_product_id', 'account_id', 'amount']);

		// Get or Create Client...
		$client = Client::firstOrCreate($req->only('email'), $req->only(['email', 'full_name', 'phone', 'client_type']));

		// Get or Create Account...
		$account = Account::firstOrCreate(['number' => $req->account_number], ['client_id' => $client->id, 'number' => $req->account_number, 'name' => $req->account_name, 'bank' =>
			$req->bank_name, 'bvn' => $req->bvn]);

		// Save transaction
		$transaction = new Transaction($inputs);

		$transaction->client()->associate($client);
		$transaction->account()->associate($account);
		$transaction->transaction_status_id = $open;
		$transaction->save();

		// $transaction = Transaction::with('client', 'account', 'events')->findOrFail($transaction->id);

		// Notify all users in the next role...
		$recipients = User::withRole('fx-ops')->get();
		$emails = [];
		foreach ($recipients as $recipient) {
			$emails[] = $recipient->email;
		}
		Notification::route('mail', $emails)->notify(new NotifyStaff($transaction));

		return response()->success('Request Successful');
	}

	/**
	 * Treat an open transaction
	 *
	 * @param  \Illuminate\Http\Request $req
	 * @param $transaction_id
	 * @return \Illuminate\Http\Response
	 */
	public function treatTransaction(Request $req, $transaction_id)
	{
		// check role...
		if (!$this->is_fx_ops) {
			return response()->error('You don\'t have the permission to perform the requested action!');
		}

		// Get the transaction...
		try {
			$transaction = Transaction::with('client')->findOrFail($transaction_id);
		} catch (ModelNotFoundException $e) {
			return response()->error('Transaction does not exist');
		}

		$transaction_status_open = TransactionStatus::where('name', $this::OPEN)->first()->id;
		$transaction_status_in_progress = TransactionStatus::where('name', $this::IN_PROGRESS)->first()->id;

		// @throw error if transaction is not OPEN or IN PROGRESS...
		if (!in_array($transaction->transaction_status_id, [$transaction_status_open, $transaction_status_in_progress])) {
			return response()->error('Transaction is not OPEN or IN PROGRESS');
		}

		// Validate the request...
		$validator = Validator::make($req->all(), [
//			'account_id' => 'exists:accounts,id',
			'org_account_id' => 'exists:organizations,id',
			'condition' => 'string',
			'comment' => 'string',
			'amount' => 'numeric',
			'calculated_amount' => 'numeric',
			'rate' => 'numeric',
		]);

		// todo validate account_id belongs to client_id

		if ($validator->fails()) {
			return response()->error($validator->errors(), 422);
		}

		$inputs = $req->only(['account_id', 'amount', 'rate', 'org_account_id', 'condition', 'calculated_amount']);
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
		$audit->comment = $req->comment;
		// todo add account_id
		$audit->calculated_amount = $transaction->calculated_amount;
		$audit->condition = $transaction->condition;
		$audit->org_account_id = $transaction->org_account_id;
		$audit->rate = $transaction->rate;

		$audit->done_by = Auth::user()->id;
		$audit->done_at = Carbon::now();
		$audit->save();

		$transaction = Transaction::with('client', 'account', 'events')->findOrFail($transaction->id);

		// Notify all users in the next role...
		$recipients = User::withRole('fx-ops-manager', 'fx-ops-lead')->get();
		foreach ($recipients as $recipient) {
			$emails[] = $recipient->email;
		}
		Notification::route('mail', $emails)->notify(new NotifyStaff($transaction, Auth::user()->staff, $audit));

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
		// check role...
		if (!($this->is_fx_ops_lead || $this->is_fx_ops_manager)) {
			return response()->error('You don\'t have the permission to perform the requested action!');
		}

		// Get the transaction...
		try {
			$transaction = Transaction::with('client')->findOrFail($transaction_id);
			$transaction_status_pending = TransactionStatus::where('name', $this::PENDING_APPROVAL)->first()->id;
			$transaction_next_status = TransactionStatus::where('name', $this::PENDING_FULFILMENT)->first()->id;
			$inputs = $request->only(['comment']);
		} catch (ModelNotFoundException $e) {
			return response()->error('Transaction does not exist');
		}

		// @throw error if transaction is not OPEN
		if ($transaction->transaction_status_id != $transaction_status_pending) {
			return response()->error('Transaction is not PENDING APPROVAL');
		}

		// Validate the request...
		$validator = Validator::make($request->all(), [
			'comment' => 'string',
		]);
		if ($validator->fails()) {
			return response()->error($validator->errors(), 422);
		}

		// Update transaction for FULFILMENT...
		$transaction->transaction_status_id = $transaction_next_status;
		$transaction->approved_by = Auth::user()->id;
		$transaction->approved_at = Carbon::now();
		$transaction->fill($inputs);
		$transaction->update();

		// trail Event
		$event = new TransactionEvent();
		$event->transaction_id = $transaction->id;
		$event->transaction_status_id = $transaction->transaction_status_id;
		$event->action = 'Approved Transaction';
		$event->comment = $request->comment;
		$event->calculated_amount = $transaction->calculated_amount;
		$event->condition = $transaction->condition;
		$event->org_account_id = $transaction->org_account_id;
		$event->rate = $transaction->rate;
		$event->done_by = Auth::User()->id;
		$event->done_at = Carbon::now();
		$event->save();
		$event->loadMissing('doneBy');

		// Re-Get transaction and all its related properties...
		$transaction = Transaction::with('client', 'account', 'events', 'events.doneBy')->findOrFail($transaction->id);

		// Update bucket funds...
		if ($transaction->transaction_type_id === 1 or $transaction->transaction_type_id === 2) {
			$update = $this->updateBucket($transaction);
		}

		// Notify all users in the next role...
		$recipients = User::withRole('treasury-ops')->get();
		foreach ($recipients as $recipient) {
			$emails[] = $recipient->email;
		}
		Notification::route('mail', $emails)->notify(new NotifyStaff($transaction, Auth::user()->staff, $event));

		// Return response...
		return response()->success(compact('transaction', 'update'));
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
		// check role...
		if (!$this->is_treasury_ops) {
			return response()->error('You don\'t have the permission to perform the requested action!');
		}

		// Get the transaction...
		try {
			$transaction = Transaction::with('client')->findOrFail($transaction_id);
			$transaction_status_pending_fulfilment = TransactionStatus::where('name', $this::PENDING_FULFILMENT)->first()->id;
			$transaction_next_status = TransactionStatus::where('name', $this::CLOSED)->first()->id;
			$inputs = $request->only(['comment']);
		} catch (ModelNotFoundException $e) {
			return response()->error('Transaction does not exist');
		}


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

		// Update transaction for FULFILMENT...
		$transaction->transaction_status_id = $transaction_next_status;
		$transaction->closed_by = Auth::user()->id;
		$transaction->closed_at = Carbon::now();
		$transaction->fill($inputs);
		$transaction->update();

		// trail Event
		$event = new TransactionEvent();
		$event->transaction_id = $transaction->id;
		$event->transaction_status_id = $transaction->transaction_status_id;
		$event->action = 'Fulfilled and Closed Transaction';
		$event->comment = $request->comment;
		$event->calculated_amount = $transaction->calculated_amount;
		$event->condition = $transaction->condition;
		$event->org_account_id = $transaction->org_account_id;
		$event->rate = $transaction->rate;
		$event->done_by = Auth::user()->id;
		$event->done_at = Carbon::now();
		$event->save();

		// Re-get transaction and related properties
		$transaction = Transaction::with('client', 'account', 'events', 'events.doneBy')->findOrFail($transaction->id);

		// Update WACC...
		$this->updateWACC($transaction->buying_product_id, $transaction->amount, $transaction->calculated_amount);

		// Notify client...
		Notification::route('mail', $transaction->client->email)->notify(new NotifyClient($transaction, $event));

		return response()->success(compact('transaction'));
	}

	/**
	 * Cancel/Close an un-closed transaction
	 *
	 * @param  \Illuminate\Http\Request $request
	 * @param $transaction_id
	 * @return \Illuminate\Http\Response
	 */
	public function cancelTransaction(Request $request, $transaction_id)
	{
		// check role...
		if (!$this->is_staff) {
			return response()->error('You don\'t have the permission to perform the requested action!');
		}

		// Get the transaction...
		try {
			$transaction = Transaction::with('client')->findOrFail($transaction_id);
			$transaction_status_closed = TransactionStatus::where('name', $this::CLOSED)->first()->id;
			$transaction_next_status = TransactionStatus::where('name', $this::CANCELLED)->first()->id;
		} catch (ModelNotFoundException $e) {
			return response()->error('Transaction does not exist');
		}

		// @throw error if transaction is already CLOSED or CANCELLED
		if (in_array($transaction->transaction_status_id, [$transaction_status_closed, $transaction_next_status])) {
			return response()->error('Transaction is already CLOSED or CANCELLED');
		}

		// Validate the request...
		$validator = Validator::make($request->all(), [
			'comment' => 'required|string'
		]);
		if ($validator->fails()) {
			return response()->error($validator->errors(), 422);
		}

		// Update transaction for FULFILMENT...
		$transaction->transaction_status_id = $transaction_next_status;
		$transaction->cancelled_by = Auth::user()->id;
		$transaction->cancelled_at = Carbon::now();
		$transaction->update();

		// trail Event
		$event = new TransactionEvent();
		$event->transaction_id = $transaction->id;
		$event->transaction_status_id = $transaction->transaction_status_id;
		$event->action = 'Cancelled Transaction';
		$event->comment = $request->comment;
		$event->calculated_amount = $transaction->calculated_amount;
		$event->condition = $transaction->condition;
		$event->org_account_id = $transaction->org_account_id;
		$event->rate = $transaction->rate;
		$event->done_by = Auth::user()->id;
		$event->done_at = Carbon::now();
		$event->save();

		$transaction = Transaction::with('client', 'account', 'events', 'events.doneBy')->findOrFail($transaction->id);

		// Notify only client...
		Notification::route('mail', $transaction->client->email)->notify(new NotifyClient($transaction, $event));

		return response()->success(compact('transaction'));
	}

	/**
	 * Reject any transaction back for approval
	 *
	 * @param  \Illuminate\Http\Request $request
	 * @param $transaction_id
	 * @return \Illuminate\Http\Response
	 */
	public function rejectTransaction(Request $request, $transaction_id)
	{
		// check role...
		if (!$this->is_staff) {
			return response()->error('You don\'t have the permission to perform the requested action!');
		}

		// Get the transaction...
		try {
			$transaction = Transaction::with('client')->findOrFail($transaction_id);
			$transaction_next_status = TransactionStatus::where('name', $this::IN_PROGRESS)->first()->id;
		} catch (ModelNotFoundException $e) {
			return response()->error('Transaction does not exist');
		}

		// @throw error if transaction is already REJECTED
		if (in_array($transaction->transaction_status_id, [$transaction_next_status])) {
			return response()->error('Transaction is already REJECTED');
		}

		// Validate the request...
		$validator = Validator::make($request->all(), [
			'comment' => 'required|string'
		]);
		if ($validator->fails()) {
			return response()->error($validator->errors(), 422);
		}

		// Update transaction for REVIEW...
		$transaction->transaction_status_id = $transaction_next_status;
		$transaction->update();

		// trail Event
		$event = new TransactionEvent();
		$event->transaction_id = $transaction->id;
		$event->transaction_status_id = $transaction->transaction_status_id;
		$event->action = 'Transaction Rejected';
		$event->comment = $request->comment;
		$event->calculated_amount = $transaction->calculated_amount;
		$event->condition = $transaction->condition;
		$event->org_account_id = $transaction->org_account_id;
		$event->rate = $transaction->rate;
		$event->done_by = Auth::user()->id;
		$event->done_at = Carbon::now();
		$event->save();

		// Re-get transaction with all its related properties...
		$transaction = Transaction::with('client', 'account', 'events', 'events.doneBy')->findOrFail($transaction->id);

		// Notify all users in the next role...
		$recipients = User::withRole('fx-ops')->get();
		$emails = [];
		foreach ($recipients as $recipient) {
			$emails[] = $recipient->email;
		}
		Notification::route('mail', $emails)->notify(new NotifyStaff($transaction, Auth::user()->staff, $event));
		Notification::route('mail', $transaction->client->email)->notify(new NotifyClient($transaction, $event));

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
	 * @param $transaction_id
	 * @return \Illuminate\Http\Response
	 */
	public function show($transaction_id)
	{
		/*
		 * if (true) {
			return response()->success(
				Auth::user()->whereHas('roles', function ($query) {
					$query->where('name', 'systems-admin');
				})->get());
		}*/

		try {
			$transaction = Transaction::with('client', 'account', 'events', 'events.doneBy')->findOrFail($transaction_id);
		} catch (ModelNotFoundException $e) {
			return response()->error('No such Transaction exists');
		}

		if ($this->is_client) {
			if ($transaction['client_id'] !== Auth::user()->client->id) {
				return response()->error('You don\'t have the permission to access that transaction');
			}
		} else {
			//
		}

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
