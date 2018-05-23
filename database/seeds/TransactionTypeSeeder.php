<?php

use App\TransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransactionTypeSeeder extends Seeder
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
		DB::table('transaction_type')->delete();

		$datas = array(
			['name' => 'purchase', 'description' => 'Purchase'],
			['name' => 'sales', 'description' => 'Sales'],
			['name' => 'swap', 'description' => 'Swap'],
			['name' => 'refund', 'description' => 'Refund'],
			['name' => 'expenses', 'description' => 'Expenses'],
			['name' => 'cross', 'description' => 'Cross'],
		);

		// Loop through each data above and create the record for them in the database
		foreach ($datas as $data) {
			TransactionType::create($data);
		}

		Model::reguard();
	}
}
