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

class TransactionController extends Controller
{
	const OPEN = 'open';
	const IN_PROGRESS = 'in-progress';
	const PENDING_APPROVAL = 'pending-approval';
	const PENDING_FULFILMENT = 'pending-fulfilment';
	const CANCELLED = 'cancelled';
	const CLOSED = 'closed';
	const RAISED = 'raised';

	const PURCHASE = 1;
	const SALES = 2;
	const SWAP = 3;
	const REFUND = 4;
	const EXPENSES = 5;
	const CROSS = 6;

	const CASH = 1 or '1';
	const TRANSFER = 2;
	const CASH_DEPOSIT = 3;

	const PASSED = true;
	const FAILED = false;

	const OTHER_TRANSACTION_TYPES = [
		self::REFUND,
		self::EXPENSES
	];

	protected $is_fx_ops;
	protected $is_treasury_ops;
	protected $is_fx_ops_manager;
	protected $is_systems_admin;

	private $transaction_type;


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
		 *
		 * @return mixed
		 */
			function ($request, $next) {
				try {
					if (Auth::user() !== NULL) {
						$auth = Auth::user();

						$this->is_client = $auth->hasRole('client');
						$this->is_fx_ops = $auth->hasRole('fx-ops');
						$this->is_treasury_ops = $auth->hasRole('treasury-ops');
						$this->is_systems_admin = $auth->hasRole('systems-admin');
						$this->is_fx_ops_manager = $auth->hasRole('fx-ops-manager');
					}
				} catch (\Exception $e) {
					Log::alert('Tried to validate auth roles - ' . $e->getMessage());
				}

				return $next($request);
			});
	}


	/**
	 *  Get Rate
	 *
	 * @param $buying_product_id
	 * @param $selling_product_id
	 *
	 * @return bool|float
	 */
	private function getRate($buying_product_id, $selling_product_id)
	{
		$rates = Product::clientRates()->get();

		$b = $buying_product_id;
		$s = $selling_product_id;

		if ($rates) {
			try {
				return $rate = round(($rates[$b - 1]['rate'] / $rates[$s - 1]['rate']), 4);
			} catch (\Exception $e) {
				return self::FAILED;
			}
		}
	}


	/**
	 *  Get Transaction Type
	 *
	 * @param Int      $selling
	 * @param Int      $buying
	 * @param int|null $transaction_type_id
	 *
	 * @return int
	 */
	private function getTransactionType(int $selling, int $buying, int $transaction_type_id = NULL)
	{
		if (in_array($transaction_type_id, self::OTHER_TRANSACTION_TYPES)) {
			return $this->transaction_type = $transaction_type_id;
		}

		// Determine transaction type...
		$have_foreign = in_array($selling, [
			1,
			2,
			3
		]);
		$want_foreign = in_array($buying, [
			1,
			2,
			3
		]);
		$have_local = in_array($selling, [4]);
		$want_local = in_array($buying, [4]);

		$same = $buying == $selling;

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

		return $this->transaction_type = TransactionType::where('name', $type)->firstOrFail()->id;
	}


	/**
	 * Update buckets for Sales and Purchase Transactions
	 *
	 * @param Transaction $transaction
	 *
	 * @return bool|string
	 */
	private function updateBucketForPurchaseOrSale(Transaction $transaction)
	{
		DB::beginTransaction();

		try {
			// Is Purchase or Sales Transaction...
			$buy = Product::findOrFail($transaction->buying_product_id);
			$sell = Product::findOrFail($transaction->selling_product_id);

			// Do calculation...
			$buy->prev_bucket = $buy->bucket;
			$buy->prev_bucket_local = $buy->bucket_local;
			switch ($transaction->transaction_mode_id) {

				case self::CASH:
				case self::CASH_DEPOSIT:
					$buy->bucket_cash = $buy->bucket_cash - $transaction->amount;
					if ($buy->bucket_cash < 0) {
						throw new \Exception('Not enough funds to approve request!');
					}

					break;
				case self::TRANSFER:
					$buy->bucket_transfer = $buy->bucket_transfer - $transaction->amount;
					if ($buy->bucket_transfer < 0) {
						throw new \Exception('Not enough funds to approve request!');
					}

					break;
				default:
					throw new \Exception('Transaction Mode not recognised!');
			}
			$buy->bucket = $buy->bucket - $transaction->amount;
			$buy->bucket_local = $buy->wacc * $buy->bucket;     // todo REVIEW
			$buy->save();

			$sell->prev_bucket = $sell->bucket;
			$sell->prev_bucket_local = $sell->bucket_local;
			switch ($transaction->transaction_mode_id) {

				case self::CASH:
				case self::CASH_DEPOSIT:
					$sell->bucket_cash = $sell->bucket_cash + ($transaction->amount * $transaction->rate);

					break;
				case self::TRANSFER:
					$sell->bucket_transfer = $sell->bucket_transfer + ($transaction->amount * $transaction->rate);

					break;
				default:
					throw new \Exception('Transaction Mode not recognised!');
			}
			$sell->bucket = $sell->bucket + ($transaction->amount * $transaction->rate);
			$sell->bucket_local = $sell->wacc * $sell->bucket;       // todo REVIEW
			$sell->save();

			DB::commit();

			return self::PASSED;
		} catch (\Exception $e) {
			DB::rollback();
			Log::emergency($e->getMessage());

			return $e->getMessage();
		}
	}


	/**
	 * Update buckets for Cross Transactions
	 *
	 * @param Transaction $transaction
	 *
	 * @return bool|string
	 */
	private function updateBucketForCross(Transaction $transaction)
	{
		DB::beginTransaction();

		try {
			// Is Cross...
			$buy = Product::findOrFail($transaction->buying_product_id);
			$sell = Product::findOrFail($transaction->selling_product_id);

			// Do calculation...
			$buy->prev_bucket = $buy->bucket;
			$buy->prev_bucket_local = $buy->bucket_local;

			// Select Cash or Transfer Bucket
			switch ($transaction->transaction_mode_id) {
				case self::CASH:
				case self::CASH_DEPOSIT:
					$buy->bucket_cash = $buy->bucket_cash - $transaction->amount;
					if ($buy->bucket_cash < 0) {
						throw new \Exception('Not enough funds to approve request!');
					}

					break;
				case self::TRANSFER:
					$buy->bucket_transfer = $buy->bucket_transfer - $transaction->amount;
					if ($buy->bucket_transfer < 0) {
						throw new \Exception('Not enough funds to approve request!');
					}

					break;
				default:
					throw new \Exception('Transaction Mode not recognised!');
			}
			$buy->bucket = $buy->bucket - $transaction->amount;
			$buy->bucket_local = $buy->wacc * $buy->bucket;     // todo REVIEW
			$buy->save();

			$sell->prev_bucket = $sell->bucket;
			$sell->prev_bucket_local = $sell->bucket_local;

			// Select Cash or Transfer Bucket
			switch ($transaction->transaction_mode_id) {
				case self::CASH:
				case self::CASH_DEPOSIT:
					$sell->bucket_cash = $sell->bucket_cash + ($transaction->amount * $transaction->rate);

					break;
				case self::TRANSFER:
					$sell->bucket_transfer = $sell->bucket_transfer + ($transaction->amount * $transaction->rate);

					break;
				default:
					throw new \Exception('Transaction Mode not recognised!');
			}
			$sell->bucket = $sell->bucket + ($transaction->amount * $transaction->rate);
			$sell->bucket_local = $sell->wacc * $sell->bucket;       // todo REVIEW
			$sell->save();

			DB::commit();

			return self::PASSED;
		} catch (\Exception $e) {
			DB::rollback();
			Log::emergency($e->getMessage());

			return $e->getMessage();
		}
	}


	/**
	 * Update buckets for Swap Transactions
	 *
	 * @param Transaction $transaction
	 *
	 * @return bool|string
	 */
	private function updateBucketForSwap(Transaction $transaction, $swap_charges)
	{
		DB::beginTransaction();

		try {
			// Is Cross...
			$buy = Product::findOrFail($transaction->buying_product_id);
			$sell = Product::findOrFail($transaction->selling_product_id);

			// Do calculation...
			$buy->prev_bucket = $buy->bucket;
			$buy->prev_bucket_local = $buy->bucket_local;
			switch ($transaction->transaction_mode_id) {

				case self::CASH:
				case self::CASH_DEPOSIT:
					$buy->bucket_cash = $buy->bucket_cash - $transaction->amount;
					if ($buy->bucket_cash < 0) {
						throw new \Exception('Not enough funds to approve request!');
					}

					break;
				case self::TRANSFER:
					$buy->bucket_transfer = $buy->bucket_transfer - $transaction->amount;
					if ($buy->bucket_transfer < 0) {
						throw new \Exception('Not enough funds to approve request!');
					}

					break;
				default:
					throw new \Exception('Transaction Mode not recognised!');
			}
			$buy->bucket = $buy->bucket - $transaction->amount;
			$buy->bucket_local = $buy->wacc * $buy->bucket;     // todo REVIEW
			$buy->save();

			$sell->prev_bucket = $sell->bucket;
			$sell->prev_bucket_local = $sell->bucket_local;
			switch ($transaction->transaction_mode_id) {

				case self::CASH:
				case self::CASH_DEPOSIT:
					$sell->bucket_cash = $sell->bucket_cash + (($transaction->amount * $transaction->rate) + $transaction->swap_charges);

					break;
				case self::TRANSFER:
					$sell->bucket_transfer = $sell->bucket_transfer + (($transaction->amount * $transaction->rate) + $transaction->swap_charges);

					break;
				default:
					throw new \Exception('Transaction Mode not recognised!');
			}
			$sell->bucket = $sell->bucket + ($transaction->amount * $transaction->rate);
			$sell->bucket_local = $sell->wacc * $sell->bucket;       // todo REVIEW
			$sell->save();

			DB::commit();

			return self::PASSED;
		} catch (\Exception $e) {
			DB::rollback();
			Log::emergency($e->getMessage());

			return $e->getMessage();
		}
	}


	/**
	 * Update buckets for Swap Transactions
	 *
	 * @param Transaction $transaction
	 *
	 * @return bool|string
	 */
	private function updateBucketForExpenses(Transaction $transaction)
	{
		DB::beginTransaction();

		try {
			// Is Cross...
			$buy = Product::findOrFail($transaction->buying_product_id);
			$sell = Product::findOrFail($transaction->selling_product_id);

			// Do calculation...
			$buy->prev_bucket = $buy->bucket;
			$buy->prev_bucket_local = $buy->bucket_local;
			switch ($transaction->transaction_mode_id) {

				case self::CASH:
				case self::CASH_DEPOSIT:
					$buy->bucket_cash = $buy->bucket_cash - $transaction->amount;
					if ($buy->bucket_cash < 0) {
						throw new \Exception('Not enough funds to approve request!');
					}

					break;
				case self::TRANSFER:
					$buy->bucket_transfer = $buy->bucket_transfer - $transaction->amount;
					if ($buy->bucket_transfer < 0) {
						throw new \Exception('Not enough funds to approve request!');
					}

					break;
				default:
					throw new \Exception('Transaction Mode not recognised!');
			}
			$buy->bucket = $buy->bucket - $transaction->amount;
			$buy->bucket_local = $buy->wacc * $buy->bucket;     // todo REVIEW
			$buy->save();

			/*$sell->prev_bucket = $sell->bucket;
			$sell->prev_bucket_local = $sell->bucket_local;
			switch ($transaction->transaction_mode_id) {

				case self::CASH:
				case self::CASH_DEPOSIT:
					$sell->bucket_cash = $sell->bucket_cash + (($transaction->amount * $transaction->rate) + $transaction->swap_charges);

					break;
				case self::TRANSFER:
					$sell->bucket_transfer = $sell->bucket_transfer + (($transaction->amount * $transaction->rate) + $transaction->swap_charges);

					break;
				default:
					throw new \Exception('Transaction Mode not recognised!');
			}
			$sell->bucket = $sell->bucket + ($transaction->amount * $transaction->rate);
			$sell->bucket_local = $sell->wacc * $sell->bucket;       // todo REVIEW
			$sell->save();*/

			DB::commit();

			return self::PASSED;
		} catch (\Exception $e) {
			DB::rollback();
			Log::emergency($e->getMessage());

			return $e->getMessage();
		}
	}


	/**
	 * Update WACC
	 *
	 * @param int   $product_id
	 * @param float $amount
	 * @param float $calculated_amount
	 *
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
				$transactions = Transaction::orderBy('updated_at', 'desc')->get();
				break;

			case $this->is_fx_ops_manager:
				$transactions = Transaction::pendingApproval()->orderBy('updated_at', 'desc')->get();
				break;

			case $this->is_treasury_ops:
				$transactions = Transaction::pendingFulfilment()->orderBy('updated_at', 'desc')->get();
				break;

			case $this->is_systems_admin:
				$transactions = Transaction::orderBy('updated_at', 'desc')->get();
				break;

			default:
				$transactions = [];
		}

		// Load missing client data along with transaction
		$transactions->loadMissing('client:id,first_name,last_name,middle_name,occupation');

		return response()->success(compact('transactions'));
	}


	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request $req
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function requestTransaction(Request $req)
	{

		// Validate the request...
		$validator = Validator::make($req->all(), [
			'client_id'           => 'exists:clients,id',
			'transaction_type_id' => 'exists:transaction_type,id',
			'transaction_mode_id' => 'exists:transaction_mode,id',
			'buying_product_id'   => 'required|exists:products,id',
			'selling_product_id'  => 'required|exists:products,id',
			'amount'              => 'required|numeric',
			'country'             => 'required|string',
			'account_id'          => 'exists:accounts,id',
			'is_domiciliary'      => 'boolean',
			'rate'                => 'numeric',
			'account_number'      => 'string|digits:10',
			'account_name'        => 'string',
			'bank_name'           => 'string',
			'sort_code'           => 'string',
			'swift_code'          => 'string',
			'routing_no'          => 'string',
			'iban'                => 'string',
			'referrer'            => 'string',
			'comment'             => 'string',
		]);

		if ($validator->fails()) {
			return response()->error($validator->errors(), 422);
		}

		switch (true) {
			case $this->is_client:
				$transaction_type_id = $this->getTransactionType($req->selling_product_id, $req->buying_product_id);
				// $transaction_mode_id = $req->transaction_mode_id;
				$req->merge([
					'transaction_type_id' => $transaction_type_id,
					// 'transaction_mode_id' => $transaction_mode_id
				]);

				$inputs = $req->only([
					'client_id',
					'transaction_type_id',
					'transaction_mode_id',
					'buying_product_id',
					'selling_product_id',
					'is_domiciliary',
					'amount',
					'rate',
					'purpose',
					'country'
				]);
				$client = Auth::user()->client;

				break;

			case $this->is_fx_ops:
				$transaction_type_id = $this->getTransactionType($req->selling_product_id, $req->buying_product_id, $req->transaction_type_id);

				if ($transaction_type_id === 4) {
					// Check if it has Originating ID
					if (!$req->originating_id) {
						return response()->error('A Refund transaction must have an originating transaction!');
					}

					$d_transaction = Transaction::where('originating_id', $req->originating_id);
					if (!empty($d_transaction)) {
						return response()->error('Transaction has been refunded already!');
					}

					$o_transaction = Transaction::findOrFail($req->originating_id);
					if (!in_array($o_transaction->transaction_status_id, [
						6,
						8
					])) {
						return response()->error('The Originating Transaction cannot be refunded!');
					}
				}

				$req->merge([
					'transaction_type_id' => $transaction_type_id,
				]);
				$inputs = $req->only([
					'client_id',
					'transaction_type_id',
					'transaction_mode_id',
					'buying_product_id',
					'selling_product_id',
					'is_domiciliary',
					'amount',
					'rate',
					'swap_charges',
					'country',
					'condition',
					'purpose',
					'referrer'
				]);
				$client = Client::findOrFail($req->client_id);

				break;

			default:
				return response()->error('You are not eligible to make this request', 403);
		}

		try {
			// Get the transaction status...
			$transaction_status_id = TransactionStatus::where('name', 'open')->first()->id;

			// Get or Create Account...
			if ($req->account_id) {
				// Get Account
				$account = Account::findOrFail($req->account_id);
			} else if ($req->account_number) {
				// Create Account
				$account = Account::firstOrCreate(
					[
						'number' => $req->account_number
					],
					[
						'number'    => $req->account_number,
						'name'      => $req->account_name,
						'bank'      => $req->bank_name,
						'client_id' => $client->id,

						'foreign'      => $req->foreign,
						'bank_address' => $req->bank_address,
						'routing_no'   => $req->routing_no,
						'sorting_code' => $req->sort_code,
						'iban'         => $req->iban,
					]);
			} else if ($req->transaction_mode_id != self::CASH) {
				return response()->error('Account Details required for Non-Cash Transactions');
			}

			// make sure account doesn't belong to a different client...
			if (isset($account)) {
				if ($account->client_id !== $client->id) {
					return response()->error('Account details provided belongs to a different customer');
				}
			}

		} catch (ModelNotFoundException $e) {
			return response()->error('No such client or account exists');
		}

		$transaction = new Transaction($inputs);

		// Check if user is fx-Ops
		if ($this->is_fx_ops) {
			// Check if its a Refund
			if ($transaction_type_id === 4) {
				$transaction->originating_id = $req->originating_id;
			}
			$transaction->initiated_by = Auth::user()->id;
			$transaction->initiated_at = Carbon::now();
		}

		$transaction->transaction_status_id = $transaction_status_id;
		if (($transaction->rate = $this->getRate($transaction->buying_product_id, $transaction->selling_product_id)) === self::FAILED) {
			return response()->error('Unable to apply rate');
		}
		$transaction->calculated_amount = $transaction->amount * $transaction->rate;
		$transaction->client()->associate($client);
		if (isset($account)) {
			$transaction->account()->associate($account);
		}
		$transaction->save();

		if ($this->is_fx_ops) {
			// trail Event...
			$audit = new TransactionEvent();

			$audit->transaction_id = $transaction->id;
			$audit->transaction_status_id = $transaction->transaction_status_id;
			$audit->action = 'Requested Transaction';
			$audit->amount = $transaction->amount;
			$audit->rate = $transaction->rate;
			$audit->comment = $req->comment;

			$audit->done_by = Auth::User()->id;
			$audit->done_at = Carbon::now();
			$audit->save();
		}

		$transaction = Transaction::with('client', 'client.kyc', 'account', 'events')->findOrFail($transaction->id);

		// Notify all users in the next role...
		$recipients = User::withRole('fx-ops')->get();
		$emails = [];
		foreach ($recipients as $recipient) {
			$emails[] = $recipient->email;
		}
		Notification::route('mail', $emails)->notify(new NotifyStaff($transaction));

		// Notify client...
		if ($this->is_client) {
			Notification::route('mail', $transaction->client->email)->notify(new NotifyClient($transaction, NULL));
		}
		return response()->success(compact('transaction'));
	}


	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request $req
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function requestExpressTransaction(Request $req)
	{
		// Validate the request...
		$validator = Validator::make($req->all(), [
			'first_name'     => 'required',
			'last_name'      => 'required',
			'email'          => 'required',
			'phone'          => 'required',
			'amount'         => 'required|numeric',
			'i_have'         => 'required',
			'i_want'         => 'required',
			'account_number' => 'required|digits:10',
			'account_name'   => 'required',
			'bank_name'      => 'required',
			'bvn'            => 'required|digits:10',
		]);

		// todo validate account_id belongs to client_id...
		if ($validator->fails()) {
			return response()->error($validator->errors(), 422);
		}

		// Determine transaction mode...
		$mode = 'cash'; // todo - Review*

		// Get Transaction type, mode and product...
		try {
			$open = TransactionStatus::where('name', 'open')->firstOrFail()->id;
			$transaction_type_id = $this->getTransactionType($req->i_have, $req->i_want);
			$transaction_mode_id = TransactionMode::where('name', $mode)->firstOrFail()->id;
			$buying_product_id = Product::findOrFail($req->i_want)->id;
			$selling_product_id = Product::findOrFail($req->i_have)->id;
		} catch (ModelNotFoundException $e) {
			return response()->error('An Error Occured: ' . $e->getMessage());
		}

		// Merge the above into the request...
		$req->merge([
			'transaction_type_id' => $transaction_type_id,
			'transaction_mode_id' => $transaction_mode_id,
			'client_type'         => 3,
			// todo - Review*
			'buying_product_id'   => $buying_product_id,
			'selling_product_id'  => $selling_product_id
		]);
		$inputs = $req->only([
			'client_id',
			'transaction_type_id',
			'transaction_mode_id',
			'buying_product_id',
			'selling_product_id',
			'account_id',
			'amount'
		]);

		// Get or Create Client...
		$client = Client::firstOrCreate($req->only('email'), $req->only([
			'email',
			'first_name',
			'middle_name',
			'last_name',
			'phone',
			'client_type'
		]));

		// Get or Create Account...
		$account = Account::firstOrCreate(['number' => $req->account_number], [
			'client_id' => $client->id,
			'number'    => $req->account_number,
			'name'      => $req->account_name,
			'bank'      => $req->bank_name
		]);

		// Save transaction
		$transaction = new Transaction($inputs);

		$transaction->client()->associate($client);
		$transaction->account()->associate($account);
		$transaction->transaction_status_id = $open;
		$transaction->save();

		// $transaction = Transaction::with('client', 'client.kyc', 'account', 'events')->findOrFail($transaction->id);

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
	 * @param                           $transaction_id
	 *
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
		if (!in_array($transaction->transaction_status_id, [
			$transaction_status_open,
			$transaction_status_in_progress
		])) {
			return response()->error('Transaction is not OPEN or IN PROGRESS');
		}

		// Validate the request...
		$validator = Validator::make($req->all(), [
			// 'account_id' => 'exists:accounts,id',
			'org_account_id' => 'exists:organizations,id',
			'condition'      => 'string',
			'comment'        => 'string',
			'amount'         => 'numeric',
			'rate'           => 'numeric',
			// 'aml_check'      => 'boolean',
			// 'kyc_check'      => 'boolean',
			// 'swap_charges'   => 'numeric',
		]);

		// todo validate account_id belongs to client_id

		if ($validator->fails()) {
			return response()->error($validator->errors(), 422);
		}

		$inputs = $req->only([
			'account_id',
			'amount',
			'rate',
			'org_account_id',
			'condition',
			'calculated_amount',
			'kyc_check',
			'aml_check',
			'swap_charges'
		]);
		$pending_approval = TransactionStatus::where('name', $this::PENDING_APPROVAL)->first()->id;

		$transaction->calculated_amount = $transaction->rate * $transaction->amount;
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

		$transaction = Transaction::with('client', 'client.kyc', 'account', 'events')->findOrFail($transaction->id);

		// Notify all users in the next role...
		$recipients = User::withRole('fx-ops-manager', 'fx-ops')->get();
		foreach ($recipients as $recipient) {
			$emails[] = $recipient->email;
		}
		Notification::route('mail', $emails)->notify(new NotifyStaff($transaction, Auth::user()->staff, $audit));

		return response()->success(compact('transaction'));
	}


	/**
	 * Update a transaction
	 *
	 * @param  \Illuminate\Http\Request $req
	 * @param  int                      $transaction_id
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function updateTransaction(Request $req, $transaction_id)
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

		// Validate the request...
		$validator = Validator::make($req->all(), [
			'funds_paid'     => 'boolean',
			'funds_received' => 'boolean',
		]);
		if ($validator->fails()) {
			return response()->error($validator->errors(), 422);
		}

		// Update Funds check
		$transaction->funds_paid = $req->funds_paid;
		$transaction->funds_received = $req->funds_received;
		$transaction->update();

		// trail Event
		$audit = new TransactionEvent();
		$audit->transaction_id = $transaction->id;
		$audit->transaction_status_id = $transaction->transaction_status_id;
		$audit->action = 'Updated Transaction';
		$audit->comment = $req->comment ? $req->comment : 'Updated Fund checks';
		// todo add account_id
		$audit->calculated_amount = $transaction->calculated_amount;
		$audit->condition = $transaction->condition;
		$audit->org_account_id = $transaction->org_account_id;
		$audit->rate = $transaction->rate;

		$audit->done_by = Auth::user()->id;
		$audit->done_at = Carbon::now();
		$audit->save();

		$transaction = Transaction::with('client', 'client.kyc', 'account', 'events')->findOrFail($transaction->id);

		return response()->success(compact('transaction'));
	}


	/**
	 * Approve a treated transaction
	 *
	 * @param  \Illuminate\Http\Request $request
	 * @param                           $transaction_id
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function approveTransaction(Request $request, $transaction_id)
	{
		// check role...
		if (!$this->is_fx_ops_manager) {
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
			'org_account_id' => 'exists:organizations,id',
			'condition'      => 'string',
			'comment'        => 'string',
		]);
		if ($validator->fails()) {
			return response()->error($validator->errors(), 422);
		}

		// try update bucket funds...
		switch ($transaction->transaction_type_id) {
			case self::PURCHASE:
			case self::SALES:
				if (($message = $this->updateBucketForPurchaseOrSale($transaction)) !== self::PASSED) {
					return response()->error($message);
				};
				break;
			case self::CROSS:
				if (($message = $this->updateBucketForCross($transaction)) !== self::PASSED) {
					return response()->error($message);
				};
				break;
			case self::SWAP:
				$transaction->swap_charges = $request->swap_charges;
				$transaction->calculated_amount = $transaction->swap_charges + $transaction->amount;
				if (($message = $this->updateBucketForSwap($transaction, $transaction->swap_charges)) !== self::PASSED) {
					return response()->error($message);
				};
				break;
			case self::EXPENSES:
				if (($message = $this->updateBucketForExpenses($transaction)) !== self::PASSED) {
					return response()->error($message);
				};
				break;
			default:
				break;
		}

		// Update transaction for FULFILMENT...
		$transaction->transaction_status_id = $transaction_next_status;
		$transaction->approved_by = Auth::user()->id;
		$transaction->approved_at = Carbon::now();
		$transaction->org_account_id = $request->org_account_id;
		$transaction->condition = $request->condition;
		$transaction->update();

		// trail Event
		$event = new TransactionEvent();
		$event->transaction_id = $transaction->id;
		$event->transaction_status_id = $transaction->transaction_status_id;
		$event->action = 'Approved Transaction';
		$event->comment = $request->comment;
		$event->condition = $transaction->condition;
		$event->org_account_id = $transaction->org_account_id;
		$event->done_by = Auth::User()->id;
		$event->done_at = Carbon::now();
		$event->save();
		$event->loadMissing('doneBy');

		// Re-Get transaction and all its related properties...
		$transaction = Transaction::with('client', 'client.kyc', 'account', 'events', 'events.doneBy')->findOrFail($transaction->id);

		// Notify all users in the next role...
		$recipients = User::withRole('treasury-ops')->get();
		foreach ($recipients as $recipient) {
			$emails[] = $recipient->email;
		}
		Notification::route('mail', $emails)->notify(new NotifyStaff($transaction, Auth::user()->staff, $event));

		// Notify Client
		Notification::route('mail', $transaction->client->email)->notify(new NotifyClient($transaction, $event));

		// Return response...
		return response()->success(compact('transaction', 'update'));
	}


	/**
	 * Fulfil an approved transaction
	 *
	 * @param  \Illuminate\Http\Request $request
	 * @param                           $transaction_id
	 *
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
			'comment'        => 'string',
			'funds_paid'     => 'boolean',
			'funds_received' => 'boolean',
		]);

		if ($validator->fails()) {
			return response()->error($validator->errors(), 422);
		}

		// Update transaction for FULFILMENT...
		$transaction->transaction_status_id = $transaction_next_status;
		$transaction->closed_by = Auth::user()->id;
		$transaction->closed_at = Carbon::now();
		$transaction->funds_paid = $request->funds_paid;
		$transaction->funds_received = $request->funds_received;
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
		$transaction = Transaction::with('client', 'client.kyc', 'account', 'events', 'events.doneBy')->findOrFail($transaction->id);

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
	 * @param                           $transaction_id
	 *
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
		if (in_array($transaction->transaction_status_id, [
			$transaction_status_closed,
			$transaction_next_status
		])) {
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

		$transaction = Transaction::with('client', 'client.kyc', 'account', 'events', 'events.doneBy')->findOrFail($transaction->id);

		// Notify only client...
		Notification::route('mail', $transaction->client->email)->notify(new NotifyClient($transaction, $event));

		return response()->success(compact('transaction'));
	}


	/**
	 * Reject any transaction back for approval
	 *
	 * @param  \Illuminate\Http\Request $request
	 * @param                           $transaction_id
	 *
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
		$event->action = 'Returned Transaction';
		$event->comment = $request->comment;
		$event->calculated_amount = $transaction->calculated_amount;
		$event->condition = $transaction->condition;
		$event->org_account_id = $transaction->org_account_id;
		$event->rate = $transaction->rate;
		$event->done_by = Auth::user()->id;
		$event->done_at = Carbon::now();
		$event->save();

		// Re-get transaction with all its related properties...
		$transaction = Transaction::with('client', 'client.kyc', 'account', 'events', 'events.doneBy')->findOrFail($transaction->id);

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
	 * Get last 3 transactions
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function recentTransactions()
	{
		switch (true) {
			case $this->is_client:
				$client = Auth::user()->client;

				$transactions = $client->transactions()
					->select([
						'id',
						'amount',
						'transaction_status_id',
						'buying_product_id',
						'selling_product_id',
						'client_id',
						'account_id',
						'updated_at'
					])
					->orderBy('updated_at', 'desc')
					->limit(6)->get();
				$transactions->loadMissing('events:id,done_by,transaction_id', 'events.doneBy:id,name,email', 'account:id,number');
				break;

			case $this->is_fx_ops:
				$transactions = Transaction::initiatedByStaff()->recent()->limit(10)->get();
				$transactions->loadMissing('events:id,done_by,transaction_id', 'client:id,first_name,middle_name,last_name,occupation', 'events.doneBy:id,name,email', 'account:id,number');
				break;

			case $this->is_fx_ops_manager:
				$transactions = Transaction::pendingApproval()->recent()->limit(10)->get();
				$transactions->loadMissing('events:id,done_by,transaction_id', 'client:id,first_name,middle_name,last_name,occupation', 'events.doneBy:id,name,email', 'account:id,number');
				break;

			case $this->is_treasury_ops:
				$transactions = Transaction::pendingFulfilment()->recent()->limit(10)->get();
				$transactions->loadMissing('events:id,done_by,transaction_id', 'client:id,first_name,middle_name,last_name,occupation', 'events.doneBy:id,name,email', 'account:id,number');
				break;

			case $this->is_systems_admin:
				$transactions = Transaction::recent()->limit(5)->get();
				$transactions->loadMissing('events:id,done_by,transaction_id', 'client:id,first_name,middle_name,last_name,occupation', 'events.doneBy:id,name,email', 'account:id,number');
				break;

			default:
				return response()->error('You don\'t have the required permission', 403);
		}

		return response()->success(compact('transactions'));
	}


	/**
	 * Display the specified resource.
	 *
	 * @param $transaction_id
	 *
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
			$transaction = Transaction::with('client', 'client.kyc', 'account', 'events', 'events.doneBy')->findOrFail($transaction_id);
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
}
