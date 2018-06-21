<?php

namespace App\Http\Controllers;

use App\Client;
use App\ClientType;
use App\Permission;
use App\Role;
use App\User;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Class JwtAuthenticateController
 * @package App\Http\Controllers
 */
class JwtAuthenticateController extends Controller
{
	const STAFFS = ['fx-ops', 'systems-admin', 'fx-ops-lead', 'fx-ops-manager', 'treasury-ops'];


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

		// Check if User is a client
		$is_client = Auth::user()->hasRole('client');

		if (!$is_client) {
			return response()->error('Invalid Username or Passowrd', 401);
		}

		try {
			$user_id = Auth::User()->id;
			$user = User::with('client')->findOrFail($user_id);
		} catch (ModelNotFoundException $e) {
			return response()->error('No Client Profile with the given credentials', 500);
		}

		// Add user role to a seperate token...
		$_token = JWTAuth::attempt($credentials, ['roles' => 'client']);

		// if no errors are encountered we can return a JWT
		return response()->success(compact('user', 'token', '_token'));
	}


	/**
	 * Authenticate an Admin
	 *
	 * @param Request $request
	 * @return mixed
	 */
	public function authenticateAdmin(Request $request)
	{
		$credentials = $request->only('email', 'password');

		try {
			// verify the credentials and create a token for the user
			if (!$token = JWTAuth::attempt($credentials)) {
				return response()->error('Authentication Failed', 401);
			}
		} catch (JWTException $e) {
			// something went wrong
			return response()->error('Oops, An Authentication Error Occurred', 500);
		}

		$user = Auth::user();
		$is_staff = $user->hasRole(self::STAFFS);

		if (!$is_staff) {
			return response()->error('Authentication Failed', 401);
		}

		$user = User::with('staff')->findOrFail($user->id);

		$roles = [];
		foreach ($user->roles as $role) {
			$roles[] = $role->name;
		}
		$roles = implode('|', $roles);

		// Add user role to a seperate token...
		$_token = JWTAuth::attempt($credentials, ['roles' => $roles]);

		// if no errors are encountered we can return a JWT
		return response()->success(compact('user', 'token', '_token'));
	}


	/**
	 * Create a User
	 *
	 * @param Request $req
	 * @return
	 */
	public function createUser(Request $req)
	{
		// Validate the request...
		$validator = Validator::make($req->all(), [
			'full_name' => 'required',
			'email' => 'required|unique:users|email',
			'phone' => 'required|unique:users|phone',
			'password' => 'required|string|confirmed|min:6',
			'password_confirmation' => 'same:password',
			'is_cooperate' => 'boolean',
			'rc_number' => 'unique:clients,rc_number'
		]);
		if ($validator->fails()) {
			return response()->error($validator->errors(), 422);
		}

		// Get details to be saved based on client type...
		if ($req->is_cooperate) {
			$_client_type_id = ClientType::where('name', 'cooperate')->first()->id;
			$req->merge(['client_type' => $_client_type_id]);
			$_client = $req->only([
				'full_name', 'email', 'phone', 'bvn', 'office_address', 'rc_number', 'client_type'
			]);
		} else {
			$_client_type_id = ClientType::where('name', 'individual')->first()->id;
			$req->merge(['client_type' => $_client_type_id]);
			$_client = $req->only([
				'full_name', 'email', 'phone', 'bvn', 'office_address', 'marital_status', 'date_of_birth', 'residential_address', 'occupation',
				'nok_full_name', 'nok_phone', 'referee', 'client_type'
			]);
		}
		$_user = ['name' => $req->full_name, 'email' => $req->email, 'password' => Hash::make($req->password)];

		// Create User & Client account...
		try {
			$_role = Role::where('name', 'client')->firstOrFail();

			// create client
			DB::beginTransaction();
			$client = Client::firstOrCreate(['email' => $req->email], $_client);
			$client->client_type = $_client_type_id;
			$user = User::create($_user);
			$user->client()->save($client);
			$user->roles()->attach($_role->id);
			DB::commit();
		} catch (\Exception $e) {
			DB::rollBack();
			return response()->error($e->getMessage());
		}
		$token = JWTAuth::fromUser($user);

		return response()->success(compact('client', 'user', 'token'));
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
		$user = User::where('email', '=', $request->input('email'))->firstOrFail();

		$role = Role::where('name', '=', $request->input('role'))->firstOrFail();
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
