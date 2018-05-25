<?php

use App\ClientType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClientTypeSeeder extends Seeder
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
		DB::table('client_type')->delete();

		$datas = array(
			['name' => 'individual', 'description' => 'Individual'],
			['name' => 'cooperate', 'description' => 'Cooperate'],
			['name' => 'express', 'description' => 'Express Client'],
		);

		// Loop through each data above and create the record for them in the database
		foreach ($datas as $data) {
			ClientType::create($data);
		}

		Model::reguard();
	}
}
