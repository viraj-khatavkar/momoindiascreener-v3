<?php

namespace App\Console\Commands\Backtest;

use App\Actions\CalculateVarianceAction;
use App\Actions\CheckIfBacktestPriceRecordExitsForDateAction;
use App\Models\BacktestNseInstrumentPrice;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class CalculateVarianceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backtest:calculate-variance {--date=} {--symbol=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate the variance of backtest instruments';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Calculating variance for backtest instruments...');
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

        $fromDateBeforeOneYear = $todayDate->copy()->subMonths(12)->addDay()->format('Y-m-d');
        $fromDateBeforeNineMonths = $todayDate->copy()->subMonths(9)->addDay()->format('Y-m-d');
        $fromDateBeforeSixMonths = $todayDate->copy()->subMonths(6)->addDay()->format('Y-m-d');
        $fromDateBeforeThreeMonths = $todayDate->copy()->subMonths(3)->addDay()->format('Y-m-d');
        $fromDateBeforeOneMonths = $todayDate->copy()->subMonths()->addDay()->format('Y-m-d');

        $backtestNseInstrumentPrice = BacktestNseInstrumentPrice::query()
            ->where('date', $date)
            ->where('symbol', $symbol)
            ->first();

        $this->forOneYear(
            $backtestNseInstrumentPrice,
            $this->fetchPrices($symbol, $fromDateBeforeOneYear, $date),
        );

        $this->forNineMonths(
            $backtestNseInstrumentPrice,
            $this->fetchPrices($symbol, $fromDateBeforeNineMonths, $date),
        );

        $this->forSixMonths(
            $backtestNseInstrumentPrice,
            $this->fetchPrices($symbol, $fromDateBeforeSixMonths, $date),
        );

        $this->forThreeMonths(
            $backtestNseInstrumentPrice,
            $this->fetchPrices($symbol, $fromDateBeforeThreeMonths, $date),
        );

        $this->forOneMonths(
            $backtestNseInstrumentPrice,
            $this->fetchPrices($symbol, $fromDateBeforeOneMonths, $date),
        );

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

    protected function forOneYear(
        BacktestNseInstrumentPrice $backtestNseInstrumentPrice,
        Collection $backtestNseInstrumentPrices
    ): void {
        if ($backtestNseInstrumentPrices->count() < 235) {
            return;
        }

        $calculateVarianceAction = app(CalculateVarianceAction::class);
        $backtestNseInstrumentPrice->variance_one_year = $calculateVarianceAction->execute(
            $backtestNseInstrumentPrices->pluck('t_percent_raw')->toArray()
        );
        $backtestNseInstrumentPrice->standard_deviation_one_year = round(
            sqrt($backtestNseInstrumentPrice->variance_one_year),
            8
        );
        $backtestNseInstrumentPrice->volatility_one_year = round(
            $backtestNseInstrumentPrice->standard_deviation_one_year * sqrt(252),
            8
        );
        $backtestNseInstrumentPrice->save();
    }

    protected function forNineMonths(
        BacktestNseInstrumentPrice $backtestNseInstrumentPrice,
        Collection $backtestNseInstrumentPrices
    ): void {
        if ($backtestNseInstrumentPrices->count() < 170) {
            return;
        }

        $calculateVarianceAction = app(CalculateVarianceAction::class);
        $backtestNseInstrumentPrice->variance_nine_months = $calculateVarianceAction->execute(
            $backtestNseInstrumentPrices->pluck('t_percent_raw')->toArray()
        );
        $backtestNseInstrumentPrice->standard_deviation_nine_months = round(
            sqrt($backtestNseInstrumentPrice->variance_nine_months),
            8
        );
        $backtestNseInstrumentPrice->volatility_nine_months = round(
            $backtestNseInstrumentPrice->standard_deviation_nine_months * sqrt(252),
            8
        );
        $backtestNseInstrumentPrice->save();
    }

    protected function forSixMonths(
        BacktestNseInstrumentPrice $backtestNseInstrumentPrice,
        Collection $backtestNseInstrumentPrices
    ): void {
        if ($backtestNseInstrumentPrices->count() < 116) {
            return;
        }

        $calculateVarianceAction = app(CalculateVarianceAction::class);
        $backtestNseInstrumentPrice->variance_six_months = $calculateVarianceAction->execute(
            $backtestNseInstrumentPrices->pluck('t_percent_raw')->toArray()
        );
        $backtestNseInstrumentPrice->standard_deviation_six_months = round(
            sqrt($backtestNseInstrumentPrice->variance_six_months),
            8
        );
        $backtestNseInstrumentPrice->volatility_six_months = round(
            $backtestNseInstrumentPrice->standard_deviation_six_months * sqrt(252),
            8
        );
        $backtestNseInstrumentPrice->save();
    }

    protected function forThreeMonths(
        BacktestNseInstrumentPrice $backtestNseInstrumentPrice,
        Collection $backtestNseInstrumentPrices
    ): void {
        if ($backtestNseInstrumentPrices->count() < 50) {
            return;
        }

        $calculateVarianceAction = app(CalculateVarianceAction::class);
        $backtestNseInstrumentPrice->variance_three_months = $calculateVarianceAction->execute(
            $backtestNseInstrumentPrices->pluck('t_percent_raw')->toArray()
        );
        $backtestNseInstrumentPrice->standard_deviation_three_months = round(
            sqrt($backtestNseInstrumentPrice->variance_three_months),
            8
        );
        $backtestNseInstrumentPrice->volatility_three_months = round(
            $backtestNseInstrumentPrice->standard_deviation_three_months * sqrt(252),
            8
        );
        $backtestNseInstrumentPrice->save();
    }

    protected function forOneMonths(
        BacktestNseInstrumentPrice $backtestNseInstrumentPrice,
        Collection $backtestNseInstrumentPrices
    ): void {
        if ($backtestNseInstrumentPrices->count() < 18) {
            return;
        }

        $calculateVarianceAction = app(CalculateVarianceAction::class);
        $backtestNseInstrumentPrice->variance_one_months = $calculateVarianceAction->execute(
            $backtestNseInstrumentPrices->pluck('t_percent_raw')->toArray()
        );
        $backtestNseInstrumentPrice->standard_deviation_one_months = round(
            sqrt($backtestNseInstrumentPrice->variance_one_months),
            8
        );
        $backtestNseInstrumentPrice->volatility_one_months = round(
            $backtestNseInstrumentPrice->standard_deviation_one_months * sqrt(252),
            8
        );
        $backtestNseInstrumentPrice->save();
    }
}
