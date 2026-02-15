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

        $niftyDailyMinusAverage = $this->fetchNiftyDailyMinusAverage($fromDateBeforeOneYear, $date);
        $niftyVariance = $this->fetchNiftyVariance($fromDateBeforeOneYear, $date);

        $backtestNseInstrumentPrice = BacktestNseInstrumentPrice::query()
            ->where('date', $date)
            ->where('symbol', $symbol)
            ->first();

        $backtestNseInstrumentPrices = $this->fetchPrices($symbol, $fromDateBeforeOneYear, $date);

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
