<?php

namespace App\Actions\Backtest;

use App\Models\BacktestNseInstrumentPrice;
use App\Models\BacktestNseTradingGapState;

class InvalidateAssumedDelistingsAction
{
    public function execute(?string $changedDate = null): void
    {
        BacktestNseTradingGapState::query()->whereKey(1)
            ->when($changedDate !== null, fn ($query) => $query->where('processed_through', '>=', $changedDate))
            ->update(['requires_rebuild' => true]);
    }

    /**
     * Gap dates depend on positive quotes, not their magnitude. Check the stored
     * decimal result because a small positive price can round down to zero.
     */
    public function executeForPriceAdjustment(string $symbol, string $beforeDate, float $factor, bool $divide): void
    {
        if (! is_finite($factor) || $factor <= 0) {
            $this->execute();

            return;
        }

        $state = BacktestNseTradingGapState::query()->lockForUpdate()->find(1);
        if ($state?->processed_through === null || $state->requires_rebuild) {
            return;
        }

        $operator = $divide ? '/' : '*';
        $changesQuoteAvailability = BacktestNseInstrumentPrice::query()
            ->where('symbol', $symbol)->where('date', '<', $beforeDate)
            ->where('date', '<=', $state->processed_through->toDateString())
            ->where('close_adjusted', '>', 0)
            ->whereRaw("ROUND(close_adjusted {$operator} {$factor}, 2) = 0")
            ->exists();

        if ($changesQuoteAvailability) {
            $state->update(['requires_rebuild' => true]);
        }
    }
}
