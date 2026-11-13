<?php

use App\Actions\Backtest\CalculateBacktestMetricsAction;
use App\Actions\Backtest\DuplicateBacktestAction;
use App\Actions\Backtest\RunBacktestAction;
use App\Actions\Backtest\StartBacktestRunAction;
use App\Actions\Backtest\StoreMarketCapAllocationAction;
use App\Enums\BacktestCashCallSourceEnum;
use App\Enums\BacktestStatusEnum;
use App\Jobs\RunBacktestJob;
use App\Models\Backtest;
use App\Models\BacktestNseInstrumentPrice;
use App\Models\NseIndex;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{Backtest, Backtest, list<string>} */
function navSourceScenario(bool $nextDay = false): array
{
    $dates = [];
    $date = Carbon::parse('2024-01-01');
    while (count($dates) < 50) {
        if ($date->isWeekday()) {
            $dates[] = $date->toDateString();
        }
        $date->addDay();
    }

    foreach ($dates as $index => $date) {
        $price = $index < 35 ? 100 : ($index < 40 ? 50 : 150);
        createScreenResultRow('SOURCE', 'Source stock', $date, [
            'is_nifty_200' => true, 'close_adjusted' => $price, 'close_raw' => $price,
        ]);
        if ($index >= 20) {
            createScreenResultRow('TARGET', 'Target stock', $date, ['is_nifty_50' => true]);
        }
    }

    $user = User::factory()->create(['is_paid' => true]);
    $settings = [
        'user_id' => $user->id, 'initial_capital' => 10000, 'max_stocks_to_hold' => 1, 'worst_rank_held' => 1,
        'rebalance_frequency' => 'weekly', 'rebalance_day' => 1, 'cash_return_rate' => 0,
        'skip_circuit_trades' => false, 'exit_before_demerger' => false,
        'brokerage_rate' => 0, 'stt_rate' => 0, 'transaction_charges_rate' => 0,
        'sebi_charges_rate' => 0, 'gst_rate' => 0, 'stamp_charges_rate' => 0,
    ];
    $source = Backtest::factory()->create([...$settings, 'name' => 'Source NAV', 'start_date' => $dates[0], 'index' => 'nifty_200']);
    $dependent = Backtest::factory()->create([
        ...$settings, 'name' => 'Dependent strategy', 'start_date' => $dates[20], 'index' => 'nifty_50',
        'cash_call' => 'full_cash_below_index_dma', 'cash_call_source' => 'backtest',
        'cash_call_backtest_id' => $source->id, 'cash_call_dma_period' => 20,
        'execute_next_trading_day' => $nextDay,
    ]);

    return [$source, $dependent, $dates];
}

/** @return array<string, mixed> */
function navSourceSettings(Backtest $backtest, array $overrides = []): array
{
    return [...$backtest->toArray(), ...$overrides];
}

function saveNavSourcePoint(Backtest $source, string $date, float $nav): void
{
    $source->dailySnapshots()->create([
        'date' => $date, 'nav' => $nav, 'portfolio_value' => $nav * 100,
        'cash' => 0, 'total_value' => $nav * 100, 'holdings_count' => 1,
    ]);
}

it('lists only owned independent no cash call sources and saves the selection', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $dependent = Backtest::factory()->create(['user_id' => $user->id]);
    $source = Backtest::factory()->create(['user_id' => $user->id, 'name' => 'My reference']);
    Backtest::factory()->create(['is_public' => true, 'name' => 'Private source settings']);
    Backtest::factory()->create(['user_id' => $user->id, 'cash_call' => 'only_exits_below_index_dma']);
    Backtest::factory()->create(['user_id' => $user->id, 'cash_call' => 'cash_call_if_not_enough_stocks']);

    $this->actingAs($user)->get('/backtests/'.$dependent->id)->assertInertia(fn (Assert $page) => $page
        ->has('cashCallBacktestOptions', 1)
        ->where('cashCallBacktestOptions.0.id', $source->id));

    $this->put('/backtests/'.$dependent->id, navSourceSettings($dependent, [
        'cash_call' => 'full_cash_below_index_dma', 'cash_call_source' => 'backtest',
        'cash_call_backtest_id' => $source->id, 'cash_call_index' => null,
    ]))->assertSessionHasNoErrors();

    expect($dependent->refresh()->cash_call_backtest_id)->toBe($source->id)
        ->and($dependent->cash_call_source)->toBe(BacktestCashCallSourceEnum::Backtest)
        ->and($dependent->settings_changed_at)->not->toBeNull();
});

