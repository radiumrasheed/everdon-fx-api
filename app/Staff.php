<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
	//
	protected $table = 'staffs';

	protected $fillable = [
		'first_name',
		'last_name',
		'middle_name',
		'gender',
		'email',
		'phone',
	];


	public function user()
	{
		return $this->belongsTo('App\User');
	}
}
