<?php

use App\Actions\AdminProcess\BuildDailyProcessStepsAction;
use App\Actions\AdminProcess\CreateAdminProcessRunAction;
use App\Enums\AdminProcessRunStatusEnum;
use App\Enums\AdminProcessStepStatusEnum;
use App\Jobs\ProcessDailyDataForBacktestJob;
use App\Jobs\RunAdminProcessStepJob;
use App\Models\AdminProcessOutputChunk;
use App\Models\AdminProcessRun;
use App\Models\User;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    $this->admin = User::factory()->create(['is_admin' => true]);
});

it('redirects guests and hides the page from non-admin users', function () {
    $this->get('/admin/processes')->assertRedirect('/login');

    $this->actingAs(User::factory()->create())
        ->get('/admin/processes')
        ->assertNotFound();
});

it('shows the required file state for the selected date', function () {
    Storage::disk('local')->put('uploads/2022-02-04/bhavcopy.csv', 'data');
    Storage::disk('local')->put('uploads/2022-02-04/etf.csv', 'data');

    $this->actingAs($this->admin)
        ->get('/admin/processes?date=2022-02-04')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Processes/Index')
            ->where('selectedDate', '2022-02-04')
            ->where('allFilesAvailable', false)
            ->has('requiredFiles', 3)
            ->where('requiredFiles.0.key', 'bhavcopy')
            ->where('requiredFiles.0.available', true)
            ->where('requiredFiles.1.key', 'corporate_actions')
            ->where('requiredFiles.1.available', false)
            ->where('requiredFiles.2.key', 'etf')
            ->where('requiredFiles.2.available', true)
        );
});

it('does not create a run when a required file is missing', function () {
    Storage::disk('local')->put('uploads/2022-02-04/bhavcopy.csv', 'data');

    $this->actingAs($this->admin)
        ->post('/admin/process-runs', ['date' => '2022-02-04'])
        ->assertSessionHasErrors('files');

    expect(AdminProcessRun::count())->toBe(0);
});

it('creates the fixed daily checklist once for a date', function () {
    storeAdminProcessRequiredFiles('2022-02-04');

    $this->actingAs($this->admin)
        ->post('/admin/process-runs', ['date' => '2022-02-04'])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/admin/process-runs/1');

    $run = AdminProcessRun::query()->with('steps')->sole();

    expect($run->process_date->format('Y-m-d'))->toBe('2022-02-04')
        ->and($run->status)->toBe(AdminProcessRunStatusEnum::Pending)
        ->and($run->steps)->toHaveCount(17)
        ->and($run->steps[0]->position)->toBe(1)
        ->and($run->steps[0]->command_line)
        ->toBe('php artisan backtest:import-instruments --omit-create --date=2022-02-04')
        ->and($run->steps[0]->is_preview)->toBeTrue()
        ->and($run->steps[5]->command_line)
        ->toBe('php artisan backtest:calculate-dividend-adjustment-factor --date=2022-02-04 --dry-run')
        ->and($run->steps[6]->key)->toBe('apply-dividend-adjustment-factor')
        ->and($run->steps[6]->command_line)
        ->toBe('php artisan backtest:calculate-dividend-adjustment-factor --date=2022-02-04')
        ->and($run->steps[7]->command_line)
        ->toBe('php artisan backtest:adjust-dividends --date=2022-02-04 --dry-run')
        ->and($run->steps[8]->key)->toBe('apply-dividends')
        ->and($run->steps[8]->command_line)
        ->toBe('php artisan backtest:adjust-dividends --date=2022-02-04')
        ->and($run->steps[9]->command_line)
        ->toBe('php artisan backtest:adjust-corporate-action --date=2022-02-04 --dry-run')
        ->and($run->steps[10]->key)->toBe('apply-corporate-action')
        ->and($run->steps[10]->command_line)
        ->toBe('php artisan backtest:adjust-corporate-action --date=2022-02-04')
        ->and($run->steps[14]->command_line)
        ->toBe('php artisan backtest:process-daily-data --date=2022-02-04')
        ->and($run->steps[16]->command_line)
        ->toBe('php artisan backtest:copy-instruments --date=2022-02-04');

    $this->actingAs($this->admin)
        ->post('/admin/process-runs', ['date' => '2022-02-04'])
        ->assertSessionHasNoErrors()
        ->assertRedirect("/admin/process-runs/{$run->id}");

    expect(AdminProcessRun::count())->toBe(1)
        ->and($run->steps()->count())->toBe(17);
});

