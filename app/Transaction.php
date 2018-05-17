<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
	//
	protected $table = 'transactions';

	protected $fillable = [
		'client_id',
		'transaction_type_id',
		'transaction_mode_id',
		'account_id',
		'product_id',
		'amount',
		'rate',
		'wacc'
	];

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

}
