<?php

use App\Actions\Backtest\StartBacktestRunAction;
use App\Enums\BacktestStatusEnum;
use App\Jobs\RunBacktestJob;
use App\Models\Backtest;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

it('blocks settings changes while an independent backtest is queued or running', function (int $progress, bool $run) {
    Queue::fake();
    $user = User::factory()->create(['is_paid' => true]);
    $source = Backtest::factory()->create(['user_id' => $user->id]);
    $backtest = Backtest::factory()->create([
        'user_id' => $user->id, 'status' => BacktestStatusEnum::Running, 'progress' => $progress,
    ]);
    $before = $backtest->refresh()->getAttributes();

    $this->actingAs($user)->put('/backtests/'.$backtest->id, [
        ...$backtest->toArray(), 'name' => 'Changed name', 'run' => $run,
        'cash_call' => 'full_cash_below_index_dma', 'cash_call_source' => 'backtest',
        'cash_call_backtest_id' => $source->id, 'cash_call_dma_period' => 20,
    ])->assertSessionHasErrors('cash_call_backtest_id');

    expect($backtest->refresh()->getAttributes())->toBe($before);
    Queue::assertNothingPushed();
})->with([0, 50])->with([false, true]);

it('rejects a second save and run without saving its settings', function () {
    Queue::fake();
    $user = User::factory()->create(['is_paid' => true]);
    Backtest::factory()->create(['user_id' => $user->id, 'status' => BacktestStatusEnum::Running]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id]);
    $before = $backtest->refresh()->getAttributes();

    $this->actingAs($user)->put('/backtests/'.$backtest->id, [
        ...$backtest->toArray(), 'name' => 'Must not be saved', 'max_stocks_to_hold' => 10, 'run' => true,
    ])->assertSessionHasErrors('run');
    $this->post('/backtests/'.$backtest->id.'/run')->assertSessionHasErrors('run');

    expect($backtest->refresh()->getAttributes())->toBe($before);
    Queue::assertNothingPushed();
});

