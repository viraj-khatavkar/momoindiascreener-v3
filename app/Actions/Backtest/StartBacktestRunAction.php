<?php

namespace App\Actions\Backtest;

use App\Enums\BacktestStatusEnum;
use App\Jobs\RunBacktestJob;
use App\Models\Backtest;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class StartBacktestRunAction
{
    public function __construct(private BacktestNavSourceAction $navSource) {}

    /** @param array<string, mixed>|null $settings */
    public function execute(Backtest $backtest, ?array $settings = null): void
    {
        $job = DB::transaction(function () use ($backtest, $settings): RunBacktestJob {
            User::query()->lockForUpdate()->findOrFail($backtest->user_id);
            $current = Backtest::query()->lockForUpdate()->findOrFail($backtest->id);
            $this->navSource->assertNotInUse($current);

            $active = Backtest::query()->where('user_id', $current->user_id)
                ->where('status', BacktestStatusEnum::Running)->lockForUpdate()->first(['id']);
            if ($active !== null) {
                throw ValidationException::withMessages([
                    'run' => 'A backtest is already running. Wait for it to finish before selecting Save and Run.',
                ]);
            }

            $backtest->setRawAttributes($current->getAttributes(), true);
            if ($settings !== null) {
                $backtest->fill($settings);
                if (collect($backtest->getDirty())->except(['name'])->isNotEmpty()) {
                    $backtest->settings_changed_at = now();
                }
                $backtest->save();
            }

            $source = $this->navSource->resolve($backtest);
            if ($source !== null) {
                $source = Backtest::query()->lockForUpdate()->findOrFail($source->id);
                $this->navSource->assertNotInUse($source);
                $this->prepare($source);
            }
            $this->prepare($backtest);

            return $source === null
                ? new RunBacktestJob($backtest)
                : new RunBacktestJob($source, dependent: $backtest);
        });

        try {
            Bus::dispatch($job);
        } catch (Throwable $exception) {
            $job->failed($exception);
            throw $exception;
        }
    }

    private function prepare(Backtest $backtest): void
    {
        $backtest->update([
            'status' => BacktestStatusEnum::Running,
            'started_at' => now(),
            'completed_at' => null,
            'progress' => 0,
            'error_message' => null,
        ]);
    }
}
