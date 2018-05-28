<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterProductsTable extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('products', function (Blueprint $table) {
			$table->decimal('wacc', 13, 4)->nullable();
			$table->decimal('seed_value', 13, 4)->nullable();
			$table->decimal('seed_value_ngn', 13, 4)->nullable();
			$table->decimal('bucket', 13, 4)->nullable();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('products', function (Blueprint $table) {
			$table->dropColumn([
				'wacc',
				'seed_value',
				'seed_value_ngn',
				'bucket']);
		});
	}
}
