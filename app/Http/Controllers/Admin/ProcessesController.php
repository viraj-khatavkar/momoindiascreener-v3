<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminProcess\BuildDailyProcessStepsAction;
use App\Enums\AdminProcessStepStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\AdminProcessRun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Response;

class ProcessesController extends Controller
{
    public function index(Request $request, BuildDailyProcessStepsAction $buildDailyProcessSteps): Response
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $date = $validated['date'] ?? now()->format('Y-m-d');

        $requiredFiles = collect($buildDailyProcessSteps->requiredFiles())
            ->map(fn (array $file): array => [
                ...$file,
                'filename' => $file['key'].'.csv',
                'available' => Storage::disk('local')->exists("uploads/{$date}/{$file['key']}.csv"),
            ])
            ->values();

        $existingRun = AdminProcessRun::query()
            ->with('user:id,name')
            ->withCount([
                'steps',
                'steps as completed_steps_count' => fn ($query) => $query
                    ->where('status', AdminProcessStepStatusEnum::Completed->value),
            ])
            ->where('process_date', $date)
            ->first();

        $recentRuns = AdminProcessRun::query()
            ->with('user:id,name')
            ->withCount([
                'steps',
                'steps as completed_steps_count' => fn ($query) => $query
                    ->where('status', AdminProcessStepStatusEnum::Completed->value),
            ])
            ->latest('process_date')
            ->limit(15)
            ->get()
            ->map(fn (AdminProcessRun $run): array => $this->runSummary($run));

        return inertia('Admin/Processes/Index', [
            'selectedDate' => $date,
            'requiredFiles' => $requiredFiles,
            'allFilesAvailable' => $requiredFiles->every('available'),
            'existingRun' => $existingRun ? $this->runSummary($existingRun) : null,
            'recentRuns' => $recentRuns,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function runSummary(AdminProcessRun $run): array
    {
        return [
            'id' => $run->id,
            'process_date' => $run->process_date->format('Y-m-d'),
            'status' => $run->status->value,
            'completed_steps' => $run->completed_steps_count,
            'total_steps' => $run->steps_count,
            'created_by' => $run->user?->name,
            'created_at' => $run->created_at?->toIso8601String(),
        ];
    }
}
