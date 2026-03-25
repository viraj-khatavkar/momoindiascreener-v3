<?php

namespace App\Jobs;

use App\Actions\Backtest\CalculateBacktestMetricsAction;
use App\Actions\Backtest\RunBacktestAction;
use App\Enums\BacktestStatusEnum;
use App\Models\Backtest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunBacktestJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public Backtest $backtest) {}

    public function handle(RunBacktestAction $runAction, CalculateBacktestMetricsAction $metricsAction): void
    {
        try {
            $this->backtest->update([
                'status' => BacktestStatusEnum::Running,
                'started_at' => now(),
                'progress' => 0,
                'error_message' => null,
            ]);

            $runAction->execute($this->backtest);
            $metricsAction->execute($this->backtest);

            $this->backtest->update([
                'status' => BacktestStatusEnum::Completed,
                'completed_at' => now(),
                'progress' => 100,
            ]);
        } catch (\Throwable $e) {
            $this->backtest->update([
                'status' => BacktestStatusEnum::Failed,
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