it('allows saving an idle backtest while another backtest is running', function () {
    Queue::fake();
    $user = User::factory()->create(['is_paid' => true]);
    Backtest::factory()->create(['user_id' => $user->id, 'status' => BacktestStatusEnum::Running]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->put('/backtests/'.$backtest->id, [
        ...$backtest->toArray(), 'name' => 'Saved for later',
    ])->assertSessionHasNoErrors();

    expect($backtest->refresh()->name)->toBe('Saved for later')
        ->and($backtest->status)->toBe(BacktestStatusEnum::Pending);
    Queue::assertNothingPushed();
});

it('allows different users to run independently and locks the owner before checking active runs', function () {
    Queue::fake();
    Backtest::factory()->create(['status' => BacktestStatusEnum::Running]);
    $backtest = Backtest::factory()->create();
    $locks = [];
    DB::listen(function (QueryExecuted $query) use (&$locks): void {
        if (str_contains($query->sql, 'for update')) {
            $locks[] = $query->sql;
        }
    });

    app(StartBacktestRunAction::class)->execute($backtest);

    expect($backtest->refresh()->status)->toBe(BacktestStatusEnum::Running)
        ->and($locks[0])->toContain('`users`')
        ->and($locks[1])->toContain('`backtests`')
        ->and($locks[2])->toContain('`status`');
    Queue::assertPushed(RunBacktestJob::class, 1);
});

it('admits a source and dependent together and keeps the run slot until the dependent finishes', function () {
    Queue::fake();
    $user = User::factory()->create(['is_paid' => true]);
    $source = Backtest::factory()->create(['user_id' => $user->id]);
    $dependent = Backtest::factory()->create([
        'user_id' => $user->id, 'cash_call' => 'full_cash_below_index_dma',
        'cash_call_source' => 'backtest', 'cash_call_backtest_id' => $source->id,
    ]);
    $other = Backtest::factory()->create(['user_id' => $user->id]);

    app(StartBacktestRunAction::class)->execute($dependent);

    expect($source->refresh()->status)->toBe(BacktestStatusEnum::Running)
        ->and($dependent->refresh()->status)->toBe(BacktestStatusEnum::Running);
    Queue::assertPushed(RunBacktestJob::class, fn (RunBacktestJob $job): bool => $job->backtest->is($source));
    Queue::assertPushed(RunBacktestJob::class, 1);

    $source->update(['status' => BacktestStatusEnum::Completed]);
    $this->actingAs($user)->put('/backtests/'.$source->id, [
        ...$source->toArray(), 'name' => 'Must remain locked',
    ])->assertSessionHasErrors('cash_call_backtest_id');
    $this->delete('/backtests/'.$source->id)->assertSessionHasErrors('cash_call_backtest_id');
    $this->put('/backtests/'.$other->id, [...$other->toArray(), 'run' => true])->assertSessionHasErrors('run');

    $dependent->update(['status' => BacktestStatusEnum::Completed]);
    $this->put('/backtests/'.$other->id, [...$other->toArray(), 'run' => true])->assertSessionHasNoErrors();
    expect($other->refresh()->status)->toBe(BacktestStatusEnum::Running);
    Queue::assertPushed(RunBacktestJob::class, 2);
});

it('starts a new source sequence with save and run after a failure', function () {
    Queue::fake();
    $user = User::factory()->create(['is_paid' => true]);
    $source = Backtest::factory()->create(['user_id' => $user->id, 'status' => BacktestStatusEnum::Failed]);
    $dependent = Backtest::factory()->create([
        'user_id' => $user->id, 'status' => BacktestStatusEnum::Failed,
        'cash_call' => 'full_cash_below_index_dma', 'cash_call_source' => 'backtest',
        'cash_call_backtest_id' => $source->id, 'error_message' => 'Source failed',
        'started_at' => now()->subDay(),
    ]);
    $oldStart = $dependent->started_at;

    $this->actingAs($user)->put('/backtests/'.$dependent->id, [
        ...$dependent->toArray(), 'name' => 'New run settings', 'run' => true,
    ])->assertSessionHasNoErrors();

    expect($dependent->refresh()->status)->toBe(BacktestStatusEnum::Running)
        ->and($dependent->name)->toBe('New run settings')
        ->and($dependent->error_message)->toBeNull()
        ->and($dependent->started_at->greaterThan($oldStart))->toBeTrue()
        ->and($source->refresh()->status)->toBe(BacktestStatusEnum::Running);
    Queue::assertPushed(RunBacktestJob::class, fn (RunBacktestJob $job): bool => $job->backtest->is($source));
    Queue::assertPushed(RunBacktestJob::class, 1);
});

it('returns current run controls without exposing another users active backtest', function () {
    $user = User::factory()->create(['is_paid' => true]);
    Backtest::factory()->create(['status' => BacktestStatusEnum::Running]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user)->get('/backtests/'.$backtest->id)->assertInertia(fn (Assert $page) => $page
        ->where('settingsLocked', false)->where('activeBacktest', null));

    $backtest->update(['status' => BacktestStatusEnum::Running]);
    $this->get('/backtests/'.$backtest->id)->assertInertia(fn (Assert $page) => $page
        ->where('settingsLocked', true)->where('activeBacktest.id', $backtest->id));
});

it('rejects invalid universe and filter selection values before changing settings', function (string $field, mixed $value) {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id]);
    $before = $backtest->refresh()->getAttributes();

    $this->actingAs($user)->putJson('/backtests/'.$backtest->id, [
        ...$backtest->toArray(), $field => $value,
    ])->assertUnprocessable()->assertJsonValidationErrors($field);

    expect($backtest->refresh()->getAttributes())->toBe($before);
})->with(['index', 'apply_filters_on'])->with(['invalid name' => ['invalid'], 'array' => [['invalid']], 'number' => [999]]);
