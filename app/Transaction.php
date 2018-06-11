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
