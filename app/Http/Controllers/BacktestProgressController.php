<?php

namespace App\Http\Controllers;

use App\Models\Backtest;
use Illuminate\Http\Request;

class BacktestProgressController extends Controller
{
    public function __invoke(Request $request, Backtest $backtest)
    {
        if ($request->user()->cannot('view', $backtest)) {
            abort(404);
        }

        return response()->json([
            'status' => $backtest->status->value,
            'progress' => $backtest->progress,
        ]);
    }
}
