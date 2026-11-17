<?php

namespace App\Http\Controllers;

use App\Actions\QueryIndustryStocksAction;
use App\Http\Requests\IndustryStocksRequest;
use App\Models\BacktestNseInstrument;
use App\Models\Industry;
use Inertia\Response;

class IndustryViewController extends Controller
{
    public function __invoke(IndustryStocksRequest $request, Industry $industry, QueryIndustryStocksAction $queryStocks): Response
    {
        $filters = $request->filters();
        ['date' => $date, 'query' => $query] = $queryStocks->execute($industry, $filters);
        $stocks = $query->paginate(50)->appends($filters)
            ->through(fn (BacktestNseInstrument $stock): array => [
                ...$stock->only(['symbol', 'name', 'close_adjusted', 'marketcap', 'price_to_earnings', 'absolute_return_one_year', 'beta']),
                'url' => $stock->quoted_symbol !== null ? route('instruments.show', ['symbol' => $stock->symbol], false) : null,
            ]);

        return inertia('IndustryView', [
            'industry' => $industry->only(['name', 'slug']),
            'date' => $date,
            'stocks' => $stocks,
            'filters' => $filters,
            'listUrl' => route('industries.show', ['industry' => $industry->slug], false),
        ]);
    }
}
