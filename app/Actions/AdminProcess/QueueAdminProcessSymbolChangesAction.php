<?php

namespace App\Actions\AdminProcess;

use App\Enums\AdminProcessRunStatusEnum;
use App\Enums\AdminProcessStepStatusEnum;
use App\Jobs\RunAdminProcessStepJob;
use App\Models\AdminProcessRun;
use App\Models\AdminProcessStep;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QueueAdminProcessSymbolChangesAction
{
    /**
     * @param  list<array{old_symbol: string, new_symbol: string}>  $symbolChanges
     */
    public function execute(
        AdminProcessRun $run,
        AdminProcessStep $step,
        array $symbolChanges,
    ): void {
        DB::transaction(function () use ($run, $step, $symbolChanges): void {
            $lockedRun = AdminProcessRun::query()->lockForUpdate()->findOrFail($run->id);
            $lockedStep = AdminProcessStep::query()
                ->where('admin_process_run_id', $lockedRun->id)
                ->lockForUpdate()
                ->findOrFail($step->id);

            if ($lockedStep->key !== 'check-instruments') {
                throw ValidationException::withMessages([
                    'symbol_changes' => 'Symbol changes are available only for the new instrument check.',
                ]);
            }

            if (! in_array($lockedStep->status, [
                AdminProcessStepStatusEnum::Completed,
                AdminProcessStepStatusEnum::Failed,
            ], true)) {
                throw ValidationException::withMessages([
                    'symbol_changes' => 'Run the new instrument check before you change a symbol.',
                ]);
            }

            $hasActiveStep = $lockedRun->steps()
                ->whereIn('status', [
                    AdminProcessStepStatusEnum::Queued->value,
                    AdminProcessStepStatusEnum::Running->value,
                ])
                ->exists();

            if ($hasActiveStep) {
                throw ValidationException::withMessages([
                    'symbol_changes' => 'Wait for the current command to finish before you change symbols.',
                ]);
            }

            $hasStartedLaterStep = $lockedRun->steps()
                ->where('position', '>', $lockedStep->position)
                ->where(function (Builder $query): void {
                    $query
                        ->where('status', '!=', AdminProcessStepStatusEnum::Pending->value)
                        ->orWhere('attempts', '>', 0);
                })
                ->exists();

            if ($hasStartedLaterStep) {
                throw ValidationException::withMessages([
                    'symbol_changes' => 'You cannot change symbols after a later checklist step has started.',
                ]);
            }

            $lockedStep->forceFill([
                'status' => AdminProcessStepStatusEnum::Queued,
                'exit_code' => null,
                'started_at' => null,
                'completed_at' => null,
                'error_message' => null,
            ])->save();

            $lockedRun->forceFill([
                'status' => AdminProcessRunStatusEnum::InProgress,
                'started_at' => $lockedRun->started_at ?? now(),
                'completed_at' => null,
                'error_message' => null,
            ])->save();

            RunAdminProcessStepJob::dispatch($lockedStep, $symbolChanges)->afterCommit();
        });
    }
}
