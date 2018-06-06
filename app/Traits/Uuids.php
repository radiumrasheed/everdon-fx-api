<?php
/**
 * Created by PhpStorm.
 * User: Rahman
 * Date: 6/5/2018
 * Time: 10:58 PM
 */

namespace App\Traits;


use Webpatser\Uuid\Uuid;

trait Uuids
{
	/**
	 * Boot function from laravel.
	 */
	protected static function boot()
	{
		parent::boot();

		static::creating(function ($model) {
			$model->{$model->getKeyName()} = Uuid::generate()->string;
		});
	}
}