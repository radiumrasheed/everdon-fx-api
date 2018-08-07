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
			$table->uuid('id');
			$table->uuid('originating_id')->nullable();
			$table->primary('id');
			$table->string('transaction_ref')->unique();
			$table->string('description')->nullable();
			$table->string('purpose')->nullable();

			$table->bigInteger('transaction_type_id');
			$table->bigInteger('transaction_mode_id');
			$table->bigInteger('transaction_status_id');

			$table->uuid('client_id');
			$table->bigInteger('selling_product_id');
			$table->bigInteger('buying_product_id');
			$table->bigInteger('account_id')->nullable();
			$table->bigInteger('org_account_id')->nullable();

			$table->decimal('amount', 13, 4);
			$table->decimal('rate', 13, 2)->nullable();
			$table->decimal('calculated_amount', 13, 4)->nullable();
			$table->decimal('swap_charges', 13, 4)->nullable();

			$table->decimal('wacc', 13, 4)->nullable();
			$table->decimal('inventory', 13, 4)->nullable();
			$table->decimal('local_inventory', 13, 4)->nullable();

			$table->string('country')->nullable();
			$table->string('condition')->nullable();
			$table->string('destination')->nullable();

			$table->string('swift_code')->nullable();
			$table->string('routing_no')->nullable();
			$table->string('sort_code')->nullable();
			$table->string('iban')->nullable();

			$table->string('documents')->nullable();
			$table->string('referrer')->nullable();

			$table->boolean('kyc_check')->nullable();
			$table->boolean('aml_check')->nullable();
			$table->boolean('funds_received')->nullable();
			$table->boolean('funds_paid')->nullable();

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
