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
			[
				'role'       => 'fx-ops',
				'first_name' => 'Amanda',
				'email'      => 'amanda.omogiafo@everdonbdc.com',
				'password'   => Hash::make('12345')
			],
			[
				'role'       => 'treasury-ops',
				'first_name' => 'Chika',
				'email'      => 'chika.ohakawa@everdonbdc.com',
				'password'   => Hash::make('12345')
			],
			[
				'role'       => 'fx-ops-manager',
				'first_name' => 'Damola',
				'email'      => 'damola@everdon-fx.com',
				'password'   => Hash::make('12345')
			],
			[
				'role'       => 'fx-ops',
				'first_name' => 'Tessy',
				'email'      => 'tessy@everdonbdc.com',
				'password'   => Hash::make('12345')
			],
			[
				'role'       => 'fx-ops-manager',
				'first_name' => 'Adeola',
				'email'      => 'adeola@everdonbdc.com',
				'password'   => Hash::make('12345')
			],
			[
				'role'       => 'fx-ops',
				'first_name' => 'Estee',
				'email'      => 'estee@everdonbdc.com',
				'password'   => Hash::make('12345')
			],
			[
				'role'       => 'treasury-ops',
				'first_name' => 'Lookman',
				'email'      => 'lookman@everdonbdc.com',
				'password'   => Hash::make('12345')
			],

			[
				'role'       => 'systems-admin',
				'first_name' => 'Cat ONE',
				'email'      => 'cat1@everdonbdc.com',
				'password'   => Hash::make('12345')
			],
			[
				'role'       => 'fx-ops',
				'first_name' => 'Cat TWO',
				'email'      => 'cat2@everdonbdc.com',
				'password'   => Hash::make('12345')
			],
			[
				'role'       => 'fx-ops',
				'first_name' => 'Cat THREE',
				'email'      => 'cat3@everdonbdc.com',
				'password'   => Hash::make('12345')
			],
			[
				'role'       => 'fx-ops-manager',
				'first_name' => 'Cat FOUR',
				'email'      => 'cat4@everdonbdc.com',
				'password'   => Hash::make('12345')
			],
			[
				'role'       => 'treasury-ops',
				'first_name' => 'Cat FIVE',
				'email'      => 'cat5@everdonbdc.com',
				'password'   => Hash::make('12345')
			]

		);

		// Loop through each user above and create the record for them in the database
		foreach ($users as $user) {
			$role = Role::where('name', $user['role'])->first();
			unset($user['role']);

			$staff = Staff::create([
				'first_name' => $user['first_name'],
				'email'      => $user['email']
			]);
			$_user = User::create([
				'name'     => $user['first_name'],
				'email'    => $user['email'],
				'password' => $user['password']
			]);

			$_user->staff()->save($staff);
			$_user->roles()->attach($role->id);
		}

		Model::reguard();

	}
}
