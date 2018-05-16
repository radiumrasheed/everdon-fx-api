<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
	//
	protected $primaryKey = 'accounts';


	public function client()
	{
		return $this->belongsTo('App\Clients', 'client_id');
	}

	public function transactions()
	{
		return $this->belongsToMany('App\Transactions', 'transaction_account', 'account_id', 'transaction_id');
	}


}
