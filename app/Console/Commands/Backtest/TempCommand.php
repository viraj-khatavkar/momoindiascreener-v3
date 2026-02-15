<?php

namespace App\Console\Commands\Backtest;

use App\Models\BacktestNseIndexConstituent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TempCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:temp-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $backtestIndexConstituents = BacktestNseIndexConstituent::query()
            ->where('index', 'nifty_200')
            ->get()
            ->pluck('symbol')
            ->toArray();

        DB::table('backtest_nse_instrument_prices')
            ->whereIn('symbol', $backtestIndexConstituents)
            ->where('date', '>=', '2014-01-01')
            ->update([
                'is_nifty_200' => true,
            ]);
    }
}