it('rejects an unavailable unauthorized self or dependent source', function (string $kind) {
    $user = User::factory()->create(['is_paid' => true]);
    $dependent = Backtest::factory()->create(['user_id' => $user->id]);
    $source = Backtest::factory()->create(['user_id' => $user->id]);
    $sourceId = $source->id;
    if ($kind === 'self') {
        $sourceId = $dependent->id;
    } elseif ($kind === 'missing') {
        $sourceId = 999999;
    } elseif ($kind === 'other owner') {
        $source->update(['user_id' => User::factory()->create()->id, 'is_public' => true]);
    } elseif ($kind === 'chain') {
        $source->update(['cash_call_source' => 'backtest', 'cash_call_backtest_id' => $dependent->id]);
    } else {
        $source->update(['cash_call' => $kind]);
    }

    $this->actingAs($user)->put('/backtests/'.$dependent->id, navSourceSettings($dependent, [
        'cash_call' => 'full_cash_below_index_dma', 'cash_call_source' => 'backtest', 'cash_call_backtest_id' => $sourceId,
    ]))->assertSessionHasErrors('cash_call_backtest_id');

    expect($dependent->refresh()->cash_call_backtest_id)->toBeNull();
})->with(['self', 'missing', 'other owner', 'chain', 'full_cash_below_index_dma', 'cash_call_if_not_enough_stocks']);

it('clears the source link when index or non DMA cash management is selected', function (string $mode) {
    [$source, $dependent] = navSourceScenario();
    $this->actingAs($dependent->user)->put('/backtests/'.$dependent->id, navSourceSettings($dependent, [
        'cash_call' => $mode, 'cash_call_source' => $mode === 'full_cash_below_index_dma' ? 'index' : 'backtest',
    ]))->assertSessionHasNoErrors();

    expect($dependent->refresh()->cash_call_source)->toBe(BacktestCashCallSourceEnum::Index)
        ->and($dependent->cash_call_backtest_id)->toBeNull();
})->with(['no_cash_call', 'cash_call_if_not_enough_stocks', 'full_cash_below_index_dma']);

it('queues the source without market reads and captures one end date for the chain in the worker', function () {
    Bus::fake();
    [$source, $dependent, $dates] = navSourceScenario();
    $marketQueries = [];
    DB::listen(function (QueryExecuted $query) use (&$marketQueries): void {
        if (preg_match('/backtest_nse_|nse_indices/', $query->sql)) {
            $marketQueries[] = $query->sql;
        }
    });

    app(StartBacktestRunAction::class)->execute($dependent);

    $job = Bus::dispatched(RunBacktestJob::class)->sole();
    expect($job->backtest->is($source))->toBeTrue()
        ->and($job->throughDate)->toBeNull()
        ->and($marketQueries)->toBe([]);
    expect($source->refresh()->status)->toBe(BacktestStatusEnum::Running)
        ->and($dependent->refresh()->status)->toBe(BacktestStatusEnum::Running)
        ->and($dependent->progress)->toBe(0);

    app()->call([$job, 'handle']);

    expect($job->throughDate)->toBe($dates[49])
        ->and($source->refresh()->status)->toBe(BacktestStatusEnum::Completed)
        ->and($dependent->refresh()->progress)->toBe(0);
    $job->assertHasChain([new RunBacktestJob($dependent, $dates[49])]);
});

