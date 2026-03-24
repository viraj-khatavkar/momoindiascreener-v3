<?php

namespace App\Console\Commands\Backtest;

use App\Actions\CalculateRsiAction;
use App\Actions\CheckIfBacktestPriceRecordExitsForDateAction;
use App\Models\BacktestNseInstrumentPrice;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class CalculateRsiCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backtest:calculate-rsi {--date=} {--symbol=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate the rsi of backtest instruments';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Calculating rsi for backtest instruments...');
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
        $fromDateBeforeOneMonth = $todayDate->copy()->subMonths()->addDay()->format('Y-m-d');

        $backtestNseInstrumentPrice = BacktestNseInstrumentPrice::query()
            ->where('date', $date)
            ->where('symbol', $symbol)
            ->first();

        $oneYearPrices = $this->fetchPrices(
            $symbol,
            $fromDateBeforeOneYear,
            $date
        );
        $nineMonthsPrices = $this->fetchPrices(
            $symbol,
            $fromDateBeforeNineMonths,
            $date
        );
        $sixMonthsPrices = $this->fetchPrices(
            $symbol,
            $fromDateBeforeSixMonths,
            $date
        );
        $threeMonthsPrices = $this->fetchPrices(
            $symbol,
            $fromDateBeforeThreeMonths,
            $date
        );
        $oneMonthsPrices = $this->fetchPrices(
            $symbol,
            $fromDateBeforeOneMonth,
            $date
        );

        $this->calculateRsi(
            $backtestNseInstrumentPrice,
            $oneYearPrices,
            $nineMonthsPrices,
            $sixMonthsPrices,
            $threeMonthsPrices,
            $oneMonthsPrices
        );
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

    protected function calculateRsi(
        BacktestNseInstrumentPrice $backtestNseInstrumentPrice,
        Collection $oneYearPrices,
        Collection $nineMonthsPrices,
        Collection $sixMonthsPrices,
        Collection $threeMonthsPrices,
        Collection $oneMonthPrices
    ): void {
        $backtestNseInstrumentPrice->refresh();

        $rsiAction = app(CalculateRsiAction::class);

        // One Year RSI - minimum 235 trading days required
        if ($oneYearPrices->count() >= 235) {
            $rsiPeriod = $oneYearPrices->count() - 1; // Use all available price changes
            $backtestNseInstrumentPrice->rsi_one_year = $rsiAction->execute($oneYearPrices, $rsiPeriod, 'close_adjusted');
        } else {
            $backtestNseInstrumentPrice->rsi_one_year = null;
        }

        // Nine Months RSI - minimum 170 trading days required
        if ($nineMonthsPrices->count() >= 170) {
            $rsiPeriod = $nineMonthsPrices->count() - 1; // Use all available price changes
            $backtestNseInstrumentPrice->rsi_nine_months = $rsiAction->execute($nineMonthsPrices, $rsiPeriod, 'close_adjusted');
        } else {
            $backtestNseInstrumentPrice->rsi_nine_months = null;
        }

        // Six Months RSI - minimum 116 trading days required
        if ($sixMonthsPrices->count() >= 116) {
            $rsiPeriod = $sixMonthsPrices->count() - 1; // Use all available price changes
            $backtestNseInstrumentPrice->rsi_six_months = $rsiAction->execute($sixMonthsPrices, $rsiPeriod, 'close_adjusted');
        } else {
            $backtestNseInstrumentPrice->rsi_six_months = null;
        }

        // Three Months RSI - minimum 50 trading days required
        if ($threeMonthsPrices->count() >= 50) {
            $rsiPeriod = $threeMonthsPrices->count() - 1; // Use all available price changes
            $backtestNseInstrumentPrice->rsi_three_months = $rsiAction->execute($threeMonthsPrices, $rsiPeriod, 'close_adjusted');
        } else {
            $backtestNseInstrumentPrice->rsi_three_months = null;
        }

        // One Month RSI - minimum 18 trading days required
        if ($oneMonthPrices->count() >= 18) {
            $rsiPeriod = $oneMonthPrices->count() - 1; // Use all available price changes
            $backtestNseInstrumentPrice->rsi_one_months = $rsiAction->execute($oneMonthPrices, $rsiPeriod, 'close_adjusted');
        } else {
            $backtestNseInstrumentPrice->rsi_one_months = null;
        }

        $backtestNseInstrumentPrice->save();

        $this->calculateAverageRsi($backtestNseInstrumentPrice);
    }

    protected function calculateAverageRsi(BacktestNseInstrumentPrice $backtestNseInstrumentPrice): void
    {
        $backtestNseInstrumentPrice->refresh();

        $averagesToCalculate = [
            'average_rsi_twelve_nine_six_three_one_months' => [
                'rsi_one_year',
                'rsi_nine_months',
                'rsi_six_months',
                'rsi_three_months',
                'rsi_one_months',
            ],
            'average_rsi_twelve_nine_six_three_months' => [
                'rsi_one_year',
                'rsi_nine_months',
                'rsi_six_months',
                'rsi_three_months',
            ],
            'average_rsi_twelve_nine_six_months' => [
                'rsi_one_year',
                'rsi_nine_months',
                'rsi_six_months',
            ],
            'average_rsi_twelve_nine_months' => [
                'rsi_one_year',
                'rsi_nine_months',
            ],
            'average_rsi_twelve_six_three_one_months' => [
                'rsi_one_year',
                'rsi_six_months',
                'rsi_three_months',
                'rsi_one_months',
            ],
            'average_rsi_twelve_six_three_months' => [
                'rsi_one_year',
                'rsi_six_months',
                'rsi_three_months',
            ],
            'average_rsi_twelve_six_months' => [
                'rsi_one_year',
                'rsi_six_months',
            ],
            'average_rsi_twelve_three_one_months' => [
                'rsi_one_year',
                'rsi_three_months',
                'rsi_one_months',
            ],
            'average_rsi_twelve_three_months' => [
                'rsi_one_year',
                'rsi_three_months',
            ],
            'average_rsi_twelve_nine_three_one_months' => [
                'rsi_one_year',
                'rsi_nine_months',
                'rsi_three_months',
                'rsi_one_months',
            ],
            'average_rsi_twelve_nine_three_months' => [
                'rsi_one_year',
                'rsi_nine_months',
                'rsi_three_months',
            ],
            'average_rsi_nine_six_three_one_months' => [
                'rsi_nine_months',
                'rsi_six_months',
                'rsi_three_months',
                'rsi_one_months',
            ],
            'average_rsi_nine_six_three_months' => [
                'rsi_nine_months',
                'rsi_six_months',
                'rsi_three_months',
            ],
            'average_rsi_nine_six_months' => [
                'rsi_nine_months',
                'rsi_six_months',
            ],
            'average_rsi_six_three_one_months' => [
                'rsi_six_months',
                'rsi_three_months',
                'rsi_one_months',
            ],
            'average_rsi_six_three_months' => [
                'rsi_six_months',
                'rsi_three_months',
            ],
            'average_rsi_three_one_months' => [
                'rsi_three_months',
                'rsi_one_months',
            ],
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
}