it('enables only the first incomplete step', function () {
    $run = app(CreateAdminProcessRunAction::class)->execute($this->admin, '2022-02-04');

    $this->actingAs($this->admin)
        ->get("/admin/process-runs/{$run->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Processes/Show')
            ->where('processRun.process_date', '2022-02-04')
            ->has('processRun.steps', 17)
            ->where('processRun.steps.0.can_run', true)
            ->where('processRun.steps.0.can_manage_symbol_changes', false)
            ->where('processRun.steps.1.can_run', false)
            ->where('processRun.steps.5.is_preview', true)
            ->where('processRun.steps.5.is_apply', false)
            ->where('processRun.steps.6.is_preview', false)
            ->where('processRun.steps.6.is_apply', true)
        );
});

it('queues several normalized symbol changes and makes the instrument check run again', function () {
    Queue::fake();
    $run = app(CreateAdminProcessRunAction::class)->execute($this->admin, '2022-02-04');
    $instrumentCheck = $run->steps[0];
    $instrumentCheck->update([
        'status' => AdminProcessStepStatusEnum::Completed,
        'attempts' => 1,
        'started_at' => now()->subSecond(),
        'completed_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->get("/admin/process-runs/{$run->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('processRun.steps.0.can_manage_symbol_changes', true)
            ->where('processRun.steps.1.can_run', true)
        );

    $this->actingAs($this->admin)
        ->post("/admin/process-runs/{$run->id}/steps/{$instrumentCheck->id}/symbol-changes", [
            'symbol_changes' => [
                ['old_symbol' => ' burgerking ', 'new_symbol' => ' rba '],
                ['old_symbol' => 'infratel', 'new_symbol' => 'industower'],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect("/admin/process-runs/{$run->id}");

    expect($instrumentCheck->fresh()->status)->toBe(AdminProcessStepStatusEnum::Queued)
        ->and($run->fresh()->status)->toBe(AdminProcessRunStatusEnum::InProgress);

    Queue::assertPushed(
        RunAdminProcessStepJob::class,
        fn (RunAdminProcessStepJob $job): bool => $job->step->is($instrumentCheck)
            && $job->symbolChanges === [
                ['old_symbol' => 'BURGERKING', 'new_symbol' => 'RBA'],
                ['old_symbol' => 'INFRATEL', 'new_symbol' => 'INDUSTOWER'],
            ]
            && $job->queue === null,
    );
});

it('validates symbol mappings before it adds them to the queue', function () {
    Queue::fake();
    $run = app(CreateAdminProcessRunAction::class)->execute($this->admin, '2022-02-04');
    $instrumentCheck = $run->steps[0];
    $instrumentCheck->update(['status' => AdminProcessStepStatusEnum::Completed]);

    $this->actingAs($this->admin)
        ->post("/admin/process-runs/{$run->id}/steps/{$instrumentCheck->id}/symbol-changes", [
            'symbol_changes' => [
                ['old_symbol' => 'BURGERKING', 'new_symbol' => 'BURGERKING'],
                ['old_symbol' => 'BURGERKING', 'new_symbol' => 'INVALID SYMBOL'],
            ],
        ])
        ->assertSessionHasErrors([
            'symbol_changes.0.new_symbol',
            'symbol_changes.1.old_symbol',
            'symbol_changes.1.new_symbol',
        ]);

    expect($instrumentCheck->fresh()->status)->toBe(AdminProcessStepStatusEnum::Completed);
    Queue::assertNothingPushed();
});

it('does not change symbols after a later checklist step has started', function () {
    Queue::fake();
    $run = app(CreateAdminProcessRunAction::class)->execute($this->admin, '2022-02-04');
    $instrumentCheck = $run->steps[0];
    $instrumentCheck->update(['status' => AdminProcessStepStatusEnum::Completed]);
    $run->steps[1]->update([
        'status' => AdminProcessStepStatusEnum::Completed,
        'attempts' => 1,
    ]);

    $this->actingAs($this->admin)
        ->post("/admin/process-runs/{$run->id}/steps/{$instrumentCheck->id}/symbol-changes", [
            'symbol_changes' => [
                ['old_symbol' => 'BURGERKING', 'new_symbol' => 'RBA'],
            ],
        ])
        ->assertSessionHasErrors('symbol_changes');

    expect($instrumentCheck->fresh()->status)->toBe(AdminProcessStepStatusEnum::Completed);
    Queue::assertNothingPushed();
});

it('adds apply steps to an unfinished legacy run without losing its progress or output', function () {
    $run = AdminProcessRun::factory()->create([
        'user_id' => $this->admin->id,
        'process_date' => '2022-02-06',
        'status' => AdminProcessRunStatusEnum::InProgress,
    ]);
    $legacySteps = collect(app(BuildDailyProcessStepsAction::class)->execute('2022-02-06'))
        ->reject(fn (array $step): bool => str_starts_with($step['key'], 'apply-'))
        ->values()
        ->map(fn (array $step, int $index): array => [
            ...$step,
            'position' => $index + 1,
            'status' => AdminProcessStepStatusEnum::Pending,
        ]);
    $run->steps()->createMany($legacySteps->all());

    $firstStep = $run->steps()->firstOrFail();
    $firstStep->update(['status' => AdminProcessStepStatusEnum::Completed]);
    $outputChunk = AdminProcessOutputChunk::factory()->create([
        'admin_process_step_id' => $firstStep->id,
        'output' => "Existing output.\n",
    ]);

    $migration = require database_path('migrations/2026_08_16_140211_add_apply_steps_to_unfinished_admin_process_runs.php');
    $migration->up();

    $run->refresh()->load('steps.outputChunks');

    expect($run->steps)->toHaveCount(17)
        ->and($run->steps[0]->id)->toBe($firstStep->id)
        ->and($run->steps[0]->status)->toBe(AdminProcessStepStatusEnum::Completed)
        ->and($run->steps[0]->outputChunks->sole()->id)->toBe($outputChunk->id)
        ->and($run->steps[6]->key)->toBe('apply-dividend-adjustment-factor')
        ->and($run->steps[6]->status)->toBe(AdminProcessStepStatusEnum::Pending)
        ->and($run->steps[8]->key)->toBe('apply-dividends')
        ->and($run->steps[8]->status)->toBe(AdminProcessStepStatusEnum::Pending)
        ->and($run->steps[10]->key)->toBe('apply-corporate-action')
        ->and($run->steps[10]->status)->toBe(AdminProcessStepStatusEnum::Pending);
});

it('adds only the next step to the existing default queue', function () {
    Queue::fake();
    $run = app(CreateAdminProcessRunAction::class)->execute($this->admin, '2022-02-04');
    $firstStep = $run->steps[0];
    $secondStep = $run->steps[1];

    $this->actingAs($this->admin)
        ->post("/admin/process-runs/{$run->id}/steps/{$firstStep->id}/run")
        ->assertSessionHasNoErrors()
        ->assertRedirect("/admin/process-runs/{$run->id}");

    expect($firstStep->fresh()->status)->toBe(AdminProcessStepStatusEnum::Queued)
        ->and($run->fresh()->status)->toBe(AdminProcessRunStatusEnum::InProgress);

    Queue::assertPushed(
        RunAdminProcessStepJob::class,
        fn (RunAdminProcessStepJob $job): bool => $job->step->is($firstStep)
            && $job->queue === null,
    );

    $this->actingAs($this->admin)
        ->post("/admin/process-runs/{$run->id}/steps/{$secondStep->id}/run")
        ->assertSessionHasErrors('step');

    expect($secondStep->fresh()->status)->toBe(AdminProcessStepStatusEnum::Pending);
});

it('runs an Artisan command with an argument array and saves its result', function () {
    Process::fake();
    $run = app(CreateAdminProcessRunAction::class)->execute($this->admin, '2022-02-04');
    $run->update(['status' => AdminProcessRunStatusEnum::InProgress]);
    $step = $run->steps[0];
    $step->update(['status' => AdminProcessStepStatusEnum::Queued]);

    $job = new RunAdminProcessStepJob($step);
    $job->handle(app(BuildDailyProcessStepsAction::class));

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [
        PHP_BINARY,
        base_path('artisan'),
        'backtest:import-instruments',
        '--omit-create',
        '--date=2022-02-04',
        '--no-interaction',
        '--no-ansi',
    ] && $process->path === base_path() && $process->timeout === 3540);

    $savedOutput = $step->outputChunks()->pluck('output')->join('');

    expect($step->fresh()->status)->toBe(AdminProcessStepStatusEnum::Completed)
        ->and($step->fresh()->exit_code)->toBe(0)
        ->and($run->fresh()->status)->toBe(AdminProcessRunStatusEnum::InProgress)
        ->and($savedOutput)->toContain('$ php artisan backtest:import-instruments')
        ->and($savedOutput)->toContain('[Step completed successfully.]');
});

it('runs several symbol changes in order and then checks for new instruments again', function () {
    $invocations = [];
    Process::fake(function (PendingProcess $process) use (&$invocations) {
        $invocations[] = [
            'command' => $process->command,
            'timeout' => $process->timeout,
        ];

        return Process::result(output: "Command output.\n");
    });
    $run = app(CreateAdminProcessRunAction::class)->execute($this->admin, '2022-02-04');
    $run->update(['status' => AdminProcessRunStatusEnum::InProgress]);
    $step = $run->steps[0];
    $step->update([
        'status' => AdminProcessStepStatusEnum::Queued,
        'attempts' => 1,
    ]);

    $job = new RunAdminProcessStepJob($step, [
        ['old_symbol' => 'BURGERKING', 'new_symbol' => 'RBA'],
        ['old_symbol' => 'INFRATEL', 'new_symbol' => 'INDUSTOWER'],
    ]);
    $job->handle(app(BuildDailyProcessStepsAction::class));

    expect($invocations)->toBe([
        [
            'command' => [
                PHP_BINARY,
                base_path('artisan'),
                'backtest:change-symbol',
                '--old-symbol=BURGERKING',
                '--new-symbol=RBA',
                '--no-interaction',
                '--no-ansi',
            ],
            'timeout' => 600,
        ],
        [
            'command' => [
                PHP_BINARY,
                base_path('artisan'),
                'backtest:change-symbol',
                '--old-symbol=INFRATEL',
                '--new-symbol=INDUSTOWER',
                '--no-interaction',
                '--no-ansi',
            ],
            'timeout' => 600,
        ],
        [
            'command' => [
                PHP_BINARY,
                base_path('artisan'),
                'backtest:import-instruments',
                '--omit-create',
                '--date=2022-02-04',
                '--no-interaction',
                '--no-ansi',
            ],
            'timeout' => 3540,
        ],
    ]);

    $savedOutput = $step->outputChunks()->pluck('output')->join('');

    expect($step->fresh()->status)->toBe(AdminProcessStepStatusEnum::Completed)
        ->and($step->fresh()->attempts)->toBe(2)
        ->and($savedOutput)->toContain('php artisan backtest:change-symbol --old-symbol=BURGERKING --new-symbol=RBA')
        ->and($savedOutput)->toContain('php artisan backtest:change-symbol --old-symbol=INFRATEL --new-symbol=INDUSTOWER')
        ->and($savedOutput)->toContain('[Symbol changes completed. Checking for new instruments again.]')
        ->and($savedOutput)->toContain('php artisan backtest:import-instruments --omit-create --date=2022-02-04');
});

it('marks a command as failed and allows the same step to be retried', function () {
    Process::fake([
        '*' => Process::result(errorOutput: 'Import failed', exitCode: 2),
    ]);
    Queue::fake();
    $run = app(CreateAdminProcessRunAction::class)->execute($this->admin, '2022-02-04');
    $run->update(['status' => AdminProcessRunStatusEnum::InProgress]);
    $step = $run->steps[0];
    $step->update(['status' => AdminProcessStepStatusEnum::Queued]);
    $job = new RunAdminProcessStepJob($step);
    $exception = null;

    try {
        $job->handle(app(BuildDailyProcessStepsAction::class));
    } catch (Throwable $caughtException) {
        $exception = $caughtException;
    }

    expect($exception)->toBeInstanceOf(RuntimeException::class);
    $job->failed($exception);

    expect($step->fresh()->status)->toBe(AdminProcessStepStatusEnum::Failed)
        ->and($step->fresh()->exit_code)->toBe(2)
        ->and($run->fresh()->status)->toBe(AdminProcessRunStatusEnum::Failed);

    $this->actingAs($this->admin)
        ->post("/admin/process-runs/{$run->id}/steps/{$step->id}/run")
        ->assertSessionHasNoErrors();

    expect($step->fresh()->status)->toBe(AdminProcessStepStatusEnum::Queued);
});

it('returns only new output chunks for the requested run', function () {
    $run = app(CreateAdminProcessRunAction::class)->execute($this->admin, '2022-02-04');
    $otherRun = app(CreateAdminProcessRunAction::class)->execute($this->admin, '2022-02-05');
    $firstChunk = AdminProcessOutputChunk::factory()->create([
        'admin_process_step_id' => $run->steps[0]->id,
        'output' => "first\n",
    ]);
    AdminProcessOutputChunk::factory()->create([
        'admin_process_step_id' => $otherRun->steps[0]->id,
        'output' => "other run\n",
    ]);
    $secondChunk = AdminProcessOutputChunk::factory()->create([
        'admin_process_step_id' => $run->steps[0]->id,
        'stream' => 'stderr',
        'output' => "second\n",
    ]);

    $this->actingAs($this->admin)
        ->getJson("/admin/process-runs/{$run->id}/updates?after={$firstChunk->id}")
        ->assertOk()
        ->assertJsonCount(1, 'output_chunks')
        ->assertJsonPath('output_chunks.0.id', $secondChunk->id)
        ->assertJsonPath('output_chunks.0.stream', 'stderr')
        ->assertJsonPath('next_cursor', $secondChunk->id)
        ->assertJsonPath('has_more', false);
});

it('stops a daily symbol job when one calculation command fails', function () {
    Artisan::shouldReceive('call')
        ->once()
        ->with('backtest:calculate-variance', [
            '--date' => '2022-02-04',
            '--symbol' => 'EXAMPLE',
        ])
        ->andReturn(1);

    $job = new ProcessDailyDataForBacktestJob('EXAMPLE', '2022-02-04');

    expect(fn () => $job->handle())
        ->toThrow(RuntimeException::class, 'backtest:calculate-variance failed for EXAMPLE');
});

function storeAdminProcessRequiredFiles(string $date): void
{
    foreach (['bhavcopy', 'corporate_actions', 'etf'] as $filename) {
        Storage::disk('local')->put("uploads/{$date}/{$filename}.csv", 'data');
    }
}
