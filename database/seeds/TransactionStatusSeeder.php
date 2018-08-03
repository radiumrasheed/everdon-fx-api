<?php

use App\TransactionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransactionStatusSeeder extends Seeder
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
		DB::table('transaction_status')->delete();

		$datas = array(
			['name' => 'open', 'description' => 'Open'],
			['name' => 'in-progress', 'description' => 'In Progress'],
			['name' => 'pending-approval', 'description' => 'Pending Approval'],
			['name' => 'pending-fulfilment', 'description' => 'Pending Fulfilment'],
			['name' => 'cancelled', 'description' => 'Cancelled'],
			['name' => 'closed', 'description' => 'Closed'],
			['name' => 'raised', 'description' => 'Raised'],
			['name'        => 'closed-processed',
			 'description' => 'Closed & Processed'
			],
		);

		// Loop through each data above and create the record for them in the database
		foreach ($datas as $data) {
			TransactionStatus::create($data);
		}

		Model::reguard();
	}
}
