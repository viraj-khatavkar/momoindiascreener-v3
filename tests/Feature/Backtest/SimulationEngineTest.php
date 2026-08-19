<?php

use App\Actions\Backtest\ApplyBacktestFiltersAction;
use App\Actions\Backtest\CalculateBacktestMetricsAction;
use App\Actions\Backtest\CalculateTransactionCostsAction;
use App\Actions\Backtest\RunBacktestAction;
use App\Enums\CorporateActionTypeEnum;
use App\Models\Backtest;
use App\Models\BacktestNseInstrumentPrice;
use App\Models\NseIndex;
use App\Models\User;

// ==========================================================================
// HELPERS
// ==========================================================================

function seedInstrument(string $date, string $symbol, float $adjustedPrice, array $overrides = []): void
{
    $defaults = [
        'date' => $date, 'symbol' => $symbol, 'name' => $symbol, 'series' => 'EQ',
        'is_delisted' => false,
        'open_adjusted' => $adjustedPrice, 'high_adjusted' => $adjustedPrice,
        'low_adjusted' => $adjustedPrice, 'close_adjusted' => $adjustedPrice,
        'open_raw' => $adjustedPrice * 10, 'high_raw' => $adjustedPrice * 10,
        'low_raw' => $adjustedPrice * 10, 'close_raw' => $adjustedPrice * 10,
        'volume_adjusted' => 1000000, 'volume_shares_adjusted' => 100000,
        'volume_raw' => 1000000, 'volume_shares_raw' => 100000,
        't_percent' => 1, 't_percent_raw' => 1, 'marketcap' => 500000, 'price_to_earnings' => 20,
        'variance_one_year' => 0.01, 'variance_nine_months' => 0.01, 'variance_six_months' => 0.01,
        'variance_three_months' => 0.01, 'variance_one_months' => 0.01,
        'standard_deviation_one_year' => 0.1, 'standard_deviation_nine_months' => 0.1,
        'standard_deviation_six_months' => 0.1, 'standard_deviation_three_months' => 0.1, 'standard_deviation_one_months' => 0.1,
        'volatility_one_year' => 0.35, 'volatility_nine_months' => 0.3, 'volatility_six_months' => 0.3,
        'volatility_three_months' => 0.3, 'volatility_one_months' => 0.3,
        'covariance' => 0.01, 'beta' => 1.0,
        'absolute_return_one_year' => 20, 'absolute_return_nine_months' => 15, 'absolute_return_six_months' => 10,
        'absolute_return_three_months' => 5, 'absolute_return_one_months' => 2,
        'average_absolute_return_twelve_nine_six_three_one_months' => 10, 'average_absolute_return_twelve_nine_six_three_months' => 12,
        'average_absolute_return_twelve_nine_six_months' => 15, 'average_absolute_return_twelve_nine_months' => 17,
        'average_absolute_return_twelve_six_three_one_months' => 9, 'average_absolute_return_twelve_six_three_months' => 11,
        'average_absolute_return_twelve_six_months' => 15, 'average_absolute_return_twelve_three_one_months' => 9,
        'average_absolute_return_twelve_three_months' => 12, 'average_absolute_return_twelve_nine_three_one_months' => 10,
        'average_absolute_return_twelve_nine_three_months' => 13, 'average_absolute_return_nine_six_three_one_months' => 8,
        'average_absolute_return_nine_six_three_months' => 10, 'average_absolute_return_nine_six_months' => 12,
        'average_absolute_return_six_three_one_months' => 5, 'average_absolute_return_six_three_months' => 7,
        'average_absolute_return_three_one_months' => 3,
        'sharpe_return_one_year' => 2.0, 'sharpe_return_nine_months' => 1.8, 'sharpe_return_six_months' => 1.5,
        'sharpe_return_three_months' => 1.0, 'sharpe_return_one_months' => 0.5,
        'average_sharpe_return_twelve_nine_six_three_one_months' => 1.3, 'average_sharpe_return_twelve_nine_six_three_months' => 1.5,
        'average_sharpe_return_twelve_nine_six_months' => 1.7, 'average_sharpe_return_twelve_nine_months' => 1.9,
        'average_sharpe_return_twelve_six_three_one_months' => 1.2, 'average_sharpe_return_twelve_six_three_months' => 1.4,
        'average_sharpe_return_twelve_six_months' => 1.6, 'average_sharpe_return_twelve_three_one_months' => 1.1,
        'average_sharpe_return_twelve_three_months' => 1.3, 'average_sharpe_return_twelve_nine_three_one_months' => 1.2,
        'average_sharpe_return_twelve_nine_three_months' => 1.5, 'average_sharpe_return_nine_six_three_one_months' => 1.0,
        'average_sharpe_return_nine_six_three_months' => 1.2, 'average_sharpe_return_nine_six_months' => 1.4,
        'average_sharpe_return_six_three_one_months' => 0.8, 'average_sharpe_return_six_three_months' => 1.0,
        'average_sharpe_return_three_one_months' => 0.5,
        'rsi_one_year' => 55, 'rsi_nine_months' => 55, 'rsi_six_months' => 55, 'rsi_three_months' => 55, 'rsi_one_months' => 55,
        'average_rsi_twelve_nine_six_three_one_months' => 55, 'average_rsi_twelve_nine_six_three_months' => 55,
        'average_rsi_twelve_nine_six_months' => 55, 'average_rsi_twelve_nine_months' => 55,
        'average_rsi_twelve_six_three_one_months' => 55, 'average_rsi_twelve_six_three_months' => 55,
        'average_rsi_twelve_six_months' => 55, 'average_rsi_twelve_three_one_months' => 55,
        'average_rsi_twelve_three_months' => 55, 'average_rsi_twelve_nine_three_one_months' => 55,
        'average_rsi_twelve_nine_three_months' => 55, 'average_rsi_nine_six_three_one_months' => 55,
        'average_rsi_nine_six_three_months' => 55, 'average_rsi_nine_six_months' => 55,
        'average_rsi_six_three_one_months' => 55, 'average_rsi_six_three_months' => 55,
        'average_rsi_three_one_months' => 55,
        'absolute_divide_beta_return_one_year' => 20, 'sharpe_divide_beta_return_one_year' => 2,
        'average_sharpe_divide_beta_return_twelve_nine_six_three_months' => 1.5,
        'average_sharpe_divide_beta_return_twelve_six_three_months' => 1.3,
        'average_sharpe_divide_beta_return_twelve_six_months' => 1.5,
        'return_twelve_minus_one_months' => 18, 'return_twelve_minus_two_months' => 15,
        'circuits_one_year' => 0, 'circuits_nine_months' => 0, 'circuits_six_months' => 0,
        'circuits_three_months' => 0, 'circuits_one_months' => 0,
        'positive_days_percent_one_year' => 55, 'positive_days_percent_nine_months' => 55,
        'positive_days_percent_six_months' => 55, 'positive_days_percent_three_months' => 55,
        'positive_days_percent_one_months' => 55,
        'away_from_high_one_year' => -5, 'away_from_high_all_time' => -10,
        'high_one_year' => $adjustedPrice * 1.1, 'high_all_time' => $adjustedPrice * 1.2,
        'ma_200' => $adjustedPrice * 0.9, 'ma_100' => $adjustedPrice * 0.95,
        'ma_50' => $adjustedPrice * 0.98, 'ma_20' => $adjustedPrice * 0.99,
        'ema_200' => $adjustedPrice * 0.9, 'ema_100' => $adjustedPrice * 0.95,
        'ema_50' => $adjustedPrice * 0.98, 'ema_20' => $adjustedPrice * 0.99,
        'median_volume_one_year' => 50000000,
        'volume_day' => 1000000, 'volume_one_year_average' => 1000000,
        'volume_nine_months_average' => 1000000, 'volume_six_months_average' => 1000000,
        'volume_three_months_average' => 1000000, 'volume_one_months_average' => 1000000,
        'volume_week_average' => 1000000,
        'is_nifty_50' => true, 'is_nifty_next_50' => false, 'is_nifty_100' => true,
        'is_nifty_200' => true, 'is_nifty_midcap_100' => false, 'is_nifty_500' => true,
        'is_nifty_smallcap_250' => false, 'is_nifty_allcap' => true, 'is_etf' => false,
    ];

    BacktestNseInstrumentPrice::insert(array_merge($defaults, $overrides));
}

