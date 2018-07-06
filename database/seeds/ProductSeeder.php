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
			['name' => 'USD', 'local' => false, 'description' => 'US Dollar', 'wacc' => 360.5, 'rate' => 360.5, 'seed_value' => 120000, 'seed_value_local' => 120000 * 360.5, 'bucket' => 350000, 'bucket_local' => 350000 * 360.5],
			['name' => 'EUR', 'local' => false, 'description' => 'Euros', 'wacc' => 422.90, 'rate' => 422.90, 'seed_value' => 120000, 'seed_value_local' => 12000 * 422.90, 'bucket' => 150000, 'bucket_local' => 150000 * 422.90],
			['name' => 'GBP', 'local' => false, 'description' => 'British pounds', 'wacc' => 483.55, 'rate' => 483.55, 'seed_value' => 120000, 'seed_value_local' => 120000 * 483.55, 'bucket' => 250000, 'bucket_local' => 250000 * 483.55],
			['name' => 'NGN', 'local' => true, 'description' => 'Nigerian Naira', 'wacc' => 1, 'rate' => 1, 'seed_value' => 120000, 'seed_value_local' => 120000 * 1, 'bucket' => 950000, 'bucket_local' => 950000 * 1]
		);

		// Loop through each data above and create the record for them in the database
		foreach ($datas as $data) {
			Product::create($data);
		}

		Model::reguard();
	}
}
