<?php

namespace App\Http\Controllers;

use App\Actions\QueryNseIndexConstituentsAction;
use App\Http\Requests\NseIndexConstituentsRequest;
use App\Models\BacktestNseInstrumentPrice;
use App\Models\NseIndex;
use Inertia\Response;

class NseIndexConstituentsController extends Controller
{
    public function __invoke(NseIndexConstituentsRequest $request, QueryNseIndexConstituentsAction $queryConstituents): Response
    {
        $index = $request->index();
        $filters = $request->filters();
        ['date' => $date, 'query' => $query] = $queryConstituents->execute($index, $filters);
        $filters['date'] = $date;
        $constituents = $query->paginate(50)->appends($filters)
            ->through(fn (BacktestNseInstrumentPrice $price): array => [
                ...$price->only(['symbol', 'name', 'close_adjusted', 'marketcap', 'price_to_earnings', 'absolute_return_one_year', 'beta']),
                'url' => route('instruments.show', ['symbol' => $price->symbol], false),
            ]);

        return inertia('NseIndexConstituents', [
            'indexName' => $index->label(),
            'slug' => $index->slug(),
            'date' => $date,
            'constituents' => $constituents,
            'filters' => $filters,
            'listUrl' => route('nse-index.constituents.index', ['slug' => $index->slug()], false),
            'exportUrl' => route('nse-index.constituents.export', ['slug' => $index->slug(), ...$filters], false),
            'indexUrl' => NseIndex::query()->where('slug', $index->slug())->exists()
                ? route('nse-index.show', ['slug' => $index->slug()], false) : null,
            'allIndicesUrl' => route('indices.index', absolute: false),
        ]);
    }
}