function seedIndexRange(string $from, string $to, float $close): void
{
    $d = new DateTime($from);
    $end = new DateTime($to);
    while ($d <= $end) {
        if ((int) $d->format('N') <= 5) {
            NseIndex::insert([
                'symbol' => 'Nifty 50', 'slug' => 'nifty-50', 'date' => $d->format('Y-m-d'),
                'open' => $close, 'high' => $close, 'low' => $close, 'close' => $close,
                'points_change' => 0, 'percentage_change' => 0, 'volume' => 0, 'turnover' => 0,
                'price_to_earnings' => 20, 'price_to_book' => 3, 'dividend_yield' => 1.5,
            ]);
        }
        $d->modify('+1 day');
    }
}

function tradingDates(int $count = 30): array
{
    $dates = [];
    $d = new DateTime('2011-01-05');
    while (count($dates) < $count) {
        if ((int) $d->format('N') <= 5) {
            $dates[] = $d->format('Y-m-d');
        }
        $d->modify('+1 day');
    }

    return $dates;
}

function makeBacktest(User $user, array $overrides = []): Backtest
{
    return Backtest::factory()->create(array_merge([
        'user_id' => $user->id, 'max_stocks_to_hold' => 3, 'worst_rank_held' => 5,
        'rebalance_frequency' => 'weekly', 'rebalance_day' => 1,
        'weightage' => 'equal_weight', 'cash_call' => 'no_cash_call',
        'cash_call_index' => 'nifty-50', 'cash_call_dma_period' => 20,
        'cash_return_rate' => 0, 'initial_capital' => 1000000,
        'median_volume_one_year' => 10000000, 'minimum_return_one_year' => -100,
        'execute_next_trading_day' => false, 'apply_hold_above_dma' => false,
    ], $overrides));
}

function run(Backtest $bt): void
{
    $action = new RunBacktestAction(new ApplyBacktestFiltersAction, new CalculateTransactionCostsAction);
    $action->execute($bt);
    (new CalculateBacktestMetricsAction)->execute($bt);
}

// ==========================================================================
// MONEY CONSERVATION
// ==========================================================================

it('conserves money on day one: total value plus charges equals initial capital', function () {
    $dates = tradingDates(10);
    seedIndexRange('2010-01-01', end($dates), 5000);
    foreach ($dates as $i => $date) {
        seedInstrument($date, 'A', 100 + $i, ['sharpe_return_one_year' => 5.0]);
        seedInstrument($date, 'B', 200 + $i, ['sharpe_return_one_year' => 4.0]);
        seedInstrument($date, 'C', 300 + $i, ['sharpe_return_one_year' => 3.0]);
    }

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user);
    run($bt);

    $snap = $bt->dailySnapshots()->orderBy('date')->first();
    $charges = (float) $bt->trades()->where('date', $dates[0])->sum('total_charges');
    expect(round((float) $snap->total_value + $charges, 2))->toBe(1000000.00);
});

it('conserves money across rebalances with flat prices: total value plus charges equals initial capital', function () {
    $dates = tradingDates(20);
    seedIndexRange('2010-01-01', end($dates), 5000);

    // FLAT prices: no market gains, only transaction costs reduce value
    // A sharpe drops → sold and replaced → generates charges but no market PnL
    foreach ($dates as $i => $date) {
        seedInstrument($date, 'A', 100, ['sharpe_return_one_year' => $i < 8 ? 5.0 : 0.1]);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
        seedInstrument($date, 'C', 150, ['sharpe_return_one_year' => 3.0]);
        seedInstrument($date, 'D', 250, ['sharpe_return_one_year' => 3.5]);
    }

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user, ['worst_rank_held' => 3]);
    run($bt);

    $sells = $bt->trades()->where('trade_type', 'sell')->count();
    expect($sells)->toBeGreaterThan(0, 'Expected sells to occur');

    // With flat prices: total_value + all charges = initial capital (no market gains/losses)
    $allCharges = (float) $bt->trades()->sum('total_charges');
    $lastSnap = $bt->dailySnapshots()->orderBy('date', 'desc')->first();
    expect(round((float) $lastSnap->total_value + $allCharges, 0))->toBe(1000000.0);
});

it('records the simulated period on the summary metrics', function () {
    $dates = tradingDates(10);
    seedIndexRange('2010-01-01', end($dates), 5000);
    foreach ($dates as $date) {
        seedInstrument($date, 'A', 100, ['sharpe_return_one_year' => 5.0]);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
    }

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user, ['max_stocks_to_hold' => 2]);
    run($bt);

    $metrics = $bt->summaryMetrics;
    expect($metrics->start_date->format('Y-m-d'))->toBe($dates[0])
        ->and($metrics->end_date->format('Y-m-d'))->toBe(end($dates));
});

it('reports simulation progress for fewer than fifty trading days', function () {
    $dates = tradingDates(10);
    seedIndexRange('2010-01-01', end($dates), 5000);

    foreach ($dates as $date) {
        seedInstrument($date, 'A', 100, ['sharpe_return_one_year' => 5.0]);
    }

    $user = User::factory()->create(['is_paid' => true]);
    $backtest = makeBacktest($user, ['max_stocks_to_hold' => 1]);
    $action = new RunBacktestAction(new ApplyBacktestFiltersAction, new CalculateTransactionCostsAction);

    $action->execute($backtest);

    expect($backtest->refresh()->progress)->toBe(95);
});

// ==========================================================================
// SELL PRICE CORRECTNESS
// ==========================================================================

it('sells at the current days price not the previous days', function () {
    $dates = tradingDates(20);
    seedIndexRange('2010-01-01', end($dates), 5000);

    // A: price rises 2/day, sharpe drops at day 8 → will be sold
    foreach ($dates as $i => $date) {
        seedInstrument($date, 'A', 100 + $i * 2, ['sharpe_return_one_year' => $i < 8 ? 5.0 : 0.1]);
        seedInstrument($date, 'B', 200 + $i, ['sharpe_return_one_year' => 4.0]);
        seedInstrument($date, 'C', 150 + $i, ['sharpe_return_one_year' => 3.0]);
        seedInstrument($date, 'D', 250 + $i, ['sharpe_return_one_year' => 3.5]);
    }

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user, ['worst_rank_held' => 3]);
    run($bt);

    $aSells = $bt->trades()->where('trade_type', 'sell')->where('symbol', 'A')->get();
    expect($aSells)->not->toBeEmpty();

    foreach ($aSells as $trade) {
        $today = BacktestNseInstrumentPrice::where('symbol', 'A')->where('date', $trade->date)->first();
        $yesterday = BacktestNseInstrumentPrice::where('symbol', 'A')->where('date', '<', $trade->date)->orderBy('date', 'desc')->first();

        expect(round((float) $trade->price, 2))->toBe(round((float) $today->close_adjusted, 2));
        if ($yesterday) {
            expect((float) $trade->price)->not->toBe((float) $yesterday->close_adjusted, 'Sell should NOT be at yesterdays price');
        }
    }
});

