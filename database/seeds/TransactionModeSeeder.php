<?php

use App\TransactionMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransactionModeSeeder extends Seeder
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
		DB::table('transaction_mode')->delete();

		$datas = array(
			['name' => 'cash', 'description' => 'Cash'],
			['name' => 'transfer', 'description' => 'Transfer'],
			['name' => 'offshore', 'description' => 'Offshore'],
		);

		// Loop through each data above and create the record for them in the database
		foreach ($datas as $data) {
			TransactionMode::create($data);
		}

		Model::reguard();
	}
}
