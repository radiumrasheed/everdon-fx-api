<?php
/**
 * Created by PhpStorm.
 * User: Rahman
 * Date: 5/16/2018
 * Time: 10:04 AM
 */

namespace App\Traits;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

trait RestExceptionHandlerTrait
{

	/**
	 * Creates a new JSON response based on exception type.
	 *
	 * @param Request $request
	 * @param Exception $e
	 * @return \Illuminate\Http\JsonResponse
	 */
	protected function getJsonResponseForException(Request $request, Exception $e)
	{
		switch (true) {
			case $this->isModelNotFoundException($e):
				$retval = $this->modelNotFound($e->getMessage());
				break;
			default:
				$retval = $this->badRequest($e->getMessage());
		}

		return $retval;
	}

	/**
	 * Determines if the given exception is an Eloquent model not found.
	 *
	 * @param Exception $e
	 * @return bool
	 */
	protected function isModelNotFoundException(Exception $e)
	{
		return $e instanceof ModelNotFoundException;
	}

	/**
	 * Returns json response for Eloquent model not found exception.
	 *
	 * @param string $message
	 * @param int $statusCode
	 * @return \Illuminate\Http\JsonResponse
	 */
	protected function modelNotFound($message = 'Record not found', $statusCode = 404)
	{
		return $this->jsonResponse(['error' => $message], $statusCode);
	}

	/**
	 * Returns json response.
	 *
	 * @param array|null $payload
	 * @param int $statusCode
	 * @return \Illuminate\Http\JsonResponse
	 */
	protected function jsonResponse(array $payload = null, $statusCode = 404)
	{
		$payload = $payload ?: [];

		return response()->error($payload, $statusCode);
	}

	/**
	 * Returns json response for generic bad request.
	 *
	 * @param string $message
	 * @param int $statusCode
	 * @return \Illuminate\Http\JsonResponse
	 */
	protected function badRequest($message = 'Bad request', $statusCode = 400)
	{
		return $this->jsonResponse(['error' => $message], $statusCode);
	}

}