it('records realized pnl against cost basis on every sell trade', function () {
    $dates = tradingDates(20);
    seedIndexRange('2010-01-01', end($dates), 5000);

    // A: rises 2/day, sharpe drops at day 8 → sold at a profit
    foreach ($dates as $i => $date) {
        seedInstrument($date, 'A', 100 + $i * 2, ['sharpe_return_one_year' => $i < 8 ? 5.0 : 0.1]);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
        seedInstrument($date, 'C', 150, ['sharpe_return_one_year' => 3.0]);
        seedInstrument($date, 'D', 250, ['sharpe_return_one_year' => 3.5]);
    }

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user, ['worst_rank_held' => 3]);
    run($bt);

    $aBuy = $bt->trades()->where('symbol', 'A')->where('trade_type', 'buy')->first();
    $aSell = $bt->trades()->where('symbol', 'A')->where('trade_type', 'sell')->first();

    expect($aSell)->not->toBeNull();

    // Cost basis is charge-inclusive: the buy's net_amount (gross + charges).
    $costBasis = (float) $aBuy->net_amount / $aBuy->quantity * $aSell->quantity;
    $expectedPnl = round((float) $aSell->net_amount - $costBasis, 2);

    expect(round((float) $aSell->realized_pnl, 2))->toBe($expectedPnl)
        ->and((float) $aSell->realized_pnl)->toBeGreaterThan(0)
        ->and(round((float) $aSell->realized_pnl_pct, 2))->toBe(round($expectedPnl / $costBasis * 100, 2))
        ->and($aBuy->realized_pnl)->toBeNull();
});

it('nets buy-side charges out of realized pnl on a flat-price round trip', function () {
    $dates = tradingDates(20);
    seedIndexRange('2010-01-01', end($dates), 5000);

    // A: flat price, sharpe drops at day 8 → sold at exactly its buy price,
    // so the only realized P&L is the full round-trip charges.
    foreach ($dates as $i => $date) {
        seedInstrument($date, 'A', 100, ['sharpe_return_one_year' => $i < 8 ? 5.0 : 0.1]);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
        seedInstrument($date, 'C', 150, ['sharpe_return_one_year' => 3.0]);
        seedInstrument($date, 'D', 250, ['sharpe_return_one_year' => 3.5]);
    }

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user, ['worst_rank_held' => 3, 'brokerage_rate' => 0.5]);
    run($bt);

    $aBuy = $bt->trades()->where('symbol', 'A')->where('trade_type', 'buy')->first();
    $aSell = $bt->trades()->where('symbol', 'A')->where('trade_type', 'sell')->first();

    expect($aSell)->not->toBeNull()
        ->and(round((float) $aSell->realized_pnl, 2))
        ->toBe(round((float) $aSell->net_amount - (float) $aBuy->net_amount, 2))
        // The loss must exceed the buy-side charges alone — proof they are
        // part of the basis and not just the sell-side charges.
        ->and((float) $aSell->realized_pnl)->toBeLessThan(-(float) $aBuy->total_charges);

    // The trade log and the position metrics agree on this closed cycle —
    // same net P&L and the same charge-inclusive percentage.
    $aPosition = collect($bt->summaryMetrics->stock_performance)->firstWhere('symbol', 'A');

    expect($aPosition)->not->toBeNull()
        ->and((float) $aPosition['net_pnl'])->toEqualWithDelta((float) $aSell->realized_pnl, 0.01)
        ->and((float) $aPosition['pnl_pct'])->toEqualWithDelta((float) $aSell->realized_pnl_pct, 0.01);
});

// ==========================================================================
// FULL CASH CALL BELOW DMA
// ==========================================================================

it('sells everything when index drops below its dma', function () {
    $dates = tradingDates(20);

    // Index: 6000 for 200 days before backtest, then crashes to 3000 at day 8
    seedIndexRange('2010-01-01', $dates[7], 6000);
    seedIndexRange($dates[8], end($dates), 3000);

    foreach ($dates as $i => $date) {
        seedInstrument($date, 'A', 100, ['sharpe_return_one_year' => 5.0]);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
        seedInstrument($date, 'C', 300, ['sharpe_return_one_year' => 3.0]);
    }

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user, ['cash_call' => 'full_cash_below_index_dma', 'cash_call_dma_period' => 20]);
    run($bt);

    $lastSnap = $bt->dailySnapshots()->orderBy('date', 'desc')->first();
    expect($lastSnap->holdings_count)->toBe(0);

    $cashCallSells = $bt->trades()->where('reason', 'like', '%Cash call%')->count();
    expect($cashCallSells)->toBeGreaterThan(0);
});

// ==========================================================================
// GOLD ALLOCATION
// ==========================================================================

it('rotates to goldbees when index drops below dma', function () {
    $dates = tradingDates(20);
    seedIndexRange('2010-01-01', $dates[7], 6000);
    seedIndexRange($dates[8], end($dates), 3000);

    foreach ($dates as $date) {
        seedInstrument($date, 'A', 100, ['sharpe_return_one_year' => 5.0]);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
        seedInstrument($date, 'C', 300, ['sharpe_return_one_year' => 3.0]);
        seedInstrument($date, 'GOLDBEES', 2500, ['sharpe_return_one_year' => 0.1, 'is_etf' => true]);
    }

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user, ['cash_call' => 'allocate_to_gold_below_index_dma', 'cash_call_dma_period' => 20]);
    run($bt);

    $goldBuys = $bt->trades()->where('trade_type', 'buy')->where('symbol', 'GOLDBEES')->count();
    expect($goldBuys)->toBeGreaterThan(0);

    $lastSnap = $bt->dailySnapshots()->orderBy('date', 'desc')->first();
    expect($lastSnap->holdings_count)->toBe(1);
});

// ==========================================================================
// HOLD ABOVE DMA OVERRIDE
// ==========================================================================

it('protects a stock from selling when it is above its own dma', function () {
    $dates = tradingDates(20);
    seedIndexRange('2010-01-01', end($dates), 5000);

    // A: sharpe drops at day 8 but price stays ABOVE ma_200 → protected
    foreach ($dates as $i => $date) {
        $aPrice = 100 + $i;
        seedInstrument($date, 'A', $aPrice, [
            'sharpe_return_one_year' => $i < 8 ? 5.0 : 0.1,
            'ma_200' => $aPrice * 0.5, // price well above MA → protected
        ]);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
        seedInstrument($date, 'C', 300, ['sharpe_return_one_year' => 3.0]);
        seedInstrument($date, 'D', 400, ['sharpe_return_one_year' => 3.5]);
    }

    $user = User::factory()->create(['is_paid' => true]);

    $btNoHold = makeBacktest($user, ['worst_rank_held' => 3, 'apply_hold_above_dma' => false]);
    run($btNoHold);
    $aSellsNoHold = $btNoHold->trades()->where('trade_type', 'sell')->where('symbol', 'A')->count();

    $btWithHold = makeBacktest($user, ['worst_rank_held' => 3, 'apply_hold_above_dma' => true, 'hold_above_dma_period' => 200]);
    run($btWithHold);
    $aSellsWithHold = $btWithHold->trades()->where('trade_type', 'sell')->where('symbol', 'A')->count();

    expect($aSellsNoHold)->toBeGreaterThan(0, 'Without override, A should be sold')
        ->and($aSellsWithHold)->toBe(0, 'With override, A should be protected (above 200 DMA)');
});

// ==========================================================================
// EXECUTE NEXT TRADING DAY
// ==========================================================================

it('buys at next days price when execute next trading day is enabled', function () {
    $dates = tradingDates(10);
    seedIndexRange('2010-01-01', end($dates), 5000);

    // A: 100 on day 1, 200 on day 2+ — clear price difference
    foreach ($dates as $i => $date) {
        seedInstrument($date, 'A', $i === 0 ? 100 : 200, ['sharpe_return_one_year' => 5.0]);
        seedInstrument($date, 'B', 300, ['sharpe_return_one_year' => 4.0]);
        seedInstrument($date, 'C', 400, ['sharpe_return_one_year' => 3.0]);
    }

    $user = User::factory()->create(['is_paid' => true]);

    $sameDay = makeBacktest($user, ['execute_next_trading_day' => false]);
    run($sameDay);

    $nextDay = makeBacktest($user, ['execute_next_trading_day' => true]);
    run($nextDay);

    $sameDayBuy = $sameDay->trades()->where('symbol', 'A')->where('trade_type', 'buy')->first();
    $nextDayBuy = $nextDay->trades()->where('symbol', 'A')->where('trade_type', 'buy')->first();

    // Same day: bought at 100 (day 1), next day: bought at 200 (day 2)
    expect(round((float) $sameDayBuy->price, 2))->toBe(100.00)
        ->and(round((float) $nextDayBuy->price, 2))->toBe(200.00)
        ->and($nextDayBuy->date->format('Y-m-d'))->toBe($dates[1]);
});

