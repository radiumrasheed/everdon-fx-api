<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
	//
	protected $table = 'products';

	public function timelines()
	{
		return $this->hasMany('App\Timeline', 'product_id')->select(['product_id', 'value', 'created_at']);
	}

	public function rates()
	{
		return $this->hasMany('App\Timeline', 'product_id')->select(['value as y', 'created_at as x']);
	}

	public function scopeDailyRates()
	{
		return $this->rates()->latest()->limit(24);
	}

	public function scopeLastRate()
	{
		return $this->rates()->latest()->limit(1);
	}
}