it('completes the full source before running the dependent and uses decision date NAV', function (bool $nextDay) {
    [$source, $dependent, $dates] = navSourceScenario($nextDay);
    $startedDependent = false;
    Event::listen('eloquent.updated: '.Backtest::class, function (Backtest $updated) use ($source, $dependent, &$startedDependent): void {
        if ($updated->id === $dependent->id && (int) $updated->progress === 1) {
            $startedDependent = true;
            expect($source->fresh()->status)->toBe(BacktestStatusEnum::Completed)
                ->and($source->summaryMetrics()->exists())->toBeTrue()
                ->and($source->dailySnapshots()->whereNull('market_cap_allocation_calculated_at')->exists())->toBeFalse();
        }
    });

    app(StartBacktestRunAction::class)->execute($dependent);

    expect($startedDependent)->toBeTrue()
        ->and($source->refresh()->status)->toBe(BacktestStatusEnum::Completed)
        ->and($dependent->refresh()->status)->toBe(BacktestStatusEnum::Completed)
        ->and($source->trades()->where('trade_type', 'sell')->count())->toBe(0)
        ->and($dependent->trades()->where('trade_type', 'sell')->pluck('date')->map->toDateString()->all())->toBe([$dates[35 + (int) $nextDay]])
        ->and($dependent->trades()->where('trade_type', 'buy')->pluck('date')->map->toDateString()->all())->toBe([$dates[20 + (int) $nextDay], $dates[40 + (int) $nextDay]])
        ->and($dependent->trades()->where('trade_type', 'sell')->value('reason'))->toContain('source NAV');
})->with([false, true]);

it('stops the dependent if any source stage fails', function (string $stage) {
    [$source, $dependent] = navSourceScenario();
    $class = match ($stage) {
        'simulation' => RunBacktestAction::class,
        'metrics' => CalculateBacktestMetricsAction::class,
        'allocation' => StoreMarketCapAllocationAction::class,
    };
    $mock = Mockery::mock(app($class));
    $mock->shouldReceive('execute')->once()->andThrow(new RuntimeException('Source stage failed'));
    $this->instance($class, $mock);

    expect(fn () => app(StartBacktestRunAction::class)->execute($dependent))->toThrow(RuntimeException::class, 'Source stage failed');
    expect($source->refresh()->status)->toBe(BacktestStatusEnum::Failed)
        ->and($dependent->refresh()->status)->toBe(BacktestStatusEnum::Failed)
        ->and($dependent->error_message)->toContain('The source backtest failed')
        ->and($dependent->dailySnapshots()->count())->toBe(0)
        ->and($dependent->trades()->count())->toBe(0);
})->with(['simulation', 'metrics', 'allocation']);

it('fails both backtests in the source worker for missing history and keeps existing results', function (string $problem) {
    Bus::fake();
    [$source, $dependent, $dates] = navSourceScenario();
    saveNavSourcePoint($source, $dates[0], 123);
    saveNavSourcePoint($dependent, $dates[20], 111);
    if ($problem === 'warmup') {
        $source->update(['start_date' => $dates[10]]);
    } else {
        BacktestNseInstrumentPrice::query()->where('symbol', 'SOURCE')->where('date', $dates[30])->delete();
    }

    app(StartBacktestRunAction::class)->execute($dependent);
    $job = Bus::dispatched(RunBacktestJob::class)->sole();
    expect(fn () => app()->call([$job, 'handle']))->toThrow(ValidationException::class);
    expect((float) $source->dailySnapshots()->sole()->nav)->toBe(123.0)
        ->and((float) $dependent->dailySnapshots()->sole()->nav)->toBe(111.0)
        ->and($source->refresh()->status)->toBe(BacktestStatusEnum::Failed)
        ->and($dependent->refresh()->status)->toBe(BacktestStatusEnum::Failed);
    $job->assertDoesntHaveChain();
    Bus::assertDispatchedTimes(RunBacktestJob::class, 1);
})->with(['warmup', 'missing date']);

