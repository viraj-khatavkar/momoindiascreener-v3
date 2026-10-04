<?php

use App\Actions\Backtest\RunBacktestAction;
use App\Enums\CorporateActionTypeEnum;
use App\Models\Backtest;
use App\Models\NseIndex;
use App\Models\User;
use Carbon\Carbon;

/** @param array<string, mixed> $settings */
function stopLossBacktest(array $settings = []): Backtest
{
    return Backtest::factory()->create(array_merge([
        'user_id' => User::factory()->create()->id,
        'start_date' => '2024-01-08',
        'initial_capital' => 100000,
        'max_stocks_to_hold' => 1,
        'worst_rank_held' => 100,
        'rebalance_frequency' => 'weekly',
        'rebalance_day' => 1,
        'execute_next_trading_day' => false,
        'apply_stop_loss' => true,
        'stop_loss_percentage' => 10,
        'trail_stop_loss' => false,
        'stop_loss_proceeds' => 'wait_for_rebalance',
        'cash_call' => 'no_cash_call',
        'cash_return_rate' => 0,
        'brokerage_rate' => 0,
        'stt_rate' => 0,
        'transaction_charges_rate' => 0,
        'sebi_charges_rate' => 0,
        'stamp_charges_rate' => 0,
    ], $settings));
}

/**
 * @param  list<int|float|null>  $closes
 * @param  array<string, mixed>  $attributes
 * @param  array<int, array<string, mixed>>  $dailyAttributes
 * @return list<string>
 */
function seedStopLossSeries(string $symbol, array $closes, array $attributes = [], array $dailyAttributes = []): array
{
    $dates = [];

    foreach ($closes as $index => $close) {
        $date = Carbon::parse('2024-01-08')->addWeekdays($index)->toDateString();
        $dates[] = $date;

        if ($close === null) {
            continue;
        }

        createScreenResultRow($symbol, $symbol, $date, array_merge([
            'open_adjusted' => $close, 'high_adjusted' => $close, 'low_adjusted' => $close, 'close_adjusted' => $close,
            'open_raw' => $close, 'high_raw' => $close, 'low_raw' => $close, 'close_raw' => $close,
            'sharpe_return_one_year' => $symbol === 'A' ? 5 : 4,
            'ma_50' => 50,
            'volatility_one_year' => 0.4,
            't_percent' => 1,
        ], $attributes, $dailyAttributes[$index] ?? []));
    }

    return $dates;
}

it('confirms a stop loss at the next lower close regardless of the rebalance execution setting', function (bool $executeNextDay) {
    $dates = seedStopLossSeries('A', [100, 100, 89, 88]);
    $backtest = stopLossBacktest([
        'execute_next_trading_day' => $executeNextDay,
        'apply_hold_above_dma' => true,
        'hold_above_dma_period' => 50,
    ]);

    app(RunBacktestAction::class)->execute($backtest);

    $sales = $backtest->trades()->where('trade_type', 'sell')->get();
    expect($sales)->toHaveCount(1)
        ->and($sales->first()->date->toDateString())->toBe($dates[3])
        ->and((float) $sales->first()->price)->toBe(88.0)
        ->and($sales->first()->reason)->toContain('Stop loss confirmed');
})->with([false, true]);

it('checks the stop again after an equal close or a rebound', function (array $closes, int $exitDay) {
    $dates = seedStopLossSeries('A', $closes);
    $backtest = stopLossBacktest();

    app(RunBacktestAction::class)->execute($backtest);

    $sales = $backtest->trades()->where('trade_type', 'sell')->get();
    expect($sales)->toHaveCount(1)
        ->and($sales->first()->date->toDateString())->toBe($dates[$exitDay]);
})->with([
    'equal to stop and equal to breach close' => [[100, 90, 89, 89, 88], 4],
    'rebound still below stop' => [[100, 87, 88, 87], 3],
    'rebound above stop requires a fresh breach' => [[100, 89, 91, 90, 89, 88], 5],
]);

