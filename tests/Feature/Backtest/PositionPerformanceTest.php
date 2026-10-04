<?php

use App\Actions\Backtest\CalculateBacktestMetricsAction;
use App\Actions\Backtest\CalculateBacktestPositionPerformanceAction;
use App\Models\Backtest;
use App\Models\BacktestTrade;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

function recordPositionTrade(Backtest $backtest, string $symbol, string $date, string $side, int $quantity, float $price, float $charges = 0): BacktestTrade
{
    return $backtest->trades()->create([
        'symbol' => $symbol, 'name' => $symbol, 'date' => $date, 'trade_type' => $side,
        'reason' => 'Weight rebalance adjustment', 'quantity' => $quantity, 'price' => $price, 'raw_price' => $price,
        'gross_amount' => $quantity * $price, 'brokerage' => $charges, 'stt' => 0,
        'transaction_charges' => 0, 'sebi_charges' => 0, 'gst' => 0, 'stamp_charges' => 0,
        'total_charges' => $charges, 'net_amount' => $quantity * $price + ($side === 'buy' ? $charges : -$charges),
    ]);
}

it('keeps repeated stock entries separate and uses only completed positions for statistics', function () {
    $backtest = Backtest::factory()->create();
    $firstEntry = recordPositionTrade($backtest, 'ITC', '2013-01-01', 'buy', 10, 100, 10);
    recordPositionTrade($backtest, 'ITC', '2014-01-01', 'sell', 4, 120, 4);
    recordPositionTrade($backtest, 'ITC', '2014-06-01', 'buy', 6, 150, 6);
    recordPositionTrade($backtest, 'ITC', '2015-01-01', 'sell', 12, 130, 12);
    $secondEntry = recordPositionTrade($backtest, 'ITC', '2017-01-01', 'buy', 5, 200, 5);
    recordPositionTrade($backtest, 'ITC', '2021-01-01', 'sell', 5, 150, 5);
    recordPositionTrade($backtest, 'FLAT', '2018-01-01', 'buy', 1, 100);
    recordPositionTrade($backtest, 'FLAT', '2018-01-01', 'sell', 1, 100);
    recordPositionTrade($backtest, 'OPEN', '2019-01-01', 'buy', 10, 100, 10);
    recordPositionTrade($backtest, 'OPEN', '2020-01-01', 'sell', 4, 120, 4);
    recordPositionTrade($backtest, 'OPEN', '2020-06-01', 'buy', 4, 150, 4);
    createBacktestPriceRow('OPEN', '2021-01-01', ['close_adjusted' => 1000]);
    recordPositionTrade($backtest, 'OPENLOSS', '2020-01-01', 'buy', 1, 10000);
    createBacktestPriceRow('OPENLOSS', '2021-01-01', ['close_adjusted' => 1]);
    $backtest->dailySnapshots()->create([
        'date' => '2021-01-01', 'nav' => 100, 'portfolio_value' => 10001,
        'cash' => 0, 'total_value' => 10001, 'holdings_count' => 2,
    ]);

    $performance = app(CalculateBacktestPositionPerformanceAction::class)->execute($backtest, Carbon::parse('2021-01-01'));
    $closed = $performance['closed'];
    $winner = $performance['top_winners']['net_pnl'][0];
    $loser = $performance['top_losers']['net_pnl'][0];
    $open = collect($performance['open_positions'])->firstWhere('symbol', 'OPEN');

    expect($closed)->toMatchArray([
        'count' => 3, 'winners' => 1, 'losers' => 1, 'breakeven' => 1,
        'total_profit' => 108.0, 'total_loss' => 260.0, 'net_pnl' => -152.0,
        'average_win' => 108.0, 'average_loss' => -260.0, 'expectancy' => -50.67,
        'average_holding_days' => 730.33, 'winners_percentage' => 33.33, 'profit_factor' => 0.4154,
    ])->and($winner)->toMatchArray([
        'entry_trade_id' => $firstEntry->id, 'symbol' => 'ITC', 'entry_date' => '2013-01-01', 'exit_date' => '2015-01-01',
        'holding_days' => 730, 'buy_value' => 1900.0, 'purchase_cost' => 1916.0,
        'sell_value' => 2040.0, 'charges' => 32.0, 'net_pnl' => 108.0, 'realized_pnl' => 108.0,
        'unrealized_pnl' => 0.0, 'pnl_pct' => 5.64, 'still_held' => false,
    ])->and($loser)->toMatchArray([
        'entry_trade_id' => $secondEntry->id, 'symbol' => 'ITC', 'entry_date' => '2017-01-01', 'exit_date' => '2021-01-01',
        'holding_days' => 1461, 'net_pnl' => -260.0, 'pnl_pct' => -25.87,
    ])->and($open)->toMatchArray([
        'quantity' => 10, 'purchase_cost' => 1614.0, 'remaining_cost' => 1210.0,
        'realized_pnl' => 72.0, 'unrealized_pnl' => 8790.0, 'net_pnl' => 8862.0, 'still_held' => true,
    ])->and($performance['top_winners']['net_pnl'])->toHaveCount(1)
        ->and($performance['top_losers']['net_pnl'])->toHaveCount(1)
        ->and($performance['open_positions'])->toHaveCount(2);
});

