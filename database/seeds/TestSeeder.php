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
			['role' => 'fx-ops', 'name' => 'Amanda', 'email' => 'amanda.omogiafo@everdonbdc.com', 'password' => Hash::make('12345')],
			['role' => 'treasury-ops', 'name' => 'Chika', 'email' => 'chika.ohakawa@everdonbdc.com', 'password' => Hash::make('12345')],
			['role' => 'fx-ops-manager', 'name' => 'Damola', 'email' => 'damola@everdon-fx.com', 'password' => Hash::make('12345')],
			['role' => 'fx-ops-lead', 'name' => 'Tessy', 'email' => 'tessy@everdonbdc.com', 'password' => Hash::make('12345')],
			['role' => 'fx-ops-manager', 'name' => 'Adeola', 'email' => 'adeola@everdonbdc.com', 'password' => Hash::make('12345')],
			['role' => 'fx-ops', 'name' => 'Estee', 'email' => 'estee@everdonbdc.com', 'password' => Hash::make('12345')],
			['role' => 'treasury-ops', 'name' => 'Lookman', 'email' => 'lookman@everdonbdc.com', 'password' => Hash::make('12345')],
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