it('uses the highest close for trailing stops and never lowers that reference', function (bool $trailing) {
    $dates = seedStopLossSeries('A', [100, 150, 140, 134, 133]);
    $backtest = stopLossBacktest(['trail_stop_loss' => $trailing]);

    app(RunBacktestAction::class)->execute($backtest);

    $sales = $backtest->trades()->where('trade_type', 'sell')->get();
    expect($sales)->toHaveCount($trailing ? 1 : 0);

    if ($trailing) {
        expect($sales->first()->date->toDateString())->toBe($dates[4])
            ->and((float) $sales->first()->price)->toBe(133.0)
            ->and($sales->first()->reason)->toContain('Trailing stop loss');
    }
})->with([false, true]);

it('does not exit on the final breach day or use intraday highs as trailing closes', function (array $closes, bool $enabled) {
    seedStopLossSeries('A', $closes, ['high_adjusted' => 999, 'high_raw' => 999]);
    $backtest = stopLossBacktest(['apply_stop_loss' => $enabled, 'trail_stop_loss' => true]);

    app(RunBacktestAction::class)->execute($backtest);

    expect($backtest->trades()->where('trade_type', 'sell')->count())->toBe(0);
})->with([
    'last date is only the breach' => [[100, 89], true],
    'intraday high has no effect' => [[100, 100, 100], true],
    'disabled stop loss' => [[100, 89, 88], false],
]);

it('requires consecutive observed closes after a missing quote', function () {
    $dates = seedStopLossSeries('A', [100, 89, null, 88, 87]);
    seedStopLossSeries('B', [100, 100, 100, 100, 100]);
    $backtest = stopLossBacktest();

    app(RunBacktestAction::class)->execute($backtest);

    $sale = $backtest->trades()->where('symbol', 'A')->where('trade_type', 'sell')->sole();
    expect($sale->date->toDateString())->toBe($dates[4])
        ->and((float) $sale->price)->toBe(87.0);
});

it('honors execution-day circuits and retries a fresh stop signal', function (bool $skipCircuits) {
    $dates = seedStopLossSeries('A', [100, 89, 88, 87], [], [2 => ['t_percent' => -5]]);
    $backtest = stopLossBacktest(['skip_circuit_trades' => $skipCircuits]);

    app(RunBacktestAction::class)->execute($backtest);

    $sale = $backtest->trades()->where('trade_type', 'sell')->sole();
    expect($sale->date->toDateString())->toBe($dates[$skipCircuits ? 3 : 2]);
})->with([false, true]);

it('does not use stale circuit status from an earlier rebalance for stop loss exits', function () {
    $dates = seedStopLossSeries('A', [100, 100, 100, 100, 100, 95, 89, 88], [], [5 => ['t_percent' => -5]]);
    $backtest = stopLossBacktest();

    app(RunBacktestAction::class)->execute($backtest);

    expect($backtest->trades()->where('trade_type', 'sell')->sole()->date->toDateString())->toBe($dates[7]);
});

it('holds stop loss proceeds until rebalance or buys a different stock on the exit day', function (string $proceeds) {
    $dates = seedStopLossSeries('A', [100, 89, 88, 88, 88, 88]);
    seedStopLossSeries('B', [100, 100, 100, 100, 100, 100]);
    $backtest = stopLossBacktest(['stop_loss_proceeds' => $proceeds]);

    app(RunBacktestAction::class)->execute($backtest);

    $exitDayBuys = $backtest->trades()->where('trade_type', 'buy')->where('date', $dates[2])->get();
    $exitSnapshot = $backtest->dailySnapshots()->where('date', $dates[2])->sole();

    if ($proceeds === 'replace_immediately') {
        expect($exitDayBuys)->toHaveCount(1)
            ->and($exitDayBuys->first()->symbol)->toBe('B')
            ->and((float) $exitSnapshot->cash)->toBe(0.0);
    } else {
        expect($exitDayBuys)->toBeEmpty()
            ->and((float) $exitSnapshot->cash)->toBe(88000.0)
            ->and($exitSnapshot->holdings_count)->toBe(0)
            ->and($backtest->trades()->where('trade_type', 'buy')->where('date', $dates[5])->count())->toBe(1);
    }
})->with(['wait_for_rebalance', 'replace_immediately']);

