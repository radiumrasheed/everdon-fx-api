<?php

use App\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 *
	 * @return void
	 */
	public function run()
	{
		Model::unguard();

		$datas = [
			['name' => 'VFD Groups', 'account_name' => 'Internals', 'account_number' => '0101020102', 'bank_name' => 'VFD'],
			['name' => 'Germaine Motors', 'account_name' => 'Sister', 'account_number' => '88283047203', 'bank_name' => 'VFD']
		];

		// Loop through each user above and create the record for them in the database
		foreach ($datas as $data) {
			Organization::create($data);
		}

		Model::reguard();
	}
}
