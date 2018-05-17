<?php
/**
 * Created by PhpStorm.
 * User: Rahman
 * Date: 5/16/2018
 * Time: 10:05 AM
 */

namespace App\Traits;


use Illuminate\Http\Request;

trait RestTrait
{
	/**
	 * Determines if request is an api call.
	 *
	 * If the request URI contains '/api/v'.
	 *
	 * @param Request $request
	 * @return bool
	 */
	protected function isApiCall(Request $request)
	{
		return strpos($request->getUri(), '/api/') !== false;
	}
}