it('keeps the captured end date when more prices arrive before the dependent starts', function () {
    [$source, $dependent, $dates] = navSourceScenario();
    $newDate = Carbon::parse($dates[49])->addWeekday()->toDateString();
    Event::listen('eloquent.updated: '.Backtest::class, function (Backtest $updated) use ($source, $newDate): void {
        if ($updated->is($source) && $updated->status === BacktestStatusEnum::Completed) {
            createScreenResultRow('SOURCE', 'Source stock', $newDate, ['is_nifty_200' => true]);
            createScreenResultRow('TARGET', 'Target stock', $newDate, ['is_nifty_50' => true]);
        }
    });

    app(StartBacktestRunAction::class)->execute($dependent);

    expect($source->refresh()->status)->toBe(BacktestStatusEnum::Completed)
        ->and($dependent->refresh()->status)->toBe(BacktestStatusEnum::Completed)
        ->and($source->dailySnapshots()->max('date'))->toBe($dates[49])
        ->and($dependent->dailySnapshots()->max('date'))->toBe($dates[49]);
});

it('keeps dependent results when a completed source NAV is missing a required date', function () {
    Bus::fake();
    [$source, $dependent, $dates] = navSourceScenario();
    saveNavSourcePoint($dependent, $dates[20], 111);
    app(StartBacktestRunAction::class)->execute($dependent);
    $sourceJob = Bus::dispatched(RunBacktestJob::class)->sole();
    app()->call([$sourceJob, 'handle']);
    $source->dailySnapshots()->where('date', $dates[30])->delete();
    $dependentJob = unserialize($sourceJob->chained[0]);

    expect(fn () => app()->call([$dependentJob, 'handle']))->toThrow(ValidationException::class, 'The source NAV is missing');
    expect($dependent->refresh()->status)->toBe(BacktestStatusEnum::Failed)
        ->and((float) $dependent->dailySnapshots()->sole()->nav)->toBe(111.0);
});

it('fails both queued backtests if source dispatch fails without deleting results', function () {
    [$source, $dependent, $dates] = navSourceScenario();
    saveNavSourcePoint($source, $dates[0], 123);
    saveNavSourcePoint($dependent, $dates[20], 111);
    Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Queue unavailable'));

    expect(fn () => app(StartBacktestRunAction::class)->execute($dependent))
        ->toThrow(RuntimeException::class, 'Queue unavailable');

    expect($source->refresh()->status)->toBe(BacktestStatusEnum::Failed)
        ->and($dependent->refresh()->status)->toBe(BacktestStatusEnum::Failed)
        ->and((float) $source->dailySnapshots()->sole()->nav)->toBe(123.0)
        ->and((float) $dependent->dailySnapshots()->sole()->nav)->toBe(111.0);
});

it('does not start duplicate work or allow source edits during a dependent run', function () {
    Bus::fake();
    [$source, $dependent] = navSourceScenario();
    app(StartBacktestRunAction::class)->execute($dependent);

    $this->actingAs($source->user)->put('/backtests/'.$source->id, navSourceSettings($source, ['name' => 'Changed']))
        ->assertSessionHasErrors('cash_call_backtest_id');
    $this->delete('/backtests/'.$source->id)->assertSessionHasErrors('cash_call_backtest_id');
    expect(fn () => app(StartBacktestRunAction::class)->execute($source))->toThrow(ValidationException::class);
    expect(fn () => app(StartBacktestRunAction::class)->execute($dependent->fresh()))->toThrow(ValidationException::class);
});

it('does not switch to an index when the source is deleted', function () {
    [$source, $dependent] = navSourceScenario();
    $source->delete();
    $dependent->refresh();
    expect($dependent->cash_call_backtest_id)->toBeNull()
        ->and($dependent->cash_call_source)->toBe(BacktestCashCallSourceEnum::Backtest);
    expect(fn () => app(StartBacktestRunAction::class)->execute($dependent))->toThrow(ValidationException::class);
});

