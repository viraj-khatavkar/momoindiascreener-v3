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

it('adds the shared delisting update after all existing daily steps', function () {
    Queue::fake();
    Process::fake();
    $run = app(CreateAdminProcessRunAction::class)->execute($this->admin, '2024-03-01');
    $step = $run->steps->last();
    expect($step->key)->toBe('update-assumed-delistings')
        ->and($step->position)->toBe(22)
        ->and($step->command_line)->toBe('php artisan backtest:update-assumed-delistings --date=2024-03-01');

    $this->actingAs($this->admin)->post("/admin/process-runs/{$run->id}/steps/{$step->id}/run")
        ->assertSessionHasErrors('step');
    $run->steps()->where('position', '<', $step->position)->update(['status' => AdminProcessStepStatusEnum::Completed]);
    $this->post("/admin/process-runs/{$run->id}/steps/{$step->id}/run")->assertSessionHasNoErrors();
    (new RunAdminProcessStepJob($step->fresh()))->handle(app(BuildDailyProcessStepsAction::class));
    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [
        PHP_BINARY, base_path('artisan'), 'backtest:update-assumed-delistings', '--date=2024-03-01', '--no-interaction', '--no-ansi',
    ]);
    expect($run->fresh()->status)->toBe(AdminProcessRunStatusEnum::Completed);
});

it('adds the final shared-data step only to unfinished saved checklists', function (AdminProcessRunStatusEnum $status) {
    $run = app(CreateAdminProcessRunAction::class)->execute($this->admin, '2024-03-01');
    $run->update(['status' => $status]);
    $run->steps()->where('key', 'update-assumed-delistings')->delete();
    $before = $run->steps()->get()->toArray();
    $migration = require database_path('migrations/2026_10_07_061818_add_assumed_delisting_step_to_unfinished_admin_process_runs.php');
    $migration->up();
    $migration->up();
    expect($run->steps()->where('key', '!=', 'update-assumed-delistings')->get()->toArray())->toBe($before)
        ->and($run->steps()->where('key', 'update-assumed-delistings')->count())->toBe($status === AdminProcessRunStatusEnum::Completed ? 0 : 1);
})->with(AdminProcessRunStatusEnum::cases());

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

    $response = $this->actingAs($this->admin)
        ->post('/admin/process-runs', ['date' => '2022-02-04'])
        ->assertSessionHasNoErrors();

    $run = AdminProcessRun::query()->with('steps')->sole();
    $response->assertRedirect('/admin/process-runs/'.$run->id);

    expect($run->process_date->format('Y-m-d'))->toBe('2022-02-04')
        ->and($run->status)->toBe(AdminProcessRunStatusEnum::Pending)
        ->and($run->steps)->toHaveCount(20)
        ->and($run->steps[0]->position)->toBe(1)
        ->and($run->steps[0]->command_line)
        ->toBe('php artisan backtest:import-instruments --omit-create --date=2022-02-04')
        ->and($run->steps[0]->is_preview)->toBeTrue()
        ->and($run->steps->where('command', 'backtest:import-corporate-actions')->pluck('command_line')->all())
        ->toBe([
            'php artisan backtest:import-corporate-actions --series=BE --date=2022-02-04',
            'php artisan backtest:import-corporate-actions --series=EQ --date=2022-02-04',
            'php artisan backtest:import-corporate-actions --series=SM --date=2022-02-04',
            'php artisan backtest:import-corporate-actions --series=ST --date=2022-02-04',
            'php artisan backtest:import-corporate-actions --series=BZ --date=2022-02-04',
        ])
        ->and($run->steps[7]->command_line)
        ->toBe('php artisan backtest:calculate-dividend-adjustment-factor --date=2022-02-04 --dry-run')
        ->and($run->steps[8]->key)->toBe('apply-dividend-adjustment-factor')
        ->and($run->steps[8]->command_line)
        ->toBe('php artisan backtest:calculate-dividend-adjustment-factor --date=2022-02-04')
        ->and($run->steps[9]->command_line)
        ->toBe('php artisan backtest:adjust-dividends --date=2022-02-04 --dry-run')
        ->and($run->steps[10]->key)->toBe('apply-dividends')
        ->and($run->steps[10]->command_line)
        ->toBe('php artisan backtest:adjust-dividends --date=2022-02-04')
        ->and($run->steps[11]->command_line)
        ->toBe('php artisan backtest:adjust-corporate-action --date=2022-02-04 --dry-run')
        ->and($run->steps[12]->key)->toBe('apply-corporate-action')
        ->and($run->steps[12]->command_line)
        ->toBe('php artisan backtest:adjust-corporate-action --date=2022-02-04')
        ->and($run->steps[16]->command_line)
        ->toBe('php artisan backtest:process-daily-data --date=2022-02-04')
        ->and($run->steps[18]->command_line)
        ->toBe('php artisan backtest:copy-instruments --date=2022-02-04');

    $this->actingAs($this->admin)
        ->post('/admin/process-runs', ['date' => '2022-02-04'])
        ->assertSessionHasNoErrors()
        ->assertRedirect("/admin/process-runs/{$run->id}");

    expect(AdminProcessRun::count())->toBe(1)
        ->and($run->steps()->count())->toBe(20);
});

