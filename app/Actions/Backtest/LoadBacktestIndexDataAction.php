<?php

namespace App\Actions\Backtest;

use App\Models\Backtest;
use App\Models\NseIndex;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class LoadBacktestIndexDataAction
{
    public function __construct(private BacktestTradingCalendarAction $tradingCalendar) {}

    /**
     * Require a complete positive index series before a run can use its DMA.
     *
     * @param  Collection<int, Carbon>  $tradingDates
     * @return array<string, float>
     */
    public function execute(Backtest $backtest, Collection $tradingDates): array
    {
        $firstDate = $tradingDates->first()->toDateString();
        $lastDate = $tradingDates->last()->toDateString();
        $period = (int) $backtest->cash_call_dma_period;
        $warmup = NseIndex::query()->where('slug', $backtest->cash_call_index)
            ->where('date', '<', $firstDate)->orderByDesc('date')->limit($period - 1)
            ->toBase()->pluck('close', 'date')->reverse();

        if ($warmup->count() < $period - 1) {
            throw ValidationException::withMessages([
                'cash_call_index' => "The selected index needs {$period} trading days of prices by {$firstDate} for its DMA. Import the missing index history or use a later start date.",
            ]);
        }

        $values = $warmup->merge(NseIndex::query()->where('slug', $backtest->cash_call_index)
            ->whereBetween('date', [$firstDate, $lastDate])->orderBy('date')
            ->toBase()->pluck('close', 'date'))
            ->map(fn ($value): float => (float) $value)->all();

        foreach ($values as $date => $close) {
            if (! is_finite($close) || $close <= 0) {
                $this->missingPrice($date);
            }
        }

        /** A missing warmup market date must not be replaced with an older index observation. */
        $warmupMarketDates = $this->tradingCalendar->query(
            $backtest->index,
            $warmup->keys()->first() ?? $firstDate,
            $firstDate,
        )->where('date', '<', $firstDate)->pluck('date');

        foreach ($warmupMarketDates->merge($tradingDates->map(fn (Carbon $date): string => $date->toDateString())) as $date) {
            if (! array_key_exists($date, $values)) {
                $this->missingPrice($date);
            }
        }

        return $values;
    }

    private function missingPrice(string $date): never
    {
        throw ValidationException::withMessages([
            'cash_call_index' => "The selected index has no valid closing price for {$date}. Import the missing index data before running this backtest.",
        ]);
    }
}
