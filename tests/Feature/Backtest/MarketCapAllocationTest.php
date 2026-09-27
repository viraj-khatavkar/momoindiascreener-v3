<?php

use App\Actions\Backtest\CalculateBacktestMetricsAction;
use App\Actions\Backtest\CalculateMarketCapAllocationAction;
use App\Actions\Backtest\LoadMarketCapAllocationAction;
use App\Actions\Backtest\RunBacktestAction;
use App\Actions\Backtest\StoreMarketCapAllocationAction;
use App\Enums\BacktestStatusEnum;
use App\Jobs\RunBacktestJob;
use App\Models\Backtest;
use App\Models\BacktestDailySnapshot;
use App\Models\BacktestNseInstrumentPrice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function seedAllocationCoverage(string $date, bool $large = true, bool $mid = true): void
{
    createBacktestPriceRow('LARGE-REFERENCE', $date, ['is_nifty_100' => $large]);
    createBacktestPriceRow('MID-REFERENCE', $date, ['is_nifty_midcap_150' => $mid]);
}

function seedAllocationSnapshot(Backtest $backtest, string $date, float $cash = 200, float $total = 1000): void
{
    $backtest->dailySnapshots()->create([
        'date' => $date, 'nav' => 100, 'portfolio_value' => $total - $cash,
        'cash' => $cash, 'total_value' => $total, 'holdings_count' => 0,
    ]);
}

function seedAllocationTrade(Backtest $backtest, string $date, string $symbol, int $quantity, float $price, string $type = 'buy'): void
{
    $backtest->trades()->create([
        'symbol' => $symbol, 'trade_type' => $type, 'reason' => 'Rebalance', 'date' => $date,
        'quantity' => $quantity, 'price' => $price, 'raw_price' => $price,
        'gross_amount' => $quantity * $price, 'net_amount' => $quantity * $price,
        'stt' => 0, 'transaction_charges' => 0, 'sebi_charges' => 0, 'gst' => 0,
        'stamp_charges' => 0, 'total_charges' => 0,
    ]);
}

it('starts with common membership coverage and values existing holdings with historical prices and membership', function () {
    $backtest = Backtest::factory()->create();
    $dates = ['2017-07-28', '2017-07-31', '2017-08-01'];

    foreach ($dates as $index => $date) {
        seedAllocationCoverage($date, mid: $index > 0);
        seedAllocationSnapshot($backtest, $date, total: $index === 2 ? 1200 : 1000);
        createBacktestPriceRow('LARGE', $date, ['close_adjusted' => $index === 2 ? 20 : 10, 'is_nifty_100' => $index < 2, 'is_nifty_midcap_150' => $index === 2]);
        createBacktestPriceRow('MID', $date, ['close_adjusted' => $index === 2 ? 30 : 20, 'is_nifty_midcap_150' => $index > 0]);
        createBacktestPriceRow('SMALL', $date, ['close_adjusted' => 30]);
        createBacktestPriceRow('GOLDBEES', $date, ['close_adjusted' => 40, 'is_etf' => true]);
    }

    seedAllocationTrade($backtest, $dates[0], 'LARGE', 10, 10);
    seedAllocationTrade($backtest, $dates[0], 'MID', 10, 20);
    seedAllocationTrade($backtest, $dates[0], 'SMALL', 10, 30);
    seedAllocationTrade($backtest, $dates[0], 'GOLDBEES', 5, 40);

    $result = app(CalculateMarketCapAllocationAction::class)->execute($backtest);

    expect($result['start_date'])->toBe('2017-07-31')
        ->and($result['excluded_days'])->toBe(1)
        ->and($result['points'])->toHaveCount(2)
        ->and($result['points'][0])->toBe([
            'date' => '2017-07-31', 'large_cap' => 10.0, 'mid_cap' => 20.0,
            'small_cap' => 30.0, 'etf' => 20.0, 'cash' => 20.0,
        ])
        ->and($result['points'][1])->toBe([
            'date' => '2017-08-01', 'large_cap' => 0.0, 'mid_cap' => 41.6667,
            'small_cap' => 25.0, 'etf' => 16.6667, 'cash' => 16.6667,
        ]);
});

