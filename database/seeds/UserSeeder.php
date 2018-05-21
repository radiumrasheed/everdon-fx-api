<?php

use App\Role;
use App\Staff;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 *
	 * @return void
	 */
	public function run()
	{
		Model::unguard();

		// $this->call(UserTableSeeder::class);
		DB::table('users')->delete();

		$users = array(
			['name' => 'Developer', 'email' => 'dev@everdon-fx.com', 'password' => Hash::make('dev')],
		);

		$role = Role::where('name', 'systems-admin')->first();

		// Loop through each user above and create the record for them in the database
		foreach ($users as $user) {
			$staff = Staff::create(['full_name' => $user['name'], 'email' => $user['email']]);
			$_user = User::create($user);

			$_user->staff()->save($staff);
			$_user->roles()->attach($role->id);
		}

		Model::reguard();
	}
}
