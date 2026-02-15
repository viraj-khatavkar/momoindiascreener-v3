<?php

namespace App\Console\Commands\Backtest;

use App\Models\BacktestNseInstrumentPrice;
use Illuminate\Console\Command;

class BulkCalculateEmaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backtest:bulk-calculate-ema';

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
        $uniqueDates = BacktestNseInstrumentPrice::query()
            ->where('date', '>=', '2011-01-03')
            ->orderBy('date')
            ->distinct()
            ->pluck('date')
            ->toArray();

        $progressBar = $this->output->createProgressBar(count($uniqueDates));
        $progressBar->start();

        foreach ($uniqueDates as $date) {
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
                    continue;
                }

                if (is_null($yesterdayPrice->ema_20)) {
                    $backtestNseInstrumentPrice->ema_20 = $backtestNseInstrumentPrice->ma_20;
                } else {
                    $multiplier = 2 / (20 + 1);
                    $backtestNseInstrumentPrice->ema_20 = ($backtestNseInstrumentPrice->close_adjusted * $multiplier) + ($yesterdayPrice->ema_20 * (1 - $multiplier));
                }

                if (is_null($yesterdayPrice->ema_50)) {
                    $backtestNseInstrumentPrice->ema_50 = $backtestNseInstrumentPrice->ma_50;
                } else {
                    $multiplier = 2 / (50 + 1);
                    $backtestNseInstrumentPrice->ema_50 = ($backtestNseInstrumentPrice->close_adjusted * $multiplier) + ($yesterdayPrice->ema_50 * (1 - $multiplier));
                }

                if (is_null($yesterdayPrice->ema_100)) {
                    $backtestNseInstrumentPrice->ema_100 = $backtestNseInstrumentPrice->ma_100;
                } else {
                    $multiplier = 2 / (100 + 1);
                    $backtestNseInstrumentPrice->ema_100 = ($backtestNseInstrumentPrice->close_adjusted * $multiplier) + ($yesterdayPrice->ema_100 * (1 - $multiplier));
                }

                if (is_null($yesterdayPrice->ema_200)) {
                    $backtestNseInstrumentPrice->ema_200 = $backtestNseInstrumentPrice->ma_200;
                } else {
                    $multiplier = 2 / (200 + 1);
                    $backtestNseInstrumentPrice->ema_200 = ($backtestNseInstrumentPrice->close_adjusted * $multiplier) + ($yesterdayPrice->ema_200 * (1 - $multiplier));
                }

                $backtestNseInstrumentPrice->save();
            }

            $progressBar->advance();
        }

        $progressBar->finish();
    }
}
