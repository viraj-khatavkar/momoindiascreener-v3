<?php

namespace App\Actions;

use App\Models\BacktestNseInstrumentPrice;

class CheckIfBacktestPriceRecordExitsForDateAction
{
    public function execute($date, $symbol = null): bool
    {
        if (is_null($symbol)) {
            return BacktestNseInstrumentPrice::query()
                ->where('date', $date)
                ->exists();
        }

        return BacktestNseInstrumentPrice::query()
            ->where('symbol', $symbol)
            ->where('date', $date)
            ->exists();
    }
}
