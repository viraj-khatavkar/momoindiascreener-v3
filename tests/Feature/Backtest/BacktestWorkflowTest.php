<?php

use App\Actions\Backtest\CalculateBacktestMetricsAction;
use App\Actions\Backtest\RunBacktestAction;
use App\Actions\Backtest\StoreMarketCapAllocationAction;
use App\Enums\BacktestCashCallEnum;
use App\Enums\BacktestStatusEnum;
use App\Jobs\RunBacktestJob;
use App\Models\Backtest;
use App\Models\BacktestDailySnapshot;
use App\Models\BacktestSummaryMetric;
use App\Models\BacktestTrade;
use App\Models\User;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\mock;

function seedRunAvailability(Backtest $backtest): void
{
    foreach (['2024-01-08', '2024-01-09'] as $date) {
        createScreenResultRow('AVAILABLE', 'Available stock', $date, [$backtest->index->isIndexFieldName() => true]);
    }
}

/**
 * Build a full valid update payload from the backtest's current attributes,
 * since StoreBacktestRequest requires every settings field.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validBacktestUpdatePayload(Backtest $backtest, array $overrides = []): array
{
    $payload = collect($backtest->toArray())
        ->except(['id', 'user_id', 'status', 'progress', 'started_at', 'completed_at', 'error_message', 'created_at', 'updated_at'])
        ->all();

    return array_merge($payload, $overrides);
}

function seedBacktestResults(Backtest $backtest): void
{
    BacktestTrade::create([
        'backtest_id' => $backtest->id, 'symbol' => 'AAA', 'name' => 'AAA', 'trade_type' => 'buy',
        'reason' => 'New entry', 'date' => '2011-01-05', 'quantity' => 10, 'price' => 100, 'raw_price' => 100,
        'gross_amount' => 1000, 'stt' => 0, 'transaction_charges' => 0.03, 'sebi_charges' => 0,
        'gst' => 0.01, 'stamp_charges' => 0.15, 'total_charges' => 0.19, 'net_amount' => 1000.19,
    ]);

    BacktestDailySnapshot::create([
        'backtest_id' => $backtest->id, 'date' => '2011-01-05', 'nav' => 100,
        'portfolio_value' => 1000, 'cash' => 0, 'total_value' => 1000, 'holdings_count' => 1,
    ]);

    BacktestSummaryMetric::create([
        'backtest_id' => $backtest->id, 'cagr' => 0.1, 'max_drawdown' => -0.1, 'total_trades' => 1,
        'total_charges_paid' => 0.19, 'final_value' => 1000, 'rolling_returns_one_year' => [],
        'rolling_returns_three_year' => [], 'rolling_returns_five_year' => [], 'stock_performance' => null,
    ]);
}

it('redirects the edit page to the settings tab of the unified page', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get('/backtests/'.$backtest->id.'/edit')
        ->assertRedirect('/backtests/'.$backtest->id.'?tab=settings');
});

it('redirects to the settings tab after creating a backtest', function () {
    $user = User::factory()->create(['is_paid' => true]);

    $response = $this->actingAs($user)->post('/backtests', ['name' => 'My Strategy']);

    $backtest = Backtest::query()->latest('id')->first();
    expect($backtest->name)->toBe('My Strategy')
        ->and((float) $backtest->brokerage_rate)->toBe(0.0)
        ->and((float) $backtest->stt_rate)->toBe(0.1)
        ->and((float) $backtest->transaction_charges_rate)->toBe(0.00307)
        ->and((float) $backtest->sebi_charges_rate)->toBe(0.0001)
        ->and((float) $backtest->gst_rate)->toBe(18.0)
        ->and((float) $backtest->stamp_charges_rate)->toBe(0.015);
    $response->assertRedirect('/backtests/'.$backtest->id.'?tab=settings');
});

it('passes the settings form options to the unified show page', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get('/backtests/'.$backtest->id)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Backtests/Show')
            ->has('backtest')
            ->has('indices')
            ->has('sortByOptions')
            ->has('applyFiltersOnOptions')
            ->has('customFilterValueOptions')
            ->has('customFilterComparatorOptions')
            ->has('rebalanceFrequencyOptions')
            ->has('weightageOptions')
            ->has('cashCallOptions')
            ->has('cashCallIndexOptions')
            ->has('benchmarkOptions'));
});

it('saves settings without queueing a run', function () {
    Queue::fake();
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->put(
        '/backtests/'.$backtest->id,
        validBacktestUpdatePayload($backtest, ['name' => 'Renamed Strategy'])
    );

    $response->assertRedirect('/backtests/'.$backtest->id.'?tab=settings');
    expect($backtest->refresh()->name)->toBe('Renamed Strategy')
        ->and($backtest->status)->toBe(BacktestStatusEnum::Pending);
    Queue::assertNothingPushed();
});

it('does not touch settings_changed_at when only the name changes', function () {
    Queue::fake();
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->put(
        '/backtests/'.$backtest->id,
        validBacktestUpdatePayload($backtest, ['name' => 'Renamed Strategy'])
    );

    expect($backtest->refresh()->settings_changed_at)->toBeNull();
});

it('touches settings_changed_at when a strategy-affecting field changes', function () {
    Queue::fake();
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->put(
        '/backtests/'.$backtest->id,
        validBacktestUpdatePayload($backtest, ['worst_rank_held' => 42])
    );

    expect($backtest->refresh()->settings_changed_at)->not->toBeNull();
});

it('saves configured transaction cost rates', function () {
    Queue::fake();
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->put(
        '/backtests/'.$backtest->id,
        validBacktestUpdatePayload($backtest, ['brokerage_rate' => 0.03, 'stt_rate' => 0.2])
    );

    $backtest->refresh();
    expect((float) $backtest->brokerage_rate)->toBe(0.03)
        ->and((float) $backtest->stt_rate)->toBe(0.2)
        // Cost rates change results, so they must flag them stale
        ->and($backtest->settings_changed_at)->not->toBeNull();
});

it('offers stop loss settings with an opt-in default', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $this->actingAs($user)->post('/backtests', ['name' => 'Stop loss defaults']);
    $backtest = $user->backtests()->latest('id')->first();

    $this->get('/backtests/'.$backtest->id)
        ->assertInertia(fn (Assert $page) => $page
            ->where('backtest.apply_stop_loss', false)
            ->where('backtest.stop_loss_percentage', '10.00')
            ->where('backtest.trail_stop_loss', false)
            ->where('backtest.stop_loss_proceeds', 'wait_for_rebalance')
            ->has('stopLossProceedsOptions', 2));
});

it('offers and queues rank and price weighting settings', function (string $weightage) {
    Queue::fake();
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id]);
    seedRunAvailability($backtest);

    $this->actingAs($user)->get('/backtests/'.$backtest->id)
        ->assertInertia(fn (Assert $page) => $page
            ->has('weightageOptions', 5)
            ->where('weightageOptions.3.id', 'rank_weighted')
            ->where('weightageOptions.4.id', 'price_weighted'));

    $this->put('/backtests/'.$backtest->id, validBacktestUpdatePayload($backtest, [
        'weightage' => $weightage,
        'run' => true,
    ]))->assertSessionHasNoErrors();

    expect($backtest->refresh()->weightage->value)->toBe($weightage)
        ->and($backtest->settings_changed_at)->not->toBeNull();
    Queue::assertPushed(RunBacktestJob::class, fn (RunBacktestJob $job): bool => $job->backtest->weightage->value === $weightage);
})->with(['rank_weighted', 'price_weighted']);

it('saves stop loss settings without changing completed results or starting a run', function (string $proceeds) {
    Queue::fake();
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id, 'status' => BacktestStatusEnum::Completed]);
    seedBacktestResults($backtest);

    $this->actingAs($user)->put('/backtests/'.$backtest->id, validBacktestUpdatePayload($backtest, [
        'apply_stop_loss' => true,
        'stop_loss_percentage' => 12.5,
        'trail_stop_loss' => true,
        'stop_loss_proceeds' => $proceeds,
    ]))->assertSessionHasNoErrors();

    $backtest->refresh();
    expect($backtest->apply_stop_loss)->toBeTrue()
        ->and((float) $backtest->stop_loss_percentage)->toBe(12.5)
        ->and($backtest->trail_stop_loss)->toBeTrue()
        ->and($backtest->stop_loss_proceeds->value)->toBe($proceeds)
        ->and($backtest->settings_changed_at)->not->toBeNull()
        ->and($backtest->status)->toBe(BacktestStatusEnum::Completed)
        ->and($backtest->trades()->count())->toBe(1)
        ->and((float) $backtest->dailySnapshots()->sole()->nav)->toBe(100.0);
    Queue::assertNothingPushed();
})->with(['wait_for_rebalance', 'replace_immediately']);

it('rejects invalid enabled stop loss settings', function (string $field, mixed $value) {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->put('/backtests/'.$backtest->id, validBacktestUpdatePayload($backtest, [
        'apply_stop_loss' => true,
        $field => $value,
    ]))->assertSessionHasErrors($field);
})->with([
    ['apply_stop_loss', 'invalid'],
    ['stop_loss_percentage', null],
    ['stop_loss_percentage', 0],
    ['stop_loss_percentage', -1],
    ['stop_loss_percentage', 100],
    ['stop_loss_percentage', 10.123],
    ['stop_loss_percentage', 'invalid'],
    ['trail_stop_loss', 'invalid'],
    ['stop_loss_proceeds', 'invalid'],
    ['stop_loss_proceeds', null],
]);

it('ignores inactive stop loss controls when stop loss is disabled', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id, 'apply_stop_loss' => true]);

    $this->actingAs($user)->put('/backtests/'.$backtest->id, validBacktestUpdatePayload($backtest, [
        'apply_stop_loss' => false,
        'stop_loss_percentage' => null,
        'trail_stop_loss' => null,
        'stop_loss_proceeds' => null,
    ]))->assertSessionHasNoErrors();

    expect($backtest->refresh()->apply_stop_loss)->toBeFalse()
        ->and((float) $backtest->stop_loss_percentage)->toBe(10.0)
        ->and($backtest->stop_loss_proceeds->value)->toBe('wait_for_rebalance');
});

it('offers the six supported cash call settings', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get('/backtests/'.$backtest->id)
        ->assertInertia(fn (Assert $page) => $page
            ->has('cashCallOptions', 6)
            ->where('cashCallOptions.0.id', 'no_cash_call')
            ->where('cashCallOptions.5.id', 'only_exits_allocate_to_gold_above_dma_below_index_dma')
            ->where('backtest.cash_call_gold_dma_period', 50));
});

it('saves each cash call mode and its gold DMA setting', function (string $mode) {
    Queue::fake();
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->put('/backtests/'.$backtest->id, validBacktestUpdatePayload($backtest, [
        'cash_call' => $mode,
        'cash_call_index' => 'nifty-500',
        'cash_call_dma_period' => 200,
        'cash_call_gold_dma_period' => 100,
        'cash_return_rate' => 7.25,
    ]))->assertSessionHasNoErrors();

    $backtest->refresh();
    expect($backtest->cash_call->value)->toBe($mode)
        ->and((float) $backtest->cash_return_rate)->toBe(7.25)
        ->and($backtest->settings_changed_at)->not->toBeNull();

    if ($backtest->cash_call->usesIndexDma()) {
        expect($backtest->cash_call_index)->toBe('nifty-500')
            ->and($backtest->cash_call_dma_period)->toBe(200);
    }

    expect($backtest->cash_call_gold_dma_period)->toBe(
        $mode === 'only_exits_allocate_to_gold_above_dma_below_index_dma' ? 100 : 50,
    );
    Queue::assertNothingPushed();
})->with([
    'no_cash_call',
    'full_cash_below_index_dma',
    'only_exits_below_index_dma',
    'allocate_to_gold_below_index_dma',
    'only_exits_allocate_to_gold_below_index_dma',
    'only_exits_allocate_to_gold_above_dma_below_index_dma',
]);

it('requires valid gold DMA settings for the conditional gold mode', function (mixed $period) {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->put('/backtests/'.$backtest->id, validBacktestUpdatePayload($backtest, [
        'cash_call' => 'only_exits_allocate_to_gold_above_dma_below_index_dma',
        'cash_call_gold_dma_period' => $period,
    ]))->assertSessionHasErrors('cash_call_gold_dma_period');

    expect($backtest->refresh()->cash_call)->toBe(BacktestCashCallEnum::NoCashCall);
})->with([null, 0, 21, 50.5, 'invalid']);

it('requires an index and index DMA for the conditional gold mode', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->put('/backtests/'.$backtest->id, validBacktestUpdatePayload($backtest, [
        'cash_call' => 'only_exits_allocate_to_gold_above_dma_below_index_dma',
        'cash_call_index' => null,
        'cash_call_dma_period' => null,
    ]))->assertSessionHasErrors(['cash_call_index', 'cash_call_dma_period']);
});

it('preserves inactive DMA settings when saving no cash call', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id, 'cash_call_gold_dma_period' => 200]);

    $this->actingAs($user)->put('/backtests/'.$backtest->id, validBacktestUpdatePayload($backtest, [
        'cash_call_index' => null,
        'cash_call_dma_period' => null,
        'cash_call_gold_dma_period' => null,
    ]))->assertSessionHasNoErrors();

    expect($backtest->refresh()->cash_call_index)->toBe('nifty-50')
        ->and($backtest->cash_call_dma_period)->toBe(50)
        ->and($backtest->cash_call_gold_dma_period)->toBe(200);
});

it('preserves the retired cash mode only for backtests that already use it', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id]);
    $payload = validBacktestUpdatePayload($backtest, ['cash_call' => 'cash_call_if_not_enough_stocks']);

    $this->actingAs($user)->put('/backtests/'.$backtest->id, $payload)->assertSessionHasErrors('cash_call');

    $backtest->update(['cash_call' => BacktestCashCallEnum::CashCallIfNotEnoughStocks]);
    $this->actingAs($user)->put('/backtests/'.$backtest->id, $payload)->assertSessionHasNoErrors();

    expect($backtest->refresh()->cash_call)->toBe(BacktestCashCallEnum::CashCallIfNotEnoughStocks);
});

it('queues the saved gold DMA configuration with save and run', function () {
    Queue::fake();
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id]);
    seedRunAvailability($backtest);

    $this->actingAs($user)->put('/backtests/'.$backtest->id, validBacktestUpdatePayload($backtest, [
        'cash_call' => 'only_exits_allocate_to_gold_above_dma_below_index_dma',
        'cash_call_gold_dma_period' => 200,
        'run' => true,
    ]))->assertSessionHasNoErrors();

    Queue::assertPushed(RunBacktestJob::class, fn (RunBacktestJob $job): bool => $job->backtest->cash_call === BacktestCashCallEnum::OnlyExitsAllocateToGoldAboveDmaBelowIndexDma
        && $job->backtest->cash_call_gold_dma_period === 200);
});

it('rejects out-of-range transaction cost rates', function () {
    Queue::fake();
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->put(
        '/backtests/'.$backtest->id,
        validBacktestUpdatePayload($backtest, ['brokerage_rate' => -1, 'gst_rate' => 101])
    )->assertSessionHasErrors(['brokerage_rate', 'gst_rate']);
});

it('saves settings, clears old results, and queues a run when the run flag is set', function () {
    Queue::fake();
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id, 'status' => BacktestStatusEnum::Completed]);
    seedBacktestResults($backtest);
    seedRunAvailability($backtest);

    $response = $this->actingAs($user)->put(
        '/backtests/'.$backtest->id,
        validBacktestUpdatePayload($backtest, ['name' => 'Renamed Strategy', 'run' => true])
    );

    $response->assertRedirect('/backtests/'.$backtest->id);
    $backtest->refresh();
    expect($backtest->name)->toBe('Renamed Strategy')
        ->and($backtest->status)->toBe(BacktestStatusEnum::Running)
        ->and($backtest->progress)->toBe(0)
        ->and($backtest->trades()->count())->toBe(0)
        ->and($backtest->dailySnapshots()->count())->toBe(0)
        ->and($backtest->summaryMetrics)->toBeNull();
    Queue::assertPushed(RunBacktestJob::class);
});

it('saves settings but does not queue a run when one is already in progress', function () {
    Queue::fake();
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id, 'status' => BacktestStatusEnum::Running]);

    $response = $this->actingAs($user)->put(
        '/backtests/'.$backtest->id,
        validBacktestUpdatePayload($backtest, ['name' => 'Renamed Strategy', 'run' => true])
    );

    $response->assertRedirect('/backtests/'.$backtest->id.'?tab=settings');
    expect($backtest->refresh()->name)->toBe('Renamed Strategy');
    Queue::assertNothingPushed();
});

it('downloads the trade log as csv', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id, 'name' => 'My Strategy']);
    seedBacktestResults($backtest);

    $response = $this->actingAs($user)->get('/backtests/'.$backtest->id.'/csv/trades');

    $response->assertOk();
    $response->assertDownload('My_Strategy_trades.csv');
    $content = $response->streamedContent();
    expect($content)->toContain('date,symbol,name,type,reason')
        ->and($content)->toContain('2011-01-05,AAA,AAA,buy,"New entry"');
});

it('downloads the nav series as csv', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id, 'name' => 'My Strategy']);
    seedBacktestResults($backtest);

    $response = $this->actingAs($user)->get('/backtests/'.$backtest->id.'/csv/nav');

    $response->assertOk();
    $response->assertDownload('My_Strategy_nav.csv');
    expect($response->streamedContent())->toContain('date,nav,portfolio_value,cash,total_value,holdings_count')
        ->and($response->streamedContent())->toContain('2011-01-05');
});

it('rejects unknown csv types', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get('/backtests/'.$backtest->id.'/csv/holdings')
        ->assertNotFound();
});

it('blocks csv downloads of another users backtest', function () {
    $owner = User::factory()->create(['is_paid' => true]);
    $other = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other)
        ->get('/backtests/'.$backtest->id.'/csv/trades')
        ->assertNotFound();
});

it('queues a run from the standalone run endpoint', function () {
    Queue::fake();
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create([
        'user_id' => $user->id, 'status' => BacktestStatusEnum::Completed, 'completed_at' => now()->subDay(),
    ]);
    seedBacktestResults($backtest);
    seedRunAvailability($backtest);

    $response = $this->actingAs($user)->post('/backtests/'.$backtest->id.'/run');

    $response->assertRedirect('/backtests/'.$backtest->id);
    $backtest->refresh();
    expect($backtest->status)->toBe(BacktestStatusEnum::Running)
        ->and($backtest->completed_at)->toBeNull()
        ->and($backtest->trades()->count())->toBe(0)
        ->and($backtest->dailySnapshots()->count())->toBe(0)
        ->and($backtest->summaryMetrics)->toBeNull();
    Queue::assertPushed(RunBacktestJob::class);
});

it('reports preparation progress when a queue worker starts the run', function () {
    $startedAt = now()->subMinute()->startOfSecond();
    $backtest = Backtest::factory()->create([
        'status' => BacktestStatusEnum::Running,
        'progress' => 0,
        'started_at' => $startedAt,
    ]);
    $runAction = mock(RunBacktestAction::class);
    $runAction->shouldReceive('validateDataAvailability')->once()->with($backtest);
    $metricsAction = mock(CalculateBacktestMetricsAction::class);

    $runAction->shouldReceive('execute')
        ->once()
        ->withArgs(function (Backtest $runningBacktest) use ($backtest): bool {
            $runningBacktest->refresh();

            expect($runningBacktest->is($backtest))->toBeTrue()
                ->and($runningBacktest->status)->toBe(BacktestStatusEnum::Running)
                ->and($runningBacktest->progress)->toBe(1)
                ->and($runningBacktest->started_at)->not->toBeNull();

            return true;
        });
    $metricsAction->shouldReceive('execute')->once();
    $storeAllocation = mock(StoreMarketCapAllocationAction::class);
    $storeAllocation->shouldReceive('execute')->once()->withArgs(function (Backtest $runningBacktest): bool {
        expect($runningBacktest->fresh()->progress)->toBe(98);

        return true;
    });

    (new RunBacktestJob($backtest))->handle($runAction, $metricsAction, $storeAllocation);

    expect($backtest->refresh()->status)->toBe(BacktestStatusEnum::Completed)
        ->and($backtest->progress)->toBe(100)
        ->and($backtest->started_at->equalTo($startedAt))->toBeTrue();
});

it('marks a crashed worker run as failed when the queue exhausts its attempts', function () {
    $backtest = Backtest::factory()->create([
        'status' => BacktestStatusEnum::Running, 'progress' => 95, 'started_at' => now()->subHour(),
    ]);
    $job = unserialize(serialize(new RunBacktestJob($backtest)));

    $job->failed(new MaxAttemptsExceededException('The worker could not complete the backtest.'));

    expect($backtest->refresh()->status)->toBe(BacktestStatusEnum::Failed)
        ->and($backtest->error_message)->toBe('The worker could not complete the backtest.')
        ->and($backtest->completed_at)->toBeNull()
        ->and($backtest->progress)->toBe(95);
});

it('does not let a late failure overwrite a completed or newer run', function (bool $newerRun) {
    $backtest = Backtest::factory()->create([
        'status' => BacktestStatusEnum::Running, 'progress' => 95, 'started_at' => now()->subHour(),
    ]);
    $payload = serialize(new RunBacktestJob($backtest));
    $backtest->update($newerRun
        ? ['started_at' => now(), 'progress' => 1]
        : ['status' => BacktestStatusEnum::Completed, 'progress' => 100, 'completed_at' => now()]);
    $expected = $backtest->fresh()->getAttributes();

    unserialize($payload)->failed(new RuntimeException('Old worker failed.'));

    expect($backtest->fresh()->getAttributes())->toBe($expected);
})->with([true, false]);

it('marks an ordinary exception as failed at each execution stage', function (string $failedStage) {
    $backtest = Backtest::factory()->create([
        'status' => BacktestStatusEnum::Running, 'started_at' => now(), 'completed_at' => now()->subDay(),
    ]);
    $actions = [
        'simulation' => mock(RunBacktestAction::class),
        'metrics' => mock(CalculateBacktestMetricsAction::class),
        'allocation' => mock(StoreMarketCapAllocationAction::class),
    ];
    $actions['simulation']->shouldReceive('validateDataAvailability')->once()->with($backtest);
    $afterFailure = false;

    foreach ($actions as $stage => $action) {
        if ($afterFailure) {
            $action->shouldNotReceive('execute');
        } elseif ($stage === $failedStage) {
            $action->shouldReceive('execute')->once()->andThrow(new RuntimeException('Stage failed.'));
            $afterFailure = true;
        } else {
            $action->shouldReceive('execute')->once();
        }
    }

    expect(fn () => (new RunBacktestJob($backtest))->handle(...array_values($actions)))
        ->toThrow(RuntimeException::class, 'Stage failed.');
    expect($backtest->refresh()->status)->toBe(BacktestStatusEnum::Failed)
        ->and($backtest->completed_at)->toBeNull()
        ->and($backtest->error_message)->toBe('Stage failed.');
})->with(['simulation', 'metrics', 'allocation']);
