<?php

use App\Actions\Backtest\BacktestNavSourceAction;
use App\Actions\Backtest\CalculateBacktestMetricsAction;
use App\Actions\Backtest\RunBacktestAction;
use App\Actions\Backtest\StartBacktestRunAction;
use App\Actions\Backtest\StoreMarketCapAllocationAction;
use App\Enums\BacktestCashCallEnum;
use App\Enums\BacktestStatusEnum;
use App\Jobs\RunBacktestJob;
use App\Models\Backtest;
use App\Models\BacktestDailySnapshot;
use App\Models\BacktestSummaryMetric;
use App\Models\BacktestTrade;
use App\Models\NseIndex;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\mock;

function seedRunAvailability(Backtest $backtest, bool $includeIndexData = false): void
{
    foreach (['2024-01-08', '2024-01-09'] as $date) {
        createScreenResultRow('AVAILABLE', 'Available stock', $date, [$backtest->index->isIndexFieldName() => true]);
    }

    if ($includeIndexData) {
        $date = Carbon::parse('2024-01-08')->subWeekdays($backtest->cash_call_dma_period - 1);
        while ($date->toDateString() <= '2024-01-09') {
            NseIndex::query()->insert([
                'symbol' => 'Nifty 50', 'slug' => $backtest->cash_call_index, 'date' => $date->toDateString(),
                'open' => 100, 'high' => 100, 'low' => 100, 'close' => 100,
                'points_change' => 0, 'percentage_change' => 0, 'volume' => 0, 'turnover' => 0,
                'price_to_earnings' => 20, 'price_to_book' => 3, 'dividend_yield' => 1.5,
            ]);
            $date->addWeekday();
        }
    }
}

it('marks an independent run failed when dispatch fails and permits a new attempt', function () {
    $backtest = Backtest::factory()->create();
    seedRunAvailability($backtest);
    Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Queue unavailable'));

    expect(fn () => app(StartBacktestRunAction::class)->execute($backtest))->toThrow(RuntimeException::class, 'Queue unavailable');

    expect($backtest->refresh()->status)->toBe(BacktestStatusEnum::Failed)
        ->and($backtest->error_message)->toBe('Queue unavailable')
        ->and($backtest->user->can('run', $backtest))->toBeTrue();

    Bus::fake();
    app(StartBacktestRunAction::class)->execute($backtest);
    expect($backtest->refresh()->status)->toBe(BacktestStatusEnum::Running)
        ->and($backtest->error_message)->toBeNull();
    Bus::assertDispatched(RunBacktestJob::class);
});

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
            ->where('rebalanceFrequencyOptions', fn ($options) => collect($options)->pluck('id')->all() === ['weekly', 'fortnightly', 'monthly', 'quarterly', 'six_monthly', 'yearly'])
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

it('offers and saves six and three month Sharpe sorting for all backtest factors', function () {
    Queue::fake();
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id]);
    $factor = 'average_sharpe_return_six_three_months';

    $this->actingAs($user)->get('/backtests/'.$backtest->id)
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sortByOptions', fn (mixed $options): bool => collect($options)->contains('id', $factor)));

    $this->put('/backtests/'.$backtest->id, validBacktestUpdatePayload($backtest, [
        'sort_by' => $factor,
        'apply_factor_two' => true, 'factor_two_sort_by' => $factor,
        'apply_factor_three' => true, 'factor_three_sort_by' => $factor,
    ]))->assertSessionHasNoErrors()->assertRedirect('/backtests/'.$backtest->id.'?tab=settings');

    $backtest->refresh();
    expect($backtest->sort_by)->toBe($factor)
        ->and($backtest->factor_two_sort_by)->toBe($factor)
        ->and($backtest->factor_three_sort_by)->toBe($factor)
        ->and($backtest->settings_changed_at)->not->toBeNull();
    Queue::assertNothingPushed();
});

it('saves and queues the selected rebalance schedule', function (string $frequency, int $day, ?int $month) {
    Queue::fake();
    $backtest = Backtest::factory()->for(User::factory()->state(['is_paid' => true]))->create();
    $this->actingAs($backtest->user)->put('/backtests/'.$backtest->id, validBacktestUpdatePayload($backtest, [
        'rebalance_frequency' => $frequency, 'rebalance_day' => $day, 'rebalance_month' => $month, 'run' => true,
    ]))->assertSessionHasNoErrors();

    Queue::assertPushed(RunBacktestJob::class, fn (RunBacktestJob $job) => $job->backtest->rebalance_frequency->value === $frequency
        && (int) $job->backtest->rebalance_day === $day && $job->backtest->rebalance_month === $month);
})->with([['fortnightly', 5, null], ['quarterly', 28, 10], ['six_monthly', 1, 4], ['yearly', 10, 12]]);

