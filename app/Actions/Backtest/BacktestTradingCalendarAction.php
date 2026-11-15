<?php

namespace App\Actions\Backtest;

use App\Enums\NseIndexEnum;
use App\Models\BacktestNseInstrumentPrice;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BacktestTradingCalendarAction
{
    /**
     * Read distinct dates from the index, then stop at the first universe member per date.
     * Filtering membership before DISTINCT scans the full, wide price history.
     */
    public function query(NseIndexEnum $index, ?string $fromDate = null, ?string $throughDate = null): Builder
    {
        $membership = BacktestNseInstrumentPrice::query()->forceIndex('bnip_date_symbol_unique')
            ->selectRaw('1')->whereColumn('backtest_nse_instrument_prices.date', 'trading_dates.date')
            ->where($index->isIndexFieldName(), true)->limit(1);

        return DB::query()->fromSub($this->priceDates($fromDate, $throughDate), 'trading_dates')
            ->select('date')->where($membership, 1)->orderBy('date');
    }

    /**
     * A limited backward scan avoids reading the full calendar for recent-date checks.
     *
     * @return Collection<int, string> Dates in descending order.
     */
    public function latestDates(NseIndexEnum $index, ?string $fromDate = null, ?string $throughDate = null, int $limit = 1): Collection
    {
        return $this->priceDates($fromDate, $throughDate)
            ->where($index->isIndexFieldName(), true)
            ->orderByDesc('date')->limit($limit)->pluck('date');
    }

    public function priceDates(?string $fromDate = null, ?string $throughDate = null): Builder
    {
        return BacktestNseInstrumentPrice::query()->forceIndex('bnip_date_symbol_unique')->toBase()
            ->select('date')->distinct()
            ->when($fromDate !== null, fn (Builder $query) => $query->where('date', '>=', $fromDate))
            ->when($throughDate !== null, fn (Builder $query) => $query->where('date', '<=', $throughDate));
    }
}