it('allows stop loss proceeds to be invested at a rebalance on the exit day without rebuying the exited stock', function (string $proceeds) {
    $dates = seedStopLossSeries('A', [100, 100, 100, 100, 89, 88]);
    seedStopLossSeries('B', [100, 100, 100, 100, 100, 100]);
    $backtest = stopLossBacktest(['stop_loss_proceeds' => $proceeds]);

    app(RunBacktestAction::class)->execute($backtest);

    $entry = $backtest->trades()->where('date', $dates[5])->where('trade_type', 'buy')->sole();
    expect($entry->symbol)->toBe('B')
        ->and((float) $backtest->dailySnapshots()->where('date', $dates[5])->sole()->cash)->toBe(0.0);
})->with(['wait_for_rebalance', 'replace_immediately']);

it('earns the configured cash return on reserved stop loss proceeds', function () {
    seedStopLossSeries('A', [100, 89, 88, 88, 88]);
    $backtest = stopLossBacktest(['cash_return_rate' => 10]);

    app(RunBacktestAction::class)->execute($backtest);

    $snapshot = $backtest->dailySnapshots()->orderByDesc('date')->first();
    $expectedCash = round(88000 * pow(1.1, 3 / 252), 2);
    expect((float) $snapshot->cash)->toBe($expectedCash)
        ->and((float) $snapshot->total_value)->toBe($expectedCash)
        ->and((float) $snapshot->portfolio_value)->toBe(0.0);
});

it('spends only net stop loss proceeds on a replacement', function () {
    $dates = seedStopLossSeries('A', [100, 89, 88]);
    seedStopLossSeries('B', [100, 100, 100]);
    $backtest = stopLossBacktest(['stop_loss_proceeds' => 'replace_immediately', 'stt_rate' => 1]);

    app(RunBacktestAction::class)->execute($backtest);

    $sale = $backtest->trades()->where('trade_type', 'sell')->sole();
    $entry = $backtest->trades()->where('symbol', 'B')->sole();
    $snapshot = $backtest->dailySnapshots()->where('date', $dates[2])->sole();
    expect((int) $sale->quantity)->toBe(990)
        ->and((float) $sale->net_amount)->toBe(86248.8)
        ->and((int) $entry->quantity)->toBe(853)
        ->and((float) $entry->stt)->toBe(853.0)
        ->and((float) $entry->net_amount)->toBeLessThanOrEqual((float) $sale->net_amount)
        ->and((float) $snapshot->cash)->toBe(105.8)
        ->and((float) $snapshot->total_value + (float) $backtest->trades()->sum('total_charges'))->toBe(88120.0);
});

it('keeps replacement cash when no unheld stock qualifies instead of topping up an existing holding', function () {
    seedStopLossSeries('A', [100, 89, 88]);
    seedStopLossSeries('B', [100, 100, 100]);
    $backtest = stopLossBacktest(['stop_loss_proceeds' => 'replace_immediately', 'max_stocks_to_hold' => 2]);

    app(RunBacktestAction::class)->execute($backtest);

    expect($backtest->trades()->where('symbol', 'B')->count())->toBe(1)
        ->and((float) $backtest->dailySnapshots()->orderByDesc('date')->first()->cash)->toBe(44000.0);
});

it('uses the updated average buy price for fixed stops after adding shares', function () {
    $dates = seedStopLossSeries('A', [100, 100, 100, 100, 100, 120, 94, 93]);
    seedStopLossSeries('B', [100, 100, 100, 100, 100, 200, 200, 200]);
    $backtest = stopLossBacktest(['max_stocks_to_hold' => 2, 'weightage' => 'equal_weight_rebalanced']);

    app(RunBacktestAction::class)->execute($backtest);

    $buys = $backtest->trades()->where('symbol', 'A')->where('trade_type', 'buy')->get();
    $averageBuyPrice = $buys->sum('gross_amount') / $buys->sum('quantity');
    $sale = $backtest->trades()->where('symbol', 'A')->where('trade_type', 'sell')->sole();

    expect($buys->count())->toBeGreaterThan(1)
        ->and($averageBuyPrice * 0.9)->toBeGreaterThan(94)
        ->and($sale->date->toDateString())->toBe($dates[7]);
});

