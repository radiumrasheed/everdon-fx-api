<?php

namespace App\Http\Controllers;

use App\Staff;
use Illuminate\Http\Request;

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

		$staffs = Staff::with('user', 'user.roles')->paginate($per_page);

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
		//
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
		//
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
		//
	}


	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  \App\Staff $staff
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function destroy(Staff $staff)
	{
		//
	}
}
