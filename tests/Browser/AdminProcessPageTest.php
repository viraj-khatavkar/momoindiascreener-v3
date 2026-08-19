<?php

use App\Actions\AdminProcess\CreateAdminProcessRunAction;
use App\Enums\AdminProcessRunStatusEnum;
use App\Enums\AdminProcessStepStatusEnum;
use App\Models\AdminProcessOutputChunk;
use App\Models\User;

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
