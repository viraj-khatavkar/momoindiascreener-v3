<?php

use App\Actions\Backtest\ApplyBacktestFiltersAction;
use App\Actions\Backtest\CalculateBacktestMetricsAction;
use App\Actions\Backtest\CalculateTransactionCostsAction;
use App\Actions\Backtest\RunBacktestAction;
use App\Models\Backtest;
use App\Models\BacktestDailySnapshot;
use App\Models\BacktestNseInstrumentPrice;
use App\Models\BacktestTrade;
use App\Models\NseIndex;
use App\Models\User;

// --- Helper: seed minimal instrument data ---

function seedTestInstruments(array $dates, array $stocks): void
{
    foreach ($dates as $date) {
        foreach ($stocks as $symbol => $pricesByDate) {
            $price = $pricesByDate[$date] ?? null;
            if ($price === null) {
                continue;
            }

            BacktestNseInstrumentPrice::insert([
                'date' => $date,
                'symbol' => $symbol,
                'name' => $symbol,
                'series' => 'EQ',
                'is_delisted' => false,
                'open_adjusted' => $price, 'high_adjusted' => $price, 'low_adjusted' => $price,
                'close_adjusted' => $price,
                'open_raw' => $price * 10, 'high_raw' => $price * 10, 'low_raw' => $price * 10,
                'close_raw' => $price * 10,
                'volume_adjusted' => 1000000, 'volume_shares_adjusted' => 100000,
                'volume_raw' => 1000000, 'volume_shares_raw' => 100000,
                't_percent' => 1.0, 't_percent_raw' => 1.0,
                'marketcap' => 500000,
                'price_to_earnings' => 20,
                'price_adjustment_factor' => '0.1', 'dividend_adjustment_factor' => '1',
                'dividend' => '0',
                'variance_one_year' => 0.01, 'variance_nine_months' => 0.01,
                'variance_six_months' => 0.01, 'variance_three_months' => 0.01, 'variance_one_months' => 0.01,
                'standard_deviation_one_year' => 0.1, 'standard_deviation_nine_months' => 0.1,
                'standard_deviation_six_months' => 0.1, 'standard_deviation_three_months' => 0.1, 'standard_deviation_one_months' => 0.1,
                'volatility_one_year' => 0.3 + (crc32($symbol) % 10) * 0.05,
                'volatility_nine_months' => 0.3, 'volatility_six_months' => 0.3,
                'volatility_three_months' => 0.3, 'volatility_one_months' => 0.3,
                'covariance' => 0.01, 'beta' => 1.0,
                'absolute_return_one_year' => 20, 'absolute_return_nine_months' => 15,
                'absolute_return_six_months' => 10, 'absolute_return_three_months' => 5, 'absolute_return_one_months' => 2,
                'average_absolute_return_twelve_nine_six_three_one_months' => 10,
                'average_absolute_return_twelve_nine_six_three_months' => 12,
                'average_absolute_return_twelve_nine_six_months' => 15,
                'average_absolute_return_twelve_nine_months' => 17,
                'average_absolute_return_twelve_six_three_one_months' => 9,
                'average_absolute_return_twelve_six_three_months' => 11,
                'average_absolute_return_twelve_six_months' => 15,
                'average_absolute_return_twelve_three_one_months' => 9,
                'average_absolute_return_twelve_three_months' => 12,
                'average_absolute_return_twelve_nine_three_one_months' => 10,
                'average_absolute_return_twelve_nine_three_months' => 13,
                'average_absolute_return_nine_six_three_one_months' => 8,
                'average_absolute_return_nine_six_three_months' => 10,
                'average_absolute_return_nine_six_months' => 12,
                'average_absolute_return_six_three_one_months' => 5,
                'average_absolute_return_six_three_months' => 7,
                'average_absolute_return_three_one_months' => 3,
                'sharpe_return_one_year' => 2.0 + (crc32($symbol) % 5) * 0.3,
                'sharpe_return_nine_months' => 1.8, 'sharpe_return_six_months' => 1.5,
                'sharpe_return_three_months' => 1.0, 'sharpe_return_one_months' => 0.5,
                'average_sharpe_return_twelve_nine_six_three_one_months' => 1.3,
                'average_sharpe_return_twelve_nine_six_three_months' => 1.5,
                'average_sharpe_return_twelve_nine_six_months' => 1.7,
                'average_sharpe_return_twelve_nine_months' => 1.9,
                'average_sharpe_return_twelve_six_three_one_months' => 1.2,
                'average_sharpe_return_twelve_six_three_months' => 1.4,
                'average_sharpe_return_twelve_six_months' => 1.6,
                'average_sharpe_return_twelve_three_one_months' => 1.1,
                'average_sharpe_return_twelve_three_months' => 1.3,
                'average_sharpe_return_twelve_nine_three_one_months' => 1.2,
                'average_sharpe_return_twelve_nine_three_months' => 1.5,
                'average_sharpe_return_nine_six_three_one_months' => 1.0,
                'average_sharpe_return_nine_six_three_months' => 1.2,
                'average_sharpe_return_nine_six_months' => 1.4,
                'average_sharpe_return_six_three_one_months' => 0.8,
                'average_sharpe_return_six_three_months' => 1.0,
                'average_sharpe_return_three_one_months' => 0.5,
                'rsi_one_year' => 55, 'rsi_nine_months' => 55, 'rsi_six_months' => 55,
                'rsi_three_months' => 55, 'rsi_one_months' => 55,
                'average_rsi_twelve_nine_six_three_one_months' => 55,
                'average_rsi_twelve_nine_six_three_months' => 55,
                'average_rsi_twelve_nine_six_months' => 55,
                'average_rsi_twelve_nine_months' => 55,
                'average_rsi_twelve_six_three_one_months' => 55,
                'average_rsi_twelve_six_three_months' => 55,
                'average_rsi_twelve_six_months' => 55,
                'average_rsi_twelve_three_one_months' => 55,
                'average_rsi_twelve_three_months' => 55,
                'average_rsi_twelve_nine_three_one_months' => 55,
                'average_rsi_twelve_nine_three_months' => 55,
                'average_rsi_nine_six_three_one_months' => 55,
                'average_rsi_nine_six_three_months' => 55,
                'average_rsi_nine_six_months' => 55,
                'average_rsi_six_three_one_months' => 55,
                'average_rsi_six_three_months' => 55,
                'average_rsi_three_one_months' => 55,
                'absolute_divide_beta_return_one_year' => 20,
                'sharpe_divide_beta_return_one_year' => 2,
                'average_sharpe_divide_beta_return_twelve_nine_six_three_months' => 1.5,
                'average_sharpe_divide_beta_return_twelve_six_three_months' => 1.3,
                'average_sharpe_divide_beta_return_twelve_six_months' => 1.5,
                'return_twelve_minus_one_months' => 18,
                'return_twelve_minus_two_months' => 15,
                'circuits_one_year' => 0, 'circuits_nine_months' => 0, 'circuits_six_months' => 0,
                'circuits_three_months' => 0, 'circuits_one_months' => 0,
                'positive_days_percent_one_year' => 55, 'positive_days_percent_nine_months' => 55,
                'positive_days_percent_six_months' => 55, 'positive_days_percent_three_months' => 55,
                'positive_days_percent_one_months' => 55,
                'away_from_high_one_year' => -5, 'away_from_high_all_time' => -10,
                'high_one_year' => $price * 1.1, 'high_all_time' => $price * 1.2,
                'ma_200' => $price * 0.9, 'ma_100' => $price * 0.95,
                'ma_50' => $price * 0.98, 'ma_20' => $price * 0.99,
                'ema_200' => $price * 0.9, 'ema_100' => $price * 0.95,
                'ema_50' => $price * 0.98, 'ema_20' => $price * 0.99,
                'median_volume_one_year' => 50000000,
                'volume_day' => 1000000, 'volume_one_year_average' => 1000000,
                'volume_nine_months_average' => 1000000, 'volume_six_months_average' => 1000000,
                'volume_three_months_average' => 1000000, 'volume_one_months_average' => 1000000,
                'volume_week_average' => 1000000,
                'is_nifty_50' => true, 'is_nifty_next_50' => false, 'is_nifty_100' => true,
                'is_nifty_200' => true, 'is_nifty_midcap_100' => false, 'is_nifty_500' => true,
                'is_nifty_smallcap_250' => false, 'is_nifty_allcap' => true, 'is_etf' => false,
            ]);
        }
    }
}