// ==========================================================================
// DEMERGER EXITS
// ==========================================================================

it('exits a held demerger stock one trading day before ex-date outside scheduled rebalance and buys a replacement', function () {
    $dates = tradingDates(10);
    seedIndexRange('2010-01-01', end($dates), 5000);

    foreach ($dates as $date) {
        seedInstrument($date, 'A', 100, ['sharpe_return_one_year' => 5.0]);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
        seedInstrument($date, 'C', 150, ['sharpe_return_one_year' => 3.0]);
        seedInstrument($date, 'D', 250, ['sharpe_return_one_year' => 2.0]);
    }

    createCorporateAction('A', $dates[5], [
        'type' => CorporateActionTypeEnum::DEMERGER,
        'description' => 'Corporate Action: EQ A DEMERGER',
        'ratio' => 'DEMERGER',
    ]);

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user);
    run($bt);

    $aSell = $bt->trades()
        ->where('symbol', 'A')
        ->where('trade_type', 'sell')
        ->where('date', $dates[4])
        ->first();

    $dBuy = $bt->trades()
        ->where('symbol', 'D')
        ->where('trade_type', 'buy')
        ->where('date', $dates[4])
        ->first();

    expect($aSell)->not->toBeNull()
        ->and($aSell->reason)->toContain('Demerger ex-date on '.$dates[5])
        ->and($dBuy)->not->toBeNull()
        ->and($dBuy->reason)->toBe('Replacement after demerger exit');
});

it('forces the demerger exit when the held stock is in a stale circuit set', function () {
    $dates = tradingDates(10);
    seedIndexRange('2010-01-01', end($dates), 5000);

    foreach ($dates as $date) {
        $aTPercent = $date === $dates[3] ? 5.00 : 1.0;
        seedInstrument($date, 'A', 100, ['sharpe_return_one_year' => 5.0, 't_percent' => $aTPercent]);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
        seedInstrument($date, 'C', 150, ['sharpe_return_one_year' => 3.0]);
        seedInstrument($date, 'D', 250, ['sharpe_return_one_year' => 2.0]);
    }

    createCorporateAction('A', $dates[5], [
        'type' => CorporateActionTypeEnum::DEMERGER,
        'description' => 'Corporate Action: EQ A DEMERGER',
        'ratio' => 'DEMERGER',
    ]);

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user);
    run($bt);

    expect($bt->trades()->where('symbol', 'A')->where('trade_type', 'sell')->where('date', $dates[4])->count())->toBe(1)
        ->and($bt->trades()->where('symbol', 'D')->where('trade_type', 'buy')->where('date', $dates[4])->count())->toBe(1);
});

it('holds through a demerger when exit before demerger is disabled', function () {
    $dates = tradingDates(10);
    seedIndexRange('2010-01-01', end($dates), 5000);

    foreach ($dates as $date) {
        seedInstrument($date, 'A', 100, ['sharpe_return_one_year' => 5.0]);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
        seedInstrument($date, 'C', 150, ['sharpe_return_one_year' => 3.0]);
        seedInstrument($date, 'D', 250, ['sharpe_return_one_year' => 2.0]);
    }

    createCorporateAction('A', $dates[5], [
        'type' => CorporateActionTypeEnum::DEMERGER,
        'description' => 'Corporate Action: EQ A DEMERGER',
        'ratio' => 'DEMERGER',
    ]);

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user, ['exit_before_demerger' => false]);
    run($bt);

    expect($bt->trades()->where('symbol', 'A')->where('trade_type', 'sell')->count())->toBe(0)
        ->and($bt->trades()->where('symbol', 'D')->count())->toBe(0);
});

it('does not exit a demerger stock that is not held before the ex-date', function () {
    $dates = tradingDates(10);
    seedIndexRange('2010-01-01', end($dates), 5000);

    foreach ($dates as $date) {
        seedInstrument($date, 'A', 100, ['sharpe_return_one_year' => 5.0]);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
        seedInstrument($date, 'C', 150, ['sharpe_return_one_year' => 3.0]);
        seedInstrument($date, 'D', 250, ['sharpe_return_one_year' => 2.0]);
    }

    createCorporateAction('D', $dates[5], [
        'type' => CorporateActionTypeEnum::DEMERGER,
        'description' => 'Corporate Action: EQ D DEMERGER',
        'ratio' => 'DEMERGER',
    ]);

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user);
    run($bt);

    expect($bt->trades()->where('date', $dates[4])->count())->toBe(0)
        ->and($bt->trades()->where('symbol', 'D')->count())->toBe(0);
});

it('blocks same-day re-entry when a demerger exit happens on a rebalance day', function () {
    $dates = tradingDates(10);
    seedIndexRange('2010-01-01', end($dates), 5000);

    foreach ($dates as $date) {
        seedInstrument($date, 'A', 100, ['sharpe_return_one_year' => 5.0]);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
        seedInstrument($date, 'C', 150, ['sharpe_return_one_year' => 3.0]);
        seedInstrument($date, 'D', 250, ['sharpe_return_one_year' => 2.0]);
    }

    createCorporateAction('A', $dates[4], [
        'type' => CorporateActionTypeEnum::DEMERGER,
        'description' => 'Corporate Action: EQ A DEMERGER',
        'ratio' => 'DEMERGER',
    ]);

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user);
    run($bt);

    $dayBeforeExDateBuys = $bt->trades()
        ->where('date', $dates[3])
        ->where('trade_type', 'buy')
        ->pluck('symbol')
        ->all();

    expect($bt->trades()->where('symbol', 'A')->where('trade_type', 'sell')->where('date', $dates[3])->count())->toBe(1)
        ->and($bt->trades()->where('symbol', 'A')->where('trade_type', 'buy')->count())->toBe(1)
        ->and($dayBeforeExDateBuys)->toContain('D')
        ->and($dayBeforeExDateBuys)->not->toContain('A');
});

it('lets a same-day cash call rebalance consume demerger exit proceeds without buying a replacement first', function () {
    $dates = tradingDates(20);
    seedIndexRange('2010-01-01', $dates[7], 6000);
    seedIndexRange($dates[8], end($dates), 3000);

    foreach ($dates as $date) {
        seedInstrument($date, 'A', 100, ['sharpe_return_one_year' => 5.0]);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
        seedInstrument($date, 'C', 150, ['sharpe_return_one_year' => 3.0]);
        seedInstrument($date, 'D', 250, ['sharpe_return_one_year' => 2.0]);
    }

    createCorporateAction('A', $dates[9], [
        'type' => CorporateActionTypeEnum::DEMERGER,
        'description' => 'Corporate Action: EQ A DEMERGER',
        'ratio' => 'DEMERGER',
    ]);

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user, ['cash_call' => 'full_cash_below_index_dma', 'cash_call_dma_period' => 20]);
    run($bt);

    $day8Trades = $bt->trades()->where('date', $dates[8])->get();

    expect($day8Trades->where('symbol', 'A')->where('trade_type', 'sell')->count())->toBe(1)
        ->and($day8Trades->where('symbol', 'D')->count())->toBe(0)
        ->and($bt->dailySnapshots()->where('date', $dates[8])->first()->holdings_count)->toBe(0);
});

// ==========================================================================
// BE SERIES EXITS
// ==========================================================================
// Rebalance day indices for tradingDates() with weekly/Mon: 0, 3, 8, 13, 18.

