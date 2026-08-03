<?php

namespace App\Http\Controllers;

use App\Enums\CorporateActionTypeEnum;
use App\Models\BacktestNseCorporateAction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Response;

class CorporateActionsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $corporateActions = BacktestNseCorporateAction::query()
            ->select(['id', 'date', 'symbol', 'type', 'description', 'ratio', 'dividend'])
            ->when($request->search, fn ($q, $search) => $q->where('symbol', Str::upper(trim($search))))
            ->when($request->type, fn ($q, $type) => $q->where('type', $type))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return inertia('CorporateActions/Index', [
            'corporateActions' => $corporateActions,
            'filters' => $request->only(['search', 'type']),
            'types' => CorporateActionTypeEnum::resolveDisplayableValueList(),
        ]);
    }
}
