<?php

namespace App\Console\Commands\Backtest;

use App\Actions\CalculateVarianceAction;
use App\Actions\CheckIfBacktestPriceRecordExitsForDateAction;
use App\Models\BacktestNseInstrumentPrice;
use App\Models\NseIndex;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CalculateCovarianceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backtest:calculate-covariance {--date=} {--symbol=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate the covariance of backtest instruments';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Calculating covariance for backtest instruments...');
        $date = $this->option('date');
        $symbol = $this->option('symbol');

        if (is_null($date)) {
            $this->error('Please provide a date');

            return Command::FAILURE;
        }

        if (! (new CheckIfBacktestPriceRecordExitsForDateAction)->execute($date, $symbol)) {
            $this->error('Price record doesnt exists for '.$date);

            return Command::FAILURE;
        }

        $todayDate = Carbon::parse($date);

        $fromDateBeforeOneYear = $todayDate->copy()->subMonths(12)->addDay()->format('Y-m-d');

        $backtestNseInstrumentPrice = BacktestNseInstrumentPrice::query()
            ->where('date', $date)
            ->where('symbol', $symbol)
            ->first();

        $backtestNseInstrumentPrices = $this->fetchPrices($symbol, $fromDateBeforeOneYear, $date);

        if (in_array($backtestNseInstrumentPrice->series, ['RR', 'IV'], true)) {
            return $this->calculateTrustCovariance($backtestNseInstrumentPrice, $fromDateBeforeOneYear, $date);
        }

        $niftyDailyMinusAverage = $this->fetchNiftyDailyMinusAverage($fromDateBeforeOneYear, $date);
        $niftyVariance = $this->fetchNiftyVariance($fromDateBeforeOneYear, $date);

        $averageReturn = $backtestNseInstrumentPrices->average('t_percent');

        if ($backtestNseInstrumentPrices->count() < 235 || is_null($backtestNseInstrumentPrice->variance_one_year)) {
            return Command::SUCCESS;
        }

        $sumOfDailyMinusAverage = $backtestNseInstrumentPrices->map(function ($price) use ($averageReturn) {
            return [
                'date' => $price->date,
                'daily_minus_average' => $price->t_percent - $averageReturn,
            ];
        })->map(function ($stockDailyMinusAverage) use ($niftyDailyMinusAverage) {
            $value = $niftyDailyMinusAverage->firstWhere(
                'date',
                $stockDailyMinusAverage['date']
            );

            if (is_null($value)) {
                return $stockDailyMinusAverage['daily_minus_average'] / 100;
            }

            return ($stockDailyMinusAverage['daily_minus_average'] / 100) *
                (
                    $niftyDailyMinusAverage->firstWhere(
                        'date',
                        $stockDailyMinusAverage['date']
                    )['daily_minus_average'] / 100
                );
        })->sum();

        $covariance = round($sumOfDailyMinusAverage / ($backtestNseInstrumentPrices->count() - 1), 10);
        $backtestNseInstrumentPrice->covariance = $covariance;
        $backtestNseInstrumentPrice->beta = ($backtestNseInstrumentPrice->covariance * 100) / ($niftyVariance / 100);
        $backtestNseInstrumentPrice->save();

        return Command::SUCCESS;
    }

    private function calculateTrustCovariance(BacktestNseInstrumentPrice $current, string $from, string $through): int
    {
        $current->covariance = null;
        $current->beta = null;
        $prices = BacktestNseInstrumentPrice::query()->where('symbol', $current->symbol)
            ->whereBetween('date', [$from, $through])->orderBy('date')->get(['date', 't_percent_raw']);
        $previousDate = BacktestNseInstrumentPrice::query()->where('symbol', $current->symbol)->where('date', '<', $from)->max('date');
        if ($prices->count() < 235 || $previousDate === null || $current->variance_one_year === null) {
            $current->save();

            return self::SUCCESS;
        }
        $benchmark = NseIndex::query()->where('slug', 'nifty-50')->whereBetween('date', [$previousDate, $through])->get(['date', 'close'])
            ->keyBy(fn (NseIndex $index): string => $index->date->toDateString());
        $pairs = [];
        foreach ($prices as $price) {
            $date = $price->date->toDateString();
            $before = $benchmark->get($previousDate);
            $after = $benchmark->get($date);
            if ($before === null || $after === null || $before->close <= 0 || $after->close <= 0) {
                $current->save();

                return self::SUCCESS;
            }
            $pairs[] = [(float) $price->t_percent_raw, (float) $after->close / (float) $before->close - 1];
            $previousDate = $date;
        }
        $stockMean = array_sum(array_column($pairs, 0)) / count($pairs);
        $indexMean = array_sum(array_column($pairs, 1)) / count($pairs);
        $cross = 0.0;
        $squares = 0.0;
        foreach ($pairs as [$stock, $index]) {
            $cross += ($stock - $stockMean) * ($index - $indexMean);
            $squares += ($index - $indexMean) ** 2;
        }
        if ($squares > 1.0e-16) {
            $covariance = $cross / (count($pairs) - 1);
            $beta = $cross / $squares;
            if (is_finite($covariance) && is_finite($beta) && abs($beta) < 10000000000) {
                $current->covariance = round($covariance, 10);
                $current->beta = round($beta, 10);
            }
        }
        $current->save();

        return self::SUCCESS;
    }

    protected function fetchPrices($symbol, $fromDate, $toDate)
    {
        return BacktestNseInstrumentPrice::query()
            ->where('symbol', $symbol)
            ->where('date', '>=', $fromDate)
            ->where('date', '<=', $toDate)
            ->select('id', 'date', 't_percent_raw', 't_percent')
            ->orderBy('date', 'asc')
            ->get();
    }

    protected function fetchNiftyDailyMinusAverage($fromDate, $toDate)
    {
        $niftyPrices = NseIndex::query()
            ->where('slug', 'nifty-50')
            ->where('date', '>=', $fromDate)
            ->where('date', '<=', $toDate)
            ->orderBy('date', 'asc')
            ->get();

        $averageReturn = $niftyPrices->average('percentage_change');

        return $niftyPrices->map(function (NseIndex $price) use ($averageReturn) {
            return [
                'date' => $price->date,
                'daily_minus_average' => $price->percentage_change - $averageReturn,
            ];
        });
    }

    protected function fetchNiftyVariance($fromDate, $toDate)
    {
        $niftyPrices = NseIndex::query()
            ->where('slug', 'nifty-50')
            ->where('date', '>=', $fromDate)
            ->where('date', '<=', $toDate)
            ->orderBy('date', 'asc')
            ->get();

        $calculateVarianceAction = app(CalculateVarianceAction::class);

        return $calculateVarianceAction->execute(
            $niftyPrices->pluck('percentage_change')->toArray()
        );
    }
}