it('exits a held stock the day it moves to the BE series and buys a replacement', function () {
    $dates = tradingDates(10);
    seedIndexRange('2010-01-01', end($dates), 5000);

    // A: top-ranked, moves from EQ to BE on day 4 (not a rebalance day).
    foreach ($dates as $i => $date) {
        seedInstrument($date, 'A', 100, ['sharpe_return_one_year' => 5.0, 'series' => $i < 4 ? 'EQ' : 'BE']);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
        seedInstrument($date, 'C', 150, ['sharpe_return_one_year' => 3.0]);
        seedInstrument($date, 'D', 250, ['sharpe_return_one_year' => 2.0]);
    }

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user, ['exit_on_be_series' => true]);
    run($bt);

    $aSell = $bt->trades()->where('symbol', 'A')->where('trade_type', 'sell')->first();
    $dBuy = $bt->trades()->where('symbol', 'D')->where('trade_type', 'buy')->where('date', $dates[4])->first();

    expect($aSell)->not->toBeNull()
        ->and($aSell->date->format('Y-m-d'))->toBe($dates[4])
        ->and($aSell->reason)->toBe('Series changed to BE - exiting')
        ->and($dBuy)->not->toBeNull()
        ->and($dBuy->reason)->toBe('Replacement after BE series exit')
        // A stays rank 1 afterwards but is blocked from re-entry while in BE
        ->and($bt->trades()->where('symbol', 'A')->where('trade_type', 'buy')->count())->toBe(1);
});

it('keeps a held stock that moves to BE when exit on BE series is disabled', function () {
    $dates = tradingDates(10);
    seedIndexRange('2010-01-01', end($dates), 5000);

    foreach ($dates as $i => $date) {
        seedInstrument($date, 'A', 100, ['sharpe_return_one_year' => 5.0, 'series' => $i < 4 ? 'EQ' : 'BE']);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
        seedInstrument($date, 'C', 150, ['sharpe_return_one_year' => 3.0]);
        seedInstrument($date, 'D', 250, ['sharpe_return_one_year' => 2.0]);
    }

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user);
    run($bt);

    // series_be defaults to true, so A stays in the universe and is never sold
    expect($bt->trades()->where('symbol', 'A')->where('trade_type', 'sell')->count())->toBe(0)
        ->and($bt->trades()->where('symbol', 'D')->count())->toBe(0);
});

it('skips buying a BE series stock on entry when exit on BE series is enabled', function () {
    $dates = tradingDates(10);
    seedIndexRange('2010-01-01', end($dates), 5000);

    // A: top-ranked but BE from day one; D should fill the third slot instead.
    foreach ($dates as $date) {
        seedInstrument($date, 'A', 100, ['sharpe_return_one_year' => 5.0, 'series' => 'BE']);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
        seedInstrument($date, 'C', 150, ['sharpe_return_one_year' => 3.0]);
        seedInstrument($date, 'D', 250, ['sharpe_return_one_year' => 2.0]);
    }

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user, ['exit_on_be_series' => true]);
    run($bt);

    $day0Buys = $bt->trades()->where('date', $dates[0])->where('trade_type', 'buy')->pluck('symbol')->all();
    expect($day0Buys)->not->toContain('A')
        ->and($day0Buys)->toContain('B')
        ->and($day0Buys)->toContain('C')
        ->and($day0Buys)->toContain('D');
});

it('defers the BE series exit while the stock is in circuit and sells the next trading day', function () {
    $dates = tradingDates(10);
    seedIndexRange('2010-01-01', end($dates), 5000);

    // A moves to BE on day 4 but is also in circuit that day → exit retried on day 5.
    foreach ($dates as $i => $date) {
        seedInstrument($date, 'A', 100, [
            'sharpe_return_one_year' => 5.0,
            'series' => $i < 4 ? 'EQ' : 'BE',
            't_percent' => $i === 4 ? 5.00 : 1.0,
        ]);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
        seedInstrument($date, 'C', 150, ['sharpe_return_one_year' => 3.0]);
        seedInstrument($date, 'D', 250, ['sharpe_return_one_year' => 2.0]);
    }

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user, ['exit_on_be_series' => true]);
    run($bt);

    $aSells = $bt->trades()->where('symbol', 'A')->where('trade_type', 'sell')->get();
    expect($aSells)->toHaveCount(1)
        ->and($aSells->first()->date->format('Y-m-d'))->toBe($dates[5]);
});

it('forces the BE series exit when the held stock is in a stale circuit set', function () {
    $dates = tradingDates(10);
    seedIndexRange('2010-01-01', end($dates), 5000);

    // A hits a circuit on rebalance day 3 (entering the stale circuit set),
    // then moves to BE on day 4 with a normal close — the exit must still fire.
    foreach ($dates as $i => $date) {
        seedInstrument($date, 'A', 100, [
            'sharpe_return_one_year' => 5.0,
            'series' => $i < 4 ? 'EQ' : 'BE',
            't_percent' => $i === 3 ? 5.00 : 1.0,
        ]);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
        seedInstrument($date, 'C', 150, ['sharpe_return_one_year' => 3.0]);
        seedInstrument($date, 'D', 250, ['sharpe_return_one_year' => 2.0]);
    }

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user, ['exit_on_be_series' => true]);
    run($bt);

    expect($bt->trades()->where('symbol', 'A')->where('trade_type', 'sell')->where('date', $dates[4])->count())->toBe(1);
});

it('skips a BE series stock when choosing replacements for a BE exit', function () {
    $dates = tradingDates(10);
    seedIndexRange('2010-01-01', end($dates), 5000);

    // A exits on day 4 (moves to BE). E — never held, top-ranked, but BE all
    // along — must be passed over for the replacement; EQ-series D gets it.
    foreach ($dates as $i => $date) {
        seedInstrument($date, 'A', 100, ['sharpe_return_one_year' => 5.0, 'series' => $i < 4 ? 'EQ' : 'BE']);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
        seedInstrument($date, 'C', 150, ['sharpe_return_one_year' => 3.0]);
        seedInstrument($date, 'D', 250, ['sharpe_return_one_year' => 2.0]);
        seedInstrument($date, 'E', 300, ['sharpe_return_one_year' => 6.0, 'series' => 'BE']);
    }

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user, ['exit_on_be_series' => true]);
    run($bt);

    $day4Buys = $bt->trades()->where('date', $dates[4])->where('trade_type', 'buy')->pluck('symbol')->all();
    expect($day4Buys)->toBe(['D'])
        ->and($bt->trades()->where('symbol', 'E')->count())->toBe(0);
});

it('does not re-buy a demerger-exited stock as a replacement for a same-day BE exit', function () {
    $dates = tradingDates(10);
    seedIndexRange('2010-01-01', end($dates), 5000);

    // Day 4 (non-rebalance): A exits ahead of its day-5 demerger ex-date and
    // B exits for moving to BE. A still ranks first — it must not come back
    // as B's replacement.
    foreach ($dates as $i => $date) {
        seedInstrument($date, 'A', 100, ['sharpe_return_one_year' => 5.0]);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0, 'series' => $i < 4 ? 'EQ' : 'BE']);
        seedInstrument($date, 'C', 150, ['sharpe_return_one_year' => 3.0]);
        seedInstrument($date, 'D', 250, ['sharpe_return_one_year' => 2.0]);
        seedInstrument($date, 'E', 300, ['sharpe_return_one_year' => 1.5]);
    }

    createCorporateAction('A', $dates[5], [
        'type' => CorporateActionTypeEnum::DEMERGER,
        'description' => 'Corporate Action: EQ A DEMERGER',
        'ratio' => 'DEMERGER',
    ]);

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user, ['exit_on_be_series' => true]);
    run($bt);

    $day4Trades = $bt->trades()->where('date', $dates[4])->get();

    expect($day4Trades->where('trade_type', 'sell')->pluck('symbol')->sort()->values()->all())->toBe(['A', 'B'])
        ->and($day4Trades->where('trade_type', 'buy')->pluck('symbol')->sort()->values()->all())->toBe(['D', 'E'])
        ->and($bt->trades()->where('symbol', 'A')->where('trade_type', 'buy')->count())->toBe(1);
});

