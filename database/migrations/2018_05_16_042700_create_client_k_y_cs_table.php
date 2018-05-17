<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateClientKYCsTable extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('client_kyc', function (Blueprint $table) {
			$table->increments('id');
			$table->bigInteger('client_id');
			$table->boolean('status');

			$table->bigInteger('last_reviewed_by');
			$table->dateTime('last_reviewed_at');

			$table->dateTime('expiry');

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
		Schema::dropIfExists('client_kyc');
	}
}