it('rejects an invalid rebalance schedule before saving or queueing it', function (string $frequency, int $day, mixed $month, string $field) {
    Queue::fake();
    $backtest = Backtest::factory()->for(User::factory()->state(['is_paid' => true]))->create();
    $this->actingAs($backtest->user)->put('/backtests/'.$backtest->id, validBacktestUpdatePayload($backtest, [
        'rebalance_frequency' => $frequency, 'rebalance_day' => $day, 'rebalance_month' => $month, 'run' => true,
    ]))->assertSessionHasErrors($field);
    expect($backtest->refresh()->rebalance_frequency->value)->toBe('monthly');
    Queue::assertNothingPushed();
})->with([
    ['fortnightly', 6, null, 'rebalance_day'], ['quarterly', 0, 1, 'rebalance_day'], ['yearly', 29, 4, 'rebalance_day'],
    ['quarterly', 10, null, 'rebalance_month'], ['six_monthly', 10, 13, 'rebalance_month'], ['yearly', 10, 0, 'rebalance_month'],
]);

it('accepts older weekly and monthly settings payloads without a rebalance month', function (string $frequency, int $day) {
    $backtest = Backtest::factory()->for(User::factory()->state(['is_paid' => true]))->create();
    $payload = validBacktestUpdatePayload($backtest, ['rebalance_frequency' => $frequency, 'rebalance_day' => $day]);
    unset($payload['rebalance_month']);
    $this->actingAs($backtest->user)->put('/backtests/'.$backtest->id, $payload)->assertSessionHasNoErrors();
    expect($backtest->fresh()->rebalance_month)->toBeNull()->and($backtest->fresh()->rebalance_frequency->value)->toBe($frequency);
})->with([['monthly', 28], ['weekly', 5]]);

it('preserves saved results if a worker receives a month-based schedule without an anchor', function () {
    $backtest = Backtest::factory()->create(['rebalance_frequency' => 'quarterly', 'rebalance_month' => null, 'started_at' => now()]);
    seedBacktestResults($backtest);
    expect(fn () => app()->call([new RunBacktestJob($backtest), 'handle']))
        ->toThrow(ValidationException::class, 'Select a first rebalance month.');
    expect($backtest->trades()->count())->toBe(1)
        ->and($backtest->dailySnapshots()->count())->toBe(1)
        ->and($backtest->summaryMetrics()->exists())->toBeTrue();
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

it('offers all seven cash call settings including cash for unfilled stock slots', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get('/backtests/'.$backtest->id)
        ->assertInertia(fn (Assert $page) => $page
            ->has('cashCallOptions', 7)
            ->where('cashCallOptions.0.id', 'no_cash_call')
            ->where('cashCallOptions.1.id', 'cash_call_if_not_enough_stocks')
            ->where('cashCallOptions.6.id', 'only_exits_allocate_to_gold_above_dma_below_index_dma')
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
    'cash_call_if_not_enough_stocks',
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

it('allows switching to cash for unfilled slots without requiring index DMA settings', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id]);
    $payload = validBacktestUpdatePayload($backtest, [
        'cash_call' => 'cash_call_if_not_enough_stocks',
        'cash_call_index' => null,
        'cash_call_dma_period' => null,
    ]);
    $this->actingAs($user)->put('/backtests/'.$backtest->id, $payload)->assertSessionHasNoErrors();

    expect($backtest->refresh()->cash_call)->toBe(BacktestCashCallEnum::CashCallIfNotEnoughStocks);
});

it('queues the saved gold DMA configuration with save and run', function () {
    Queue::fake();
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id]);
    seedRunAvailability($backtest, includeIndexData: true);

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

it('saves settings and queues a run without reading market data or clearing old results', function () {
    Queue::fake();
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id, 'status' => BacktestStatusEnum::Completed]);
    seedBacktestResults($backtest);
    seedRunAvailability($backtest);
    $marketQueries = [];
    DB::listen(function (QueryExecuted $query) use (&$marketQueries): void {
        if (preg_match('/backtest_nse_|nse_indices/', $query->sql)) {
            $marketQueries[] = $query->sql;
        }
    });

    $response = $this->actingAs($user)->put(
        '/backtests/'.$backtest->id,
        validBacktestUpdatePayload($backtest, ['name' => 'Renamed Strategy', 'run' => true])
    );

    $response->assertRedirect('/backtests/'.$backtest->id);
    $backtest->refresh();
    expect($backtest->name)->toBe('Renamed Strategy')
        ->and($backtest->status)->toBe(BacktestStatusEnum::Running)
        ->and($backtest->progress)->toBe(0)
        ->and($backtest->trades()->count())->toBe(1)
        ->and($backtest->dailySnapshots()->count())->toBe(1)
        ->and($backtest->summaryMetrics)->not->toBeNull()
        ->and($marketQueries)->toBe([]);
    Queue::assertPushed(RunBacktestJob::class);
});

