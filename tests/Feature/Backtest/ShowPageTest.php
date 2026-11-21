<?php

use App\Actions\Backtest\LoadBenchmarkSeriesAction;
use App\Enums\BacktestStatusEnum;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Backtest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withHeader('X-Inertia-Version', app(HandleInertiaRequests::class)->version(Request::create('/')) ?? '');
});

it('renders the unified backtest page for its owner', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get('/backtests/'.$backtest->id)
        ->assertOk();
});

it('returns 404 for another users backtest', function () {
    $owner = User::factory()->create(['is_paid' => true]);
    $other = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other)
        ->get('/backtests/'.$backtest->id)
        ->assertNotFound();
});

it('skips the default benchmark history when a shared result selects another comparison', function (string $benchmark) {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->for($user)->create(['status' => BacktestStatusEnum::Completed]);
    $this->mock(LoadBenchmarkSeriesAction::class)->shouldNotReceive('execute');

    $this->actingAs($user)->get('/backtests/'.$backtest->id.'?benchmark='.$benchmark, [
        'X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'Backtests/Show',
        'X-Inertia-Partial-Data' => 'defaultBenchmark',
    ])->assertSuccessful()->assertJsonPath('props.defaultBenchmark', null);
})->with(['nifty-500', 'none']);

it('defers the trade log and returns every trade in bounded pages without duplicates', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id, 'status' => BacktestStatusEnum::Completed]);
    $trade = [
        'backtest_id' => $backtest->id, 'symbol' => 'STOCK', 'name' => 'Stock', 'trade_type' => 'buy',
        'reason' => 'Rebalance', 'date' => '2018-01-01', 'quantity' => 1, 'price' => 100, 'raw_price' => 100,
        'gross_amount' => 100, 'brokerage' => 0, 'stt' => 0, 'transaction_charges' => 0, 'sebi_charges' => 0,
        'gst' => 0, 'stamp_charges' => 0, 'total_charges' => 1, 'net_amount' => 101,
    ];
    DB::table('backtest_trades')->insert(array_fill(0, 301, $trade));
    DB::table('backtest_trades')->insert(array_fill(0, 200, [...$trade, 'trade_type' => 'sell', 'net_amount' => 99]));
    $lastTrade = $backtest->trades()->create(array_replace($trade, [
        'date' => '2018-01-02', 'trade_type' => 'sell', 'quantity' => 501,
        'gross_amount' => 50100, 'total_charges' => 5, 'net_amount' => 50095,
        'realized_pnl' => -506, 'realized_pnl_pct' => -1.0,
    ]));

    $this->actingAs($user)->get('/backtests/'.$backtest->id)
        ->assertInertia(fn (Assert $page) => $page->missing('trades')->missing('tradeLogSummary'));

    DB::enableQueryLog();
    DB::flushQueryLog();

    try {
        $response = $this->actingAs($user)->get('/backtests/'.$backtest->id, [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'Backtests/Show',
            'X-Inertia-Partial-Data' => 'trades',
        ]);
        $queries = collect(DB::getQueryLog())->pluck('query')
            ->filter(fn (string $query): bool => str_contains($query, 'from `backtest_trades`') && ! str_contains($query, 'count(*)'));
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    $response->assertSuccessful()
        ->assertJsonCount(100, 'props.trades.data')
        ->assertJsonPath('props.trades.total', 502)
        ->assertJsonPath('props.trades.data.0', [
            ...$lastTrade->fresh()->toArray(), 'reason_category' => 'filter-exit',
            'day_totals' => ['buys' => 0, 'sells' => 1, 'bought' => 0, 'sold' => 50100, 'charges' => 5],
        ])
        ->assertJsonPath('scrollProps.trades.pageName', 'trades_page')
        ->assertJsonPath('scrollProps.trades.nextPage', 2)
        ->assertJsonPath('mergeProps', ['trades.data'])
        ->assertJsonPath('matchPropsOn', ['trades.data.id']);
    expect($queries)->toHaveCount(2);
    expect($queries->first())->toContain('limit 100');
    expect($queries->last())->toContain('`date` in (', 'group by `date`');

    $dayTotals = ['buys' => 301, 'sells' => 200, 'bought' => 30100, 'sold' => 20000, 'charges' => 501];
    expect(array_column($response->json('props.trades.data'), 'day_totals'))->toHaveCount(2)->toContain($dayTotals);
    $ids = array_column($response->json('props.trades.data'), 'id');
    for ($page = 2; $page <= 6; $page++) {
        $response = $this->get('/backtests/'.$backtest->id.'?trades_page='.$page, [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'Backtests/Show',
            'X-Inertia-Partial-Data' => 'trades',
        ])->assertSuccessful()->assertJsonCount($page === 6 ? 2 : 100, 'props.trades.data');
        expect(array_column($response->json('props.trades.data'), 'day_totals'))->toBe([$dayTotals]);
        array_push($ids, ...array_column($response->json('props.trades.data'), 'id'));
    }
    expect(array_unique($ids))->toHaveCount(502);
    expect($ids)->toBe($backtest->trades()->orderByDesc('date')->orderBy('trade_type')->orderBy('id')->pluck('id')->all());
    $response->assertJsonPath('scrollProps.trades.nextPage', null);
});

