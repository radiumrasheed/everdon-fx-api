<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
	//
	protected $table = 'staffs';

	protected $fillable = [
		'full_name',
		'email',
		'phone',
	];
}
