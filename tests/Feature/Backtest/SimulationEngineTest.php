<?php

use App\Actions\Backtest\ApplyBacktestFiltersAction;
use App\Actions\Backtest\CalculateBacktestMetricsAction;
use App\Actions\Backtest\CalculateTransactionCostsAction;
use App\Actions\Backtest\RunBacktestAction;
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
        'price_adjustment_factor' => '0.1', 'dividend_adjustment_factor' => '1', 'dividend' => '0',
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
    $action = new RunBacktestAction(new ApplyBacktestFiltersAction(), new CalculateTransactionCostsAction());
    $action->execute($bt);
    (new CalculateBacktestMetricsAction())->execute($bt);
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
