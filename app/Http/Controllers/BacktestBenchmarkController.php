<?php

namespace App\Http\Controllers;

use App\Actions\Backtest\LoadBenchmarkSeriesAction;
use App\Models\Backtest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BacktestBenchmarkController extends Controller
{
    public function __invoke(Request $request, Backtest $backtest, LoadBenchmarkSeriesAction $loadBenchmark)
    {
        $ability = $request->routeIs('public-backtests.benchmark') ? 'viewPublic' : 'view';

        abort_unless(Gate::allows($ability, $backtest), 404);

        $slug = $request->validate([
            'slug' => ['required', 'string'],
        ])['slug'];

        return response()->json($loadBenchmark->execute($backtest, $slug));
    }
}