it('blocks entry when the stock moves to BE on the execution day under execute next trading day', function () {
    $dates = tradingDates(10);
    seedIndexRange('2010-01-01', end($dates), 5000);

    // Decision day = day 0 (A still EQ); execution day = day 1 (A now BE).
    foreach ($dates as $i => $date) {
        seedInstrument($date, 'A', 100, ['sharpe_return_one_year' => 5.0, 'series' => $i === 0 ? 'EQ' : 'BE']);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
        seedInstrument($date, 'C', 150, ['sharpe_return_one_year' => 3.0]);
        seedInstrument($date, 'D', 250, ['sharpe_return_one_year' => 2.0]);
    }

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user, ['exit_on_be_series' => true, 'execute_next_trading_day' => true]);
    run($bt);

    expect($bt->trades()->where('symbol', 'A')->count())->toBe(0);
});

it('lets the rebalance absorb a BE series exit on a rebalance day without re-buying it', function () {
    $dates = tradingDates(10);
    seedIndexRange('2010-01-01', end($dates), 5000);

    // A moves to BE exactly on day 3, a rebalance day.
    foreach ($dates as $i => $date) {
        seedInstrument($date, 'A', 100, ['sharpe_return_one_year' => 5.0, 'series' => $i < 3 ? 'EQ' : 'BE']);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
        seedInstrument($date, 'C', 150, ['sharpe_return_one_year' => 3.0]);
        seedInstrument($date, 'D', 250, ['sharpe_return_one_year' => 2.0]);
    }

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user, ['exit_on_be_series' => true]);
    run($bt);

    $day3Buys = $bt->trades()->where('date', $dates[3])->where('trade_type', 'buy')->get();

    expect($bt->trades()->where('symbol', 'A')->where('trade_type', 'sell')->where('date', $dates[3])->count())->toBe(1)
        ->and($day3Buys->pluck('symbol')->all())->toContain('D')
        ->and($day3Buys->pluck('symbol')->all())->not->toContain('A')
        // The scheduled rebalance absorbs the freed cash — no replacement flow
        ->and($day3Buys->firstWhere('symbol', 'D')->reason)->toBe('New entry');
});

// ==========================================================================
// EQUAL WEIGHT REBALANCED
// ==========================================================================

it('trims overweight positions when using equal weight rebalanced', function () {
    $dates = tradingDates(20);
    seedIndexRange('2010-01-01', end($dates), 5000);

    // A: price doubles → becomes overweight → should be trimmed at rebalance
    foreach ($dates as $i => $date) {
        seedInstrument($date, 'A', 100 + $i * 10, ['sharpe_return_one_year' => 5.0]);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
        seedInstrument($date, 'C', 150, ['sharpe_return_one_year' => 3.0]);
    }

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user, ['weightage' => 'equal_weight_rebalanced']);
    run($bt);

    $trimSells = $bt->trades()->where('symbol', 'A')->where('trade_type', 'sell')
        ->where('reason', 'Weight rebalance adjustment')->count();

    expect($trimSells)->toBeGreaterThan(0);
});

// ==========================================================================
// INVERSE VOLATILITY
// ==========================================================================

it('sells stocks whose volatility becomes null under inverse volatility weighting', function () {
    $dates = tradingDates(20);
    seedIndexRange('2010-01-01', end($dates), 5000);

    // C has valid volatility initially (gets bought), then becomes null at day 8 (should be sold)
    foreach ($dates as $i => $date) {
        seedInstrument($date, 'A', 100, ['sharpe_return_one_year' => 5.0, 'volatility_one_year' => 0.3]);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0, 'volatility_one_year' => 0.4]);
        seedInstrument($date, 'C', 300, [
            'sharpe_return_one_year' => 3.0,
            'volatility_one_year' => $i < 8 ? 0.5 : null, // valid → null
        ]);
    }

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user, ['weightage' => 'inverse_volatility']);
    run($bt);

    // C should be bought initially (valid vol) then sold when vol becomes null
    $cBuys = $bt->trades()->where('symbol', 'C')->where('trade_type', 'buy')->count();
    $cSells = $bt->trades()->where('symbol', 'C')->where('trade_type', 'sell')
        ->where('reason', 'like', '%volatility%')->count();

    expect($cBuys)->toBeGreaterThan(0, 'C should be bought initially (has valid vol)')
        ->and($cSells)->toBeGreaterThan(0, 'C should be sold when vol becomes null');
});

it('gives more weight to lower volatility stocks', function () {
    $dates = tradingDates(5);
    seedIndexRange('2010-01-01', end($dates), 5000);

    // Same price, different vol → low vol should get more shares
    foreach ($dates as $date) {
        seedInstrument($date, 'A', 100, ['sharpe_return_one_year' => 5.0, 'volatility_one_year' => 0.2]);
        seedInstrument($date, 'B', 100, ['sharpe_return_one_year' => 4.0, 'volatility_one_year' => 0.8]);
    }

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user, ['max_stocks_to_hold' => 2, 'weightage' => 'inverse_volatility']);
    run($bt);

    $aBuy = $bt->trades()->where('symbol', 'A')->where('trade_type', 'buy')->first();
    $bBuy = $bt->trades()->where('symbol', 'B')->where('trade_type', 'buy')->first();

    expect($aBuy->quantity)->toBeGreaterThan($bBuy->quantity);
});

// ==========================================================================
// CASH INTEREST
// ==========================================================================

it('cash with interest produces higher final value than without', function () {
    $dates = tradingDates(20);
    seedIndexRange('2010-01-01', end($dates), 5000);
    foreach ($dates as $i => $date) {
        seedInstrument($date, 'A', 100 + $i, ['sharpe_return_one_year' => 5.0]);
        seedInstrument($date, 'B', 200 + $i, ['sharpe_return_one_year' => 4.0]);
    }

    $user = User::factory()->create(['is_paid' => true]);

    $withInterest = makeBacktest($user, ['max_stocks_to_hold' => 2, 'cash_return_rate' => 50]); // exaggerated
    $noInterest = makeBacktest($user, ['max_stocks_to_hold' => 2, 'cash_return_rate' => 0]);

    run($withInterest);
    run($noInterest);

    $lastWith = (float) $withInterest->dailySnapshots()->orderBy('date', 'desc')->first()->total_value;
    $lastWithout = (float) $noInterest->dailySnapshots()->orderBy('date', 'desc')->first()->total_value;

    expect($lastWith)->toBeGreaterThan($lastWithout);
});

// ==========================================================================
// CASH CALL: NOT ENOUGH STOCKS
// ==========================================================================

it('keeps proportional cash when fewer stocks available than max slots', function () {
    $dates = tradingDates(5);
    seedIndexRange('2010-01-01', end($dates), 5000);

    // 2 stocks, max_stocks = 5
    foreach ($dates as $date) {
        seedInstrument($date, 'A', 100, ['sharpe_return_one_year' => 5.0]);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
    }

    $user = User::factory()->create(['is_paid' => true]);

    $noCash = makeBacktest($user, ['max_stocks_to_hold' => 5, 'cash_call' => 'no_cash_call']);
    $cashCall = makeBacktest($user, ['max_stocks_to_hold' => 5, 'cash_call' => 'cash_call_if_not_enough_stocks']);

    run($noCash);
    run($cashCall);

    $noCashSnap = $noCash->dailySnapshots()->orderBy('date')->first();
    $cashCallSnap = $cashCall->dailySnapshots()->orderBy('date')->first();

    $cashPct = (float) $cashCallSnap->cash / (float) $cashCallSnap->total_value * 100;

    expect((float) $cashCallSnap->cash)->toBeGreaterThan((float) $noCashSnap->cash)
        ->and($cashPct)->toBeGreaterThan(50);
});

// ==========================================================================
// NEGATIVE CASH GUARD
// ==========================================================================

