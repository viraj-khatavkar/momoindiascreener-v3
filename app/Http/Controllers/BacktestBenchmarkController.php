<?php

namespace App\Http\Controllers;

use App\Models\Backtest;
use App\Models\NseIndex;
use Illuminate\Http\Request;

class BacktestBenchmarkController extends Controller
{
    public function __invoke(Request $request, Backtest $backtest)
    {
        if ($request->user()->cannot('view', $backtest)) {
            abort(404);
        }

        $slug = $request->validate([
            'slug' => ['required', 'string'],
        ])['slug'];

        $firstSnapshot = $backtest->dailySnapshots()->orderBy('date')->first();
        $lastSnapshot = $backtest->dailySnapshots()->orderBy('date', 'desc')->first();

        if (! $firstSnapshot || ! $lastSnapshot) {
            return response()->json([]);
        }

        $startDate = $firstSnapshot->date->format('Y-m-d');
        $endDate = $lastSnapshot->date->format('Y-m-d');

        $indexData = NseIndex::query()
            ->where('slug', $slug)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->select('date', 'close')
            ->distinct()
            ->get();

        if ($indexData->isEmpty()) {
            return response()->json([]);
        }

        $startClose = (float) $indexData->first()->close;

        if ($startClose <= 0) {
            return response()->json([]);
        }

        $benchmarkData = $indexData->map(fn ($row) => [
            'date' => $row->date->format('Y-m-d'),
            'nav' => round(100.0 * ((float) $row->close / $startClose), 6),
        ]);

        return response()->json($benchmarkData);
    }
}
