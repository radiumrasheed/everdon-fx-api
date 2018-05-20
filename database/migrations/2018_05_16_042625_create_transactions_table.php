<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransactionsTable extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('transactions', function (Blueprint $table) {
			$table->increments('id');

			$table->bigInteger('transaction_type_id');
			$table->bigInteger('transaction_mode_id');
			$table->bigInteger('transaction_status_id');

			$table->bigInteger('client_id');
			$table->bigInteger('product_id');
			$table->bigInteger('account_id');

			$table->float('amount');
			$table->float('rate')->nullable();
			$table->float('wacc')->nullable();

			$table->bigInteger('initiated_by')->nullable();
			$table->bigInteger('reviewed_by')->nullable();
			$table->bigInteger('approved_by')->nullable();
			$table->bigInteger('closed_by')->nullable();

			$table->dateTime('initiated_at')->nullable();
			$table->dateTime('reviewed_at')->nullable();
			$table->dateTime('approved_at')->nullable();
			$table->dateTime('closed_at')->nullable();

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
		Schema::dropIfExists('transactions');
	}
}