it('does not change position statistics when one partial sale is split into several transactions', function () {
    $results = [];
    foreach ([1, 5] as $saleCount) {
        $backtest = Backtest::factory()->create();
        recordPositionTrade($backtest, 'ITC', '2020-01-01', 'buy', 100, 100);
        for ($index = 0; $index < $saleCount; $index++) {
            recordPositionTrade($backtest, 'ITC', '2020-02-01', 'sell', 10 / $saleCount, 110);
        }
        recordPositionTrade($backtest, 'ITC', '2020-03-01', 'sell', 90, 90);
        $results[] = app(CalculateBacktestPositionPerformanceAction::class)->execute($backtest, Carbon::parse('2020-03-01'));
    }

    expect($results[0]['closed'])->toBe($results[1]['closed'])
        ->and($results[0]['closed'])->toMatchArray(['count' => 1, 'winners' => 0, 'losers' => 1, 'net_pnl' => -800.0]);
});

it('handles empty closed results and one-sided results without invented averages', function (string $outcome, int $sales, float $salePrice, ?float $winRate, ?float $averageWin, ?float $averageLoss, ?float $profitFactor) {
    $backtest = Backtest::factory()->create();
    recordPositionTrade($backtest, 'ITC', '2020-01-01', 'buy', 1, 100);
    if ($sales > 0) {
        recordPositionTrade($backtest, 'ITC', '2020-02-01', 'sell', 1, $salePrice);
    }

    $performance = app(CalculateBacktestPositionPerformanceAction::class)->execute($backtest, Carbon::parse('2020-02-01'));

    expect($performance['closed'])->toMatchArray([
        'count' => $sales, 'winners_percentage' => $winRate, 'average_win' => $averageWin,
        'average_loss' => $averageLoss, 'profit_factor' => $profitFactor,
    ])->and($performance['open_positions'])->toHaveCount(1 - $sales);
})->with([
    ['open', 0, 0.0, null, null, null, null],
    ['winner', 1, 110.0, 100.0, 10.0, null, null],
    ['loser', 1, 90.0, 0.0, null, -10.0, 0.0],
    ['breakeven', 1, 100.0, 0.0, null, null, null],
]);

it('uses the last available close for final holdings without reading later prices', function () {
    $backtest = Backtest::factory()->create();
    recordPositionTrade($backtest, 'ITC', '2020-01-01', 'buy', 10, 100, 10);
    createBacktestPriceRow('ITC', '2020-01-02', ['close_adjusted' => 120]);
    createBacktestPriceRow('ITC', '2020-01-03', ['close_adjusted' => 900]);
    createBacktestPriceRow('ITC', '2020-01-04', ['close_adjusted' => 1000]);
    foreach (['2020-01-02', '2020-01-05'] as $date) {
        $backtest->dailySnapshots()->create([
            'date' => $date, 'nav' => 120, 'portfolio_value' => 1200,
            'cash' => 0, 'total_value' => 1200, 'holdings_count' => 1,
        ]);
    }

    $performance = app(CalculateBacktestPositionPerformanceAction::class)->execute($backtest, Carbon::parse('2020-01-05'));

    expect($performance['open_positions'][0])->toMatchArray([
        'unrealized_value' => 1200.0, 'realized_pnl' => 0.0, 'unrealized_pnl' => 190.0, 'net_pnl' => 190.0,
    ]);
});

it('keeps distinct bounded top lists for amount and percentage with stable ties', function () {
    $backtest = Backtest::factory()->create();
    for ($index = 1; $index <= 25; $index++) {
        recordPositionTrade($backtest, 'GAIN'.$index, '2020-01-01', 'buy', 1, $index * 100);
        recordPositionTrade($backtest, 'GAIN'.$index, '2020-01-02', 'sell', 1, $index * 101);
        recordPositionTrade($backtest, 'LOSS'.$index, '2020-01-01', 'buy', 1, $index * 100);
        recordPositionTrade($backtest, 'LOSS'.$index, '2020-01-02', 'sell', 1, $index * 99);
    }
    recordPositionTrade($backtest, 'HIGH_PERCENT', '2020-01-01', 'buy', 1, 1);
    recordPositionTrade($backtest, 'HIGH_PERCENT', '2020-01-02', 'sell', 1, 2);
    recordPositionTrade($backtest, 'LOW_PERCENT', '2020-01-01', 'buy', 1, 1);
    recordPositionTrade($backtest, 'LOW_PERCENT', '2020-01-02', 'sell', 1, 0.1);

    $performance = app(CalculateBacktestPositionPerformanceAction::class)->execute($backtest, Carbon::parse('2020-01-02'));

    expect($performance['closed']['count'])->toBe(52)
        ->and(array_column($performance['top_winners']['net_pnl'], 'symbol'))->toBe(array_map(fn (int $index): string => 'GAIN'.$index, range(25, 6)))
        ->and(array_column($performance['top_losers']['net_pnl'], 'symbol'))->toBe(array_map(fn (int $index): string => 'LOSS'.$index, range(25, 6)))
        ->and(array_column($performance['top_winners']['pnl_pct'], 'symbol'))->toBe(['HIGH_PERCENT', ...array_map(fn (int $index): string => 'GAIN'.$index, range(1, 19))])
        ->and(array_column($performance['top_losers']['pnl_pct'], 'symbol'))->toBe(['LOW_PERCENT', ...array_map(fn (int $index): string => 'LOSS'.$index, range(1, 19))]);
});

