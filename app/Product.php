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

	public function wacc()
	{
		return $this->hasMany('App\Timeline', 'product_id')->select(['value as y', 'created_at as x']);
	}

	public function rate()
	{
		return $this->hasMany('App\Timeline', 'product_id')->select(['rate as y', 'created_at as x']);
	}

	public function scopeDailyWacc()
	{
		return $this->wacc()->latest()->limit(24);
	}

	public function scopeDailyRates()
	{
		return $this->rate()->latest()->limit(24);
	}

	public function scopeLastRate()
	{
		return $this->rate()->latest()->limit(1);
	}

	public function scopeLastWacc()
	{
		return $this->wacc()->latest()->limit(1);
	}
}
