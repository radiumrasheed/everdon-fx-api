<?php

use App\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 *
	 * @return void
	 */
	public function run()
	{
		//
		// $this->call(UsersTableSeeder::class);
		Model::unguard();

		// $this->call(UserTableSeeder::class);
		DB::table('roles')->delete();

		$roles = array(
			['name' => 'systems-admin', 'display_name' => 'Systems Admin'],
			['name' => 'fx-ops', 'display_name' => 'FX Ops'],
			['name' => 'fx-ops-manager', 'display_name' => 'FX Ops Manager'],
			['name' => 'treasury-ops', 'display_name' => 'Treasury Ops'],
			['name' => 'client', 'display_name' => 'Client'],
		);

		// Loop through each role above and create the record for them in the database
		foreach ($roles as $role) {
			Role::create($role);
		}

		Model::reguard();
	}
}
