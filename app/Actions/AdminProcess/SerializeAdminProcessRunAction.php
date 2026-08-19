<?php

namespace App\Actions\AdminProcess;

use App\Enums\AdminProcessStepStatusEnum;
use App\Models\AdminProcessRun;
use App\Models\AdminProcessStep;
use Illuminate\Support\Str;

class SerializeAdminProcessRunAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(AdminProcessRun $run): array
    {
        $run->loadMissing(['user', 'steps']);

        $firstIncompleteStep = $run->steps->first(
            fn (AdminProcessStep $step): bool => $step->status !== AdminProcessStepStatusEnum::Completed,
        );
        $hasActiveStep = $run->steps->contains(
            fn (AdminProcessStep $step): bool => in_array($step->status, [
                AdminProcessStepStatusEnum::Queued,
                AdminProcessStepStatusEnum::Running,
            ], true),
        );
        $hasStartedStepAfterInstrumentCheck = $run->steps->contains(
            fn (AdminProcessStep $step): bool => $step->position > 1
                && ($step->status !== AdminProcessStepStatusEnum::Pending || $step->attempts > 0),
        );

        return [
            'id' => $run->id,
            'process_date' => $run->process_date->format('Y-m-d'),
            'status' => $run->status->value,
            'started_at' => $run->started_at?->toIso8601String(),
            'completed_at' => $run->completed_at?->toIso8601String(),
            'error_message' => $run->error_message,
            'created_by' => $run->user?->name,
            'completed_steps' => $run->steps->where('status', AdminProcessStepStatusEnum::Completed)->count(),
            'total_steps' => $run->steps->count(),
            'steps' => $run->steps->map(function (AdminProcessStep $step) use (
                $firstIncompleteStep,
                $hasActiveStep,
                $hasStartedStepAfterInstrumentCheck,
            ): array {
                $canRun = ! $hasActiveStep
                    && $firstIncompleteStep?->is($step)
                    && in_array($step->status, [
                        AdminProcessStepStatusEnum::Pending,
                        AdminProcessStepStatusEnum::Failed,
                    ], true);

                $durationEnd = $step->completed_at
                    ?? ($step->started_at ? now() : null);

                return [
                    'id' => $step->id,
                    'position' => $step->position,
                    'key' => $step->key,
                    'name' => $step->name,
                    'description' => $step->description,
                    'command_line' => $step->command_line,
                    'is_preview' => $step->is_preview,
                    'is_apply' => Str::startsWith($step->key, 'apply-'),
                    'status' => $step->status->value,
                    'attempts' => $step->attempts,
                    'exit_code' => $step->exit_code,
                    'started_at' => $step->started_at?->toIso8601String(),
                    'completed_at' => $step->completed_at?->toIso8601String(),
                    'duration_seconds' => $step->started_at && $durationEnd
                        ? $step->started_at->diffInSeconds($durationEnd)
                        : null,
                    'error_message' => $step->error_message,
                    'can_run' => $canRun,
                    'can_manage_symbol_changes' => $step->key === 'check-instruments'
                        && ! $hasActiveStep
                        && ! $hasStartedStepAfterInstrumentCheck
                        && in_array($step->status, [
                            AdminProcessStepStatusEnum::Completed,
                            AdminProcessStepStatusEnum::Failed,
                        ], true),
                ];
            })->values()->all(),
        ];
    }
}
