<?php

use App\Product;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
	/**
	 * Seed the application's database.
	 *
	 * @return void
	 */
	public function run()
	{
		$this->call(RoleSeeder::class);
		$this->call(UserSeeder::class);

		$this->call(TransactionModeSeeder::class);
		$this->call(TransactionStatusSeeder::class);
		$this->call(TransactionTypeSeeder::class);
		$this->call(ClientTypeSeeder::class);
		$this->call(ProductSeeder::class);
		$this->call(OrganizationSeeder::class);
	}
}
