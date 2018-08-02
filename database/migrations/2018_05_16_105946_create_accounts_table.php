<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAccountsTable extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('accounts', function (Blueprint $table) {
			$table->increments('id');
			$table->uuid('client_id');
			$table->boolean('default')->default(false);
			$table->boolean('foreign')->default(false);
			$table->string('number')->unique();
			$table->string('name');
			$table->string('bank');

			$table->string('bank_address')->nullable();
			$table->string('swift_code')->nullable();
			$table->string('routing_no')->nullable();
			$table->string('sort_code')->nullable();
			$table->string('iban')->nullable();

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
		Schema::dropIfExists('accounts');
	}
}
