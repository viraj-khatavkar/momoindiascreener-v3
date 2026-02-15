<?php

namespace App\Console\Commands\Backtest;

use App\Models\BacktestNseInstrumentPrice;
use Illuminate\Console\Command;

class CalculateTPercentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backtest:calculate-t-percent {--date=}';

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
        $this->info('Calculating t_percent for NSE instruments...');
        $date = $this->option('date');

        if (is_null($date)) {
            $this->error('Please provide a date');

            return Command::FAILURE;
        }

        $backtestNseInstrumentPrices = BacktestNseInstrumentPrice::query()
            ->where('date', $date)
            ->get();

        foreach ($backtestNseInstrumentPrices as $backtestNseInstrumentPrice) {
            $yesterdayPrice = BacktestNseInstrumentPrice::query()
                ->where('symbol', $backtestNseInstrumentPrice->symbol)
                ->where('date', '<', $date)
                ->orderBy('date', 'desc')
                ->first();

            if (is_null($yesterdayPrice)) {
                $dailyReturn = 0;
            } else {
                $dailyReturn = $backtestNseInstrumentPrice->close_adjusted / $yesterdayPrice->close_adjusted - 1;
            }

            $backtestNseInstrumentPrice->t_percent = $dailyReturn * 100;
            $backtestNseInstrumentPrice->t_percent_raw = $dailyReturn;
            $backtestNseInstrumentPrice->save();
        }
    }
}
