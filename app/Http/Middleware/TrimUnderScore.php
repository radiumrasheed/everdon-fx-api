<?php
/**
 * Created by PhpStorm.
 * User: Rahman
 * Date: 9/10/2018
 * Time: 8:01 AM
 */

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TransformsRequest;

class TrimUnderScore extends TransformsRequest
{

	/*if (substr($key, 0, 1) === '_') {
			$key = ltrim($key, '_');

			// return $key
		}*/

	/**
	 * Clean the request's data.
	 *
	 * @param  \Illuminate\Http\Request $request
	 *
	 * @return void
	 */
	protected function clean($request)
	{

		if ($request->has('_selling_product_id')) {
			$request->merge(['selling_product_id' => $request->_selling_product_id]);
		}

		if ($request->has('_buying_product_id')) {
			$request->merge(['buying_product_id' => $request->_buying_product_id]);
		}

		if ($request->has('_transaction_type_id')) {
			$request->merge(['transaction_type_id' => $request->_transaction_type_id]);
		}

		parent::clean($request);
	}
}
