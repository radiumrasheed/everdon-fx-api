<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class Controller extends BaseController
{
	use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
	/** Requesting user is a client */
	protected $is_client = false;

	/** Requesting user is a staff */
	protected $is_staff = false;

	/**
	 * Controller constructor.
	 */
	public function __construct()
	{

		$this->middleware(function ($request, $next) {
			try {
				$this->is_client = Auth::user()->hasRole('client');
				$this->is_staff = Auth::user()->hasRole(['systems-admin', 'fx-ops', 'fx-ops-manager', 'fx-ops-lead', 'treasury-ops']);
			} catch (\Exception $e) {
				Log::alert('Tried to authenticate');
			}

			return $next($request);
		});
	}
}