it('never produces negative cash across volatile price movements with rebalancing', function () {
    $dates = tradingDates(20);
    seedIndexRange('2010-01-01', end($dates), 5000);

    foreach ($dates as $i => $date) {
        seedInstrument($date, 'A', 100 + sin($i * 0.7) * 30, ['sharpe_return_one_year' => 5.0 - $i * 0.2]);
        seedInstrument($date, 'B', 200 + cos($i * 0.5) * 50, ['sharpe_return_one_year' => 4.0 + $i * 0.1]);
        seedInstrument($date, 'C', 150 + sin($i * 0.3) * 40, ['sharpe_return_one_year' => 3.0]);
        seedInstrument($date, 'D', 300 - $i * 3, ['sharpe_return_one_year' => 2.0 + $i * 0.15]);
    }

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user, ['max_stocks_to_hold' => 2, 'worst_rank_held' => 2, 'weightage' => 'equal_weight_rebalanced']);
    run($bt);

    $negativeCash = $bt->dailySnapshots()->where('cash', '<', -0.01)->count();
    expect($negativeCash)->toBe(0);
});

// ==========================================================================
// CIRCUIT-HIT TRADE SKIPPING
// ==========================================================================
// Rebalance day indices for tradingDates() with weekly/Mon: 0, 3, 8, 13, 18.

it('skips selling a held stock that is in circuit on the rebalance day and retries on the next rebalance', function () {
    $dates = tradingDates(20);
    seedIndexRange('2010-01-01', end($dates), 5000);

    // A starts top-ranked but its sharpe drops from day 3 onward (would be sold).
    // On day 3 (first rebalance after entry), A's t_percent is 5.00 → sell skipped.
    // On day 8 (next rebalance), A's t_percent is normal → sold.
    foreach ($dates as $i => $date) {
        $aSharpe = $i < 3 ? 5.0 : 0.1;
        $aTPercent = $i === 3 ? 5.00 : 1.0;
        seedInstrument($date, 'A', 100, ['sharpe_return_one_year' => $aSharpe, 't_percent' => $aTPercent]);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
        seedInstrument($date, 'C', 150, ['sharpe_return_one_year' => 3.0]);
        seedInstrument($date, 'D', 250, ['sharpe_return_one_year' => 3.5]);
    }

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user, ['worst_rank_held' => 3]);
    run($bt);

    $aSells = $bt->trades()->where('symbol', 'A')->where('trade_type', 'sell')->get();
    expect($aSells)->toHaveCount(1)
        ->and($aSells->first()->date->format('Y-m-d'))->toBe($dates[8]);
});

it('substitutes the next-ranked stock for an entry candidate that is in circuit', function () {
    $dates = tradingDates(10);
    seedIndexRange('2010-01-01', end($dates), 5000);

    // Ranking by sharpe: A > B > C > D. max_stocks_to_hold=3.
    // On day 0, A is in circuit → A skipped, D slides in to fill the third slot.
    foreach ($dates as $i => $date) {
        $aTPercent = $i === 0 ? 5.00 : 1.0;
        seedInstrument($date, 'A', 100, ['sharpe_return_one_year' => 5.0, 't_percent' => $aTPercent]);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
        seedInstrument($date, 'C', 150, ['sharpe_return_one_year' => 3.0]);
        seedInstrument($date, 'D', 250, ['sharpe_return_one_year' => 2.0]);
    }

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user);
    run($bt);

    $day0Buys = $bt->trades()->where('date', $dates[0])->where('trade_type', 'buy')->pluck('symbol')->all();
    expect($day0Buys)->not->toContain('A')
        ->and($day0Buys)->toContain('B')
        ->and($day0Buys)->toContain('C')
        ->and($day0Buys)->toContain('D');
});

it('anchors the circuit check to the execution day, ignoring decision-day circuits, when execute next trading day is enabled', function () {
    $dates = tradingDates(10);
    seedIndexRange('2010-01-01', end($dates), 5000);

    // Decision day = day 0; execution day = day 1. A is in circuit on day 0 only.
    // Circuit anchor is execution day → A trades on day 1.
    foreach ($dates as $i => $date) {
        $aTPercent = $i === 0 ? 5.00 : 1.0;
        seedInstrument($date, 'A', 100, ['sharpe_return_one_year' => 5.0, 't_percent' => $aTPercent]);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
        seedInstrument($date, 'C', 300, ['sharpe_return_one_year' => 3.0]);
    }

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user, ['execute_next_trading_day' => true]);
    run($bt);

    $aBuys = $bt->trades()->where('symbol', 'A')->where('trade_type', 'buy')->count();
    expect($aBuys)->toBeGreaterThan(0);
});

it('skips entry when the stock is in circuit on the execution day under execute next trading day', function () {
    $dates = tradingDates(10);
    seedIndexRange('2010-01-01', end($dates), 5000);

    // Decision day = day 0 (clean); execution day = day 1 (A in circuit).
    // Circuit anchor is execution day → A entry skipped on day 1, D slides in.
    foreach ($dates as $i => $date) {
        $aTPercent = $i === 1 ? 5.00 : 1.0;
        seedInstrument($date, 'A', 100, ['sharpe_return_one_year' => 5.0, 't_percent' => $aTPercent]);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
        seedInstrument($date, 'C', 150, ['sharpe_return_one_year' => 3.0]);
        seedInstrument($date, 'D', 250, ['sharpe_return_one_year' => 2.0]);
    }

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user, ['execute_next_trading_day' => true]);
    run($bt);

    $aBuysOnDay1 = $bt->trades()->where('symbol', 'A')->where('trade_type', 'buy')->where('date', $dates[1])->count();
    expect($aBuysOnDay1)->toBe(0);
});

it('keeps a circuit-hit holding when full cash call sells everything', function () {
    $dates = tradingDates(20);
    seedIndexRange('2010-01-01', $dates[7], 6000);
    seedIndexRange($dates[8], end($dates), 3000);

    // Cash-call rebalance fires on day 8 (Mon, index has crashed below DMA20).
    // A is in circuit on day 8 → kept; B and C sold.
    foreach ($dates as $i => $date) {
        $aTPercent = $i === 8 ? 5.00 : 1.0;
        seedInstrument($date, 'A', 100, ['sharpe_return_one_year' => 5.0, 't_percent' => $aTPercent]);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
        seedInstrument($date, 'C', 300, ['sharpe_return_one_year' => 3.0]);
    }

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user, ['cash_call' => 'full_cash_below_index_dma', 'cash_call_dma_period' => 20]);
    run($bt);

    $day8Sells = $bt->trades()->where('date', $dates[8])->where('trade_type', 'sell')->pluck('symbol')->all();
    expect($day8Sells)->toContain('B')
        ->and($day8Sells)->toContain('C')
        ->and($day8Sells)->not->toContain('A');

    $day8Snap = $bt->dailySnapshots()->where('date', $dates[8])->first();
    expect($day8Snap->holdings_count)->toBe(1);
});

it('skips the goldbees rotation buy when goldbees is in circuit on the rebalance day', function () {
    $dates = tradingDates(20);
    seedIndexRange('2010-01-01', $dates[7], 6000);
    seedIndexRange($dates[8], end($dates), 3000);

    foreach ($dates as $i => $date) {
        seedInstrument($date, 'A', 100, ['sharpe_return_one_year' => 5.0]);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
        $goldTPercent = $i === 8 ? 5.00 : 1.0;
        seedInstrument($date, 'GOLDBEES', 2500, [
            'sharpe_return_one_year' => 0.1,
            'is_etf' => true,
            't_percent' => $goldTPercent,
        ]);
    }

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user, ['cash_call' => 'allocate_to_gold_below_index_dma', 'cash_call_dma_period' => 20]);
    run($bt);

    $day8GoldBuys = $bt->trades()
        ->where('date', $dates[8])
        ->where('symbol', 'GOLDBEES')
        ->where('trade_type', 'buy')
        ->count();
    expect($day8GoldBuys)->toBe(0);
});

