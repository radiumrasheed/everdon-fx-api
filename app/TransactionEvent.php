<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TransactionEvent extends Model
{
	//
	protected $table = 'transaction_events';

	public function transaction()
	{
		return $this->belongsTo('App\Transactions', 'transaction_id');
	}

}
