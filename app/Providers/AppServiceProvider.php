<?php

namespace App\Providers;

use App\Client;
use App\ClientKYC;
use App\Observers\TransactionObserver;
use App\Transaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
	/**
	 * Bootstrap any application services.
	 *
	 * @return void
	 */
	public function boot()
	{
		//
		Schema::defaultStringLength(191);

		Transaction::observe(TransactionObserver::class);

		Client::created(function ($client) {
			$client->kyc()->save(new ClientKYC());
		});
	}

	/**
	 * Register any application services.
	 *
	 * @return void
	 */
	public function register()
	{
		//
	}
}