it('rejects settings changes and does not queue another run when one is already in progress', function () {
    Queue::fake();
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id, 'status' => BacktestStatusEnum::Running]);
    $originalName = $backtest->name;

    $response = $this->actingAs($user)->put(
        '/backtests/'.$backtest->id,
        validBacktestUpdatePayload($backtest, ['name' => 'Renamed Strategy', 'run' => true])
    );

    $response->assertSessionHasErrors('cash_call_backtest_id');
    expect($backtest->refresh()->name)->toBe($originalName);
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
        ->and($backtest->trades()->count())->toBe(1)
        ->and($backtest->dailySnapshots()->count())->toBe(1)
        ->and($backtest->summaryMetrics)->not->toBeNull();
    Queue::assertPushed(RunBacktestJob::class);
});

it('reports preparation progress when a queue worker starts the run', function () {
    $startedAt = now()->subMinute()->startOfSecond();
    $backtest = Backtest::factory()->create([
        'status' => BacktestStatusEnum::Running,
        'progress' => 0,
        'started_at' => $startedAt,
    ]);
    seedBacktestResults($backtest);
    $runAction = mock(RunBacktestAction::class);
    $runAction->shouldReceive('validateDataAvailability')->once()->with($backtest)
        ->andReturnUsing(function (Backtest $runningBacktest): void {
            expect($runningBacktest->trades()->count())->toBe(1)
                ->and($runningBacktest->dailySnapshots()->count())->toBe(1)
                ->and($runningBacktest->summaryMetrics()->exists())->toBeTrue();
        });
    $metricsAction = mock(CalculateBacktestMetricsAction::class);

    $runAction->shouldReceive('execute')
        ->once()
        ->withArgs(function (Backtest $runningBacktest) use ($backtest): bool {
            $runningBacktest->refresh();

            expect($runningBacktest->is($backtest))->toBeTrue()
                ->and($runningBacktest->status)->toBe(BacktestStatusEnum::Running)
                ->and($runningBacktest->progress)->toBe(1)
                ->and($runningBacktest->started_at)->not->toBeNull()
                ->and($runningBacktest->trades()->exists())->toBeFalse()
                ->and($runningBacktest->dailySnapshots()->exists())->toBeFalse()
                ->and($runningBacktest->summaryMetrics()->exists())->toBeFalse();

            return true;
        });
    $metricsAction->shouldReceive('execute')->once();
    $storeAllocation = mock(StoreMarketCapAllocationAction::class);
    $storeAllocation->shouldReceive('execute')->once()->withArgs(function (Backtest $runningBacktest): bool {
        expect($runningBacktest->fresh()->progress)->toBe(98);

        return true;
    });

    (new RunBacktestJob($backtest))->handle($runAction, $metricsAction, $storeAllocation, app(BacktestNavSourceAction::class));

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

    expect(fn () => (new RunBacktestJob($backtest))->handle(...[...array_values($actions), app(BacktestNavSourceAction::class)]))
        ->toThrow(RuntimeException::class, 'Stage failed.');
    expect($backtest->refresh()->status)->toBe(BacktestStatusEnum::Failed)
        ->and($backtest->completed_at)->toBeNull()
        ->and($backtest->error_message)->toBe('Stage failed.');
})->with(['simulation', 'metrics', 'allocation']);
