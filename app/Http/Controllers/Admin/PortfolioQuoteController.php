<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BacktestNseInstrumentPrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortfolioQuoteController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'symbol' => ['required', 'string', 'max:50'],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);

        $quote = BacktestNseInstrumentPrice::query()
            ->where('symbol', $request->string('symbol')->trim()->upper()->toString())
            ->where('date', '<=', $request->input('date'))
            ->where('close_raw', '>', 0)
            ->orderByDesc('date')
            ->first(['symbol', 'name', 'close_raw', 'date']);

        return response()->json(['quote' => $quote ? [
            'symbol' => $quote->symbol,
            'name' => $quote->name,
            'close' => (float) $quote->close_raw,
            'date' => $quote->date->toDateString(),
        ] : null]);
    }
}
