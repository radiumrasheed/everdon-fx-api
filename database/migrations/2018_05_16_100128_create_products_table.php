<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProductsTable extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('products', function (Blueprint $table) {
			$table->increments('id');
			$table->string('name');
			$table->string('description');
			$table->boolean('local');

			$table->decimal('rate', 13, 4)->nullable();
			$table->decimal('wacc', 13, 4)->nullable();
			$table->decimal('prev_wacc', 13, 4)->nullable();

			$table->decimal('seed_value', 13, 4)->nullable();
			$table->decimal('prev_seed_value', 13, 4)->nullable();
			$table->decimal('seed_value_local', 13, 4)->nullable();
			$table->decimal('prev_seed_value_local', 13, 4)->nullable();

			$table->decimal('bucket', 13, 4)->nullable();
			$table->decimal('prev_bucket', 13, 4)->nullable();
			$table->decimal('bucket_local', 13, 4)->nullable();
			$table->decimal('prev_bucket_local', 13, 4)->nullable();

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
		Schema::dropIfExists('products');
	}
}
