<?php

namespace App\Actions\AdminProcess;

use App\Enums\AdminProcessRunStatusEnum;
use App\Enums\AdminProcessStepStatusEnum;
use App\Jobs\RunAdminProcessStepJob;
use App\Models\AdminProcessRun;
use App\Models\AdminProcessStep;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QueueAdminProcessStepAction
{
    public function execute(AdminProcessRun $run, AdminProcessStep $step): void
    {
        DB::transaction(function () use ($run, $step): void {
            $lockedRun = AdminProcessRun::query()->lockForUpdate()->findOrFail($run->id);
            $lockedStep = AdminProcessStep::query()
                ->where('admin_process_run_id', $lockedRun->id)
                ->lockForUpdate()
                ->findOrFail($step->id);

            if (! in_array($lockedStep->status, [
                AdminProcessStepStatusEnum::Pending,
                AdminProcessStepStatusEnum::Failed,
            ], true)) {
                throw ValidationException::withMessages([
                    'step' => 'This step cannot be added to the queue in its current state.',
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
                    'step' => 'Wait for the current step to finish before you start another step.',
                ]);
            }

            $hasIncompletePriorStep = $lockedRun->steps()
                ->where('position', '<', $lockedStep->position)
                ->where('status', '!=', AdminProcessStepStatusEnum::Completed->value)
                ->exists();

            if ($hasIncompletePriorStep) {
                throw ValidationException::withMessages([
                    'step' => 'Complete all prior steps before you start this step.',
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

            RunAdminProcessStepJob::dispatch($lockedStep)->afterCommit();
        });
    }
}
