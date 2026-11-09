<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PortfolioDateRequest;
use App\Http\Requests\SavePortfolioHoldingRequest;
use App\Models\Portfolio;
use App\Models\PortfolioHolding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class PortfolioHoldingsController extends Controller
{
    public function store(SavePortfolioHoldingRequest $request, Portfolio $portfolio): RedirectResponse
    {
        $portfolio->holdings()->create($request->safe()->only(['symbol', 'quantity', 'price']));

        return to_route('admin.portfolios.edit', ['portfolio' => $portfolio, 'date' => $request->validated('date')])
            ->with('success', 'Holding added.');
    }

    public function update(SavePortfolioHoldingRequest $request, Portfolio $portfolio, PortfolioHolding $holding): RedirectResponse
    {
        $holding->update($request->safe()->only(['symbol', 'quantity', 'price']));

        return to_route('admin.portfolios.edit', ['portfolio' => $portfolio, 'date' => $request->validated('date')])
            ->with('success', 'Holding updated.');
    }

    public function destroy(PortfolioDateRequest $request, Portfolio $portfolio, PortfolioHolding $holding): RedirectResponse
    {
        Gate::authorize('update', $portfolio);
        $holding->delete();

        return to_route('admin.portfolios.edit', ['portfolio' => $portfolio, 'date' => $request->validated('date')])
            ->with('success', 'Holding removed.');
    }
}
