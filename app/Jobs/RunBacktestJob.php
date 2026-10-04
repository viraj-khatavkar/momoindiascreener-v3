<?php

namespace App\Jobs;

use App\Actions\Backtest\CalculateBacktestMetricsAction;
use App\Actions\Backtest\RunBacktestAction;
use App\Actions\Backtest\StoreMarketCapAllocationAction;
use App\Enums\BacktestStatusEnum;
use App\Models\Backtest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class RunBacktestJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    private ?string $startedAt = null;

    public function __construct(public Backtest $backtest)
    {
        $this->startedAt = $backtest->getRawOriginal('started_at');
    }

    public function handle(RunBacktestAction $runAction, CalculateBacktestMetricsAction $metricsAction, StoreMarketCapAllocationAction $storeAllocation): void
    {
        try {
            $this->backtest->update([
                'status' => BacktestStatusEnum::Running,
                'started_at' => $this->startedAt ?? now(),
                'completed_at' => null,
                'progress' => 1,
                'error_message' => null,
            ]);

            $runAction->validateDataAvailability($this->backtest);
            $runAction->execute($this->backtest);
            $metricsAction->execute($this->backtest);
            $this->backtest->update(['progress' => 98]);
            $storeAllocation->execute($this->backtest);

            $this->backtest->update([
                'status' => BacktestStatusEnum::Completed,
                'completed_at' => now(),
                'progress' => 100,
            ]);
        } catch (Throwable $e) {
            $this->failed($e);
            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $query = Backtest::query()
            ->whereKey($this->backtest->getKey())
            ->where('status', BacktestStatusEnum::Running);

        if ($this->startedAt !== null) {
            $query->where('started_at', $this->startedAt);
        }

        $query->update([
            'status' => BacktestStatusEnum::Failed,
            'completed_at' => null,
            'error_message' => Str::limit($exception?->getMessage() ?? 'The queue worker could not complete this backtest.', 60000, ''),
        ]);
    }
}
