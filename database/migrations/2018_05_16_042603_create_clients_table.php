<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateClientsTable extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('clients', function (Blueprint $table) {
			$table->increments('id');
			$table->bigInteger('client_type');
			$table->bigInteger('user_id')->nullable();
			// $table->bigInteger('client_kyc_id');

			$table->string('full_name');
			$table->string('email')->unique();
			$table->string('phone');
			$table->string('office_address')->nullable();
			$table->string('bvn')->nullable();

			$table->string('identification')->nullable();
			$table->string('identification_number')->nullable();
			$table->string('identification_image')->nullable();
			$table->string('avatar')->nullable();

			$table->string('rc_number')->nullable();

			$table->string('marital_status')->nullable();
			$table->string('residential_address')->nullable();
			$table->string('occupation')->nullable();
			$table->string('nok_full_name')->nullable();
			$table->string('nok_phone')->nullable();
			$table->string('referee_1')->nullable();
			$table->string('referee_2')->nullable();
			$table->string('referee_3')->nullable();
			$table->string('referee_4')->nullable();
			$table->date('date_of_birth')->nullable();


			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::dropIfExists('clients');
	}
}