it('does not skip trades when skip_circuit_trades is disabled', function () {
    $dates = tradingDates(10);
    seedIndexRange('2010-01-01', end($dates), 5000);

    foreach ($dates as $i => $date) {
        $aTPercent = $i === 0 ? 5.00 : 1.0;
        seedInstrument($date, 'A', 100, ['sharpe_return_one_year' => 5.0, 't_percent' => $aTPercent]);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
        seedInstrument($date, 'C', 150, ['sharpe_return_one_year' => 3.0]);
    }

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user, ['skip_circuit_trades' => false]);
    run($bt);

    $aBuysOnDay0 = $bt->trades()->where('symbol', 'A')->where('trade_type', 'buy')->where('date', $dates[0])->count();
    expect($aBuysOnDay0)->toBeGreaterThan(0);
});

it('flags every defined circuit threshold and skips entry', function (float $tPercent) {
    $dates = tradingDates(10);
    seedIndexRange('2010-01-01', end($dates), 5000);

    // A would be top-ranked but its t_percent on day 0 hits a circuit threshold.
    // Expected: A skipped on entry, D slides up to fill the third slot.
    foreach ($dates as $i => $date) {
        $aTPercent = $i === 0 ? $tPercent : 1.0;
        seedInstrument($date, 'A', 100, ['sharpe_return_one_year' => 5.0, 't_percent' => $aTPercent]);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
        seedInstrument($date, 'C', 150, ['sharpe_return_one_year' => 3.0]);
        seedInstrument($date, 'D', 250, ['sharpe_return_one_year' => 2.0]);
    }

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user);
    run($bt);

    $aBuysOnDay0 = $bt->trades()->where('symbol', 'A')->where('trade_type', 'buy')->where('date', $dates[0])->count();
    expect($aBuysOnDay0)->toBe(0);
})->with([
    'upper 5%' => 5.00, 'upper 5% rounded down' => 4.99,
    'upper 10%' => 10.00, 'upper 10% rounded down' => 9.99,
    'upper 20%' => 20.00, 'upper 20% rounded down' => 19.99,
    'lower 5%' => -5.00, 'lower 5% rounded up' => -4.99,
    'lower 10%' => -10.00, 'lower 10% rounded up' => -9.99,
    'lower 20%' => -20.00, 'lower 20% rounded up' => -19.99,
]);

it('does not flag stocks just outside circuit thresholds', function (float $tPercent) {
    $dates = tradingDates(10);
    seedIndexRange('2010-01-01', end($dates), 5000);

    foreach ($dates as $i => $date) {
        $aTPercent = $i === 0 ? $tPercent : 1.0;
        seedInstrument($date, 'A', 100, ['sharpe_return_one_year' => 5.0, 't_percent' => $aTPercent]);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
        seedInstrument($date, 'C', 150, ['sharpe_return_one_year' => 3.0]);
    }

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user);
    run($bt);

    $aBuysOnDay0 = $bt->trades()->where('symbol', 'A')->where('trade_type', 'buy')->where('date', $dates[0])->count();
    expect($aBuysOnDay0)->toBeGreaterThan(0);
})->with([
    'just past upper 5%' => 5.01, 'just under upper 4.99' => 4.98,
    'just past upper 10%' => 10.01, 'just under upper 9.99' => 9.98,
    'just past upper 20%' => 20.01, 'just under upper 19.99' => 19.98,
    'just past lower 5%' => -5.01, 'just under lower 4.99' => -4.98,
    'just past lower 10%' => -10.01, 'just under lower 9.99' => -9.98,
    'just past lower 20%' => -20.01, 'just under lower 19.99' => -19.98,
]);

// ==========================================================================
// PER-TRADE-CYCLE STOCK PERFORMANCE
// ==========================================================================

it('records entry date, exit date, and holding days for a still-held position', function () {
    $dates = tradingDates(10);
    seedIndexRange('2010-01-01', end($dates), 5000);

    foreach ($dates as $i => $date) {
        seedInstrument($date, 'A', 100 + $i, ['sharpe_return_one_year' => 5.0]);
        seedInstrument($date, 'B', 200 + $i, ['sharpe_return_one_year' => 4.0]);
    }

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user, ['max_stocks_to_hold' => 2]);
    run($bt);

    $perf = $bt->summaryMetrics?->stock_performance ?? [];
    $a = collect($perf)->firstWhere('symbol', 'A');

    expect($a)->not->toBeNull()
        ->and($a['entry_date'])->toBe($dates[0])
        ->and($a['exit_date'])->toBeNull()
        ->and($a['still_held'])->toBeTrue()
        ->and($a['holding_days'])->toBeGreaterThan(0);
});

it('produces a separate trade cycle for each buy-sell round-trip on the same stock', function () {
    $dates = tradingDates(20);
    seedIndexRange('2010-01-01', end($dates), 5000);

    // A starts top-ranked, drops out by day 8 (sold), then recovers by day 13 (re-bought).
    // Expected: two distinct positions for A.
    foreach ($dates as $i => $date) {
        $aSharpe = match (true) {
            $i < 8 => 5.0,
            $i < 13 => 0.1,
            default => 5.5,
        };
        seedInstrument($date, 'A', 100, ['sharpe_return_one_year' => $aSharpe]);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
        seedInstrument($date, 'C', 150, ['sharpe_return_one_year' => 3.0]);
        seedInstrument($date, 'D', 250, ['sharpe_return_one_year' => 2.0]);
    }

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user, ['max_stocks_to_hold' => 2, 'worst_rank_held' => 2]);
    run($bt);

    $perf = $bt->summaryMetrics?->stock_performance ?? [];
    $aPositions = collect($perf)->where('symbol', 'A')->values();

    expect($aPositions)->toHaveCount(2);

    $closed = $aPositions->firstWhere('still_held', false);
    expect($closed)->not->toBeNull()
        ->and($closed['entry_date'])->toBe($dates[0])
        ->and($closed['exit_date'])->not->toBeNull()
        ->and($closed['holding_days'])->toBeGreaterThan(0);
});

// ==========================================================================
// CONFIGURABLE TRANSACTION COSTS
// ==========================================================================

it('applies a configured brokerage rate to trades and still conserves money', function () {
    $dates = tradingDates(10);
    seedIndexRange('2010-01-01', end($dates), 5000);

    // Flat prices: only charges reduce value, so conservation is exact.
    foreach ($dates as $date) {
        seedInstrument($date, 'A', 100, ['sharpe_return_one_year' => 5.0]);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
    }

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user, ['max_stocks_to_hold' => 2, 'brokerage_rate' => 0.5]);
    run($bt);

    $buy = $bt->trades()->where('trade_type', 'buy')->first();
    $expectedGst = ((float) $buy->brokerage + (float) $buy->transaction_charges + (float) $buy->sebi_charges) * 0.18;

    expect((float) $buy->brokerage)->toEqualWithDelta((float) $buy->gross_amount * 0.005, 0.01)
        // GST base includes the brokerage
        ->and((float) $buy->gst)->toEqualWithDelta($expectedGst, 0.02);

    $allCharges = (float) $bt->trades()->sum('total_charges');
    $lastSnap = $bt->dailySnapshots()->orderBy('date', 'desc')->first();
    expect(round((float) $lastSnap->total_value + $allCharges, 0))->toBe(1000000.0);
});

it('charges nothing on trades when every cost rate is zero', function () {
    $dates = tradingDates(10);
    seedIndexRange('2010-01-01', end($dates), 5000);

    foreach ($dates as $date) {
        seedInstrument($date, 'A', 100, ['sharpe_return_one_year' => 5.0]);
        seedInstrument($date, 'B', 200, ['sharpe_return_one_year' => 4.0]);
    }

    $user = User::factory()->create(['is_paid' => true]);
    $bt = makeBacktest($user, [
        'max_stocks_to_hold' => 2,
        'brokerage_rate' => 0,
        'stt_rate' => 0,
        'transaction_charges_rate' => 0,
        'sebi_charges_rate' => 0,
        'gst_rate' => 0,
        'stamp_charges_rate' => 0,
    ]);
    run($bt);

    expect($bt->trades()->count())->toBeGreaterThan(0)
        ->and((float) $bt->trades()->sum('total_charges'))->toBe(0.0);
});
