<?php

namespace App\Jobs;

use App\Actions\Backtest\BacktestNavSourceAction;
use App\Actions\Backtest\CalculateBacktestMetricsAction;
use App\Actions\Backtest\RunBacktestAction;
use App\Actions\Backtest\StoreMarketCapAllocationAction;
use App\Enums\BacktestStatusEnum;
use App\Models\Backtest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class RunBacktestJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    private ?string $startedAt = null;

    private ?int $dependentBacktestId = null;

    private ?string $dependentStartedAt = null;

    public function __construct(public Backtest $backtest, public ?string $throughDate = null, ?Backtest $dependent = null)
    {
        $this->startedAt = $backtest->getRawOriginal('started_at');
        $this->dependentBacktestId = $dependent?->getKey();
        $this->dependentStartedAt = $dependent?->getRawOriginal('started_at');
    }

    public function handle(RunBacktestAction $runAction, CalculateBacktestMetricsAction $metricsAction, StoreMarketCapAllocationAction $storeAllocation, BacktestNavSourceAction $navSource): void
    {
        try {
            $this->backtest->update([
                'status' => BacktestStatusEnum::Running,
                'started_at' => $this->startedAt ?? now(),
                'completed_at' => null,
                'progress' => 1,
                'error_message' => null,
            ]);

            $this->validateData($runAction, $navSource);

            DB::transaction(function (): void {
                $this->backtest->trades()->delete();
                $this->backtest->dailySnapshots()->delete();
                $this->backtest->summaryMetrics()->delete();
            });

            if ($this->throughDate === null) {
                $runAction->execute($this->backtest);
            } else {
                $runAction->execute($this->backtest, $this->throughDate);
            }
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

    private function validateData(RunBacktestAction $runAction, BacktestNavSourceAction $navSource): void
    {
        $dependent = null;
        if ($this->dependentBacktestId !== null) {
            $dependent = Backtest::query()->where('status', BacktestStatusEnum::Running)
                ->where('started_at', $this->dependentStartedAt)->where('progress', 0)
                ->findOrFail($this->dependentBacktestId);

            if ($navSource->resolve($dependent)?->id !== $this->backtest->id) {
                throw ValidationException::withMessages([
                    'cash_call_backtest_id' => 'The source selection has changed. Save and run the dependent backtest again.',
                ]);
            }

            $this->throughDate ??= $navSource->latestDate($dependent);
            $runAction->validateDataAvailability($dependent, $this->throughDate, validateSourceNav: false);
            if ($this->throughDate === null) {
                throw ValidationException::withMessages(['start_date' => 'No trading dates are available for the dependent backtest.']);
            }
        }

        if ($this->throughDate === null) {
            $runAction->validateDataAvailability($this->backtest);
        } else {
            $runAction->validateDataAvailability($this->backtest, $this->throughDate);
        }

        if ($dependent !== null) {
            $navSource->validateHistory($dependent, $this->backtest, $this->throughDate);
            if ($this->chained === []) {
                $this->appendToChain(new self($dependent, $this->throughDate));
            }
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

        if ($this->dependentBacktestId !== null) {
            Backtest::query()->whereKey($this->dependentBacktestId)
                ->where('status', BacktestStatusEnum::Running)
                ->where('started_at', $this->dependentStartedAt)
                ->where('progress', 0)
                ->update([
                    'status' => BacktestStatusEnum::Failed,
                    'completed_at' => null,
                    'error_message' => Str::limit('The source backtest failed. The dependent backtest did not run. '.($exception?->getMessage() ?? 'The source worker stopped.'), 60000, ''),
                ]);
        }
    }
}
