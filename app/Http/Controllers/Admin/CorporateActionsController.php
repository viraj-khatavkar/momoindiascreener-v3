<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CorporateActionTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\BacktestNseCorporateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Response;

class CorporateActionsController extends Controller
{
    public function index(Request $request): Response
    {
        $corporateActions = BacktestNseCorporateAction::query()
            ->when($request->search, fn ($q, $search) => $q->where('symbol', 'like', "%{$search}%"))
            ->when($request->type, fn ($q, $type) => $q->where('type', $type))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(100)
            ->withQueryString();

        return inertia('Admin/CorporateActions/Index', [
            'corporateActions' => $corporateActions,
            'filters' => $request->only(['search', 'type']),
            'types' => array_column(CorporateActionTypeEnum::cases(), 'value'),
        ]);
    }

    public function create(): Response
    {
        return inertia('Admin/CorporateActions/Create', [
            'types' => array_column(CorporateActionTypeEnum::cases(), 'value'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'symbol' => ['required', 'string', 'max:255'],
            'series' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::enum(CorporateActionTypeEnum::class)],
            'description' => ['nullable', 'string', 'max:255'],
            'ratio' => ['nullable', 'string', 'max:255'],
            'dividend' => ['nullable', 'numeric'],
            'dividend_adjustment_factor' => ['nullable', 'numeric'],
            'price_adjustment_factor' => ['nullable', 'numeric'],
        ]);

        $validated['symbol'] = Str::upper($validated['symbol']);

        BacktestNseCorporateAction::create($validated);

        return redirect('/admin/corporate-actions')->with('success', 'Corporate action created successfully.');
    }

    public function edit(BacktestNseCorporateAction $corporateAction): Response
    {
        return inertia('Admin/CorporateActions/Edit', [
            'corporateAction' => $corporateAction,
            'types' => array_column(CorporateActionTypeEnum::cases(), 'value'),
        ]);
    }

    public function update(Request $request, BacktestNseCorporateAction $corporateAction): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'symbol' => ['required', 'string', 'max:255'],
            'series' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::enum(CorporateActionTypeEnum::class)],
            'description' => ['nullable', 'string', 'max:255'],
            'ratio' => ['nullable', 'string', 'max:255'],
            'dividend' => ['nullable', 'numeric'],
            'dividend_adjustment_factor' => ['nullable', 'numeric'],
            'price_adjustment_factor' => ['nullable', 'numeric'],
        ]);

        $validated['symbol'] = Str::upper($validated['symbol']);

        $corporateAction->update($validated);

        return redirect('/admin/corporate-actions')->with('success', 'Corporate action updated successfully.');
    }

    public function destroy(BacktestNseCorporateAction $corporateAction): RedirectResponse
    {
        $corporateAction->delete();

        return redirect('/admin/corporate-actions')->with('success', 'Corporate action deleted successfully.');
    }
}
