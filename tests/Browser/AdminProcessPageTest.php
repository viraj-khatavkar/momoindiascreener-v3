<?php

use App\Actions\AdminProcess\BuildDailyProcessStepsAction;
use App\Actions\AdminProcess\CreateAdminProcessRunAction;
use App\Enums\AdminProcessRunStatusEnum;
use App\Enums\AdminProcessStepStatusEnum;
use App\Jobs\RunAdminProcessStepJob;
use App\Models\AdminProcessOutputChunk;
use App\Models\User;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;

it('repeats the index import from its command block and resumes live output', function () {
    Queue::fake();
    Process::fake();
    $admin = User::factory()->create(['is_admin' => true]);
    $run = app(CreateAdminProcessRunAction::class)->execute($admin, '2026-02-17');
    $indexStep = $run->steps->firstWhere('key', 'import-indices');
    $run->steps()->update([
        'status' => AdminProcessStepStatusEnum::Completed,
        'attempts' => 1,
        'started_at' => now()->subSecond(),
        'completed_at' => now(),
    ]);
    $run->update(['status' => AdminProcessRunStatusEnum::Completed, 'completed_at' => now()]);
    AdminProcessOutputChunk::factory()->create([
        'admin_process_step_id' => $indexStep->id,
        'output' => "Original index import.\n",
    ]);
    $this->actingAs($admin);

    $page = visit("/admin/process-runs/{$run->id}")
        ->assertSee('Daily processing is complete.')
        ->assertSeeIn("#step-{$indexStep->id}", 'Import indices again')
        ->assertDontSee('Go to next step')
        ->click("#step-{$indexStep->id} button:has-text('Show terminal output')")
        ->assertSeeIn("#process-output-{$indexStep->id}", 'Original index import.');

    foreach ([2, 3] as $attempt) {
        $page->click('Import indices again')
            ->assertSeeIn("#step-{$indexStep->id}", 'Waiting for a queue worker')
            ->assertDontSee('Import indices again');

        (new RunAdminProcessStepJob($indexStep->fresh()))->handle(app(BuildDailyProcessStepsAction::class));

        $page->assertSeeIn("#process-output-{$indexStep->id}", "--- Retry {$attempt} ---")
            ->assertSeeIn("#process-output-{$indexStep->id}", 'Original index import.')
            ->assertSee('Daily processing is complete.')
            ->assertSeeIn("#step-{$indexStep->id}", 'Import indices again')
            ->assertDontSee('Go to next step')
            ->assertNoJavaScriptErrors();
    }

    Queue::assertPushed(RunAdminProcessStepJob::class, 2);
});

it('shows market cap and adjusted PE imports before marking ETFs', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $run = app(CreateAdminProcessRunAction::class)->execute($admin, '2024-03-01');
    $adjustmentPosition = $run->steps->sole('key', 'apply-corporate-action')->position;
    $run->steps()->where('position', '<=', $adjustmentPosition)->update([
        'status' => AdminProcessStepStatusEnum::Completed->value,
    ]);

    $this->actingAs($admin);

    visit("/admin/process-runs/{$run->id}")
        ->assertSee($adjustmentPosition.'. Apply corporate action adjustments')
        ->assertSee(($adjustmentPosition + 1).'. Import market cap')
        ->assertSee(($adjustmentPosition + 2).'. Import price to earnings')
        ->assertSee(($adjustmentPosition + 3).'. Mark ETFs')
        ->assertSee($run->steps->count().'. Update assumed delistings')
        ->assertSee('php artisan backtest:update-assumed-delistings --date=2024-03-01')
        ->assertSee('save values in crores')
        ->assertSee('Import ADJUSTED P/E values')
        ->assertSee('php artisan backtest:import-marketcap --date=2024-03-01')
        ->assertSee('php artisan backtest:import-price-to-earnings --date=2024-03-01')
        ->assertNoJavaScriptErrors();
});

it('adds several symbol name changes to the instrument check', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $run = app(CreateAdminProcessRunAction::class)->execute($admin, '2022-02-04');
    $run->steps[0]->update([
        'status' => AdminProcessStepStatusEnum::Completed,
        'attempts' => 1,
        'started_at' => now()->subSecond(),
        'completed_at' => now(),
    ]);

    $this->actingAs($admin);

    visit("/admin/process-runs/{$run->id}")
        ->assertSee('Resolve symbol name changes')
        ->fill('#old-symbol-0', 'burgerking')
        ->fill('#new-symbol-0', 'rba')
        ->click('Add another mapping')
        ->assertValue('#old-symbol-0', 'BURGERKING')
        ->assertValue('#new-symbol-0', 'RBA')
        ->fill('#old-symbol-1', 'infratel')
        ->fill('#new-symbol-1', 'industower')
        ->assertSee('Run changes and check again')
        ->assertNoJavaScriptErrors();
});

it('shows compact daily data progress and returns to the top', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $run = app(CreateAdminProcessRunAction::class)->execute($admin, '2022-02-04');
    $dailyDataStep = $run->steps()
        ->where('key', 'process-daily-data')
        ->firstOrFail();

    $run->steps()
        ->where('position', '<', $dailyDataStep->position)
        ->update(['status' => AdminProcessStepStatusEnum::Completed->value]);
    $dailyDataStep->update([
        'status' => AdminProcessStepStatusEnum::Running,
        'attempts' => 1,
        'started_at' => now(),
    ]);
    $run->update([
        'status' => AdminProcessRunStatusEnum::InProgress,
        'started_at' => now(),
    ]);

    AdminProcessOutputChunk::factory()->create([
        'admin_process_step_id' => $dailyDataStep->id,
        'output' => "Daily data progress: 0% (0/1940)\n",
    ]);
    AdminProcessOutputChunk::factory()->create([
        'admin_process_step_id' => $dailyDataStep->id,
        'output' => "Daily data progress: 53% (1028/1940)\n",
    ]);

    $this->actingAs($admin);

    $page = visit("/admin/process-runs/{$run->id}")
        ->waitForText('Daily data progress')
        ->assertSee('53%')
        ->assertSee('1,028 of 1,940 instrument jobs completed.')
        ->assertDontSee('Daily data progress: 0% (0/1940)')
        ->assertNoJavaScriptErrors();

    $page->script('window.scrollTo(0, document.body.scrollHeight)');
    $page->wait(0.2)
        ->assertSee('Go to top')
        ->press('Go to top')
        ->wait(1);

    expect((int) $page->script('window.scrollY'))->toBeLessThan(5);
});
