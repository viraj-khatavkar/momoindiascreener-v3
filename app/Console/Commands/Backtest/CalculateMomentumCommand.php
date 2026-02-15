<?php

namespace App\Console\Commands\Backtest;

use App\Actions\CalculatePercentageDifferenceAction;
use App\Actions\CheckIfBacktestPriceRecordExitsForDateAction;
use App\Models\BacktestNseInstrumentPrice;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class CalculateMomentumCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backtest:calculate-momentum {--date=} {--symbol=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate the momentum of backtest instruments';

    protected const TARGET_PERCENTAGES = [
        4.99,
        5.00,
        9.99,
        10.00,
        19.99,
        20.00,
        -4.99,
        -5.00,
        -9.99,
        -10.00,
        -19.99,
        -20.00,
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Calculating momentum for backtest instruments...');
        $date = $this->option('date');
        $symbol = $this->option('symbol');

        if (is_null($date)) {
            $this->error('Please provide a date');

            return Command::FAILURE;
        }

        if (is_null($symbol)) {
            $this->error('Please provide a symbol');

            return Command::FAILURE;
        }

        if (! (new CheckIfBacktestPriceRecordExitsForDateAction)->execute($date, $symbol)) {
            $this->error('Price record doesnt exists for '.$date);

            return Command::FAILURE;
        }

        $todayDate = Carbon::parse($date);

        $fromDateBeforeFourteenMonths = $todayDate->copy()->subMonths(14)->addDay()->format('Y-m-d');
        $fromDateBeforeThirteenMonths = $todayDate->copy()->subMonths(13)->addDay()->format('Y-m-d');
        $fromDateBeforeOneYear = $todayDate->copy()->subMonths(12)->addDay()->format('Y-m-d');
        $fromDateBeforeNineMonths = $todayDate->copy()->subMonths(9)->addDay()->format('Y-m-d');
        $fromDateBeforeSixMonths = $todayDate->copy()->subMonths(6)->addDay()->format('Y-m-d');
        $fromDateBeforeThreeMonths = $todayDate->copy()->subMonths(3)->addDay()->format('Y-m-d');
        $fromDateBeforeTwoMonths = $todayDate->copy()->subMonths(2)->addDay()->format('Y-m-d');
        $fromDateBeforeOneMonth = $todayDate->copy()->subMonths()->addDay()->format('Y-m-d');
        $fromDateBeforeOneWeek = $todayDate->copy()->subWeek()->addDay()->format('Y-m-d');

        $backtestNseInstrumentPrice = BacktestNseInstrumentPrice::query()
            ->where('date', $date)
            ->where('symbol', $symbol)
            ->first();

        $calculatePercentageAction = app(CalculatePercentageDifferenceAction::class);

        $oneYearPrices = $this->fetchPrices($symbol, $fromDateBeforeOneYear, $date);
        $nineMonthsPrices = $this->fetchPrices($symbol, $fromDateBeforeNineMonths, $date);
        $sixMonthsPrices = $this->fetchPrices($symbol, $fromDateBeforeSixMonths, $date);
        $threeMonthsPrices = $this->fetchPrices($symbol, $fromDateBeforeThreeMonths, $date);
        $oneMonthsPrices = $this->fetchPrices($symbol, $fromDateBeforeOneMonth, $date);
        $oneWeekPrices = $this->fetchPrices($symbol, $fromDateBeforeOneWeek, $date);

        $twelveOnePrices = BacktestNseInstrumentPrice::query()
            ->where('symbol', $symbol)
            ->where('date', '>=', $fromDateBeforeThirteenMonths)
            ->where('date', '<=', $fromDateBeforeOneMonth)
            ->orderBy('date')
            ->get();

        $twelveTwoPrices = BacktestNseInstrumentPrice::query()
            ->where('symbol', $symbol)
            ->where('date', '>=', $fromDateBeforeFourteenMonths)
            ->where('date', '<=', $fromDateBeforeTwoMonths)
            ->orderBy('date', 'asc')
            ->get();

        if ($twelveOnePrices->count() >= 235) {
            $backtestNseInstrumentPrice->return_twelve_minus_one_months = ($twelveOnePrices->last(
            )->close_adjusted / $twelveOnePrices->first()->close_adjusted - 1) * 100;
        }

        if ($twelveTwoPrices->count() >= 235) {
            $backtestNseInstrumentPrice->return_twelve_minus_two_months = ($twelveTwoPrices->last(
            )->close_adjusted / $twelveTwoPrices->first()->close_adjusted - 1) * 100;
        }

        if ($backtestNseInstrumentPrice->volatility_one_year) {
            $backtestNseInstrumentPrice->absolute_return_one_year = $calculatePercentageAction->execute(
                $oneYearPrices->first()->close_adjusted,
                $oneYearPrices->last()->close_adjusted
            );

            if ($backtestNseInstrumentPrice->volatility_one_year > 0) {
                $backtestNseInstrumentPrice->sharpe_return_one_year = $backtestNseInstrumentPrice->absolute_return_one_year / ($backtestNseInstrumentPrice->volatility_one_year * 100);
            }
        }

        if ($backtestNseInstrumentPrice->volatility_nine_months) {
            $backtestNseInstrumentPrice->absolute_return_nine_months = $calculatePercentageAction->execute(
                $nineMonthsPrices->first()->close_adjusted,
                $nineMonthsPrices->last()->close_adjusted
            );

            if ($backtestNseInstrumentPrice->volatility_nine_months > 0) {
                $backtestNseInstrumentPrice->sharpe_return_nine_months = $backtestNseInstrumentPrice->absolute_return_nine_months / ($backtestNseInstrumentPrice->volatility_nine_months * 100);
            }
        }

        if ($backtestNseInstrumentPrice->volatility_six_months) {
            $backtestNseInstrumentPrice->absolute_return_six_months = $calculatePercentageAction->execute(
                $sixMonthsPrices->first()->close_adjusted,
                $sixMonthsPrices->last()->close_adjusted
            );

            if ($backtestNseInstrumentPrice->volatility_six_months > 0) {
                $backtestNseInstrumentPrice->sharpe_return_six_months = $backtestNseInstrumentPrice->absolute_return_six_months / ($backtestNseInstrumentPrice->volatility_six_months * 100);
            }
        }

        if ($backtestNseInstrumentPrice->volatility_three_months) {
            $backtestNseInstrumentPrice->absolute_return_three_months = $calculatePercentageAction->execute(
                $threeMonthsPrices->first()->close_adjusted,
                $threeMonthsPrices->last()->close_adjusted
            );

            if ($backtestNseInstrumentPrice->volatility_three_months > 0) {
                $backtestNseInstrumentPrice->sharpe_return_three_months = $backtestNseInstrumentPrice->absolute_return_three_months / ($backtestNseInstrumentPrice->volatility_three_months * 100);
            }
        }

        if ($backtestNseInstrumentPrice->volatility_one_months) {
            $backtestNseInstrumentPrice->absolute_return_one_months = $calculatePercentageAction->execute(
                $oneMonthsPrices->first()->close_adjusted,
                $oneMonthsPrices->last()->close_adjusted
            );

            if ($backtestNseInstrumentPrice->volatility_one_months > 0) {
                $backtestNseInstrumentPrice->sharpe_return_one_months = $backtestNseInstrumentPrice->absolute_return_one_months / ($backtestNseInstrumentPrice->volatility_one_months * 100);
            }
        }

        // Volume
        $backtestNseInstrumentPrice->median_volume_one_year = $oneYearPrices->median('volume_adjusted');

        if (is_null($oneYearPrices->last())) {
            return Command::SUCCESS;
        }

        $backtestNseInstrumentPrice->volume_day = $oneYearPrices->last()->volume_adjusted;
        $backtestNseInstrumentPrice->volume_one_year_average = $oneYearPrices->average('volume_adjusted');
        $backtestNseInstrumentPrice->volume_nine_months_average = $nineMonthsPrices->average('volume_adjusted');
        $backtestNseInstrumentPrice->volume_six_months_average = $sixMonthsPrices->average('volume_adjusted');
        $backtestNseInstrumentPrice->volume_three_months_average = $threeMonthsPrices->average('volume_adjusted');
        $backtestNseInstrumentPrice->volume_one_months_average = $oneMonthsPrices->average('volume_adjusted');
        $backtestNseInstrumentPrice->volume_week_average = $oneWeekPrices->average('volume_adjusted');

        // Moving Averages
        $ma200Prices = $oneYearPrices->sortByDesc('date')->take(200);
        $backtestNseInstrumentPrice->ma_200 = $ma200Prices->count() === 200 ? $ma200Prices->average('close_adjusted') : null;

        $ma100Prices = $oneYearPrices->sortByDesc('date')->take(100);
        $backtestNseInstrumentPrice->ma_100 = $ma100Prices->count() === 100 ? $ma100Prices->average('close_adjusted') : null;

        $ma50Prices = $oneYearPrices->sortByDesc('date')->take(50);
        $backtestNseInstrumentPrice->ma_50 = $ma50Prices->count() === 50 ? $ma50Prices->average('close_adjusted') : null;

        $ma20Prices = $oneYearPrices->sortByDesc('date')->take(20);
        $backtestNseInstrumentPrice->ma_20 = $ma20Prices->count() === 20 ? $ma20Prices->average('close_adjusted') : null;

        // Positive Days Percentage
        $countOfPositiveDaysInOneYear = $oneYearPrices->filter(
            fn (BacktestNseInstrumentPrice $price) => $price->t_percent > 0
        )->count();
        if ($oneYearPrices->count() > 0) {
            $backtestNseInstrumentPrice->positive_days_percent_one_year = (
                $countOfPositiveDaysInOneYear / $oneYearPrices->count()
            ) * 100;
        }

        $countOfPositiveDaysInNineMonths = $nineMonthsPrices->filter(
            fn (BacktestNseInstrumentPrice $price) => $price->t_percent > 0
        )->count();
        if ($nineMonthsPrices->count() > 0) {
            $backtestNseInstrumentPrice->positive_days_percent_nine_months = (
                $countOfPositiveDaysInNineMonths / $nineMonthsPrices->count()
            ) * 100;
        }

        $countOfPositiveDaysInSixMonths = $sixMonthsPrices->filter(
            fn (BacktestNseInstrumentPrice $price) => $price->t_percent > 0
        )->count();
        if ($sixMonthsPrices->count() > 0) {
            $backtestNseInstrumentPrice->positive_days_percent_six_months = (
                $countOfPositiveDaysInSixMonths / $sixMonthsPrices->count()
            ) * 100;
        }

        $countOfPositiveDaysInThreeMonths = $threeMonthsPrices->filter(
            fn (BacktestNseInstrumentPrice $price) => $price->t_percent > 0
        )->count();
        if ($threeMonthsPrices->count() > 0) {
            $backtestNseInstrumentPrice->positive_days_percent_three_months = (
                $countOfPositiveDaysInThreeMonths / $threeMonthsPrices->count()
            ) * 100;
        }

        $countOfPositiveDaysInOneMonth = $oneMonthsPrices->filter(
            fn (BacktestNseInstrumentPrice $price) => $price->t_percent > 0
        )->count();
        if ($oneMonthsPrices->count() > 0) {
            $backtestNseInstrumentPrice->positive_days_percent_one_months = (
                $countOfPositiveDaysInOneMonth / $oneMonthsPrices->count()
            ) * 100;
        }

        $backtestNseInstrumentPrice->circuits_one_year = $this->countCircuits($oneYearPrices);
        $backtestNseInstrumentPrice->circuits_nine_months = $this->countCircuits($nineMonthsPrices);
        $backtestNseInstrumentPrice->circuits_six_months = $this->countCircuits($sixMonthsPrices);
        $backtestNseInstrumentPrice->circuits_three_months = $this->countCircuits($threeMonthsPrices);
        $backtestNseInstrumentPrice->circuits_one_months = $this->countCircuits($oneMonthsPrices);

        $backtestNseInstrumentPrice->save();

        $this->calculateAverageAbsoluteReturns($backtestNseInstrumentPrice);
        $this->calculateBetaReturns($backtestNseInstrumentPrice);
        $this->calculateHighs($backtestNseInstrumentPrice, $symbol, $date);
        $this->calculateEma($backtestNseInstrumentPrice, $symbol, $date);
    }

    protected function fetchPrices($symbol, $fromDate, $toDate)
    {
        return BacktestNseInstrumentPrice::query()
            ->where('symbol', $symbol)
            ->where('date', '>=', $fromDate)
            ->where('date', '<=', $toDate)
            ->orderBy('date', 'asc')
            ->get();
    }

    protected function countCircuits(Collection $prices): int
    {
        return $prices->filter(function (BacktestNseInstrumentPrice $price) {
            return in_array($price->t_percent, self::TARGET_PERCENTAGES);
        })->count();
    }

    protected function calculateAverageAbsoluteReturns(BacktestNseInstrumentPrice $backtestNseInstrumentPrice): void
    {
        $backtestNseInstrumentPrice->refresh();

        $averagesToCalculate = [
            'average_absolute_return_twelve_nine_six_three_one_months' => ['absolute_return_one_year', 'absolute_return_nine_months', 'absolute_return_six_months', 'absolute_return_three_months', 'absolute_return_one_months'],
            'average_absolute_return_twelve_nine_six_three_months' => ['absolute_return_one_year', 'absolute_return_nine_months', 'absolute_return_six_months', 'absolute_return_three_months'],
            'average_absolute_return_twelve_nine_six_months' => ['absolute_return_one_year', 'absolute_return_nine_months', 'absolute_return_six_months'],
            'average_absolute_return_twelve_nine_months' => ['absolute_return_one_year', 'absolute_return_nine_months'],
            'average_absolute_return_twelve_six_three_one_months' => ['absolute_return_one_year', 'absolute_return_six_months', 'absolute_return_three_months', 'absolute_return_one_months'],
            'average_absolute_return_twelve_six_three_months' => ['absolute_return_one_year', 'absolute_return_six_months', 'absolute_return_three_months'],
            'average_absolute_return_twelve_six_months' => ['absolute_return_one_year', 'absolute_return_six_months'],
            'average_absolute_return_twelve_three_one_months' => ['absolute_return_one_year', 'absolute_return_three_months', 'absolute_return_one_months'],
            'average_absolute_return_twelve_three_months' => ['absolute_return_one_year', 'absolute_return_three_months'],
            'average_absolute_return_twelve_nine_three_one_months' => ['absolute_return_one_year', 'absolute_return_nine_months', 'absolute_return_three_months', 'absolute_return_one_months'],
            'average_absolute_return_twelve_nine_three_months' => ['absolute_return_one_year', 'absolute_return_nine_months', 'absolute_return_three_months'],
            'average_absolute_return_nine_six_three_one_months' => ['absolute_return_nine_months', 'absolute_return_six_months', 'absolute_return_three_months', 'absolute_return_one_months'],
            'average_absolute_return_nine_six_three_months' => ['absolute_return_nine_months', 'absolute_return_six_months', 'absolute_return_three_months'],
            'average_absolute_return_nine_six_months' => ['absolute_return_nine_months', 'absolute_return_six_months'],
            'average_absolute_return_six_three_one_months' => ['absolute_return_six_months', 'absolute_return_three_months', 'absolute_return_one_months'],
            'average_absolute_return_six_three_months' => ['absolute_return_six_months', 'absolute_return_three_months'],
            'average_absolute_return_three_one_months' => ['absolute_return_three_months', 'absolute_return_one_months'],
            'average_sharpe_return_twelve_nine_six_three_one_months' => ['sharpe_return_one_year', 'sharpe_return_nine_months', 'sharpe_return_six_months', 'sharpe_return_three_months', 'sharpe_return_one_months'],
            'average_sharpe_return_twelve_nine_six_three_months' => ['sharpe_return_one_year', 'sharpe_return_nine_months', 'sharpe_return_six_months', 'sharpe_return_three_months'],
            'average_sharpe_return_twelve_nine_six_months' => ['sharpe_return_one_year', 'sharpe_return_nine_months', 'sharpe_return_six_months'],
            'average_sharpe_return_twelve_nine_months' => ['sharpe_return_one_year', 'sharpe_return_nine_months'],
            'average_sharpe_return_twelve_six_three_one_months' => ['sharpe_return_one_year', 'sharpe_return_six_months', 'sharpe_return_three_months', 'sharpe_return_one_months'],
            'average_sharpe_return_twelve_six_three_months' => ['sharpe_return_one_year', 'sharpe_return_six_months', 'sharpe_return_three_months'],
            'average_sharpe_return_twelve_six_months' => ['sharpe_return_one_year', 'sharpe_return_six_months'],
            'average_sharpe_return_twelve_three_one_months' => ['sharpe_return_one_year', 'sharpe_return_three_months', 'sharpe_return_one_months'],
            'average_sharpe_return_twelve_three_months' => ['sharpe_return_one_year', 'sharpe_return_three_months'],
            'average_sharpe_return_twelve_nine_three_one_months' => ['sharpe_return_one_year', 'sharpe_return_nine_months', 'sharpe_return_three_months', 'sharpe_return_one_months'],
            'average_sharpe_return_twelve_nine_three_months' => ['sharpe_return_one_year', 'sharpe_return_nine_months', 'sharpe_return_three_months'],
            'average_sharpe_return_nine_six_three_one_months' => ['sharpe_return_nine_months', 'sharpe_return_six_months', 'sharpe_return_three_months', 'sharpe_return_one_months'],
            'average_sharpe_return_nine_six_three_months' => ['sharpe_return_nine_months', 'sharpe_return_six_months', 'sharpe_return_three_months'],
            'average_sharpe_return_nine_six_months' => ['sharpe_return_nine_months', 'sharpe_return_six_months'],
            'average_sharpe_return_six_three_one_months' => ['sharpe_return_six_months', 'sharpe_return_three_months', 'sharpe_return_one_months'],
            'average_sharpe_return_six_three_months' => ['sharpe_return_six_months', 'sharpe_return_three_months'],
            'average_sharpe_return_three_one_months' => ['sharpe_return_three_months', 'sharpe_return_one_months'],
        ];

        foreach ($averagesToCalculate as $column => $averageableColumns) {
            $shouldSkip = false;
            foreach ($averageableColumns as $averageableColumn) {
                if (! $backtestNseInstrumentPrice->{$averageableColumn}) {
                    $shouldSkip = true;
                    break;
                }
            }

            if ($shouldSkip) {
                continue;
            }

            $backtestNseInstrumentPrice->{$column} = collect($averageableColumns)
                ->map(function ($averageableColumn) use ($backtestNseInstrumentPrice) {
                    return $backtestNseInstrumentPrice->{$averageableColumn};
                })
                ->avg();

            $backtestNseInstrumentPrice->save();
        }
    }

    protected function calculateBetaReturns(BacktestNseInstrumentPrice $backtestNseInstrumentPrice): void
    {
        $backtestNseInstrumentPrice->refresh();

        if (! is_null($backtestNseInstrumentPrice->variance_one_year) && $backtestNseInstrumentPrice->beta != 0) {
            $backtestNseInstrumentPrice->absolute_divide_beta_return_one_year = $backtestNseInstrumentPrice->absolute_return_one_year / $backtestNseInstrumentPrice->beta;
            $backtestNseInstrumentPrice->sharpe_divide_beta_return_one_year = $backtestNseInstrumentPrice->sharpe_return_one_year / $backtestNseInstrumentPrice->beta;
            $backtestNseInstrumentPrice->average_sharpe_divide_beta_return_twelve_nine_six_three_months = $backtestNseInstrumentPrice->average_sharpe_return_twelve_nine_six_three_months / $backtestNseInstrumentPrice->beta;
            $backtestNseInstrumentPrice->average_sharpe_divide_beta_return_twelve_six_three_months = $backtestNseInstrumentPrice->average_sharpe_return_twelve_six_three_months / $backtestNseInstrumentPrice->beta;
            $backtestNseInstrumentPrice->average_sharpe_divide_beta_return_twelve_six_months = $backtestNseInstrumentPrice->average_sharpe_return_twelve_six_months / $backtestNseInstrumentPrice->beta;
            $backtestNseInstrumentPrice->save();
        }
    }

    protected function calculateHighs(BacktestNseInstrumentPrice $backtestNseInstrumentPrice, $symbol, $toDate): void
    {
        $backtestNseInstrumentPrice->refresh();

        $oneYearBeforeDate = Carbon::parse($toDate)->subMonths(12)->addDay()->format('Y-m-d');
        $percentageAction = app(CalculatePercentageDifferenceAction::class);

        $todayClose = $backtestNseInstrumentPrice->close_adjusted;

        $backtestNseInstrumentPrice->high_one_year = BacktestNseInstrumentPrice::query()
            ->where('symbol', $symbol)
            ->where('date', '>=', $oneYearBeforeDate)
            ->where('date', '<=', $toDate)
            ->max('high_adjusted');

        $backtestNseInstrumentPrice->away_from_high_one_year = $percentageAction->execute(
            $backtestNseInstrumentPrice->high_one_year,
            $todayClose
        );

        $backtestNseInstrumentPrice->high_all_time = BacktestNseInstrumentPrice::query()
            ->where('symbol', $symbol)
            ->where('date', '<=', $toDate)
            ->max('high_adjusted');

        $backtestNseInstrumentPrice->away_from_high_all_time = $percentageAction->execute(
            $backtestNseInstrumentPrice->high_all_time,
            $todayClose
        );

        $backtestNseInstrumentPrice->save();
    }

    protected function calculateEma(BacktestNseInstrumentPrice $backtestNseInstrumentPrice, $symbol, $toDate)
    {
        $yesterdayPrice = BacktestNseInstrumentPrice::query()
            ->where('symbol', $symbol)
            ->where('date', '<', $toDate)
            ->orderBy('date', 'desc')
            ->first();

        if (is_null($yesterdayPrice)) {
            return;
        }

        $backtestNseInstrumentPrice->refresh();

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
}