it('starts a new trailing stop after a position is fully exited and later bought again', function () {
    $dates = seedStopLossSeries('A', [100, 120, 107, 106, 106, 106, 105, 104]);
    $backtest = stopLossBacktest(['trail_stop_loss' => true]);

    app(RunBacktestAction::class)->execute($backtest);

    expect($backtest->trades()->where('trade_type', 'sell')->sole()->date->toDateString())->toBe($dates[3])
        ->and($backtest->trades()->where('trade_type', 'buy')->where('date', $dates[5])->count())->toBe(1)
        ->and($backtest->dailySnapshots()->orderByDesc('date')->first()->holdings_count)->toBe(1);
});

it('blocks all stocks exited by stop loss and another forced exit from same-day replacement', function (string $exitType) {
    $dates = seedStopLossSeries('A', [100, 89, 88, 88]);
    seedStopLossSeries('B', [100, 100, 100, 100], [], $exitType === 'BE' ? [2 => ['series' => 'BE']] : []);
    seedStopLossSeries('C', [100, 100, 100, 100], ['sharpe_return_one_year' => 3]);
    seedStopLossSeries('D', [100, 100, 100, 100], ['sharpe_return_one_year' => 2]);

    if ($exitType === 'demerger') {
        createCorporateAction('B', $dates[3], ['type' => CorporateActionTypeEnum::DEMERGER->value]);
    }

    $backtest = stopLossBacktest([
        'max_stocks_to_hold' => 2,
        'stop_loss_proceeds' => 'replace_immediately',
        'exit_on_be_series' => $exitType === 'BE',
    ]);
    app(RunBacktestAction::class)->execute($backtest);

    expect($backtest->trades()->where('date', $dates[2])->where('trade_type', 'sell')->pluck('symbol')->sort()->values()->all())->toBe(['A', 'B'])
        ->and($backtest->trades()->where('date', $dates[2])->where('trade_type', 'buy')->pluck('symbol')->sort()->values()->all())->toBe(['C', 'D']);
})->with(['BE', 'demerger']);

it('does not spend reserved stop loss cash on another forced exit replacement', function () {
    $dates = seedStopLossSeries('A', [100, 89, 88, 88, 88]);
    seedStopLossSeries('B', [100, 100, 100, 100, 100], [], [3 => ['series' => 'BE'], 4 => ['series' => 'BE']]);
    seedStopLossSeries('C', [100, 100, 100, 100, 100], ['sharpe_return_one_year' => 3]);
    $backtest = stopLossBacktest(['max_stocks_to_hold' => 2, 'exit_on_be_series' => true]);

    app(RunBacktestAction::class)->execute($backtest);

    $snapshot = $backtest->dailySnapshots()->where('date', $dates[3])->sole();
    expect((float) $snapshot->cash)->toBeGreaterThanOrEqual(44000.0)
        ->and((float) $snapshot->total_value)->toBe(94000.0);
});

it('applies all entry restrictions and execution-day prices to stop loss replacements', function (bool $executeNextDay) {
    $dates = seedStopLossSeries('A', [100, 100, 89, 88]);
    seedStopLossSeries('B', [100, 100, 100, 100], ['sharpe_return_one_year' => 4], [3 => ['t_percent' => 5]]);
    seedStopLossSeries('C', [100, 100, 100, 100], ['sharpe_return_one_year' => 3], [3 => ['series' => 'BE']]);
    seedStopLossSeries('D', [100, 100, 100, 100], ['sharpe_return_one_year' => 2, 'median_volume_one_year' => 0]);
    seedStopLossSeries('E', [100, 100, 100, 100], ['sharpe_return_one_year' => 1.5, 'volatility_one_year' => null]);
    seedStopLossSeries('F', [100, 100, 100, 200], ['sharpe_return_one_year' => 1]);
    $backtest = stopLossBacktest([
        'stop_loss_proceeds' => 'replace_immediately',
        'execute_next_trading_day' => $executeNextDay,
        'exit_on_be_series' => true,
        'weightage' => 'inverse_volatility',
    ]);

    app(RunBacktestAction::class)->execute($backtest);

    $replacement = $backtest->trades()->where('date', $dates[3])->where('trade_type', 'buy')->sole();
    expect($replacement->symbol)->toBe('F')
        ->and((float) $replacement->price)->toBe(200.0)
        ->and((int) $replacement->quantity)->toBe(440);
})->with([false, true]);