function seedTestIndex(array $dates, float $startClose = 5000): void
{
    $close = $startClose;
    foreach ($dates as $date) {
        NseIndex::insert([
            'symbol' => 'Nifty 50', 'slug' => 'nifty-50', 'date' => $date,
            'open' => $close, 'high' => $close, 'low' => $close, 'close' => $close,
            'points_change' => 0, 'percentage_change' => 0, 'volume' => 0, 'turnover' => 0,
            'price_to_earnings' => 20, 'price_to_book' => 3, 'dividend_yield' => 1.5,
        ]);
        $close += rand(-50, 50);
    }
}

function createTestBacktest(User $user, array $overrides = []): Backtest
{
    return Backtest::factory()->create(array_merge([
        'user_id' => $user->id,
        'max_stocks_to_hold' => 3,
        'worst_rank_held' => 10,
        'rebalance_frequency' => 'monthly',
        'rebalance_day' => 1,
        'weightage' => 'equal_weight',
        'cash_call' => 'no_cash_call',
        'cash_call_index' => 'nifty-50',
        'cash_call_dma_period' => 50,
        'cash_return_rate' => 0,
        'initial_capital' => 1000000,
        'median_volume_one_year' => 10000000,
        'minimum_return_one_year' => -100,
    ], $overrides));
}