it('requires both indices on the same date and skips later gaps in coverage', function () {
    $backtest = Backtest::factory()->create();

    foreach (['2017-07-28', '2017-07-31', '2017-08-01', '2017-08-02'] as $index => $date) {
        seedAllocationSnapshot($backtest, $date, cash: 1000);
        seedAllocationCoverage($date, large: $index !== 1, mid: $index !== 0 && $index !== 3);
    }

    $result = app(CalculateMarketCapAllocationAction::class)->execute($backtest);

    expect($result['start_date'])->toBe('2017-08-01')
        ->and($result['excluded_days'])->toBe(3)
        ->and($result['points'])->toHaveCount(1)
        ->and($result['points'][0]['cash'])->toBe(100.0)
        ->and($result['points'][0]['small_cap'])->toBe(0.0);
});

it('returns no allocation when either index has no historical coverage', function (bool $large, bool $mid) {
    $backtest = Backtest::factory()->create();
    seedAllocationSnapshot($backtest, '2017-07-31');
    seedAllocationCoverage('2017-07-31', $large, $mid);

    expect(app(CalculateMarketCapAllocationAction::class)->execute($backtest))
        ->toBe(['start_date' => null, 'excluded_days' => 1, 'points' => []]);
})->with([[false, true], [true, false], [false, false]]);

it('uses end of day quantities after partial sales and same day reentry and keeps ETFs separate', function () {
    $backtest = Backtest::factory()->create();

    foreach (['2017-07-31', '2017-08-01'] as $date) {
        seedAllocationCoverage($date);
        seedAllocationSnapshot($backtest, $date, cash: 200);
        createBacktestPriceRow('STOCK', $date, ['close_adjusted' => 100, 'is_nifty_100' => true, 'is_nifty_midcap_150' => true]);
        createBacktestPriceRow('ETF', $date, ['close_adjusted' => 100, 'is_etf' => true, 'is_nifty_100' => true]);
    }

    seedAllocationTrade($backtest, '2017-07-31', 'STOCK', 6, 100);
    seedAllocationTrade($backtest, '2017-07-31', 'ETF', 2, 100);
    seedAllocationTrade($backtest, '2017-08-01', 'STOCK', 2, 100, 'sell');
    seedAllocationTrade($backtest, '2017-08-01', 'ETF', 2, 100, 'sell');
    seedAllocationTrade($backtest, '2017-08-01', 'ETF', 4, 100);

    $points = app(CalculateMarketCapAllocationAction::class)->execute($backtest)['points'];

    expect($points[0]['large_cap'])->toBe(60.0)
        ->and($points[0]['etf'])->toBe(20.0)
        ->and($points[1]['large_cap'])->toBe(40.0)
        ->and($points[1]['mid_cap'])->toBe(0.0)
        ->and($points[1]['etf'])->toBe(40.0)
        ->and($points[1]['cash'])->toBe(20.0);
});

it('carries the last known price and membership when a held stock has no new quote', function () {
    $backtest = Backtest::factory()->create();

    foreach (['2017-07-31', '2017-08-01'] as $date) {
        seedAllocationCoverage($date);
        seedAllocationSnapshot($backtest, $date);
    }

    createBacktestPriceRow('STOCK', '2017-07-31', ['close_adjusted' => 100, 'is_nifty_100' => true]);
    createBacktestPriceRow('STOCK', '2017-08-02', ['close_adjusted' => 200, 'is_nifty_midcap_150' => true]);
    seedAllocationTrade($backtest, '2017-07-31', 'STOCK', 8, 100);

    $points = app(CalculateMarketCapAllocationAction::class)->execute($backtest)['points'];

    expect($points)->toHaveCount(2)
        ->and($points[1]['large_cap'])->toBe(80.0)
        ->and($points[1]['mid_cap'])->toBe(0.0)
        ->and($points[1]['cash'])->toBe(20.0);
});

it('excludes dates with unknown holding membership or zero portfolio value', function () {
    $backtest = Backtest::factory()->create();
    seedAllocationCoverage('2017-07-31');
    seedAllocationCoverage('2017-08-01');
    seedAllocationSnapshot($backtest, '2017-07-31');
    seedAllocationSnapshot($backtest, '2017-08-01', cash: 0, total: 0);
    seedAllocationTrade($backtest, '2017-07-31', 'UNKNOWN', 8, 100);

    expect(app(CalculateMarketCapAllocationAction::class)->execute($backtest))
        ->toBe(['start_date' => null, 'excluded_days' => 2, 'points' => []]);
});

