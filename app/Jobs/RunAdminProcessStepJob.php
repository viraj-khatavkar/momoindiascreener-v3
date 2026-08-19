<?php

namespace App\Jobs;

use App\Actions\AdminProcess\BuildDailyProcessStepsAction;
use App\Enums\AdminProcessRunStatusEnum;
use App\Enums\AdminProcessStepStatusEnum;
use App\Models\AdminProcessOutputChunk;
use App\Models\AdminProcessStep;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Throwable;

class RunAdminProcessStepJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    /**
     * @param  list<array{old_symbol: string, new_symbol: string}>  $symbolChanges
     */
    public function __construct(
        public AdminProcessStep $step,
        public array $symbolChanges = [],
    ) {
        $this->step = $step->withoutRelations();
    }

    public function handle(BuildDailyProcessStepsAction $buildDailyProcessSteps): void
    {
        $step = AdminProcessStep::query()->with('run')->find($this->step->id);

        if (! $step || $step->status !== AdminProcessStepStatusEnum::Queued) {
            return;
        }

        $date = $step->run->process_date->format('Y-m-d');
        $definition = collect($buildDailyProcessSteps->execute($date))
            ->firstWhere('key', $step->key);

        if (! $definition) {
            throw new LogicException("Unknown admin process step: {$step->key}");
        }

        if ($this->symbolChanges !== [] && $step->key !== 'check-instruments') {
            throw new LogicException('Symbol changes can run only with the new instrument check.');
        }

        $step->forceFill([
            'status' => AdminProcessStepStatusEnum::Running,
            'attempts' => $step->attempts + 1,
            'started_at' => now(),
            'completed_at' => null,
            'error_message' => null,
        ])->save();

        if ($step->attempts > 1) {
            $this->writeOutput($step, 'stdout', "\n--- Retry {$step->attempts} ---\n");
        }

        if ($this->symbolChanges !== []) {
            $mappingLabel = count($this->symbolChanges) === 1 ? 'mapping' : 'mappings';
            $this->writeOutput(
                $step,
                'stdout',
                "\n[Applying ".count($this->symbolChanges)." symbol {$mappingLabel}.]\n\n",
            );

            foreach ($this->symbolChanges as $symbolChange) {
                $arguments = [
                    "--old-symbol={$symbolChange['old_symbol']}",
                    "--new-symbol={$symbolChange['new_symbol']}",
                ];

                $this->runCommand(
                    $step,
                    'backtest:change-symbol',
                    $arguments,
                    implode(' ', ['php artisan backtest:change-symbol', ...$arguments]),
                    600,
                );
            }

            $this->writeOutput(
                $step,
                'stdout',
                "[Symbol changes completed. Checking for new instruments again.]\n\n",
            );
        }

        $this->runCommand(
            $step,
            $definition['command'],
            $definition['arguments'],
            $definition['command_line'],
            3540,
        );

        $this->writeOutput($step, 'stdout', "\n[Step completed successfully.]\n");

        DB::transaction(function () use ($step): void {
            $step->forceFill([
                'status' => AdminProcessStepStatusEnum::Completed,
                'completed_at' => now(),
                'error_message' => null,
            ])->save();

            $run = $step->run()->lockForUpdate()->firstOrFail();
            $hasIncompleteStep = $run->steps()
                ->where('status', '!=', AdminProcessStepStatusEnum::Completed->value)
                ->exists();

            $run->forceFill([
                'status' => $hasIncompleteStep
                    ? AdminProcessRunStatusEnum::InProgress
                    : AdminProcessRunStatusEnum::Completed,
                'completed_at' => $hasIncompleteStep ? null : now(),
                'error_message' => null,
            ])->save();
        });
    }

    public function failed(?Throwable $exception): void
    {
        $step = AdminProcessStep::query()->with('run')->find($this->step->id);

        if (! $step || $step->status === AdminProcessStepStatusEnum::Completed) {
            return;
        }

        $message = Str::limit(
            $exception?->getMessage() ?? 'The queue worker could not complete this step.',
            60000,
            '',
        );

        $step->forceFill([
            'status' => AdminProcessStepStatusEnum::Failed,
            'completed_at' => now(),
            'error_message' => $message,
        ])->save();

        $step->run->forceFill([
            'status' => AdminProcessRunStatusEnum::Failed,
            'completed_at' => null,
            'error_message' => $message,
        ])->save();

        $this->writeOutput($step, 'stderr', "\n[Step failed] {$message}\n");
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return [
            "admin-process-run:{$this->step->admin_process_run_id}",
            "admin-process-step:{$this->step->id}",
        ];
    }

    private function writeOutput(AdminProcessStep $step, string $stream, string $output): void
    {
        AdminProcessOutputChunk::query()->create([
            'admin_process_step_id' => $step->id,
            'stream' => $stream,
            'output' => $output,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  list<string>  $arguments
     */
    private function runCommand(
        AdminProcessStep $step,
        string $commandName,
        array $arguments,
        string $commandLine,
        int $timeout,
    ): void {
        $this->writeOutput($step, 'stdout', "$ {$commandLine}\n\n");

        $command = [
            PHP_BINARY,
            base_path('artisan'),
            $commandName,
            ...$arguments,
            '--no-interaction',
            '--no-ansi',
        ];

        $result = Process::path(base_path())
            ->timeout($timeout)
            ->run($command, function (string $type, string $output) use ($step): void {
                if ($output === '') {
                    return;
                }

                $this->writeOutput(
                    $step,
                    $type === 'err' ? 'stderr' : 'stdout',
                    $output,
                );
            });

        $step->forceFill(['exit_code' => $result->exitCode()])->save();

        if ($result->failed()) {
            throw new RuntimeException(
                "The command \"{$commandLine}\" stopped with exit code {$result->exitCode()}.",
            );
        }

        $this->writeOutput($step, 'stdout', "\n");
    }
}