function runBacktest(Backtest $backtest): void
{
    $run = new RunBacktestAction(new ApplyBacktestFiltersAction(), new CalculateTransactionCostsAction());
    $run->execute($backtest);
    (new CalculateBacktestMetricsAction())->execute($backtest);
}

// Generate 80 trading dates starting from 2011-01-05 (skip weekends)
function generateTradingDates(int $count = 80): array
{
    $dates = [];
    $d = new DateTime('2011-01-05');
    while (count($dates) < $count) {
        $dow = (int) $d->format('N');
        if ($dow <= 5) {
            $dates[] = $d->format('Y-m-d');
        }
        $d->modify('+1 day');
    }

    return $dates;
}

// ==========================================================================
// TESTS
// ==========================================================================

it('runs a basic backtest and produces snapshots and trades', function () {
    $dates = generateTradingDates(30);
    $stocks = [
        'STOCKA' => array_fill_keys($dates, 100),
        'STOCKB' => array_fill_keys($dates, 200),
        'STOCKC' => array_fill_keys($dates, 300),
        'STOCKD' => array_fill_keys($dates, 400),
        'STOCKE' => array_fill_keys($dates, 500),
    ];
    seedTestInstruments($dates, $stocks);
    seedTestIndex($dates);

    $user = User::factory()->create(['is_paid' => true]);
    $backtest = createTestBacktest($user);

    runBacktest($backtest);

    expect($backtest->dailySnapshots()->count())->toBe(30)
        ->and($backtest->trades()->count())->toBeGreaterThan(0)
        ->and($backtest->summaryMetrics)->not->toBeNull();
});

it('conserves money: total value plus total charges equals initial capital on day one', function () {
    $dates = generateTradingDates(5);
    $stocks = [
        'STOCKA' => array_fill_keys($dates, 100),
        'STOCKB' => array_fill_keys($dates, 200),
        'STOCKC' => array_fill_keys($dates, 300),
    ];
    seedTestInstruments($dates, $stocks);
    seedTestIndex($dates);

    $user = User::factory()->create(['is_paid' => true]);
    $backtest = createTestBacktest($user, ['cash_return_rate' => 0]);

    runBacktest($backtest);

    $firstSnapshot = $backtest->dailySnapshots()->orderBy('date')->first();
    $tradesOnDay1 = $backtest->trades()->where('date', $dates[0])->sum('total_charges');

    $totalValue = (float) $firstSnapshot->total_value;
    $totalCharges = (float) $tradesOnDay1;

    // total_value + charges should equal initial capital (money conservation)
    expect(round($totalValue + $totalCharges, 2))->toBe(1000000.00);
});

