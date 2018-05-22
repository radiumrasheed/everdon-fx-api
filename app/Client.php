<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
	//
	protected $table = 'clients';

	protected $fillable = [
		'full_name',
		'email',
		'office_address',
		'phone',
		'bvn',
		'client_type',
		'marital_status',
		'residential_address',
		'occupation',
		'nok_full_name',
		'nok_phone',
		'referee_1',
		'referee_2',
		'referee_3',
		'referee_4',
		'date_of_birth',
		'rc_number'
	];

	/**
	 * Get the accounts of client.
	 */
	public function accounts()
	{
		return $this->hasMany('App\Account', 'client_id');
	}

	/**
	 * Get the client's transactions.
	 */
	public function transactions()
	{
		return $this->hasMany('App\Transaction', 'client_id');
	}

	/**
	 * Get the type of client.
	 */
	public function client_type()
	{
		return $this->hasOne('App\ClientType');
	}

	/**
	 * Get the KYC details of the client
	 * */
	public function client_kyc()
	{
		return $this->hasOne('App\ClientKYC', 'client_id');
	}
}
