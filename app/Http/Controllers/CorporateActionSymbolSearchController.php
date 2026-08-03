<?php

namespace App\Http\Controllers;

use App\Models\BacktestNseCorporateAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CorporateActionSymbolSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:50'],
        ]);

        $query = $request->string('q')->trim()->upper()->toString();

        $symbols = BacktestNseCorporateAction::query()
            ->where('symbol', 'like', $query.'%')
            ->distinct()
            ->orderBy('symbol')
            ->limit(10)
            ->pluck('symbol');

        return response()->json($symbols);
    }
}
