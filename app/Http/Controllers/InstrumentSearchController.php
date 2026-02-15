<?php

namespace App\Http\Controllers;

use App\Models\BacktestNseInstrumentPrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstrumentSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:50'],
        ]);

        $query = $request->string('q')->upper()->toString();

        $latestDate = BacktestNseInstrumentPrice::query()
            ->where('is_nifty_allcap', true)
            ->orderBy('date', 'desc')
            ->limit(1)
            ->value('date');

        $instruments = BacktestNseInstrumentPrice::query()
            ->where('date', $latestDate)
            ->where(function ($q) use ($query) {
                $q->where('symbol', 'LIKE', $query.'%')
                    ->orWhere('name', 'LIKE', $query.'%');
            })
            ->orderByRaw('CASE WHEN symbol LIKE ? THEN 0 ELSE 1 END', [$query.'%'])
            ->orderBy('symbol')
            ->limit(10)
            ->get(['symbol', 'name']);

        return response()->json($instruments);
    }
}