it('keeps cash as a percentage of the saved total when adjusted historical prices change', function () {
    $backtest = Backtest::factory()->create();
    seedAllocationCoverage('2017-07-31');
    seedAllocationSnapshot($backtest, '2017-07-31', cash: 200, total: 1000);
    seedAllocationTrade($backtest, '2017-07-31', 'STOCK', 8, 100);
    createBacktestPriceRow('STOCK', '2017-07-31', ['close_adjusted' => 50, 'is_nifty_100' => true]);

    $point = app(CalculateMarketCapAllocationAction::class)->execute($backtest)['points'][0];

    expect($point['large_cap'])->toBe(80.0)
        ->and($point['cash'])->toBe(20.0);
});

it('carries holdings across price batches without querying for each trading day', function () {
    $backtest = Backtest::factory()->create();

    for ($index = 0; $index < 127; $index++) {
        $date = now()->startOfYear()->addDays($index)->toDateString();
        seedAllocationCoverage($date);
        seedAllocationSnapshot($backtest, $date);
        createBacktestPriceRow('STOCK', $date, ['close_adjusted' => 100]);

        if ($index === 0) {
            seedAllocationTrade($backtest, $date, 'STOCK', 8, 100);
        }
    }

    DB::enableQueryLog();
    DB::flushQueryLog();

    try {
        $result = app(CalculateMarketCapAllocationAction::class)->execute($backtest);
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
    }

    expect($result['points'])->toHaveCount(127)
        ->and($result['points'][126]['small_cap'])->toBe(80.0)
        ->and($queries)->toHaveCount(5);
});

it('loads allocation as a separate deferred prop for completed backtests', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id, 'status' => BacktestStatusEnum::Completed]);
    seedAllocationCoverage('2017-07-31');
    seedAllocationSnapshot($backtest, '2017-07-31', cash: 1000);
    app(StoreMarketCapAllocationAction::class)->execute($backtest);
    $this->mock(CalculateMarketCapAllocationAction::class)->shouldNotReceive('execute');

    $this->actingAs($user)->get('/backtests/'.$backtest->id)
        ->assertInertia(fn (Assert $page) => $page
            ->missing('marketCapAllocation')
            ->reloadOnly('marketCapAllocation', fn (Assert $reload) => $reload
                ->where('marketCapAllocation.start_date', '2017-07-31')
                ->has('marketCapAllocation.points', 1)
                ->where('marketCapAllocation.points.0.cash', 100)
            )
        );
});

it('does not expose allocation for another users backtest', function () {
    $backtest = Backtest::factory()->create(['status' => BacktestStatusEnum::Completed]);
    $other = User::factory()->create(['is_paid' => true]);

    $this->actingAs($other)->get('/backtests/'.$backtest->id, [
        'X-Inertia-Partial-Component' => 'Backtests/Show',
        'X-Inertia-Partial-Data' => 'marketCapAllocation',
    ])->assertNotFound();
});

it('returns no allocation for a backtest without results', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id]);

    expect(app(CalculateMarketCapAllocationAction::class)->execute($backtest))
        ->toBe(['start_date' => null, 'excluded_days' => 0, 'points' => []]);

    $this->actingAs($user)->get('/backtests/'.$backtest->id)
        ->assertInertia(fn (Assert $page) => $page->where('marketCapAllocation', null));
});

it('stores daily allocation and excluded dates without changing saved NAV or trades', function () {
    $backtest = Backtest::factory()->create(['status' => BacktestStatusEnum::Completed]);
    seedAllocationSnapshot($backtest, '2017-07-28');
    seedAllocationSnapshot($backtest, '2017-07-31');
    seedAllocationCoverage('2017-07-31');
    createBacktestPriceRow('STOCK', '2017-07-31', ['close_adjusted' => 100, 'is_nifty_100' => true]);
    seedAllocationTrade($backtest, '2017-07-28', 'STOCK', 8, 100);
    $originalSnapshots = $backtest->dailySnapshots()->orderBy('date')->get()->toArray();
    $originalTrades = $backtest->trades()->get()->toArray();

    expect(app(StoreMarketCapAllocationAction::class)->execute($backtest))->toBe(2);

    $snapshots = $backtest->dailySnapshots()->orderBy('date')->get();
    expect($snapshots[0]->market_cap_allocation)->toBeNull()
        ->and($snapshots[0]->market_cap_allocation_calculated_at)->not->toBeNull()
        ->and($snapshots[1]->market_cap_allocation)->toEqual([
            'large_cap' => 80.0, 'mid_cap' => 0.0, 'small_cap' => 0.0, 'etf' => 0.0, 'cash' => 20.0,
        ])
        ->and($snapshots[1]->market_cap_allocation_calculated_at)->not->toBeNull()
        ->and($snapshots->map(fn (BacktestDailySnapshot $snapshot): array => array_replace(
            $snapshot->toArray(), ['market_cap_allocation' => null, 'market_cap_allocation_calculated_at' => null],
        ))->all())->toBe($originalSnapshots)
        ->and($backtest->trades()->get()->toArray())->toBe($originalTrades);

    $this->mock(CalculateMarketCapAllocationAction::class)->shouldNotReceive('execute');
    expect(app(StoreMarketCapAllocationAction::class)->execute($backtest))->toBe(0);
});

