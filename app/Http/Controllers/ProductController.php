<?php

namespace App\Http\Controllers;

use App\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index()
	{
		$products = Product::all();

		return response()->success(compact('products'));
	}


	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function getRates()
	{
		$rates = Product::clientRates()->get();

		return response()->success(compact('rates'));
	}


	/**
	 * Show the form for creating a new resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function create()
	{
		//
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
	 * @param  \App\Product $product
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function show(Product $product)
	{
		//
	}


	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  \App\Product $product
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function edit(Product $product)
	{
		//
	}


	/**
	 * Update the specified resource in storage.
	 *
	 * @param  \Illuminate\Http\Request $request
	 * @param  \App\Product             $product
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function update(Request $request, Product $product)
	{

		// Todo Validate

		// Update & save only selected fields...
		$product->rate = $request->rate;
		$product->wacc = $request->wacc;
		$product->bucket_cash = $request->bucket_cash;
		$product->bucket_transfer = $request->bucket_transfer;
		$product->bucket = (float) $product->bucket_cash + $product->bucket_transfer;
		$product->save();

		return response()->success(compact('product'));
	}


	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  \App\Product $product
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function destroy(Product $product)
	{
		//
	}
}
