<?php

use App\Role;
use App\Staff;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestSeeder extends Seeder
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

		$users = array(
			['role' => 'systems-admin', 'name' => 'Cat ONE', 'email' => 'cat1@everdonbdc.com', 'password' => Hash::make('12345')],
			['role' => 'fx-ops', 'name' => 'Cat TWO', 'email' => 'cat2@everdonbdc.com', 'password' => Hash::make('12345')],
			['role' => 'fx-ops-lead', 'name' => 'Cat THREE', 'email' => 'cat3@everdonbdc.com', 'password' => Hash::make('12345')],
			['role' => 'fx-ops-manager', 'name' => 'Cat FOUR', 'email' => 'cat4@everdonbdc.com', 'password' => Hash::make('12345')],
			['role' => 'treasury-ops', 'name' => 'Cat FIVE', 'email' => 'cat5@everdonbdc.com', 'password' => Hash::make('12345')]
		);


		// Loop through each user above and create the record for them in the database
		foreach ($users as $user) {
			$role = Role::where('name', $user['role'])->first();
			unset($user['role']);

			$staff = Staff::create(['full_name' => $user['name'], 'email' => $user['email']]);
			$_user = User::create($user);

			$_user->staff()->save($staff);
			$_user->roles()->attach($role->id);
		}

		Model::reguard();

	}
}