it('reads saved allocation with one query and no price or trade calculations', function () {
    $backtest = Backtest::factory()->create();
    seedAllocationSnapshot($backtest, '2017-07-28', cash: 1000);
    seedAllocationSnapshot($backtest, '2017-07-31', cash: 1000);
    seedAllocationCoverage('2017-07-31');
    $expected = app(CalculateMarketCapAllocationAction::class)->execute($backtest);
    app(StoreMarketCapAllocationAction::class)->execute($backtest);
    BacktestNseInstrumentPrice::query()->delete();
    $this->mock(CalculateMarketCapAllocationAction::class)->shouldNotReceive('execute');
    DB::enableQueryLog();
    DB::flushQueryLog();

    try {
        $result = app(LoadMarketCapAllocationAction::class)->execute($backtest);
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
    }

    expect($result)->toBe($expected)
        ->and($queries)->toHaveCount(1)
        ->and($queries[0]['query'])->toContain('backtest_daily_snapshots')
        ->not->toContain('backtest_nse_instrument_prices', 'backtest_trades');
});

it('fills only missing allocation for completed backtests and skips checked dates on later runs', function () {
    $completed = Backtest::factory()->create(['status' => BacktestStatusEnum::Completed]);
    seedAllocationSnapshot($completed, '2017-07-28', cash: 1000);
    seedAllocationSnapshot($completed, '2017-07-31', cash: 1000);
    seedAllocationCoverage('2017-07-31');
    $unfinished = collect([BacktestStatusEnum::Pending, BacktestStatusEnum::Running, BacktestStatusEnum::Failed])
        ->map(function (BacktestStatusEnum $status): Backtest {
            $backtest = Backtest::factory()->create(['status' => $status]);
            seedAllocationSnapshot($backtest, '2017-07-31', cash: 1000);

            return $backtest;
        });

    $this->artisan('backtest:backfill-market-cap-allocation')
        ->expectsOutput('Processed 1 completed backtests.')->assertSuccessful();

    expect($completed->dailySnapshots()->whereNull('market_cap_allocation_calculated_at')->count())->toBe(0);
    foreach ($unfinished as $backtest) {
        expect($backtest->dailySnapshots()->first()->market_cap_allocation_calculated_at)->toBeNull();
    }

    $this->mock(CalculateMarketCapAllocationAction::class)->shouldNotReceive('execute');
    $this->artisan('backtest:backfill-market-cap-allocation')
        ->expectsOutput('Processed 0 completed backtests.')->assertSuccessful();
});

it('can refresh one completed backtest when historical index data changes', function () {
    $first = Backtest::factory()->create(['status' => BacktestStatusEnum::Completed]);
    $second = Backtest::factory()->create(['status' => BacktestStatusEnum::Completed]);

    foreach ([$first, $second] as $backtest) {
        seedAllocationSnapshot($backtest, '2017-07-31', cash: 1000);
    }

    $this->artisan('backtest:backfill-market-cap-allocation', ['--backtest' => $first->id])->assertSuccessful();
    expect($first->dailySnapshots()->first()->market_cap_allocation)->toBeNull();
    seedAllocationCoverage('2017-07-31');

    $this->artisan('backtest:backfill-market-cap-allocation', ['--backtest' => $first->id, '--refresh' => true])->assertSuccessful();

    expect($first->dailySnapshots()->first()->market_cap_allocation['cash'])->toEqual(100.0)
        ->and($second->dailySnapshots()->first()->market_cap_allocation_calculated_at)->toBeNull();

    BacktestNseInstrumentPrice::query()->update(['is_nifty_midcap_150' => false]);
    $this->artisan('backtest:backfill-market-cap-allocation', ['--backtest' => $first->id, '--refresh' => true])->assertSuccessful();
    expect($first->dailySnapshots()->first()->market_cap_allocation)->toBeNull()
        ->and($first->dailySnapshots()->first()->market_cap_allocation_calculated_at)->not->toBeNull();
});