it('enables only the first incomplete step', function () {
    $run = app(CreateAdminProcessRunAction::class)->execute($this->admin, '2022-02-04');

    $this->actingAs($this->admin)
        ->get("/admin/process-runs/{$run->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Processes/Show')
            ->where('processRun.process_date', '2022-02-04')
            ->has('processRun.steps', 20)
            ->where('processRun.steps.0.can_run', true)
            ->where('processRun.steps.0.can_manage_symbol_changes', false)
            ->where('processRun.steps.1.can_run', false)
            ->where('processRun.steps.5.command_line', 'php artisan backtest:import-corporate-actions --series=ST --date=2022-02-04')
            ->where('processRun.steps.6.command_line', 'php artisan backtest:import-corporate-actions --series=BZ --date=2022-02-04')
            ->where('processRun.steps.7.is_preview', true)
            ->where('processRun.steps.7.is_apply', false)
            ->where('processRun.steps.8.is_preview', false)
            ->where('processRun.steps.8.is_apply', true)
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
        ->reject(fn (array $step): bool => in_array($step['key'], ['import-corporate-actions-st', 'import-corporate-actions-bz', 'update-assumed-delistings'], true))
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

it('adds ST and BZ only to unfinished runs and preserves existing steps', function (AdminProcessRunStatusEnum $status) {
    $run = AdminProcessRun::factory()->create([
        'user_id' => $this->admin->id,
        'process_date' => '2023-10-06',
        'status' => $status,
    ]);
    $legacySteps = collect(app(BuildDailyProcessStepsAction::class)->execute('2023-10-06'))
        ->reject(fn (array $step): bool => in_array($step['key'], ['import-corporate-actions-st', 'import-corporate-actions-bz', 'update-assumed-delistings'], true))
        ->values()
        ->map(fn (array $step, int $index): array => [
            ...$step,
            'position' => $index + 1,
            'status' => $status === AdminProcessRunStatusEnum::Completed
                ? AdminProcessStepStatusEnum::Completed
                : AdminProcessStepStatusEnum::Pending,
        ]);
    $run->steps()->createMany($legacySteps->all());

    $firstStep = $run->steps()->firstOrFail();
    $firstStep->update([
        'status' => AdminProcessStepStatusEnum::Completed,
        'attempts' => 1,
        'exit_code' => 0,
        'started_at' => now()->subSecond(),
        'completed_at' => now(),
    ]);
    $outputChunk = AdminProcessOutputChunk::factory()->create([
        'admin_process_step_id' => $firstStep->id,
        'output' => "Existing output.\n",
    ]);
    $originalSteps = $run->steps()->get()->keyBy('key');
    $originalRun = $run->fresh()->getAttributes();

    $migration = require database_path('migrations/2026_09_21_053606_add_st_and_bz_corporate_action_steps_to_unfinished_admin_process_runs.php');
    $migration->up();

    $run->refresh()->load('steps.outputChunks');

    expect($run->getAttributes())->toBe($originalRun)
        ->and($run->steps[0]->outputChunks->sole()->id)->toBe($outputChunk->id)
        ->and($run->steps[0]->outputChunks->sole()->output)->toBe("Existing output.\n");

    foreach ($originalSteps as $key => $originalStep) {
        $savedStep = $run->steps->firstWhere('key', $key);

        expect(collect($savedStep->getAttributes())->except('position')->all())
            ->toBe(collect($originalStep->getAttributes())->except('position')->all());
    }

    if ($status === AdminProcessRunStatusEnum::Completed) {
        expect($run->steps)->toHaveCount(17)
            ->and($run->steps->pluck('position')->all())->toBe(range(1, 17))
            ->and($run->steps->pluck('key')->all())->toBe($originalSteps->keys()->all());

        return;
    }

    expect($run->steps)->toHaveCount(19)
        ->and($run->steps->pluck('position')->all())->toBe(range(1, 19))
        ->and($run->steps[4]->key)->toBe('import-corporate-actions-sm')
        ->and($run->steps[5]->key)->toBe('import-corporate-actions-st')
        ->and($run->steps[5]->arguments)->toBe(['--series=ST', '--date=2023-10-06'])
        ->and($run->steps[5]->command_line)->toBe('php artisan backtest:import-corporate-actions --series=ST --date=2023-10-06')
        ->and($run->steps[5]->status)->toBe(AdminProcessStepStatusEnum::Pending)
        ->and($run->steps[6]->key)->toBe('import-corporate-actions-bz')
        ->and($run->steps[6]->arguments)->toBe(['--series=BZ', '--date=2023-10-06'])
        ->and($run->steps[6]->command_line)->toBe('php artisan backtest:import-corporate-actions --series=BZ --date=2023-10-06')
        ->and($run->steps[6]->status)->toBe(AdminProcessStepStatusEnum::Pending)
        ->and($run->steps[7]->key)->toBe('calculate-dividend-adjustment-factor');

    $savedSteps = $run->steps->toArray();
    $migration->up();

    expect($run->refresh()->load('steps.outputChunks')->steps->toArray())->toBe($savedSteps);
})->with(AdminProcessRunStatusEnum::cases());

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

it('runs the ST and BZ corporate action steps through the queue job', function (string $series) {
    Process::fake();
    $run = app(CreateAdminProcessRunAction::class)->execute($this->admin, '2023-10-09');
    $run->update(['status' => AdminProcessRunStatusEnum::InProgress]);
    $step = $run->steps->firstWhere('key', 'import-corporate-actions-'.strtolower($series));
    $step->update(['status' => AdminProcessStepStatusEnum::Queued]);

    $job = new RunAdminProcessStepJob($step);
    $job->handle(app(BuildDailyProcessStepsAction::class));

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [
        PHP_BINARY,
        base_path('artisan'),
        'backtest:import-corporate-actions',
        "--series={$series}",
        '--date=2023-10-09',
        '--no-interaction',
        '--no-ansi',
    ]);

    expect($step->fresh()->status)->toBe(AdminProcessStepStatusEnum::Completed)
        ->and($step->fresh()->exit_code)->toBe(0)
        ->and($step->fresh()->error_message)->toBeNull();
})->with(['ST', 'BZ']);

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

it('requires valuation uploads and adds their steps from March 2024', function (string $date, int $fileCount, int $stepCount) {
    $files = app(BuildDailyProcessStepsAction::class)->requiredFiles($date);

    foreach ($files as $file) {
        Storage::put("uploads/{$date}/{$file['key']}.csv", 'data');
    }

    $this->actingAs($this->admin)->get("/admin/processes?date={$date}")
        ->assertInertia(fn (Assert $page) => $page
            ->has('requiredFiles', $fileCount)
            ->where('allFilesAvailable', true));

    $this->actingAs($this->admin)->post('/admin/process-runs', ['date' => $date])
        ->assertSessionHasNoErrors();

    $run = AdminProcessRun::query()->with('steps')->sole();
    expect($run->steps)->toHaveCount($stepCount);

    if ($date < '2024-03-01') {
        expect($run->steps->pluck('key'))->not->toContain('import-marketcap', 'import-price-to-earnings');

        return;
    }

    expect($run->steps[1]->key)->toBe('import-instruments')
        ->and($run->steps[2]->key)->toBe('import-corporate-actions-be')
        ->and($run->steps[12]->key)->toBe('apply-corporate-action')
        ->and($run->steps[13]->key)->toBe('import-marketcap')
        ->and($run->steps[14]->key)->toBe('import-price-to-earnings')
        ->and($run->steps[15]->key)->toBe('mark-etfs');

    $this->actingAs($this->admin)->get("/admin/process-runs/{$run->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->has('processRun.steps', 22)
            ->where('processRun.steps.13.command_line', "php artisan backtest:import-marketcap --date={$date}")
            ->where('processRun.steps.14.command_line', "php artisan backtest:import-price-to-earnings --date={$date}")
            ->where('processRun.steps.13.can_run', false));
})->with([
    ['2024-02-28', 3, 20],
    ['2024-02-29', 3, 20],
    ['2024-03-01', 5, 22],
    ['2024-03-04', 5, 22],
]);

it('prevents new runs when either valuation file is missing', function (string $missingFile) {
    foreach (app(BuildDailyProcessStepsAction::class)->requiredFiles('2024-03-01') as $file) {
        if ($file['key'] !== $missingFile) {
            Storage::put("uploads/2024-03-01/{$file['key']}.csv", 'data');
        }
    }

    $this->actingAs($this->admin)->post('/admin/process-runs', ['date' => '2024-03-01'])
        ->assertSessionHasErrors('files');

    expect(AdminProcessRun::count())->toBe(0);
})->with(['marketcap', 'price_to_earnings']);

it('runs the new valuation steps through the existing queue job', function (string $key) {
    Process::fake();
    Queue::fake();
    $run = app(CreateAdminProcessRunAction::class)->execute($this->admin, '2024-03-01');
    $step = $run->steps->firstWhere('key', $key);
    $run->steps()->where('position', '<', $step->position)->update(['status' => AdminProcessStepStatusEnum::Completed]);

    $this->actingAs($this->admin)
        ->post("/admin/process-runs/{$run->id}/steps/{$step->id}/run")
        ->assertSessionHasNoErrors();

    Queue::assertPushed(RunAdminProcessStepJob::class);
    (new RunAdminProcessStepJob($step->fresh()))->handle(app(BuildDailyProcessStepsAction::class));

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [
        PHP_BINARY, base_path('artisan'), "backtest:{$key}", '--date=2024-03-01', '--no-interaction', '--no-ansi',
    ]);
    expect($step->fresh()->status)->toBe(AdminProcessStepStatusEnum::Completed);
})->with(['import-marketcap', 'import-price-to-earnings']);

it('adds valuation steps only to eligible unfinished runs and preserves their history', function (string $date, AdminProcessRunStatusEnum $status) {
    $run = AdminProcessRun::factory()->create([
        'user_id' => $this->admin->id, 'process_date' => $date, 'status' => $status,
    ]);
    $legacySteps = collect(app(BuildDailyProcessStepsAction::class)->execute($date))
        ->reject(fn (array $step): bool => in_array($step['key'], ['import-marketcap', 'import-price-to-earnings', 'update-assumed-delistings'], true))
        ->values()->map(fn (array $step, int $index): array => [
            ...$step, 'position' => $index + 1, 'status' => AdminProcessStepStatusEnum::Pending,
        ]);
    $run->steps()->createMany($legacySteps->all());
    $firstStep = $run->steps()->firstOrFail();
    $firstStep->update(['status' => AdminProcessStepStatusEnum::Completed, 'attempts' => 1, 'exit_code' => 0]);
    $chunk = AdminProcessOutputChunk::factory()->create(['admin_process_step_id' => $firstStep->id]);
    $originalSteps = $run->steps()->get()->keyBy('key');
    $originalRun = $run->fresh()->getAttributes();

    $migration = require database_path('migrations/2026_09_27_021020_add_valuation_steps_to_unfinished_admin_process_runs.php');
    $migration->up();
    $run->refresh()->load('steps.outputChunks');
    $isEligible = $date >= '2024-03-01' && $status !== AdminProcessRunStatusEnum::Completed;
    $stepCount = $isEligible ? 21 : 19;

    expect($run->getAttributes())->toBe($originalRun)
        ->and($run->steps)->toHaveCount($stepCount)
        ->and($run->steps->pluck('position')->all())->toBe(range(1, $stepCount))
        ->and($run->steps[0]->outputChunks->sole()->id)->toBe($chunk->id);

    foreach ($originalSteps as $key => $originalStep) {
        expect(collect($run->steps->firstWhere('key', $key)->getAttributes())->except('position')->all())
            ->toBe(collect($originalStep->getAttributes())->except('position')->all());
    }

    if ($isEligible) {
        expect($run->steps[2]->key)->toBe('import-marketcap')
            ->and($run->steps[2]->status)->toBe(AdminProcessStepStatusEnum::Pending)
            ->and($run->steps[3]->key)->toBe('import-price-to-earnings')
            ->and($run->steps[3]->arguments)->toBe(["--date={$date}"]);
    }

    $savedSteps = $run->steps->toArray();
    $migration->up();
    expect($run->refresh()->load('steps.outputChunks')->steps->toArray())->toBe($savedSteps);
})->with(['2024-02-29', '2024-03-01'])->with(AdminProcessRunStatusEnum::cases());

it('moves saved valuation steps before ETFs and keeps their execution history', function (AdminProcessRunStatusEnum $status) {
    $run = AdminProcessRun::factory()->create([
        'user_id' => $this->admin->id, 'process_date' => '2024-03-01', 'status' => $status,
    ]);
    $steps = collect(app(BuildDailyProcessStepsAction::class)->execute('2024-03-01'))
        ->reject(fn (array $step): bool => $step['key'] === 'update-assumed-delistings')->keyBy('key');
    $legacyKeys = $steps->keys()->reject(fn (string $key): bool => in_array($key, ['import-marketcap', 'import-price-to-earnings'], true))
        ->values()->all();
    array_splice($legacyKeys, 2, 0, ['import-marketcap', 'import-price-to-earnings']);
    $run->steps()->createMany(collect($legacyKeys)->map(fn (string $key, int $index): array => [
        ...$steps->get($key), 'position' => $index + 1, 'status' => AdminProcessStepStatusEnum::Pending,
    ])->all());

    $marketcapStep = $run->steps()->where('key', 'import-marketcap')->firstOrFail();
    $marketcapStep->update(['status' => AdminProcessStepStatusEnum::Completed, 'attempts' => 1, 'exit_code' => 0]);
    $chunk = AdminProcessOutputChunk::factory()->create(['admin_process_step_id' => $marketcapStep->id]);
    $originalSteps = $run->steps()->get()->keyBy('key');
    $originalRun = $run->fresh()->getAttributes();

    $migration = require database_path('migrations/2026_09_27_024655_move_valuation_steps_before_mark_etfs_in_admin_process_runs.php');
    $migration->up();
    $run->refresh()->load('steps.outputChunks');

    expect($run->getAttributes())->toBe($originalRun)
        ->and($run->steps->pluck('position')->all())->toBe(range(1, 21))
        ->and($marketcapStep->outputChunks()->sole()->id)->toBe($chunk->id);

    foreach ($originalSteps as $key => $originalStep) {
        expect(collect($run->steps->firstWhere('key', $key)->getAttributes())->except('position')->all())
            ->toBe(collect($originalStep->getAttributes())->except('position')->all());
    }

    $expectedKeys = $status === AdminProcessRunStatusEnum::Completed ? $legacyKeys : $steps->keys()->all();
    expect($run->steps->pluck('key')->all())->toBe($expectedKeys);

    $savedSteps = $run->steps->toArray();
    $migration->up();
    expect($run->refresh()->load('steps.outputChunks')->steps->toArray())->toBe($savedSteps);

    $migration->down();
    expect($run->refresh()->steps->pluck('key')->all())->toBe($legacyKeys);
})->with(AdminProcessRunStatusEnum::cases());

function storeAdminProcessRequiredFiles(string $date): void
{
    foreach (['bhavcopy', 'corporate_actions', 'etf'] as $filename) {
        Storage::disk('local')->put("uploads/{$date}/{$filename}.csv", 'data');
    }
}