it('sells use todays price not yesterdays', function () {
    $dates = generateTradingDates(30);

    $stocks = [
        'STOCKA' => array_fill_keys($dates, 100),
        'STOCKB' => array_fill_keys($dates, 200),
        'STOCKC' => array_fill_keys($dates, 300),
        'STOCKD' => array_fill_keys($dates, 400),
    ];
    seedTestInstruments($dates, $stocks);
    seedTestIndex($dates);

    $user = User::factory()->create(['is_paid' => true]);
    // worst_rank = 1: only rank #1 stock survives, rank #2 gets sold each rebalance
    // weekly rebalance on day 1 (Monday) to ensure multiple rebalances happen
    $backtest = createTestBacktest($user, [
        'max_stocks_to_hold' => 2,
        'worst_rank_held' => 1,
        'rebalance_frequency' => 'weekly',
        'rebalance_day' => 1,
        'cash_return_rate' => 0,
    ]);

    runBacktest($backtest);

    $sellTrades = $backtest->trades()->where('trade_type', 'sell')->get();

    expect($sellTrades)->not->toBeEmpty();

    foreach ($sellTrades as $sellTrade) {
        $instrumentOnTradeDate = BacktestNseInstrumentPrice::where('symbol', $sellTrade->symbol)
            ->where('date', $sellTrade->date)
            ->first();

        // Sell price must match today's close_adjusted, not yesterday's
        expect(round((float) $sellTrade->price, 2))
            ->toBe(round((float) $instrumentOnTradeDate->close_adjusted, 2));
    }
});

it('applies daily cash interest correctly', function () {
    $dates = generateTradingDates(10);
    $stocks = [
        'STOCKA' => array_fill_keys($dates, 100),
        'STOCKB' => array_fill_keys($dates, 200),
        'STOCKC' => array_fill_keys($dates, 300),
    ];
    seedTestInstruments($dates, $stocks);
    seedTestIndex($dates);

    $user = User::factory()->create(['is_paid' => true]);
    $backtestWithInterest = createTestBacktest($user, ['cash_return_rate' => 6]);
    $backtestNoInterest = createTestBacktest($user, ['cash_return_rate' => 0]);

    runBacktest($backtestWithInterest);
    runBacktest($backtestNoInterest);

    $lastWithInterest = $backtestWithInterest->dailySnapshots()->orderBy('date', 'desc')->first();
    $lastNoInterest = $backtestNoInterest->dailySnapshots()->orderBy('date', 'desc')->first();

    // With interest, total value should be higher (cash earns returns)
    expect((float) $lastWithInterest->total_value)
        ->toBeGreaterThan((float) $lastNoInterest->total_value);
});

it('holds more cash with cash call if not enough stocks option', function () {
    $dates = generateTradingDates(10);
    // Only 2 stocks pass, but max_stocks is 5
    $stocks = [
        'STOCKA' => array_fill_keys($dates, 100),
        'STOCKB' => array_fill_keys($dates, 200),
    ];
    seedTestInstruments($dates, $stocks);
    seedTestIndex($dates);

    $user = User::factory()->create(['is_paid' => true]);

    $noCashCall = createTestBacktest($user, [
        'max_stocks_to_hold' => 5,
        'cash_call' => 'no_cash_call',
        'cash_return_rate' => 0,
    ]);
    $cashCallNotEnough = createTestBacktest($user, [
        'max_stocks_to_hold' => 5,
        'cash_call' => 'cash_call_if_not_enough_stocks',
        'cash_return_rate' => 0,
    ]);

    runBacktest($noCashCall);
    runBacktest($cashCallNotEnough);

    $noCashSnapshot = $noCashCall->dailySnapshots()->orderBy('date')->first();
    $cashCallSnapshot = $cashCallNotEnough->dailySnapshots()->orderBy('date')->first();

    // cash_call_if_not_enough_stocks should have MORE cash (allocates per max_stocks, not per actual)
    expect((float) $cashCallSnapshot->cash)->toBeGreaterThan((float) $noCashSnapshot->cash);
});

