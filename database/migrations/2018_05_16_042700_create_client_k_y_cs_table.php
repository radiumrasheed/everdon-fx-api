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

			$table->boolean('status')->default(0);
			$table->boolean('awaiting_review')->default(0);

			$table->bigInteger('last_reviewed_by')->nullable();
			$table->dateTime('last_reviewed_at')->nullable();

			$table->dateTime('expiry')->nullable();

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
