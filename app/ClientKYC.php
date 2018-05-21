<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ClientKYC extends Model
{
	//
	protected $table = 'client_kyc';

	public function client()
	{
		$this->belongsTo('App\Client', 'client_id');
	}
}
