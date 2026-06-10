<?php

namespace App\Actions\Backtest;

use App\Enums\BacktestStatusEnum;
use App\Jobs\RunBacktestJob;
use App\Models\Backtest;

class StartBacktestRunAction
{
    public function execute(Backtest $backtest): void
    {
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
    }
}
