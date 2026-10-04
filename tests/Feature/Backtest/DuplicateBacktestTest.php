<?php

use App\Actions\Backtest\DuplicateBacktestAction;
use App\Enums\BacktestStatusEnum;
use App\Models\Backtest;
use App\Models\BacktestDailySnapshot;
use App\Models\BacktestSummaryMetric;
use App\Models\BacktestTrade;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('copies all saved settings into an independent pending backtest without copying results or queueing a run', function (string $status) {
    Queue::fake();
    $user = User::factory()->create(['is_paid' => true]);
    $source = Backtest::factory()->for($user)->create([
        'name' => 'My Strategy',
        'status' => $status,
        'progress' => 95,
        'started_at' => now()->subDays(3),
        'completed_at' => now()->subDays(2),
        'settings_changed_at' => now()->subDay(),
        'error_message' => 'Old run error',
        'created_at' => now()->subMonth(),
        'updated_at' => now()->subDay(),
        'max_stocks_to_hold' => 17,
        'worst_rank_held' => 45,
        'apply_hold_above_dma' => true,
        'hold_above_dma_period' => 100,
        'execute_next_trading_day' => true,
        'skip_circuit_trades' => false,
        'exit_before_demerger' => false,
        'exit_on_be_series' => true,
        'apply_stop_loss' => true,
        'stop_loss_percentage' => 12.75,
        'trail_stop_loss' => true,
        'stop_loss_proceeds' => 'replace_immediately',
        'weightage' => 'rank_weighted',
        'rebalance_day' => 5,
        'cash_call' => 'only_exits_allocate_to_gold_above_dma_below_index_dma',
        'cash_call_index' => 'nifty-500',
        'cash_call_dma_period' => 100,
        'cash_call_gold_dma_period' => 200,
        'cash_return_rate' => 4.25,
        'brokerage_rate' => 0.125,
        'stt_rate' => 0.15,
        'transaction_charges_rate' => 0.004,
        'sebi_charges_rate' => 0.00002,
        'gst_rate' => 15,
        'stamp_charges_rate' => 0.025,
        'start_date' => '2013-01-01',
        'minimum_return_one_year' => 14,
        'apply_ma' => true,
        'above_ma_200' => true,
        'apply_ema' => true,
        'above_ema_100' => true,
        'apply_pe' => true,
        'price_to_earnings_to' => 40,
        'series_be' => false,
        'ignore_above_beta' => 1.5,
        'apply_factor_two' => true,
        'apply_factor_three' => true,
        'apply_custom_filter_one' => true,
        'apply_custom_filter_five' => true,
    ])->refresh();

    $trade = BacktestTrade::create([
        'backtest_id' => $source->id, 'symbol' => 'ITC', 'name' => 'ITC', 'trade_type' => 'buy',
        'reason' => 'New entry', 'date' => '2013-01-01', 'quantity' => 10, 'price' => 100,
        'raw_price' => 100, 'gross_amount' => 1000, 'stt' => 0, 'transaction_charges' => 0,
        'sebi_charges' => 0, 'gst' => 0, 'stamp_charges' => 1, 'total_charges' => 1, 'net_amount' => 1001,
    ]);
    $snapshot = BacktestDailySnapshot::create([
        'backtest_id' => $source->id, 'date' => '2013-01-01', 'nav' => 100,
        'portfolio_value' => 1000, 'cash' => 500, 'total_value' => 1500, 'holdings_count' => 1,
    ]);
    $metrics = BacktestSummaryMetric::create([
        'backtest_id' => $source->id, 'cagr' => 0.1, 'max_drawdown' => -0.1,
        'total_trades' => 1, 'total_charges_paid' => 1, 'final_value' => 1500,
    ]);
    $original = $source->getAttributes();

    $response = $this->actingAs($user)->post(route('backtests.duplicate', $source), [
        'name' => 'Untrusted name', 'user_id' => 999999, 'status' => 'running',
        'stop_loss_percentage' => 50, 'run' => true,
    ]);

    $copy = $user->backtests()->whereKeyNot($source->id)->sole();
    $response->assertRedirect('/backtests/'.$copy->id.'?tab=settings')
        ->assertSessionHas('success');

    $runFields = [
        'id', 'name', 'status', 'progress', 'started_at', 'completed_at',
        'settings_changed_at', 'error_message', 'created_at', 'updated_at',
    ];

    expect(Arr::except($copy->getAttributes(), $runFields))->toBe(Arr::except($original, $runFields))
        ->and($copy->name)->toBe('My Strategy (Copy)')
        ->and($copy->status)->toBe(BacktestStatusEnum::Pending)
        ->and($copy->progress)->toBe(0)
        ->and($copy->started_at)->toBeNull()
        ->and($copy->completed_at)->toBeNull()
        ->and($copy->settings_changed_at)->toBeNull()
        ->and($copy->error_message)->toBeNull()
        ->and($copy->created_at->gt($source->created_at))->toBeTrue()
        ->and($copy->updated_at->gt($source->updated_at))->toBeTrue()
        ->and($copy->trades()->exists())->toBeFalse()
        ->and($copy->dailySnapshots()->exists())->toBeFalse()
        ->and($copy->summaryMetrics()->exists())->toBeFalse()
        ->and($source->refresh()->getAttributes())->toBe($original)
        ->and($trade->refresh()->backtest_id)->toBe($source->id)
        ->and($snapshot->refresh()->backtest_id)->toBe($source->id)
        ->and($metrics->refresh()->backtest_id)->toBe($source->id);

    $copy->update(['stop_loss_percentage' => 20]);
    expect($source->refresh()->stop_loss_percentage)->toBe('12.75');
    Queue::assertNothingPushed();
})->with(['pending', 'running', 'completed', 'failed']);

