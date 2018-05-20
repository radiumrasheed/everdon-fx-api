<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PasswordReset extends Model
{
	//
	public $timestamps = false;
	protected $table = 'password_resets';
	/**
	 * Fields that can be mass assigned
	 *
	 * @var array
	 */
	protected $fillable = ["email",
		"token"];

}