it('hides source identities and choices on public results', function () {
    [$source, $dependent] = navSourceScenario();
    $dependent->update(['is_public' => true, 'status' => BacktestStatusEnum::Completed]);
    $this->get(route('public-backtests.show', $dependent))->assertInertia(fn (Assert $page) => $page
        ->missing('backtest.cash_call_backtest_id')->has('cashCallBacktestOptions', 0)->where('cashCallBacktest', null));
});

it('keeps dependent saved results unchanged after an independent source rerun', function () {
    [$source, $dependent, $dates] = navSourceScenario();
    app(StartBacktestRunAction::class)->execute($dependent);
    $nav = $dependent->dailySnapshots()->pluck('nav', 'date')->all();
    $trades = $dependent->trades()->get()->toArray();
    $metrics = $dependent->summaryMetrics()->first()->toArray();

    BacktestNseInstrumentPrice::query()->where('symbol', 'SOURCE')->where('date', '>=', $dates[35])
        ->update(['close_adjusted' => 200, 'close_raw' => 200]);
    app(StartBacktestRunAction::class)->execute($source->fresh());

    expect($dependent->dailySnapshots()->pluck('nav', 'date')->all())->toBe($nav)
        ->and($dependent->trades()->get()->toArray())->toBe($trades)
        ->and($dependent->summaryMetrics()->first()->toArray())->toBe($metrics);

    app(StartBacktestRunAction::class)->execute($dependent->fresh());
    expect($dependent->trades()->where('trade_type', 'sell')->count())->toBe(0);
});

it('uses the same cash and gold rules for NAV and identical index signals including stock stop replacements', function (string $mode, bool $nextDay) {
    [$source, $dependent, $dates] = navSourceScenario($nextDay);
    foreach ($dates as $index => $date) {
        createScreenResultRow('GOLDBEES', 'Gold', $date, ['ma_50' => 90]);
        if ($index >= 20) {
            createScreenResultRow('OTHER', 'Other stock', $date, [
                'is_nifty_50' => true, 'sharpe_return_one_year' => $index >= 35 ? 20 : 5,
            ]);
        }
        if (in_array($index, [27, 28], true)) {
            BacktestNseInstrumentPrice::query()->where('symbol', 'SOURCE')->where('date', $date)->update(['close_adjusted' => 50]);
            BacktestNseInstrumentPrice::query()->where('symbol', 'TARGET')->where('date', $date)->update(['close_adjusted' => $index === 27 ? 80 : 79]);
        }
    }
    app(StartBacktestRunAction::class)->execute($source);
    foreach ($source->dailySnapshots()->get(['date', 'nav']) as $point) {
        NseIndex::create(['symbol' => 'Test NAV index', 'slug' => 'test-nav-index', 'date' => $point->date, 'close' => $point->nav]);
    }
    $dependent->update([
        'cash_call' => $mode, 'apply_stop_loss' => true, 'stop_loss_percentage' => 10,
        'stop_loss_proceeds' => 'replace_immediately',
    ]);
    $indexBased = $dependent->replicate()->fill([
        'name' => 'Equivalent index source', 'cash_call_source' => 'index',
        'cash_call_backtest_id' => null, 'cash_call_index' => 'test-nav-index',
    ]);
    $indexBased->save();

    app(RunBacktestAction::class)->execute($dependent);
    app(RunBacktestAction::class)->execute($indexBased);

    $columns = ['symbol', 'date', 'trade_type', 'quantity', 'price', 'gross_amount', 'total_charges', 'net_amount'];
    expect($dependent->trades()->orderBy('id')->get($columns)->toArray())
        ->toBe($indexBased->trades()->orderBy('id')->get($columns)->toArray())
        ->and($dependent->dailySnapshots()->pluck('nav', 'date')->all())
        ->toBe($indexBased->dailySnapshots()->pluck('nav', 'date')->all())
        ->and($dependent->trades()->where('reason', 'like', '%Stop loss%')->exists())->toBeTrue();
})->with([
    'full_cash_below_index_dma', 'only_exits_below_index_dma', 'allocate_to_gold_below_index_dma',
    'only_exits_allocate_to_gold_below_index_dma', 'only_exits_allocate_to_gold_above_dma_below_index_dma',
])->with([false, true]);