it('searches and filters the full trade history and resets the scroll results', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id, 'status' => BacktestStatusEnum::Completed]);
    DB::table('backtest_trades')->insert(array_fill(0, 150, tradeLogAttributes($backtest)));
    $target = $backtest->trades()->create(tradeLogAttributes($backtest, [
        'symbol' => 'FINDME', 'name' => 'Special Company', 'date' => '2017-01-02', 'trade_type' => 'sell',
        'reason' => 'Rank exceeded exit threshold - rotating to gold',
    ]));
    $backtest->trades()->create(tradeLogAttributes($backtest, [
        'symbol' => 'FINDME', 'name' => 'Special Company', 'date' => '2017-01-02', 'reason' => 'New entry',
    ]));
    $otherBacktest = Backtest::factory()->create(['user_id' => $user->id, 'status' => BacktestStatusEnum::Completed]);
    $otherBacktest->trades()->create(tradeLogAttributes($otherBacktest, ['symbol' => 'FINDME', 'date' => '2017-01-02']));

    $this->actingAs($user)->get('/backtests/'.$backtest->id.'?'.http_build_query([
        'trade_search' => 'sPeCiAl', 'trade_type' => 'sell', 'trade_reason' => 'gold-rotation', 'trade_year' => 2017,
    ]), [
        'X-Inertia' => 'true',
        'X-Inertia-Partial-Component' => 'Backtests/Show',
        'X-Inertia-Partial-Data' => 'trades,tradeLogSummary,tradeFilters',
        'X-Inertia-Reset' => 'trades',
    ])->assertSuccessful()
        ->assertJsonCount(1, 'props.trades.data')
        ->assertJsonPath('props.trades.data.0.id', $target->id)
        ->assertJsonPath('props.trades.data.0.reason_category', 'gold-rotation')
        ->assertJsonPath('props.trades.data.0.day_totals', ['buys' => 0, 'sells' => 1, 'bought' => 0, 'sold' => 100, 'charges' => 1])
        ->assertJsonPath('props.tradeLogSummary.total', 152)
        ->assertJsonPath('props.tradeLogSummary.counts', ['all' => 2, 'buy' => 1, 'sell' => 1])
        ->assertJsonPath('props.tradeLogSummary.reasons', ['gold-rotation' => 1])
        ->assertJsonPath('props.tradeLogSummary.years', [2018, 2017])
        ->assertJsonPath('props.tradeFilters.year', 2017)
        ->assertJsonPath('scrollProps.trades.reset', true)
        ->assertJsonMissingPath('mergeProps');

    $this->get('/backtests/'.$backtest->id.'?trade_sort=asc', [
        'X-Inertia' => 'true',
        'X-Inertia-Partial-Component' => 'Backtests/Show',
        'X-Inertia-Partial-Data' => 'trades',
    ])->assertSuccessful()->assertJsonPath('props.trades.data.0.date', '2017-01-02T00:00:00.000000Z');
});

it('treats wildcard search characters as literal text', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id, 'status' => BacktestStatusEnum::Completed]);
    $backtest->trades()->create(tradeLogAttributes($backtest, ['name' => 'Company_10%']));
    $backtest->trades()->create(tradeLogAttributes($backtest, ['name' => 'CompanyA100']));

    $this->actingAs($user)->get('/backtests/'.$backtest->id.'?trade_search='.urlencode('_10%'), [
        'X-Inertia' => 'true',
        'X-Inertia-Partial-Component' => 'Backtests/Show',
        'X-Inertia-Partial-Data' => 'trades',
    ])->assertSuccessful()->assertJsonCount(1, 'props.trades.data');
});

it('filters a position holding period without including another position or backtest', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->for($user)->create(['status' => BacktestStatusEnum::Completed]);
    $expected = [];
    foreach (['2013-01-01', '2014-01-01', '2014-06-01', '2015-01-01', '2017-01-01', '2021-01-01'] as $index => $date) {
        $trade = $backtest->trades()->create(tradeLogAttributes($backtest, [
            'symbol' => 'ITC', 'date' => $date, 'trade_type' => $index % 2 === 0 ? 'buy' : 'sell',
        ]));
        if ($index < 4) {
            $expected[] = $trade->id;
        }
    }
    $backtest->trades()->create(tradeLogAttributes($backtest, ['symbol' => 'ITCOTHER', 'date' => '2014-01-01']));
    $other = Backtest::factory()->for($user)->create();
    $other->trades()->create(tradeLogAttributes($other, ['symbol' => 'ITC', 'date' => '2014-01-01']));

    $response = $this->actingAs($user)->get('/backtests/'.$backtest->id.'?'.http_build_query([
        'trade_symbol' => 'ITC', 'trade_from' => '2013-01-01', 'trade_through' => '2015-01-01', 'trade_sort' => 'asc',
    ]), [
        'X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'Backtests/Show',
        'X-Inertia-Partial-Data' => 'trades,tradeLogSummary,tradeFilters', 'X-Inertia-Reset' => 'trades',
    ])->assertSuccessful()->assertJsonCount(4, 'props.trades.data')
        ->assertJsonPath('props.trades.total', 4)
        ->assertJsonPath('props.tradeLogSummary.total', 7)
        ->assertJsonPath('props.tradeLogSummary.years', [2015, 2014, 2013])
        ->assertJsonPath('props.tradeLogSummary.counts', ['all' => 4, 'buy' => 2, 'sell' => 2])
        ->assertJsonPath('props.tradeFilters.symbol', 'ITC')
        ->assertJsonPath('props.tradeFilters.from', '2013-01-01')
        ->assertJsonPath('props.tradeFilters.through', '2015-01-01');
    expect(array_column($response->json('props.trades.data'), 'id'))->toBe($expected);
});

