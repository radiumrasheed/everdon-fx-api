<?php

namespace App;

use App\Traits\Uuids;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Webpatser\Uuid\Uuid;

class Transaction extends Model
{

	protected $table = 'transactions';

	public $incrementing = false;

	protected $fillable = [
		'client_id',
		'transaction_type_id',
		'transaction_mode_id',
		'account_id',
		'buying_product_id',
		'selling_product_id',
		'amount',
		'condition',
		'swift_code',
		'routing_no',
		'sort_code',
		'iban',
		'documents',
		'kyc_check',
		'aml_check',
		'documents',
		'org_account_id',
		'calculated_amount',
		'rate'
	];

	/**
	 * Generate a unique transaction reference
	 *
	 * @return string
	 */
	protected function generateTransactionRef()
	{
		return 'FX' . $this->attributes['transaction_type_id'] . $this->attributes['transaction_mode_id'] . '/' . Carbon::now()->timestamp;
	}

	protected static function boot()
	{
		parent::boot();

		static::creating(function (Model $model) {
			$model->{$model->getKeyName()} = Uuid::generate()->string;

			$model->transaction_ref = $model->generateTransactionRef();
		});
	}

	public function scopeOpen($query)
	{
		return $query->where('transaction_status_id', 1);
	}

	public function scopeInProgress($query)
	{
		return $query->where('transaction_status_id', 2);
	}

	public function scopeOpenOrInProgress($query)
	{
		return $query->where('transaction_status_id', 1)->orWhere('transaction_status_id', 2);
	}

	public function scopePendingApproval($query)
	{
		return $query->where('transaction_status_id', 3);
	}

	public function scopePendingFulfilment($query)
	{
		return $query->where('transaction_status_id', 4);
	}

	public function scopeCancelled($query)
	{
		return $query->where('transaction_status_id', 5);
	}

	public function scopeClosed($query)
	{
		return $query->where('transaction_status_id', 6);
	}

	public function scopeRaised($query)
	{
		return $query->where('transaction_status_id', 7);
	}

	public function scopeRecent($query)
	{
		return $query->select(['id', 'amount', 'transaction_status_id', 'transaction_ref', 'buying_product_id', 'selling_product_id', 'client_id', 'account_id', 'updated_at'])
			->orderBy('updated_at', 'desc');
	}

	/**
	 * Get the client of a transaction
	 */
	public function client()
	{
		return $this->belongsTo('App\Client', 'client_id', 'id');
	}

	/**
	 * Get the account of a transaction
	 */
	public function account()
	{
		return $this->belongsTo('App\Account', 'account_id', 'id');
	}

	/**
	 * Get the events on a transaction
	 */
	public function events()
	{
		return $this->hasMany('App\TransactionEvent', 'transaction_id');
	}

	/**
	 * Get the User that closed the transaction
	 */
	public function closedBy()
	{
		return $this->hasOne('App\User', 'closed_by');
	}

	/**
	 * Get the User that approved the transaction
	 */
	public function approvedBy()
	{
		return $this->hasOne('App\User', 'approved_by');
	}

	/**
	 * Get the User that reviewed the transaction
	 */
	public function reviewedBy()
	{
		return $this->hasOne('App\User', 'reviewed_by');
	}

	/**
	 * Get the User that initiated the transaction
	 */
	public function initiatedBy()
	{
		return $this->hasOne('App\User', 'initiated_by');
	}
}
