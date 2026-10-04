<?php

namespace App\Actions\Backtest;

use App\Models\Backtest;
use App\Models\BacktestNseInstrumentPrice;
use Illuminate\Support\Collection;
use stdClass;

class CalculateMarketCapAllocationAction
{
    /**
     * @return array{start_date: ?string, excluded_days: int, points: list<array{date: string, large_cap: float, mid_cap: float, small_cap: float, etf: float, cash: float}>}
     */
    public function execute(Backtest $backtest): array
    {
        $result = ['start_date' => null, 'excluded_days' => 0, 'points' => []];
        $snapshots = $backtest->dailySnapshots()->orderBy('date')
            ->toBase()->get(['date', 'cash', 'total_value']);

        if ($snapshots->isEmpty()) {
            return $result;
        }

        /** Scalar subqueries with a limit keep MySQL from materializing the entire price table for each index. */
        $coveredDates = $backtest->dailySnapshots()
            ->where(BacktestNseInstrumentPrice::query()->selectRaw('1')
                ->whereColumn('backtest_nse_instrument_prices.date', 'backtest_daily_snapshots.date')
                ->where('is_nifty_100', true)->limit(1), 1)
            ->where(BacktestNseInstrumentPrice::query()->selectRaw('1')
                ->whereColumn('backtest_nse_instrument_prices.date', 'backtest_daily_snapshots.date')
                ->where('is_nifty_midcap_150', true)->limit(1), 1)
            ->toBase()->pluck('date')->flip();

        if ($coveredDates->isEmpty()) {
            $result['excluded_days'] = $snapshots->count();

            return $result;
        }

        $firstCoveredDate = $coveredDates->keys()->min();
        $holdings = [];

        foreach ($snapshots->chunk(21) as $chunk) {
            $chunkTrades = $backtest->trades()
                ->whereIn('date', $chunk->pluck('date')->all())
                ->orderBy('date')->orderBy('id')
                ->toBase()->get(['date', 'symbol', 'trade_type', 'quantity', 'price'])->groupBy('date');
            $symbols = array_unique([...array_keys($holdings), ...$chunkTrades->flatten(1)->pluck('symbol')->all()]);
            $dates = $chunk->pluck('date')->filter(fn (string $date): bool => $date >= $firstCoveredDate)->values()->all();
            $pricesByDate = $this->loadPrices($symbols, $dates);

            foreach ($chunk as $snapshot) {
                foreach ($chunkTrades->get($snapshot->date, []) as $trade) {
                    $holding = $holdings[$trade->symbol] ?? ['quantity' => 0, 'price' => 0.0, 'category' => null];
                    $holding['quantity'] += $trade->trade_type === 'buy' ? (int) $trade->quantity : -(int) $trade->quantity;

                    if ($holding['quantity'] <= 0) {
                        unset($holdings[$trade->symbol]);

                        continue;
                    }

                    $holding['price'] = (float) $trade->price;
                    $holdings[$trade->symbol] = $holding;
                }

                $hasCoverage = $coveredDates->has($snapshot->date);
                $dailyPrices = $pricesByDate->get($snapshot->date, collect())->keyBy('symbol');

                foreach ($holdings as $symbol => &$holding) {
                    $price = $dailyPrices->get($symbol);

                    if ($price) {
                        if ((float) $price->close_adjusted > 0) {
                            $holding['price'] = (float) $price->close_adjusted;
                        }

                        if ($hasCoverage) {
                            $holding['category'] = $this->category($price);
                        }
                    }
                }
                unset($holding);

                if (! $hasCoverage || (float) $snapshot->total_value <= 0 || collect($holdings)->contains(fn (array $holding): bool => $holding['category'] === null)) {
                    $result['excluded_days']++;

                    continue;
                }

                $values = ['large_cap' => 0.0, 'mid_cap' => 0.0, 'small_cap' => 0.0, 'etf' => 0.0, 'cash' => (float) $snapshot->cash];

                foreach ($holdings as $holding) {
                    $values[$holding['category']] += $holding['quantity'] * $holding['price'];
                }

                $investedValue = array_sum($values) - $values['cash'];
                $total = (float) $snapshot->total_value;

                if ($investedValue <= 0 && $total > $values['cash']) {
                    $result['excluded_days']++;

                    continue;
                }

                /** Preserve the saved portfolio and cash values when historical adjusted prices have changed since the run. */
                $investmentScale = $investedValue > 0 ? ($total - $values['cash']) / $investedValue : 0;
                $point = ['date' => $snapshot->date];

                foreach ($values as $category => $value) {
                    $point[$category] = round(($category === 'cash' ? $value : $value * $investmentScale) / $total * 100, 4);
                }

                $result['start_date'] ??= $snapshot->date;
                $result['points'][] = $point;
            }

            unset($chunkTrades, $pricesByDate, $dailyPrices);
        }

        return $result;
    }

    /**
     * @param  list<string>  $symbols
     * @param  list<string>  $dates
     * @return Collection<string, Collection<int, stdClass>>
     */
    private function loadPrices(array $symbols, array $dates): Collection
    {
        if ($symbols === [] || $dates === []) {
            return collect();
        }

        return BacktestNseInstrumentPrice::query()
            ->forceIndex('bnip_symbol_date_index')
            ->whereIn('symbol', $symbols)
            ->whereBetween('date', [min($dates), max($dates)])
            ->orderBy('symbol')
            ->orderBy('date')
            ->toBase()->get(['date', 'symbol', 'close_adjusted', 'is_nifty_100', 'is_nifty_midcap_150', 'is_etf'])
            ->groupBy('date');
    }

    private function category(stdClass $price): string
    {
        return match (true) {
            (bool) $price->is_etf, $price->symbol === 'GOLDBEES' => 'etf',
            (bool) $price->is_nifty_100 => 'large_cap',
            (bool) $price->is_nifty_midcap_150 => 'mid_cap',
            default => 'small_cap',
        };
    }
}
