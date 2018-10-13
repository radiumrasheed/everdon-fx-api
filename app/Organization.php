<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{

	protected $table = 'organizations';

	protected $fillable = [
		'name',
		'bank_name',
		'account_name',
		'account_number',
	];
}