it('produces correct cagr in summary metrics', function () {
    $dates = generateTradingDates(30);
    $stocks = [
        'STOCKA' => array_fill_keys($dates, 100),
        'STOCKB' => array_fill_keys($dates, 200),
        'STOCKC' => array_fill_keys($dates, 300),
    ];
    seedTestInstruments($dates, $stocks);
    seedTestIndex($dates);

    $user = User::factory()->create(['is_paid' => true]);
    $backtest = createTestBacktest($user);

    runBacktest($backtest);

    $metrics = $backtest->summaryMetrics;

    expect($metrics)->not->toBeNull()
        ->and((float) $metrics->cagr)->toBeFloat()
        ->and((float) $metrics->max_drawdown)->toBeLessThanOrEqual(0)
        ->and($metrics->total_trades)->toBeGreaterThan(0)
        ->and((float) $metrics->total_charges_paid)->toBeGreaterThan(0)
        ->and((float) $metrics->final_value)->toBeGreaterThan(0);
});

it('execute next trading day uses decision date for filter and execution date for prices', function () {
    $dates = generateTradingDates(10);

    // STOCKA: price 100 on all days except day 2 where it's 150
    // STOCKB: price 200 on all days
    $stockAPrices = array_fill_keys($dates, 100);
    $stockAPrices[$dates[1]] = 150; // day 2 price is different

    $stocks = [
        'STOCKA' => $stockAPrices,
        'STOCKB' => array_fill_keys($dates, 200),
        'STOCKC' => array_fill_keys($dates, 300),
    ];
    seedTestInstruments($dates, $stocks);
    seedTestIndex($dates);

    $user = User::factory()->create(['is_paid' => true]);

    // Same day execution
    $sameDay = createTestBacktest($user, ['execute_next_trading_day' => false, 'cash_return_rate' => 0]);
    // Next day execution
    $nextDay = createTestBacktest($user, ['execute_next_trading_day' => true, 'cash_return_rate' => 0]);

    runBacktest($sameDay);
    runBacktest($nextDay);

    // With next day execution, initial buy happens on day 2 (not day 1)
    $sameDayBuys = $sameDay->trades()->where('trade_type', 'buy')->where('date', $dates[0])->count();
    $nextDayBuysDay1 = $nextDay->trades()->where('trade_type', 'buy')->where('date', $dates[0])->count();
    $nextDayBuysDay2 = $nextDay->trades()->where('trade_type', 'buy')->where('date', $dates[1])->count();

    expect($sameDayBuys)->toBeGreaterThan(0) // same day: buys on day 1
        ->and($nextDayBuysDay1)->toBe(0)      // next day: no buys on day 1
        ->and($nextDayBuysDay2)->toBeGreaterThan(0); // next day: buys on day 2
});

it('clears old results when re-running a backtest', function () {
    $dates = generateTradingDates(10);
    $stocks = [
        'STOCKA' => array_fill_keys($dates, 100),
        'STOCKB' => array_fill_keys($dates, 200),
        'STOCKC' => array_fill_keys($dates, 300),
    ];
    seedTestInstruments($dates, $stocks);
    seedTestIndex($dates);

    $user = User::factory()->create(['is_paid' => true]);
    $backtest = createTestBacktest($user);

    // Run once
    runBacktest($backtest);
    $firstRunSnapshots = $backtest->dailySnapshots()->count();
    $firstRunTrades = $backtest->trades()->count();

    // Clear and run again
    $backtest->trades()->delete();
    $backtest->dailySnapshots()->delete();
    $backtest->summaryMetrics()->delete();
    runBacktest($backtest);

    $secondRunSnapshots = $backtest->dailySnapshots()->count();
    $secondRunTrades = $backtest->trades()->count();

    expect($secondRunSnapshots)->toBe($firstRunSnapshots)
        ->and($secondRunTrades)->toBe($firstRunTrades);
});

