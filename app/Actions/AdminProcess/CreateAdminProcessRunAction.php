<?php

namespace App\Actions\AdminProcess;

use App\Enums\AdminProcessRunStatusEnum;
use App\Enums\AdminProcessStepStatusEnum;
use App\Models\AdminProcessRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateAdminProcessRunAction
{
    public function __construct(private BuildDailyProcessStepsAction $buildDailyProcessSteps) {}

    public function execute(User $user, string $date): AdminProcessRun
    {
        return DB::transaction(function () use ($user, $date): AdminProcessRun {
            $run = AdminProcessRun::query()->firstOrCreate(
                ['process_date' => $date],
                [
                    'user_id' => $user->id,
                    'status' => AdminProcessRunStatusEnum::Pending,
                ],
            );

            if ($run->wasRecentlyCreated) {
                $run->steps()->createMany(
                    collect($this->buildDailyProcessSteps->execute($date))
                        ->values()
                        ->map(fn (array $step, int $index): array => [
                            ...$step,
                            'position' => $index + 1,
                            'status' => AdminProcessStepStatusEnum::Pending,
                        ])
                        ->all(),
                );
            }

            return $run->load(['user', 'steps']);
        });
    }
}
