<?php

use App\Actions\Backtest\CalculateBacktestMetricsAction;
use App\Models\Backtest;
use Illuminate\Support\Facades\DB;

it('processes a long trade history in bounded batches without splitting position cycles', function () {
    $backtest = Backtest::factory()->create();
    foreach (['2018-01-01', '2018-01-05'] as $index => $date) {
        $backtest->dailySnapshots()->create([
            'date' => $date, 'nav' => 100 + $index, 'portfolio_value' => 100000 + 1000 * $index,
            'cash' => 0, 'total_value' => 100000 + 1000 * $index, 'holdings_count' => 1,
        ]);
    }

    $trade = [
        'backtest_id' => $backtest->id, 'symbol' => 'STOCK', 'name' => 'Stock', 'trade_type' => 'buy',
        'reason' => 'Rebalance', 'date' => '2018-01-01', 'quantity' => 1, 'price' => 100, 'raw_price' => 100,
        'gross_amount' => 100, 'stt' => 0, 'transaction_charges' => 0, 'sebi_charges' => 0,
        'gst' => 0, 'stamp_charges' => 0, 'total_charges' => 1, 'net_amount' => 101,
    ];

    /** Insert the full sale first to require chronological order independently of the record ID. */
    $backtest->trades()->create(array_replace($trade, [
        'date' => '2018-01-03', 'trade_type' => 'sell', 'quantity' => 498, 'price' => 200, 'raw_price' => 200,
        'gross_amount' => 99600, 'total_charges' => 498, 'net_amount' => 99102,
    ]));
    DB::table('backtest_trades')->insert(array_fill(0, 499, $trade));
    $backtest->trades()->create(array_replace($trade, [
        'date' => '2018-01-02', 'trade_type' => 'sell', 'price' => 150, 'raw_price' => 150,
        'gross_amount' => 150, 'total_charges' => 2, 'net_amount' => 148,
    ]));
    $backtest->trades()->create(array_replace($trade, [
        'date' => '2018-01-03', 'quantity' => 2, 'price' => 200, 'raw_price' => 200,
        'gross_amount' => 400, 'total_charges' => 4, 'net_amount' => 404,
    ]));
    $backtest->trades()->create(array_replace($trade, [
        'date' => '2018-01-04', 'price' => 300, 'raw_price' => 300,
        'gross_amount' => 300, 'total_charges' => 3, 'net_amount' => 303,
    ]));
    createBacktestPriceRow('STOCK', '2018-01-05', ['close_adjusted' => 400]);

    DB::enableQueryLog();
    DB::flushQueryLog();

    try {
        app(CalculateBacktestMetricsAction::class)->execute($backtest);
        $tradeQueries = collect(DB::getQueryLog())->pluck('query')
            ->filter(fn (string $query): bool => str_contains($query, 'from `backtest_trades`') && ! str_contains($query, 'aggregate'));
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    expect($tradeQueries->count())->toBeGreaterThan(1);
    foreach ($tradeQueries as $query) {
        expect($query)->toContain('limit 500')->not->toContain('select *');
    }

    $metrics = $backtest->summaryMetrics;
    $closed = $metrics->stock_performance['top_winners']['net_pnl'][0];
    $open = $metrics->stock_performance['open_positions'][0];

    expect($metrics->stock_performance['closed']['count'])->toBe(1)
        ->and($metrics->stock_performance['open_positions'])->toHaveCount(1)
        ->and($metrics->total_trades)->toBe(503)
        ->and((float) $metrics->total_charges_paid)->toBe(1006.0)
        ->and($closed['entry_date'])->toBe('2018-01-01')
        ->and($closed['exit_date'])->toBe('2018-01-03')
        ->and($closed['holding_days'])->toBe(2)
        ->and($closed['buy_value'])->toEqual(49900)
        ->and($closed['sell_value'])->toEqual(99750)
        ->and($closed['charges'])->toEqual(999)
        ->and($closed['net_pnl'])->toEqual(48851)
        ->and($closed['pnl_pct'])->toBe(round(48851 / 50399 * 100, 2))
        ->and($open['entry_date'])->toBe('2018-01-03')
        ->and($open['exit_date'])->toBeNull()
        ->and($open['holding_days'])->toBe(2)
        ->and($open['buy_value'])->toEqual(700)
        ->and($open['unrealized_value'])->toEqual(1200)
        ->and($open['charges'])->toEqual(7)
        ->and($open['net_pnl'])->toEqual(493);
});