it('does not use execution day NAV when next day execution is selected', function () {
    [$source, $dependent, $dates] = navSourceScenario(true);
    BacktestNseInstrumentPrice::query()->where('symbol', 'SOURCE')->where('date', $dates[36])->update(['close_adjusted' => 200]);
    app(StartBacktestRunAction::class)->execute($dependent);
    expect($dependent->trades()->where('trade_type', 'sell')->whereDate('date', $dates[36])->exists())->toBeTrue();
});

it('keeps both simulations on the captured end date when more market dates arrive between jobs', function () {
    [$source, $dependent, $dates] = navSourceScenario();
    $newDate = Carbon::parse($dates[49])->addWeekday()->toDateString();
    Event::listen('eloquent.updated: '.Backtest::class, function (Backtest $updated) use ($source, $newDate): void {
        if ($updated->id === $source->id && $updated->status === BacktestStatusEnum::Completed) {
            createScreenResultRow('SOURCE', 'Source stock', $newDate, ['is_nifty_200' => true]);
            createScreenResultRow('TARGET', 'Target stock', $newDate, ['is_nifty_50' => true]);
        }
    });
    app(StartBacktestRunAction::class)->execute($dependent);
    expect($source->dailySnapshots()->max('date'))->toBe($dates[49])
        ->and($dependent->dailySnapshots()->max('date'))->toBe($dates[49])
        ->and($dependent->fresh()->status)->toBe(BacktestStatusEnum::Completed);
});

it('rejects incomplete failed or invalid completed source NAV data', function (string $problem) {
    [$source, $dependent, $dates] = navSourceScenario();
    $source->update(['status' => $problem === 'failed' ? BacktestStatusEnum::Failed : BacktestStatusEnum::Completed]);
    foreach ($dates as $index => $date) {
        if (($problem === 'warmup gap' && $index === 19) || ($problem === 'simulation gap' && $index === 30)) {
            continue;
        }
        saveNavSourcePoint($source, $date, $problem === 'negative NAV' && $index === 19 ? -5 : 100);
    }

    expect(fn () => app(RunBacktestAction::class)->execute($dependent))->toThrow(ValidationException::class);
    expect($dependent->trades()->count())->toBe(0)
        ->and($dependent->dailySnapshots()->count())->toBe(0);
})->with(['failed', 'warmup gap', 'simulation gap', 'negative NAV']);

it('propagates a source worker timeout without changing a newer dependent run', function (bool $newerRun) {
    Bus::fake();
    [$source, $dependent, $dates] = navSourceScenario();
    app(StartBacktestRunAction::class)->execute($dependent);
    $source->refresh();
    $job = unserialize(serialize(new RunBacktestJob($source, $dates[49], $dependent)));
    if ($newerRun) {
        $dependent->update(['started_at' => now()->addMinute()]);
    }
    $job->failed(new RuntimeException('Source worker timed out'));

    expect($source->refresh()->status)->toBe(BacktestStatusEnum::Failed)
        ->and($dependent->refresh()->status)->toBe($newerRun ? BacktestStatusEnum::Running : BacktestStatusEnum::Failed);
})->with([false, true]);

it('copies the selected source as a setting without copying any results', function () {
    [$source, $dependent] = navSourceScenario();
    $copy = app(DuplicateBacktestAction::class)->execute($dependent);
    expect($copy->cash_call_source)->toBe(BacktestCashCallSourceEnum::Backtest)
        ->and($copy->cash_call_backtest_id)->toBe($source->id)
        ->and($copy->dailySnapshots()->count())->toBe(0);
});
