<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BacktestNseInstrument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortfolioSymbolSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'max:50']]);
        $search = $request->string('q')->trim()->toString();

        return response()->json(BacktestNseInstrument::query()
            ->where(fn ($query) => $query
                ->where('symbol', 'like', $search.'%')
                ->orWhere('name', 'like', '%'.$search.'%'))
            ->orderBy('symbol')
            ->limit(10)
            ->pluck('symbol'));
    }
}
