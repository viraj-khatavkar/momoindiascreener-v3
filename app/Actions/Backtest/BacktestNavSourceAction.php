<?php

namespace App\Actions\Backtest;

use App\Enums\BacktestCashCallEnum;
use App\Enums\BacktestCashCallSourceEnum;
use App\Enums\BacktestStatusEnum;
use App\Models\Backtest;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BacktestNavSourceAction
{
    public function __construct(private BacktestTradingCalendarAction $tradingCalendar) {}

    public function resolve(Backtest $backtest): ?Backtest
    {
        if (! $backtest->usesBacktestNav()) {
            return null;
        }

        $source = Backtest::query()->where('user_id', $backtest->user_id)
            ->whereKey($backtest->cash_call_backtest_id)->first();

        if ($source === null || $source->is($backtest)
            || $source->cash_call !== BacktestCashCallEnum::NoCashCall
            || $source->cash_call_source !== BacktestCashCallSourceEnum::Index
            || $source->cash_call_backtest_id !== null) {
            throw ValidationException::withMessages([
                'cash_call_backtest_id' => 'Select another backtest that you own with No cash call and no source backtest.',
            ]);
        }

        return $source;
    }

    public function assertNotInUse(Backtest $backtest): void
    {
        if ($this->isInUse($backtest)) {
            throw ValidationException::withMessages([
                'cash_call_backtest_id' => 'This backtest is running or supplies NAV to a running backtest. Wait for the run to finish before changing, deleting or running it again.',
            ]);
        }
    }

    public function isInUse(Backtest $backtest): bool
    {
        return $backtest->status === BacktestStatusEnum::Running
            || $backtest->cashCallDependents()->where('status', BacktestStatusEnum::Running)->exists();
    }

    private function calendar(Backtest $backtest, ?string $throughDate = null): Builder
    {
        return $this->tradingCalendar->query(
            $backtest->index,
            $backtest->start_date?->toDateString() ?? '2011-01-05',
            $throughDate,
        );
    }

    public function latestDate(Backtest $backtest): ?string
    {
        return $this->tradingCalendar->latestDates(
            $backtest->index,
            $backtest->start_date?->toDateString() ?? '2011-01-05',
        )->first();
    }

    public function validateHistory(Backtest $backtest, Backtest $source, string $throughDate): void
    {
        $dates = $this->calendar($backtest, $throughDate)->pluck('date');
        $sourceDates = $this->calendar($source, $throughDate)->pluck('date');
        $period = (int) $backtest->cash_call_dma_period;
        $firstSignalDate = $sourceDates->get($period - 1);

        if ($firstSignalDate === null || $firstSignalDate > $dates->first()) {
            $message = "The source needs {$period} trading days of NAV by this backtest's first decision date.";
            $message .= $firstSignalDate !== null
                ? " Set this backtest's start date to {$firstSignalDate} or later, or start the source earlier."
                : ' Start the source earlier or use a shorter DMA period.';

            throw ValidationException::withMessages(['cash_call_backtest_id' => $message]);
        }

        $missingDate = $dates->diff($sourceDates)->first();
        if ($missingDate !== null) {
            throw ValidationException::withMessages([
                'cash_call_backtest_id' => "The source has no trading data for {$missingDate}. Complete its data before running this backtest.",
            ]);
        }
    }

    /**
     * Read one completed NAV series into this run's memory. No source copy is saved.
     *
     * @param  Collection<int, Carbon>  $tradingDates
     * @return array<string, float>
     */
    public function load(Backtest $backtest, Collection $tradingDates): array
    {
        $source = $this->resolve($backtest);
        if ($source === null || $source->status !== BacktestStatusEnum::Completed) {
            throw ValidationException::withMessages([
                'cash_call_backtest_id' => 'The source backtest must complete successfully before this backtest can run.',
            ]);
        }

        $firstDate = $tradingDates->first()->toDateString();
        $lastDate = $tradingDates->last()->toDateString();
        $period = (int) $backtest->cash_call_dma_period;
        $warmupDates = $this->tradingCalendar->latestDates(
            $source->index,
            $source->start_date?->toDateString() ?? '2011-01-05',
            Carbon::parse($firstDate)->subDay()->toDateString(),
            $period - 1,
        );

        $values = $source->dailySnapshots()
            ->whereBetween('date', [$warmupDates->last() ?? $firstDate, $lastDate])
            ->orderBy('date')->toBase()->pluck('nav', 'date')
            ->map(fn ($value): float => (float) $value)->all();

        if ($warmupDates->count() < $period - 1) {
            throw ValidationException::withMessages([
                'cash_call_backtest_id' => "The completed source does not have enough NAV history for the {$period} DMA. Start the source earlier.",
            ]);
        }

        foreach ($values as $date => $value) {
            if (! is_finite($value) || $value < 0) {
                throw ValidationException::withMessages([
                    'cash_call_backtest_id' => "The source NAV is invalid on {$date}.",
                ]);
            }
        }

        $requiredDates = $warmupDates->merge($tradingDates->map(fn ($date): string => $date->toDateString()));
        foreach ($requiredDates as $date) {
            if (! array_key_exists($date, $values)) {
                throw ValidationException::withMessages([
                    'cash_call_backtest_id' => 'The source NAV is missing for '.$date.'. Rerun the source before using it.',
                ]);
            }
        }

        return $values;
    }
}
