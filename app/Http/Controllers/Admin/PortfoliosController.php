<?php

namespace App\Http\Controllers\Admin;

use App\Actions\BuildPortfolioValuationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\PortfolioDateRequest;
use App\Http\Requests\SavePortfolioRequest;
use App\Models\BacktestNseCorporateAction;
use App\Models\Portfolio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;

class PortfoliosController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Portfolio::class);
        $validated = $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $search = $validated['search'] ?? '';
        $portfolios = $request->user()->portfolios()
            ->select(['id', 'name', 'updated_at'])
            ->withCount('holdings')
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Portfolio $portfolio): array => [
                'id' => $portfolio->id,
                'name' => $portfolio->name,
                'holdings_count' => $portfolio->holdings_count,
                'updated_at' => $portfolio->updated_at->toIso8601String(),
                'editUrl' => route('admin.portfolios.edit', $portfolio),
            ]);

        return inertia('Admin/Portfolios/Index', [
            'portfolios' => $portfolios,
            'filters' => ['search' => $search],
            'portfolioRoutes' => [
                'index' => route('admin.portfolios.index'),
                'create' => route('admin.portfolios.create'),
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Portfolio::class);

        return inertia('Admin/Portfolios/Create', [
            'portfolioRoutes' => [
                'index' => route('admin.portfolios.index'),
                'store' => route('admin.portfolios.store'),
            ],
        ]);
    }

    public function store(SavePortfolioRequest $request): RedirectResponse
    {
        $portfolio = $request->user()->portfolios()->create($request->validated());

        return to_route('admin.portfolios.edit', $portfolio)->with('success', 'Portfolio created.');
    }

    public function show(PortfolioDateRequest $request, Portfolio $portfolio): RedirectResponse
    {
        return to_route('admin.portfolios.edit', ['portfolio' => $portfolio, ...$request->validated()]);
    }

    public function edit(PortfolioDateRequest $request, Portfolio $portfolio, BuildPortfolioValuationAction $buildValuation): Response
    {
        $valuation = $buildValuation->execute($portfolio, $request->validated('date'));
        $valuation['holdings'] = array_map(fn (array $holding): array => [
            ...$holding,
            'updateUrl' => route('admin.portfolios.holdings.update', [$portfolio, $holding['id']]),
            'destroyUrl' => route('admin.portfolios.holdings.destroy', [$portfolio, $holding['id']]),
        ], $valuation['holdings']);

        $corporateActions = BacktestNseCorporateAction::query()
            ->whereIn('symbol', array_unique(array_column($valuation['holdings'], 'symbol')))
            ->where('date', $valuation['date'])
            ->orderBy('symbol')
            ->orderBy('id')
            ->get(['id', 'symbol', 'date', 'type', 'description', 'ratio', 'dividend'])
            ->map(fn (BacktestNseCorporateAction $action): array => [
                'id' => $action->id,
                'symbol' => $action->symbol,
                'date' => $action->date->toDateString(),
                'type' => $action->type?->value,
                'description' => $action->description,
                'ratio' => $action->ratio,
                'dividend' => $action->dividend,
                'editUrl' => route('corporate-actions.edit', $action),
            ]);

        return inertia('Admin/Portfolios/Edit', [
            'portfolio' => $portfolio->only(['id', 'name']),
            'valuation' => $valuation,
            'corporateActions' => $corporateActions,
            'today' => now()->toDateString(),
            'portfolioRoutes' => [
                'index' => route('admin.portfolios.index'),
                'edit' => route('admin.portfolios.edit', $portfolio),
                'update' => route('admin.portfolios.update', $portfolio),
                'addHolding' => route('admin.portfolios.holdings.store', $portfolio),
                'download' => route('admin.portfolios.download', ['portfolio' => $portfolio, 'date' => $valuation['date']]),
                'symbols' => route('admin.portfolios.symbols'),
                'quote' => route('admin.portfolios.quote'),
            ],
        ]);
    }

    public function update(SavePortfolioRequest $request, Portfolio $portfolio): RedirectResponse
    {
        $portfolio->update($request->validated());

        return back()->with('success', 'Portfolio name updated.');
    }
}
