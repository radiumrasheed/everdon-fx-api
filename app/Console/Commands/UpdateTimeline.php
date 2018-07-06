<?php

namespace App\Console\Commands;

use App\Events\NewRates;
use App\Product;
use App\Timeline;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UpdateTimeline extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'update:timeline';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Update all WACC timelines';

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function handle()
	{
		try {
			$products = Product::findOrFail([1, 2, 3]);
		} catch (ModelNotFoundException $e) {
			$this->error('An error occurred... ' . $e->getMessage());
		}

		$bar = $this->output->createProgressBar(count($products));

		foreach ($products as $product) {
			$timeline = new Timeline(['value' => $product->wacc, 'rate' => $product->rate]);

			$product->timelines()->save($timeline);

			$bar->advance();
		}

		$usd = Product::findOrFail(1)->dailyRates()->get();
		$gbp = Product::findOrFail(2)->dailyRates()->get();
		$eur = Product::findOrFail(3)->dailyRates()->get();

		$rates = compact('usd', 'gbp', 'eur');

		$bar->finish();

		$this->info(PHP_EOL . ' Timeline updated. ' . PHP_EOL);

		return event(new NewRates($rates));

	}
}
