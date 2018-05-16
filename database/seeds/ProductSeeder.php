<?php

use App\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 *
	 * @return void
	 */
	public function run()
	{
		// $this->call(UsersTableSeeder::class);
		Model::unguard();

		// $this->call(UserTableSeeder::class);
		DB::table('products')->delete();

		$datas = array(
			['name' => 'USD', 'description' => 'US Dollar'],
			['name' => 'EUR', 'description' => 'Euros'],
			['name' => 'GBP', 'description' => 'British pounds'],
		);

		// Loop through each data above and create the record for them in the database
		foreach ($datas as $data) {
			Product::create($data);
		}

		Model::reguard();
	}
}
