<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Log;
use Zizaco\Entrust\Traits\EntrustUserTrait;

class User extends Authenticatable
{
	use Notifiable, EntrustUserTrait;

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'name',
		'email',
		'password',
		'avatar'
	];

	/**
	 * The attributes that should be hidden for arrays.
	 *
	 * @var array
	 */
	protected $hidden = [
		'password',
		'remember_token',
	];


	/**
	 * Get the type of client.
	 */
	public function client()
	{
		return $this->hasOne('App\Client');
	}


	/**
	 * Get the type of client.
	 */
	public function staff()
	{
		return $this->hasOne('App\Staff');
	}

}
