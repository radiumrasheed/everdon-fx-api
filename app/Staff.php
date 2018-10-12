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


	/**
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
	public function user()
	{
		return $this->belongsTo('App\User');
	}


	/**
	 * The "booting" method of the model.
	 *
	 * @return void
	 */
	public static function boot()
	{
		parent::boot();

		static::deleting(function ($staff) {
			$staff->user()->delete();
		});
	}
}
