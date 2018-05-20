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
			$table->bigInteger('transaction_id');
			$table->bigInteger('transaction_status_id');

			$table->string('action');

			$table->float('amount')->nullable();
			$table->float('rate')->nullable();
			$table->float('wacc')->nullable();

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
