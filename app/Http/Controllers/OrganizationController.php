<?php

namespace App\Http\Controllers;

use App\Organization;
use App\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrganizationController extends Controller
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

		$organization = Organization::orderBy($sort_field, $sort_order)
			->paginate($per_page);

		return response()->success(compact('organization'));
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

		$organization = Organization::create([
			'name'           => $request->name,
			'bank_name'      => $request->bank_name,
			'account_name'   => $request->account_name,
			'account_number' => $request->account_number,
		]);

		$organization->save();

		return response()->success(compact('organization'));
	}


	/**
	 * Display the specified resource.
	 *
	 * @param  \App\Organization $organization
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function show(Staff $organization)
	{
		return response()->success(compact('organization'));
	}


	/**
	 * Update the specified resource in storage.
	 *
	 * @param  \Illuminate\Http\Request $request
	 * @param  \App\Organization        $organization
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function update(Request $request, Organization $organization)
	{
		// todo validate

		Log::debug($organization);

		$organization->update($request->all());

		return response()->success(compact('organization'));
	}


	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  \App\Organization $organization
	 *
	 * @return \Illuminate\Http\Response
	 * @throws \Exception
	 */
	public function destroy(Organization $organization)
	{
		$organization->delete();

		return response()->success('Organization account deleted!');
	}
}
