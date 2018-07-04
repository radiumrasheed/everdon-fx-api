<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'clients';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'full_name',
		'email',
		'office_address',
		'phone',
		'bvn',
		'avatar',
		'identification',
		'identification_number',
		'identification_document',
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
		return $this->hasMany('App\Transaction', 'client_id')->orderByDesc('updated_at');
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
	public function kyc()
	{
		return $this->hasOne('App\ClientKYC', 'client_id');
	}

	/**
	 * Scope a search query
	 *
	 * @param \Illuminate\Database\Eloquent\Builder $query
	 * @param $term
	 * @return \Illuminate\Database\Eloquent\Builder
	 */
	public function scopeSearch($query, $term)
	{
		return $query->where('full_name', 'like', '%' . $term . '%')->orWhere('email', 'like', '%' . $term . '%')->select(['id', 'full_name', 'email']);
	}
}
