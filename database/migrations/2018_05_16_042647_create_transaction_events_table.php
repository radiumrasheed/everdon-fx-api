<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransactionEventsTable extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('transaction_events', function (Blueprint $table) {
			$table->increments('id');
			$table->uuid('transaction_id');
			$table->bigInteger('transaction_status_id');
			$table->bigInteger('org_account_id')->nullable();

			$table->string('action');
			$table->string('comment')->nullable();
			$table->string('condition')->nullable();

			$table->decimal('amount', 13, 4)->nullable();
			$table->decimal('calculated_amount', 13, 4)->nullable();
			$table->decimal('rate', 13, 2)->nullable();

			$table->bigInteger('done_by');
			$table->dateTime('done_at');

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
		Schema::dropIfExists('transaction_events');
	}
}
