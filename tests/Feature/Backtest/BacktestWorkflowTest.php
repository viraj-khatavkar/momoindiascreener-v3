<?php

use App\Actions\Backtest\CalculateBacktestMetricsAction;
use App\Actions\Backtest\RunBacktestAction;
use App\Enums\BacktestCashCallEnum;
use App\Enums\BacktestStatusEnum;
use App\Jobs\RunBacktestJob;
use App\Models\Backtest;
use App\Models\BacktestDailySnapshot;
use App\Models\BacktestSummaryMetric;
use App\Models\BacktestTrade;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\mock;

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
        'rolling_returns_three_year' => [], 'rolling_returns_five_year' => [], 'stock_performance' => [],
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
    expect($backtest->name)->toBe('My Strategy');
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
    $backtest = Backtest::factory()->create(['user_id' => $user->id, 'status' => BacktestStatusEnum::Completed]);
    seedBacktestResults($backtest);

    $response = $this->actingAs($user)->post('/backtests/'.$backtest->id.'/run');

    $response->assertRedirect('/backtests/'.$backtest->id);
    $backtest->refresh();
    expect($backtest->status)->toBe(BacktestStatusEnum::Running)
        ->and($backtest->trades()->count())->toBe(0)
        ->and($backtest->dailySnapshots()->count())->toBe(0)
        ->and($backtest->summaryMetrics)->toBeNull();
    Queue::assertPushed(RunBacktestJob::class);
});

it('reports preparation progress when a queue worker starts the run', function () {
    $backtest = Backtest::factory()->create([
        'status' => BacktestStatusEnum::Running,
        'progress' => 0,
    ]);
    $runAction = mock(RunBacktestAction::class);
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

    (new RunBacktestJob($backtest))->handle($runAction, $metricsAction);

    expect($backtest->refresh()->status)->toBe(BacktestStatusEnum::Completed)
        ->and($backtest->progress)->toBe(100);
});
