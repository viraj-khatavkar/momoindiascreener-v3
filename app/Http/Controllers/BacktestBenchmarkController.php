<?php

namespace App\Http\Controllers;

use App\Actions\Backtest\LoadBenchmarkSeriesAction;
use App\Models\Backtest;
use Illuminate\Http\Request;

class BacktestBenchmarkController extends Controller
{
    public function __invoke(Request $request, Backtest $backtest, LoadBenchmarkSeriesAction $loadBenchmark)
    {
        if ($request->user()->cannot('view', $backtest)) {
            abort(404);
        }

        $slug = $request->validate([
            'slug' => ['required', 'string'],
        ])['slug'];

        return response()->json($loadBenchmark->execute($backtest, $slug));
    }
}
