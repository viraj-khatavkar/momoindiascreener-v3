<?php

namespace App\Console\Commands\Backtest;

use App\Models\BacktestNseInstrumentPrice;
use App\Models\MarketHeartbeat;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CalculateMarketHeartbeatCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backtest:calculate-market-heartbeat {--date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate market heartbeat for backtests table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $date = $this->option('date');

        if (is_null($date)) {
            $this->error('Please provide a date');

            return Command::FAILURE;
        }

        $indicesToCheck = [
            'nifty-50',
            'nifty-next-50',
            'nifty-100',
            'nifty-500',
            'nifty-allcap',
        ];

        foreach ($indicesToCheck as $index) {
            $indexColumn = Str::of($index)->replace('-', '_')->prepend('is_')->toString();

            $backtestNseInstrumentPrices = BacktestNseInstrumentPrice::query()
                ->where('date', $date)
                ->where($indexColumn, true)
                ->select(
                    'id',
                    'ma_200',
                    'ma_100',
                    'ma_50',
                    'ma_20',
                    'away_from_high_all_time',
                    'close_adjusted',
                    'absolute_return_one_year',
                    't_percent'
                )
                ->get();

            if ($backtestNseInstrumentPrices->count() === 0) {
                continue;
            }

            $percentageAboveMa200 = $backtestNseInstrumentPrices->filter(function ($backtestNseInstrumentPrice) {
                return $backtestNseInstrumentPrice->close_adjusted > $backtestNseInstrumentPrice->ma_200;
            })->count() / $backtestNseInstrumentPrices->count() * 100;

            $percentageAboveMa100 = $backtestNseInstrumentPrices->filter(function ($backtestNseInstrumentPrice) {
                return $backtestNseInstrumentPrice->close_adjusted > $backtestNseInstrumentPrice->ma_100;
            })->count() / $backtestNseInstrumentPrices->count() * 100;

            $percentageAboveMa50 = $backtestNseInstrumentPrices->filter(function ($backtestNseInstrumentPrice) {
                return $backtestNseInstrumentPrice->close_adjusted > $backtestNseInstrumentPrice->ma_50;
            })->count() / $backtestNseInstrumentPrices->count() * 100;

            $percentageAboveMa20 = $backtestNseInstrumentPrices->filter(function ($backtestNseInstrumentPrice) {
                return $backtestNseInstrumentPrice->close_adjusted > $backtestNseInstrumentPrice->ma_20;
            })->count() / $backtestNseInstrumentPrices->count() * 100;

            $percentageOfStocksWithReturnsOneYearAboveZero = $backtestNseInstrumentPrices->filter(
                function ($backtestNseInstrumentPrice) {
                    return $backtestNseInstrumentPrice->absolute_return_one_year > 0;
                }
            )->count() / $backtestNseInstrumentPrices->count() * 100;

            $percentageOfStocksWithReturnsOneYearAboveTen = $backtestNseInstrumentPrices->filter(
                function ($backtestNseInstrumentPrice) {
                    return $backtestNseInstrumentPrice->absolute_return_one_year > 10;
                }
            )->count() / $backtestNseInstrumentPrices->count() * 100;

            $percentageOfStocksWithReturnsOneYearAboveHundred = $backtestNseInstrumentPrices->filter(
                function ($backtestNseInstrumentPrice) {
                    return $backtestNseInstrumentPrice->absolute_return_one_year > 100;
                }
            )->count() / $backtestNseInstrumentPrices->count() * 100;

            $percentageOfStocksWithinTenPercentOfATH = $backtestNseInstrumentPrices->filter(
                function ($backtestNseInstrumentPrice) {
                    return $backtestNseInstrumentPrice->away_from_high_all_time > -10;
                }
            )->count() / $backtestNseInstrumentPrices->count() * 100;

            $percentageOfStocksWithinTwentyPercentOfATH = $backtestNseInstrumentPrices->filter(
                function ($backtestNseInstrumentPrice) {
                    return $backtestNseInstrumentPrice->away_from_high_all_time > -20;
                }
            )->count() / $backtestNseInstrumentPrices->count() * 100;

            $percentageOfStocksWithinThirtyPercentOfATH = $backtestNseInstrumentPrices->filter(
                function ($backtestNseInstrumentPrice) {
                    return $backtestNseInstrumentPrice->away_from_high_all_time > -30;
                }
            )->count() / $backtestNseInstrumentPrices->count() * 100;

            $advances = $backtestNseInstrumentPrices->filter(function ($backtestNseInstrumentPrice) {
                return $backtestNseInstrumentPrice->t_percent > 0;
            })->count();

            $declines = $backtestNseInstrumentPrices->filter(function ($backtestNseInstrumentPrice) {
                return $backtestNseInstrumentPrice->t_percent < 0;
            })->count();

            $advanceDeclineRatio = $advances / ($declines > 0 ? $declines : 1);

            MarketHeartbeat::updateOrInsert([
                'index' => $index,
                'date' => $date,
            ], [
                'percentage_above_ma_200' => $percentageAboveMa200,
                'percentage_above_ma_100' => $percentageAboveMa100,
                'percentage_above_ma_50' => $percentageAboveMa50,
                'percentage_above_ma_20' => $percentageAboveMa20,
                'percentage_of_stocks_with_returns_one_year_above_zero' => $percentageOfStocksWithReturnsOneYearAboveZero,
                'percentage_of_stocks_with_returns_one_year_above_ten' => $percentageOfStocksWithReturnsOneYearAboveTen,
                'percentage_of_stocks_with_returns_one_year_above_hundred' => $percentageOfStocksWithReturnsOneYearAboveHundred,
                'percentage_of_stocks_within_ten_percent_of_ath' => $percentageOfStocksWithinTenPercentOfATH,
                'percentage_of_stocks_within_twenty_percent_of_ath' => $percentageOfStocksWithinTwentyPercentOfATH,
                'percentage_of_stocks_within_thirty_percent_of_ath' => $percentageOfStocksWithinThirtyPercentOfATH,
                'advances' => $advances,
                'declines' => $declines,
                'advance_decline_ratio' => $advanceDeclineRatio,
            ]);
        }
    }
}
