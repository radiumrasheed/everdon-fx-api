<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Middleware\BaseMiddleware;


class TokenEntrustRole extends BaseMiddleware
{
	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request $request
	 * @param  \Closure $next
	 * @param $role
	 * @return mixed
	 */
	public function handle($request, Closure $next, $role)
	{
		if (!$token = $this->auth->setRequest($request)->getToken()) {
			return $this->respond('tymon.jwt.absent', 'token_not_provided', Response::HTTP_UNAUTHORIZED);
		}

		try {
			$user = $this->auth->authenticate($token);
		} catch (TokenExpiredException $e) {
			return $this->respond('tymon.jwt.expired', 'token_expired', $e->getStatusCode(), [$e]);
		} catch (JWTException $e) {
			return $this->respond('tymon.jwt.invalid', 'token_invalid', $e->getStatusCode(), [$e]);
		}

		if (!$user) {
			return $this->respond('tymon.jwt.user_not_found', 'user_not_found', Response::HTTP_UNAUTHORIZED);
		}

		if (!$user->hasRole(explode('|', $role))) {
			return $this->respond('tymon.jwt.invalid', 'token_invalid', Response::HTTP_UNAUTHORIZED, 'Unauthorized');
		}

		$this->events->fire('tymon.jwt.valid', $user);
		return $next($request);
	}
}