it('applies cash-call restrictions when investing stop loss proceeds', function (string $cashCall, bool $goldAboveDma, ?string $replacement, string $proceeds) {
    $dates = seedStopLossSeries('A', [100, 89, 88, 88, 88, 88]);
    seedStopLossSeries('B', array_fill(0, 6, 100));
    seedStopLossSeries('GOLDBEES', array_fill(0, 6, 100), ['is_nifty_allcap' => false, 'ma_50' => $goldAboveDma ? 50 : 150]);

    for ($offset = -30; $offset <= 5; $offset++) {
        $date = Carbon::parse('2024-01-08')->addWeekdays($offset)->toDateString();
        $close = $offset > 0 ? 80 : 100;
        NseIndex::insert([
            'symbol' => 'Nifty 50', 'slug' => 'nifty-50', 'date' => $date,
            'open' => $close, 'high' => $close, 'low' => $close, 'close' => $close,
            'points_change' => 0, 'percentage_change' => 0, 'volume' => 0, 'turnover' => 0,
            'price_to_earnings' => 20, 'price_to_book' => 3, 'dividend_yield' => 1.5,
        ]);
    }

    $backtest = stopLossBacktest([
        'cash_call' => $cashCall,
        'cash_call_dma_period' => 20,
        'stop_loss_proceeds' => $proceeds,
    ]);
    app(RunBacktestAction::class)->execute($backtest);

    $investmentDay = $proceeds === 'wait_for_rebalance' ? 5 : 2;

    if ($proceeds === 'wait_for_rebalance') {
        expect($backtest->trades()->where('trade_type', 'buy')->whereBetween('date', [$dates[2], $dates[4]])->count())->toBe(0);
        $replacement = $replacement === 'B' ? 'A' : $replacement;
    }

    $buys = $backtest->trades()->where('trade_type', 'buy')->where('date', $dates[$investmentDay])->pluck('symbol')->all();
    expect($buys)->toBe($replacement === null ? [] : [$replacement]);
})->with([
    ['no_cash_call', true, 'B'],
    ['full_cash_below_index_dma', true, null],
    ['only_exits_below_index_dma', true, null],
    ['allocate_to_gold_below_index_dma', true, 'GOLDBEES'],
    ['only_exits_allocate_to_gold_below_index_dma', true, 'GOLDBEES'],
    ['only_exits_allocate_to_gold_above_dma_below_index_dma', true, 'GOLDBEES'],
    ['only_exits_allocate_to_gold_above_dma_below_index_dma', false, null],
])->with(['replace_immediately', 'wait_for_rebalance']);

it('uses adjusted prices for stop loss signals', function () {
    $dates = seedStopLossSeries('A', [100, 89, 88], ['close_raw' => 1000]);
    $backtest = stopLossBacktest();

    app(RunBacktestAction::class)->execute($backtest);

    expect($backtest->trades()->where('trade_type', 'sell')->sole()->date->toDateString())->toBe($dates[2]);
});

it('skips replacement stocks due to exit before a demerger', function (bool $executeNextDay, bool $isRebalanceDay, bool $exitBeforeDemerger) {
    $exitDay = $isRebalanceDay ? 5 + (int) $executeNextDay : 3;
    $closes = array_fill(0, $exitDay + 2, 100);
    $closes[$exitDay - 1] = 89;
    $closes[$exitDay] = 88;
    $dates = seedStopLossSeries('A', $closes);
    seedStopLossSeries('B', array_fill(0, count($closes), 100));
    seedStopLossSeries('C', array_fill(0, count($closes), 100), ['sharpe_return_one_year' => 3]);
    createCorporateAction('B', $dates[$exitDay + 1], ['type' => CorporateActionTypeEnum::DEMERGER->value]);
    $backtest = stopLossBacktest([
        'execute_next_trading_day' => $executeNextDay,
        'exit_before_demerger' => $exitBeforeDemerger,
        'stop_loss_proceeds' => 'replace_immediately',
    ]);

    app(RunBacktestAction::class)->execute($backtest);

    $replacement = $backtest->trades()->where('date', $dates[$exitDay])->where('trade_type', 'buy')->sole();
    expect($replacement->symbol)->toBe($exitBeforeDemerger ? 'C' : 'B');
})->with([false, true])->with([false, true])->with([false, true]);