it('preserves portfolio metrics and saved execution records when calculating compact position results', function () {
    $backtest = Backtest::factory()->create(['initial_capital' => 100000]);
    foreach (['2013-01-01' => 100, '2015-01-01' => 120, '2017-01-01' => 90, '2021-01-01' => 140] as $date => $nav) {
        $backtest->dailySnapshots()->create([
            'date' => $date, 'nav' => $nav, 'portfolio_value' => $nav * 1000,
            'cash' => 0, 'total_value' => $nav * 1000, 'holdings_count' => 1,
        ]);
    }
    recordPositionTrade($backtest, 'ITC', '2013-01-01', 'buy', 10, 100, 10);
    recordPositionTrade($backtest, 'ITC', '2015-01-01', 'sell', 10, 120, 12);
    recordPositionTrade($backtest, 'ITC', '2017-01-01', 'buy', 10, 100, 10);
    $trades = $backtest->trades()->get()->toArray();
    $snapshots = $backtest->dailySnapshots()->get()->toArray();

    app(CalculateBacktestMetricsAction::class)->execute($backtest);
    $metrics = $backtest->summaryMetrics;

    expect((float) $metrics->cagr)->toBe(round(pow(1.4, 1 / 8) - 1, 4))
        ->and((float) $metrics->max_drawdown)->toBe(-0.25)
        ->and($metrics->max_drawdown_start_date->toDateString())->toBe('2015-01-01')
        ->and($metrics->max_drawdown_end_date->toDateString())->toBe('2017-01-01')
        ->and((float) $metrics->final_value)->toBe(140000.0)
        ->and($metrics->total_trades)->toBe(3)
        ->and((float) $metrics->total_charges_paid)->toBe(32.0)
        ->and((float) $metrics->winners_percentage)->toBe(100.0)
        ->and($metrics->stock_performance['closed']['count'])->toBe(1)
        ->and($backtest->trades()->get()->toArray())->toBe($trades)
        ->and($backtest->dailySnapshots()->get()->toArray())->toBe($snapshots);
});

it('bounds worker memory and saved result size for twenty thousand completed positions', function () {
    $backtest = Backtest::factory()->create();
    $rows = [];
    $base = [
        'backtest_id' => $backtest->id, 'symbol' => 'ITC', 'name' => 'ITC', 'date' => '2020-01-01',
        'quantity' => 1, 'reason' => 'Rebalance', 'brokerage' => 0, 'stt' => 0,
        'transaction_charges' => 0, 'sebi_charges' => 0, 'gst' => 0, 'stamp_charges' => 0, 'total_charges' => 0,
    ];
    for ($index = 0; $index < 20000; $index++) {
        $salePrice = $index % 2 === 0 ? 101 : 99;
        $rows[] = [...$base, 'trade_type' => 'buy', 'price' => 100, 'raw_price' => 100, 'gross_amount' => 100, 'net_amount' => 100];
        $rows[] = [...$base, 'trade_type' => 'sell', 'price' => $salePrice, 'raw_price' => $salePrice, 'gross_amount' => $salePrice, 'net_amount' => $salePrice];
        if (count($rows) === 500) {
            DB::table((new BacktestTrade)->getTable())->insert($rows);
            $rows = [];
        }
    }

    memory_reset_peak_usage();
    $initialMemory = memory_get_usage(true);
    $performance = app(CalculateBacktestPositionPerformanceAction::class)->execute($backtest, Carbon::parse('2020-01-02'));
    $additionalMemory = memory_get_peak_usage(true) - $initialMemory;

    expect($performance['closed'])->toMatchArray(['count' => 20000, 'winners' => 10000, 'losers' => 10000, 'net_pnl' => 0.0])
        ->and($performance['open_positions'])->toBeEmpty()
        ->and($performance['top_winners']['net_pnl'])->toHaveCount(20)
        ->and($performance['top_losers']['net_pnl'])->toHaveCount(20)
        ->and(strlen(json_encode($performance, JSON_THROW_ON_ERROR)))->toBeLessThan(100000)
        ->and($additionalMemory)->toBeLessThan(16 * 1024 * 1024);
});
