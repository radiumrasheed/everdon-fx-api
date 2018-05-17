<?php

namespace App\Http\Controllers;

use App\Permission;
use App\Role;
use App\User;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Class JwtAuthenticateController
 * @package App\Http\Controllers
 */
class JwtAuthenticateController extends Controller
{

	/**
	 * Get all Users in the system
	 *
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function index()
	{
		return response()->json([
			'auth' => Auth::user(),
			'users' => User::with('client')->get()
		]);
	}

	/**
	 * Authenticate a User
	 *
	 * @param Request $request
	 * @return mixed
	 */
	public function authenticate(Request $request)
	{
		$credentials = $request->only('email', 'password');

		try {
			// verify the credentials and create a token for the user
			if (!$token = JWTAuth::attempt($credentials)) {
				return response()->error('Authentication Failed', 401);
			}
		} catch (JWTException $e) {
			// something went wrong
			return response()->error('Oops, An Error Occurred', 500);
		}

		$user_id = Auth::User()->id;
		$user = User::with('client')->findOrFail($user_id);

		// if no errors are encountered we can return a JWT
		return response()->success(compact('token', 'user'));
	}

	/**
	 * Create a role
	 *
	 * @param Request $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function createRole(Request $request)
	{
		// Todo
		$role = new Role();
		$role->name = $request->input('name');
		$role->save();

		return response()->json("created");
	}

	/**
	 * Create a Permission
	 *
	 * @param Request $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function createPermission(Request $request)
	{
		// Todo
		$viewUsers = new Permission();
		$viewUsers->name = $request->input('name');
		$viewUsers->save();

		return response()->json("created");
	}

	/**
	 * Assign a Role to a User
	 *
	 * @param Request $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function assignRole(Request $request)
	{
		// Todo
		$user = User::where('email', '=', $request->input('email'))->first();

		$role = Role::where('name', '=', $request->input('role'))->first();
		//$user->attachRole($request->input('role'));
		$user->roles()->attach($role->id);

		return response()->json("created");
	}

	/**
	 * Attach a permission to a Role
	 *
	 * @param Request $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function attachPermission(Request $request)
	{
		// Todo
		$role = Role::where('name', '=', $request->input('role'))->first();
		$permission = Permission::where('name', '=', $request->input('permission'))->first();
		$role->attachPermission($permission);

		return response()->json("created");
	}

}
