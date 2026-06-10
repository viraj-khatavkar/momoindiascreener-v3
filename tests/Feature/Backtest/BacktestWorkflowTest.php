<?php

use App\Enums\BacktestStatusEnum;
use App\Jobs\RunBacktestJob;
use App\Models\Backtest;
use App\Models\BacktestDailySnapshot;
use App\Models\BacktestSummaryMetric;
use App\Models\BacktestTrade;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

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
