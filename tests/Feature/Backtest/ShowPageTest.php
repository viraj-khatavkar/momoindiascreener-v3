<?php

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

it('defers the trade log and returns every trade in bounded pages without duplicates', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id, 'status' => BacktestStatusEnum::Completed]);
    $trade = [
        'backtest_id' => $backtest->id, 'symbol' => 'STOCK', 'name' => 'Stock', 'trade_type' => 'buy',
        'reason' => 'Rebalance', 'date' => '2018-01-01', 'quantity' => 1, 'price' => 100, 'raw_price' => 100,
        'gross_amount' => 100, 'brokerage' => 0, 'stt' => 0, 'transaction_charges' => 0, 'sebi_charges' => 0,
        'gst' => 0, 'stamp_charges' => 0, 'total_charges' => 1, 'net_amount' => 101,
    ];
    DB::table('backtest_trades')->insert(array_fill(0, 501, $trade));
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
        ->assertJsonPath('props.trades.data.0', [...$lastTrade->fresh()->toArray(), 'reason_category' => 'filter-exit'])
        ->assertJsonPath('scrollProps.trades.pageName', 'trades_page')
        ->assertJsonPath('scrollProps.trades.nextPage', 2)
        ->assertJsonPath('mergeProps', ['trades.data'])
        ->assertJsonPath('matchPropsOn', ['trades.data.id']);
    expect($queries)->toHaveCount(1);
    expect($queries->first())->toContain('limit 100');

    $ids = array_column($response->json('props.trades.data'), 'id');
    for ($page = 2; $page <= 6; $page++) {
        $response = $this->get('/backtests/'.$backtest->id.'?trades_page='.$page, [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'Backtests/Show',
            'X-Inertia-Partial-Data' => 'trades',
        ])->assertSuccessful()->assertJsonCount($page === 6 ? 2 : 100, 'props.trades.data');
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
        'symbol' => 'FINDME', 'name' => 'Special Company', 'date' => '2017-01-01', 'reason' => 'New entry',
    ]));
    $otherBacktest = Backtest::factory()->create(['user_id' => $user->id, 'status' => BacktestStatusEnum::Completed]);
    $otherBacktest->trades()->create(tradeLogAttributes($otherBacktest, ['symbol' => 'FINDME', 'date' => '2017-01-01']));

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
    ])->assertSuccessful()->assertJsonPath('props.trades.data.0.date', '2017-01-01T00:00:00.000000Z');
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