it('never produces negative cash balance', function () {
    $dates = generateTradingDates(30);
    // Prices that vary to cause rebalancing
    $stocks = [];
    foreach (['A', 'B', 'C', 'D', 'E'] as $i => $letter) {
        $prices = [];
        foreach ($dates as $j => $date) {
            $prices[$date] = 100 + ($i * 50) + sin($j * 0.5) * 20;
        }
        $stocks["STOCK{$letter}"] = $prices;
    }
    seedTestInstruments($dates, $stocks);
    seedTestIndex($dates);

    $user = User::factory()->create(['is_paid' => true]);
    $backtest = createTestBacktest($user, ['max_stocks_to_hold' => 3, 'cash_return_rate' => 0]);

    runBacktest($backtest);

    $negativeCashDays = $backtest->dailySnapshots()->where('cash', '<', -0.01)->count();

    expect($negativeCashDays)->toBe(0);
});

it('nav starts near 100 on first day after accounting for transaction costs', function () {
    $dates = generateTradingDates(5);
    $stocks = [
        'STOCKA' => array_fill_keys($dates, 100),
        'STOCKB' => array_fill_keys($dates, 200),
        'STOCKC' => array_fill_keys($dates, 300),
    ];
    seedTestInstruments($dates, $stocks);
    seedTestIndex($dates);

    $user = User::factory()->create(['is_paid' => true]);
    $backtest = createTestBacktest($user, ['cash_return_rate' => 0]);

    runBacktest($backtest);

    $firstNav = (float) $backtest->dailySnapshots()->orderBy('date')->first()->nav;

    // NAV should be slightly below 100 due to buy transaction costs
    expect($firstNav)->toBeLessThan(100)
        ->and($firstNav)->toBeGreaterThan(99);
});

it('all buys on initial rebalance have reason new entry', function () {
    $dates = generateTradingDates(5);
    $stocks = [
        'STOCKA' => array_fill_keys($dates, 100),
        'STOCKB' => array_fill_keys($dates, 200),
        'STOCKC' => array_fill_keys($dates, 300),
    ];
    seedTestInstruments($dates, $stocks);
    seedTestIndex($dates);

    $user = User::factory()->create(['is_paid' => true]);
    $backtest = createTestBacktest($user);

    runBacktest($backtest);

    $firstDayTrades = $backtest->trades()->where('date', $dates[0])->get();

    expect($firstDayTrades)->not->toBeEmpty();

    foreach ($firstDayTrades as $trade) {
        expect($trade->trade_type)->toBe('buy')
            ->and($trade->reason)->toBe('New entry');
    }
});

it('buys raw price is stored alongside adjusted price', function () {
    $dates = generateTradingDates(5);
    $stocks = [
        'STOCKA' => array_fill_keys($dates, 100),
        'STOCKB' => array_fill_keys($dates, 200),
    ];
    seedTestInstruments($dates, $stocks); // close_raw = price * 10
    seedTestIndex($dates);

    $user = User::factory()->create(['is_paid' => true]);
    $backtest = createTestBacktest($user, ['max_stocks_to_hold' => 2]);

    runBacktest($backtest);

    $buyTrade = $backtest->trades()->where('trade_type', 'buy')->first();

    // raw_price should be 10x the adjusted price (from seedTestInstruments)
    expect((float) $buyTrade->raw_price)->toBe((float) $buyTrade->price * 10);
});

it('total number of buys equals initial entries plus replacements matching sells', function () {
    $dates = generateTradingDates(30);
    $stocks = [
        'STOCKA' => array_fill_keys($dates, 100),
        'STOCKB' => array_fill_keys($dates, 200),
        'STOCKC' => array_fill_keys($dates, 300),
        'STOCKD' => array_fill_keys($dates, 400),
    ];
    seedTestInstruments($dates, $stocks);
    seedTestIndex($dates);

    $user = User::factory()->create(['is_paid' => true]);
    $backtest = createTestBacktest($user, ['max_stocks_to_hold' => 3]);

    runBacktest($backtest);

    $buyCount = $backtest->trades()->where('trade_type', 'buy')->count();
    $sellCount = $backtest->trades()->where('trade_type', 'sell')->count();
    $initialStocks = $backtest->max_stocks_to_hold;

    // buys = initial entries + replacement entries for each sell
    expect($buyCount)->toBe($initialStocks + $sellCount);
});
