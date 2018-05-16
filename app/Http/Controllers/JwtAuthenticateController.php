<?php

namespace App\Http\Controllers;

use App\Permission;
use App\Role;
use App\User;
use Illuminate\Http\Request;

use App\Http\Requests;
use Illuminate\Support\Facades\Auth;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Log;

class JwtAuthenticateController extends Controller
{

	public function index()
	{
		return response()->json(['auth' => Auth::user(), 'users' => User::all()]);
	}

	public function authenticate(Request $request)
	{
		$credentials = $request->only('email', 'password');

		try {
			// verify the credentials and create a token for the user
			if (!$token = JWTAuth::attempt($credentials)) {
				return response()->json(['error' => 'invalid_credentials'], 401);
			}
		} catch (JWTException $e) {
			// something went wrong
			return response()->json(['error' => 'could_not_create_token'], 500);
		}

		// if no errors are encountered we can return a JWT
		return response()->json(compact('token'));
	}

	public function createRole(Request $request)
	{
		// Todo
		$role = new Role();
		$role->name = $request->input('name');
		$role->save();

		return response()->json("created");
	}

	public function createPermission(Request $request)
	{
		// Todo
		$viewUsers = new Permission();
		$viewUsers->name = $request->input('name');
		$viewUsers->save();

		return response()->json("created");
	}

	public function assignRole(Request $request)
	{
		// Todo
		$user = User::where('email', '=', $request->input('email'))->first();

		$role = Role::where('name', '=', $request->input('role'))->first();
		//$user->attachRole($request->input('role'));
		$user->roles()->attach($role->id);

		return response()->json("created");
	}

	public function attachPermission(Request $request)
	{
		// Todo
		$role = Role::where('name', '=', $request->input('role'))->first();
		$permission = Permission::where('name', '=', $request->input('permission'))->first();
		$role->attachPermission($permission);

		return response()->json("created");
	}

}
