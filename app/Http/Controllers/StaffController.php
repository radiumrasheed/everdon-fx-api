<?php

namespace App\Http\Controllers;

use App\Role;
use App\Staff;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StaffController extends Controller
{
	/**
	 * Display a listing of the resource.
	 *
	 * @param Request $request
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index(Request $request)
	{
		$per_page = $request->query('per_page', 15);
		$sort_field = $request->query('sort_field', 'id');
		$sort_order = $request->query('sort_order', 'desc');

		$sort_field = !empty($sort_field) ? $sort_field : 'id';
		$sort_order = !empty($sort_order) ? $sort_order : 'asc';

		$roles = array_filter(explode(',', $request->query('role', NULL)));

		$staffs = Staff::orderBy($sort_field, $sort_order)
			->whereHas('user', function ($user) use ($roles) {
				return $user->whereHas('roles', function ($role) use ($roles) {
					if (empty($roles)) {
						return $role;
					}

					return $role->whereIn('name', $roles);
				});
			})
			->with('user', 'user.roles')
			->paginate($per_page);

		return response()->success(compact('staffs'));
	}


	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request $request
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function store(Request $request)
	{
		// @todo validation...

		$role = Role::where('name', $request->role)->firstOrFail();

		$staff = Staff::create([
			'first_name' => $request->first_name,
			'last_name'  => $request->last_name,
			'email'      => $request->email,
			'gender'     => $request->gender,
			'phone'      => $request->phone
		]);
		$_user = User::create([
			'name'     => $request->first_name . ' ' . $request->last_name,
			'email'    => $request->email,
			'password' => Hash::make($request->password)
		]);

		$_user->staff()->save($staff);
		$_user->roles()->attach($role->id);

		$staff->loadMissing('user', 'user.roles');

		return response()->success(compact('staff'));
	}


	/**
	 * Display the specified resource.
	 *
	 * @param  \App\Staff $staff
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function show(Staff $staff)
	{
		$staff->loadMissing('user', 'user.roles');

		return response()->success(compact('staff'));
	}


	/**
	 * Update the specified resource in storage.
	 *
	 * @param  \Illuminate\Http\Request $request
	 * @param  \App\Staff               $staff
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function update(Request $request, Staff $staff)
	{
		// todo validate

		$staff->update($request->except('email'));

		$staff->loadMissing('user', 'user.roles');

		return response()->success(compact('staff'));
	}


	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  \App\Staff $staff
	 *
	 * @return \Illuminate\Http\Response
	 * @throws \Exception
	 */
	public function destroy(Staff $staff)
	{
		$staff->delete();

		return response()->success('Staff account deleted!');
	}
}