it('rejects invalid or unavailable backtest IDs for allocation backfill', function (string $id) {
    $this->artisan('backtest:backfill-market-cap-allocation', ['--backtest' => $id])->assertFailed();
})->with(['0', '-1', 'invalid', '999999999']);

it('saves allocation before a new backtest job is marked complete', function () {
    $backtest = Backtest::factory()->create(['status' => BacktestStatusEnum::Running]);
    seedAllocationCoverage('2017-07-31');
    $runAction = $this->mock(RunBacktestAction::class);
    $runAction->shouldReceive('execute')->once()->andReturnUsing(function (Backtest $running): void {
        seedAllocationSnapshot($running, '2017-07-31', cash: 1000);
    });
    $metricsAction = $this->mock(CalculateBacktestMetricsAction::class);
    $metricsAction->shouldReceive('execute')->once();

    app()->call([new RunBacktestJob($backtest), 'handle']);

    expect($backtest->refresh()->status)->toBe(BacktestStatusEnum::Completed)
        ->and($backtest->dailySnapshots()->first()->market_cap_allocation['cash'])->toEqual(100.0)
        ->and($backtest->dailySnapshots()->first()->market_cap_allocation_calculated_at)->not->toBeNull();
});

it('does not attach old allocation to replacement snapshots from a concurrent rerun', function () {
    $backtest = Backtest::factory()->create();
    seedAllocationSnapshot($backtest, '2017-07-31', cash: 1000);
    $this->mock(CalculateMarketCapAllocationAction::class)->shouldReceive('execute')->once()
        ->andReturnUsing(function () use ($backtest): array {
            $backtest->dailySnapshots()->delete();
            seedAllocationSnapshot($backtest, '2017-07-31', cash: 200);

            return ['points' => [[
                'date' => '2017-07-31', 'large_cap' => 0.0, 'mid_cap' => 0.0,
                'small_cap' => 0.0, 'etf' => 0.0, 'cash' => 100.0,
            ]]];
        });

    expect(app(StoreMarketCapAllocationAction::class)->execute($backtest))->toBe(0)
        ->and($backtest->dailySnapshots()->count())->toBe(1)
        ->and($backtest->dailySnapshots()->first()->market_cap_allocation)->toBeNull()
        ->and($backtest->dailySnapshots()->first()->market_cap_allocation_calculated_at)->toBeNull();
});

it('stores a long history in batches without changing existing allocations', function () {
    $backtest = Backtest::factory()->create();
    $rows = [];
    $points = [];

    for ($index = 0; $index < 501; $index++) {
        $date = now()->startOfYear()->addDays($index)->toDateString();
        $rows[] = [
            'backtest_id' => $backtest->id, 'date' => $date, 'nav' => 100,
            'portfolio_value' => 800, 'cash' => 200, 'total_value' => 1000, 'holdings_count' => 1,
        ];
        $points[] = [
            'date' => $date, 'large_cap' => $index % 80, 'mid_cap' => 80 - $index % 80,
            'small_cap' => 0.0, 'etf' => 0.0, 'cash' => 20.0,
        ];
    }

    BacktestDailySnapshot::insert($rows);
    $this->mock(CalculateMarketCapAllocationAction::class)->shouldReceive('execute')->once()->andReturn(['points' => $points]);
    DB::enableQueryLog();
    DB::flushQueryLog();

    try {
        $updated = app(StoreMarketCapAllocationAction::class)->execute($backtest);
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
    }

    expect($updated)->toBe(501)
        ->and($queries)->toHaveCount(3)
        ->and($backtest->dailySnapshots()->whereNull('market_cap_allocation_calculated_at')->count())->toBe(0)
        ->and($backtest->dailySnapshots()->orderBy('date')->first()->market_cap_allocation['mid_cap'])->toEqual(80)
        ->and($backtest->dailySnapshots()->orderByDesc('date')->first()->market_cap_allocation['large_cap'])->toEqual(20);

    seedAllocationSnapshot($backtest, now()->startOfYear()->addDays(501)->toDateString(), cash: 1000);
    $this->mock(CalculateMarketCapAllocationAction::class)->shouldReceive('execute')->once()->andReturn(['points' => []]);
    expect(app(StoreMarketCapAllocationAction::class)->execute($backtest))->toBe(1)
        ->and($backtest->dailySnapshots()->orderBy('date')->first()->market_cap_allocation['mid_cap'])->toEqual(80);
});
