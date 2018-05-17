<?php

namespace App\Providers;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;

class ResponseServiceProvider extends ServiceProvider
{
	/**
	 * Bootstrap the application services.
	 *
	 * @return void
	 */
	public function boot()
	{
		Response::macro('success', function ($data) {
			return Response::json([
				'error' => FALSE,
				'data' => $data,
			]);
		});

		Response::macro('error', function ($message, $status = 400) {
			if (is_array($message)) {
				return Response::json([
					'message' => $status . ' error',
					'error' => TRUE,
					'errors' => $message,
					'status_code' => $status], $status);
			}
			return Response::json(['message' => $status . ' error',
				'error' => TRUE,
				'errors' => ['message' => $message],
				'status_code' => $status
			], $status);
		});
	}

	/**
	 * Register the application services.
	 *
	 * @return void
	 */
	public function register()
	{
		//
	}
}