it('numbers repeat copies and copies of copies within the owners backtests', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $source = Backtest::factory()->for($user)->create(['name' => 'Momentum']);
    Backtest::factory()->create(['name' => 'Momentum (Copy)']);

    foreach (['Momentum (Copy)', 'Momentum (Copy 2)', 'Momentum (Copy 3)'] as $expectedName) {
        $this->actingAs($user)->post(route('backtests.duplicate', $source))->assertRedirect();
        $source = $user->backtests()->latest('id')->firstOrFail();
        expect($source->name)->toBe($expectedName);
    }
});

it('keeps long Unicode copy names within the settings name limit', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $source = Backtest::factory()->for($user)->create(['name' => str_repeat('株', 250)]);

    foreach ([' (Copy)', ' (Copy 2)'] as $suffix) {
        $this->actingAs($user)->post(route('backtests.duplicate', $source))->assertRedirect();
        $copy = $user->backtests()->latest('id')->firstOrFail();
        expect(Str::length($copy->name))->toBe(250)
            ->and($copy->name)->toBe(str_repeat('株', 250 - Str::length($suffix)).$suffix);
    }
});

it('does not retain loaded result relations on the copied model', function () {
    $source = Backtest::factory()->create();
    $source->load(['trades', 'dailySnapshots', 'summaryMetrics']);

    $copy = app(DuplicateBacktestAction::class)->execute($source);

    expect($copy->relationLoaded('trades'))->toBeFalse()
        ->and($copy->relationLoaded('dailySnapshots'))->toBeFalse()
        ->and($copy->relationLoaded('summaryMetrics'))->toBeFalse()
        ->and($source->relationLoaded('trades'))->toBeTrue();
});

it('does not allow another user to duplicate a backtest', function () {
    $source = Backtest::factory()->create();
    $other = User::factory()->create(['is_paid' => true]);

    $this->actingAs($other)->post(route('backtests.duplicate', $source))->assertNotFound();

    expect(Backtest::count())->toBe(1);
});

it('requires a paid subscription to duplicate a backtest', function () {
    $user = User::factory()->create(['is_paid' => false]);
    $source = Backtest::factory()->for($user)->create();

    $this->actingAs($user)->post(route('backtests.duplicate', $source))->assertRedirect('/pricing');

    expect(Backtest::count())->toBe(1);
});

it('requires authentication to duplicate a backtest', function () {
    $source = Backtest::factory()->create();

    $this->post(route('backtests.duplicate', $source))->assertRedirect('/login');

    expect(Backtest::count())->toBe(1);
});
