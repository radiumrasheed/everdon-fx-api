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
			['name' => 'USD', 'description' => 'US Dollar', 'wacc' => 360.5, 'seed_value' => 120000, 'seed_value_ngn' => 4326000, 'bucket' => 350000],
			['name' => 'EUR', 'description' => 'Euros', 'wacc' => 422.90, 'seed_value' => 120000, 'seed_value_ngn' => 5074800, 'bucket' => 150000],
			['name' => 'GBP', 'description' => 'British pounds', 'wacc' => 483.55, 'seed_value' => 120000, 'seed_value_ngn' => 5802600, 'bucket' => 250000],
			['name' => 'NGN', 'description' => 'Nigerian Naira', 'wacc' => 1, 'seed_value' => 120000, 'seed_value_ngn' => 120000, 'bucket' => 950000],
		);

		// Loop through each data above and create the record for them in the database
		foreach ($datas as $data) {
			Product::create($data);
		}

		Model::reguard();
	}
}