it('rejects incomplete or reversed position filter dates', function (array $filters, array $errors) {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->for($user)->create();
    $this->actingAs($user)->getJson('/backtests/'.$backtest->id.'?'.http_build_query($filters))
        ->assertUnprocessable()->assertJsonValidationErrors($errors);
})->with([
    [['trade_symbol' => 'ITC'], ['trade_from', 'trade_through']],
    [['trade_from' => '2013-01-01'], ['trade_symbol', 'trade_through']],
    [['trade_through' => '2015-01-01'], ['trade_symbol', 'trade_from']],
    [['trade_symbol' => 'ITC', 'trade_from' => '2015-01-01', 'trade_through' => '2013-01-01'], ['trade_through']],
]);

it('validates trade log filters and hides other users deferred results', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id, 'status' => BacktestStatusEnum::Completed]);

    $this->actingAs($user)->getJson('/backtests/'.$backtest->id.'?trade_sort=invalid&trades_page=0&trade_type=invalid')
        ->assertUnprocessable()->assertJsonValidationErrors(['trade_sort', 'trades_page', 'trade_type']);

    $this->actingAs(User::factory()->create(['is_paid' => true]))->get('/backtests/'.$backtest->id, [
        'X-Inertia' => 'true',
        'X-Inertia-Partial-Component' => 'Backtests/Show',
        'X-Inertia-Partial-Data' => 'trades,tradeLogSummary',
    ])->assertNotFound();
});

it('returns an empty completed trade log without scroll pages', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id, 'status' => BacktestStatusEnum::Completed]);

    $this->actingAs($user)->get('/backtests/'.$backtest->id, [
        'X-Inertia' => 'true',
        'X-Inertia-Partial-Component' => 'Backtests/Show',
        'X-Inertia-Partial-Data' => 'trades,tradeLogSummary',
    ])->assertSuccessful()
        ->assertJsonCount(0, 'props.trades.data')
        ->assertJsonPath('props.tradeLogSummary.total', 0)
        ->assertJsonPath('props.tradeLogSummary.counts', ['all' => 0, 'buy' => 0, 'sell' => 0])
        ->assertJsonPath('scrollProps.trades.nextPage', null);
});

it('classifies forced exits and replacement purchases separately', function (string $reason, string $category) {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id, 'status' => BacktestStatusEnum::Completed]);
    $backtest->trades()->create(tradeLogAttributes($backtest, ['reason' => $reason]));

    $this->actingAs($user)->get('/backtests/'.$backtest->id.'?trade_reason='.$category, [
        'X-Inertia' => 'true',
        'X-Inertia-Partial-Component' => 'Backtests/Show',
        'X-Inertia-Partial-Data' => 'trades',
    ])->assertSuccessful()->assertJsonCount(1, 'props.trades.data')->assertJsonPath('props.trades.data.0.reason_category', $category);
})->with([
    ['Stop loss confirmed', 'stop-loss'],
    ['Trailing stop loss confirmed', 'stop-loss'],
    ['Replacement after stop-loss exit', 'replacement'],
    ['Assumed delisting - no valid price for 100 market trading days through 2024-06-01; exit at last traded close using future data', 'assumed-delisting'],
    ['Replacement after assumed delisting', 'replacement'],
    ['Replacement after assumed delisting - allocating to gold', 'gold-rotation'],
]);

/**
 * @param  array<string, mixed>  $attributes
 * @return array<string, mixed>
 */
function tradeLogAttributes(Backtest $backtest, array $attributes = []): array
{
    return array_replace([
        'backtest_id' => $backtest->id, 'symbol' => 'STOCK', 'name' => 'Stock', 'trade_type' => 'buy',
        'reason' => 'New entry', 'date' => '2018-01-01', 'quantity' => 1, 'price' => 100, 'raw_price' => 100,
        'gross_amount' => 100, 'brokerage' => 0, 'stt' => 0, 'transaction_charges' => 0, 'sebi_charges' => 0,
        'gst' => 0, 'stamp_charges' => 0, 'total_charges' => 1, 'net_amount' => 101,
    ], $attributes);
}
