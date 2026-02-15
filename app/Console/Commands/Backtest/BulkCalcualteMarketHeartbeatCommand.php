<?php

namespace App\Console\Commands\Backtest;

use App\Models\BacktestNseInstrumentPrice;
use App\Models\MarketHeartbeat;
use Illuminate\Console\Command;

class BulkCalcualteMarketHeartbeatCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backtest:bulk-calculate-market-heartbeat';

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
        MarketHeartbeat::truncate();
        $dates = BacktestNseInstrumentPrice::query()
            ->where('date', '>=', '2011-01-01')
            ->select('date')
            ->distinct('date')
            ->get()
            ->pluck('date');

        $bar = $this->output->createProgressBar($dates->count());
        $bar->start();

        $dates->each(function ($date) use ($bar) {
            $this->call('backtest:calculate-market-heartbeat', ['--date' => $date]);
            $bar->advance();
        });

        $bar->finish();
    }
}