it('preserves the trailing peak when a rebalance adds shares below that peak', function () {
    $dates = seedStopLossSeries('A', [100, 140, 140, 140, 140, 130, 127, 125, 124]);
    seedStopLossSeries('B', [100, 100, 100, 100, 100, 200, 200, 200, 200]);
    $backtest = stopLossBacktest([
        'max_stocks_to_hold' => 2,
        'weightage' => 'equal_weight_rebalanced',
        'trail_stop_loss' => true,
    ]);

    app(RunBacktestAction::class)->execute($backtest);

    expect($backtest->trades()->where('symbol', 'A')->where('trade_type', 'buy')->count())->toBe(2)
        ->and($backtest->trades()->where('symbol', 'A')->where('trade_type', 'sell')->sole()->date->toDateString())->toBe($dates[8]);
});

it('preserves the average entry price after a partial sale', function () {
    $dates = seedStopLossSeries('A', [100, 100, 100, 100, 100, 200, 150, 149, 89, 88]);
    seedStopLossSeries('B', array_fill(0, 10, 100));
    $backtest = stopLossBacktest(['max_stocks_to_hold' => 2, 'weightage' => 'equal_weight_rebalanced']);

    app(RunBacktestAction::class)->execute($backtest);

    $sales = $backtest->trades()->where('symbol', 'A')->where('trade_type', 'sell')->orderBy('date')->get();
    expect($sales)->toHaveCount(2)
        ->and($sales[0]->date->toDateString())->toBe($dates[5])
        ->and((int) $sales[0]->quantity)->toBe(125)
        ->and($sales[1]->date->toDateString())->toBe($dates[9])
        ->and((int) $sales[1]->quantity)->toBe(375)
        ->and($sales[1]->reason)->toContain('Stop loss confirmed');
});

it('excludes entry charges from the fixed stop reference', function () {
    $dates = seedStopLossSeries('A', [100, 94, 93, 89, 88]);
    $backtest = stopLossBacktest(['brokerage_rate' => 5, 'gst_rate' => 18]);

    app(RunBacktestAction::class)->execute($backtest);

    expect($backtest->trades()->where('trade_type', 'sell')->sole()->date->toDateString())->toBe($dates[4]);
});

it('executes every simultaneous stop and accounts for all replacement proceeds', function () {
    $dates = seedStopLossSeries('A', [100, 89, 88]);
    seedStopLossSeries('B', [200, 178, 176]);
    seedStopLossSeries('C', [100, 100, 100], ['sharpe_return_one_year' => 3]);
    seedStopLossSeries('D', [200, 200, 200], ['sharpe_return_one_year' => 2]);
    $backtest = stopLossBacktest(['max_stocks_to_hold' => 2, 'stop_loss_proceeds' => 'replace_immediately']);

    app(RunBacktestAction::class)->execute($backtest);

    $snapshot = $backtest->dailySnapshots()->where('date', $dates[2])->sole();
    expect($backtest->trades()->where('date', $dates[2])->where('trade_type', 'sell')->pluck('symbol')->sort()->values()->all())->toBe(['A', 'B'])
        ->and($backtest->trades()->where('date', $dates[2])->where('trade_type', 'buy')->pluck('symbol')->sort()->values()->all())->toBe(['C', 'D'])
        ->and((float) $snapshot->total_value)->toBe(88000.0)
        ->and((float) $snapshot->cash)->toBe(0.0)
        ->and($snapshot->holdings_count)->toBe(2);
});
