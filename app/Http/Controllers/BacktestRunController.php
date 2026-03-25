<?php

namespace App\Http\Controllers;

use App\Enums\BacktestStatusEnum;
use App\Jobs\RunBacktestJob;
use App\Models\Backtest;
use Illuminate\Http\Request;

class BacktestRunController extends Controller
{
    public function __invoke(Request $request, Backtest $backtest)
    {
        if ($request->user()->cannot('run', $backtest)) {
            abort(404);
        }

        // Clear old results if re-running
        $backtest->trades()->delete();
        $backtest->dailySnapshots()->delete();
        $backtest->summaryMetrics()->delete();

        $backtest->update([
            'status' => BacktestStatusEnum::Running,
            'started_at' => now(),
            'progress' => 0,
            'error_message' => null,
        ]);

        RunBacktestJob::dispatch($backtest);

        return redirect()->to('/backtests/'.$backtest->getKey())
            ->with('success', 'Backtest queued for execution');
    }
}
