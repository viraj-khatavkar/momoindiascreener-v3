<?php

namespace App\Actions;

use App\Models\BacktestNseInstrumentPrice;
use App\Models\Portfolio;
use App\Models\PortfolioHolding;
use Illuminate\Support\Collection;

class BuildPortfolioValuationAction
{
    /**
     * @return array{
     *     date: string, marketDate: ?string, holdings: list<array<string, mixed>>,
     *     summary: array{cost: float, value: float, profitLoss: ?float, percentChange: ?float, stocks: int, lots: int, missingPrices: int, earlierPrices: int}
     * }
     */
    public function execute(Portfolio $portfolio, ?string $date = null): array
    {
        $marketDate = BacktestNseInstrumentPrice::query()
            ->when($date, fn ($query) => $query->where('date', '<=', $date))
            ->max('date');
        $date ??= $marketDate ?? now()->toDateString();

        $holdings = $portfolio->holdings()->orderBy('symbol')->orderBy('id')->get();
        $quotes = $this->latestQuotes($holdings->pluck('symbol')->uniqueStrict(), $date);

        $rows = $holdings->map(function (PortfolioHolding $holding) use ($quotes, $marketDate): array {
            $quote = $quotes->get($holding->symbol);
            $cost = round($holding->quantity * (float) $holding->price, 2);
            $close = $quote ? (float) $quote->close_raw : null;
            $value = $close !== null ? round($holding->quantity * $close, 2) : null;
            $profitLoss = $value !== null ? round($value - $cost, 2) : null;
            $priceDate = $quote?->date->toDateString();

            return [
                'id' => $holding->id,
                'symbol' => $holding->symbol,
                'name' => $quote?->name,
                'series' => $quote?->series,
                'quantity' => $holding->quantity,
                'price' => (float) $holding->price,
                'close' => $close,
                'cost' => $cost,
                'value' => $value,
                'profitLoss' => $profitLoss,
                'percentChange' => $profitLoss !== null && $cost > 0 ? round($profitLoss / $cost * 100, 2) : null,
                'priceDate' => $priceDate,
                'earlierPrice' => $priceDate !== null && $priceDate < $marketDate,
                'open' => $quote ? (float) $quote->open_raw : null,
                'high' => $quote ? (float) $quote->high_raw : null,
                'low' => $quote ? (float) $quote->low_raw : null,
                'marketcap' => $quote?->marketcap,
                'volatility_one_year' => $quote?->volatility_one_year,
                'beta' => $quote?->beta,
            ];
        });

        $cost = round($rows->sum('cost'), 2);
        $value = round($rows->sum('value'), 2);
        $missingPrices = $rows->whereNull('close')->count();
        $profitLoss = $missingPrices === 0 ? round($value - $cost, 2) : null;

        return [
            'date' => $date,
            'marketDate' => $marketDate,
            'holdings' => $rows->values()->all(),
            'summary' => [
                'cost' => $cost,
                'value' => $value,
                'profitLoss' => $profitLoss,
                'percentChange' => $profitLoss !== null && $cost > 0 ? round($profitLoss / $cost * 100, 2) : null,
                'stocks' => $rows->pluck('symbol')->unique()->count(),
                'lots' => $rows->count(),
                'missingPrices' => $missingPrices,
                'earlierPrices' => $rows->where('earlierPrice', true)->count(),
            ],
        ];
    }

    /**
     * Binding each symbol lets MySQL seek to the selected date in the (symbol, date) index.
     * Each lookup stops at the first positive raw close. All lookups share one database request.
     *
     * @param  Collection<int, string>  $symbols
     * @return Collection<string, BacktestNseInstrumentPrice>
     */
    private function latestQuotes(Collection $symbols, string $date): Collection
    {
        $quotes = null;

        foreach ($symbols as $symbol) {
            $lookup = BacktestNseInstrumentPrice::query()
                ->forceIndex('bnip_symbol_date_index')
                ->select(['name', 'series', 'date', 'close_raw', 'open_raw', 'high_raw', 'low_raw', 'marketcap', 'volatility_one_year', 'beta'])
                ->selectRaw('? as holding_symbol', [$symbol])
                ->where('symbol', $symbol)
                ->where('date', '<=', $date)
                ->where('close_raw', '>', 0)
                ->orderByDesc('date')
                ->limit(1);

            if ($quotes === null) {
                $quotes = $lookup;
            } else {
                $quotes->unionAll($lookup);
            }
        }

        return $quotes?->get()->keyBy('holding_symbol') ?? collect();
    }
}
