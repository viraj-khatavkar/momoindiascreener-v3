<?php

use App\Actions\Backtest\RunBacktestAction;
use App\Models\Backtest;
use App\Models\NseIndex;
use Carbon\Carbon;

/** @param array<string, mixed> $settings */
function goldRulesBacktest(array $settings = []): Backtest
{
    return Backtest::factory()->create([
        'start_date' => '2024-01-01', 'index' => 'etf', 'initial_capital' => 10000,
        'max_stocks_to_hold' => 1, 'worst_rank_held' => 1,
        'rebalance_frequency' => 'weekly', 'rebalance_day' => 1,
        'cash_call_dma_period' => 20, 'cash_return_rate' => 0,
        'brokerage_rate' => 0, 'stt_rate' => 0, 'transaction_charges_rate' => 0,
        'sebi_charges_rate' => 0, 'gst_rate' => 0, 'stamp_charges_rate' => 0,
        ...$settings,
    ]);
}

/** @param array<string, mixed> $attributes */
function goldRulesQuote(string $symbol, string $date, float $price = 100, array $attributes = []): void
{
    createScreenResultRow($symbol, $symbol, $date, [
        'is_etf' => true, 'close_adjusted' => $price, 'close_raw' => $price,
        'sharpe_return_one_year' => $symbol === 'GOLDBEES' ? 20 : 10,
        'ma_50' => 50, 'volatility_one_year' => 0.4, 't_percent' => 1,
        ...$attributes,
    ]);
}

/** @param array<string, float|int> $closes */
function goldRulesIndex(array $closes): void
{
    $date = Carbon::parse('2023-11-01');
    while ($date->toDateString() < '2024-01-01') {
        $closes[$date->toDateString()] = 100;
        $date->addWeekday();
    }
    foreach ($closes as $date => $close) {
        NseIndex::query()->insert([
            'symbol' => 'Nifty 50', 'slug' => 'nifty-50', 'date' => $date,
            'open' => $close, 'high' => $close, 'low' => $close, 'close' => $close,
            'points_change' => 0, 'percentage_change' => 0, 'volume' => 0, 'turnover' => 0,
            'price_to_earnings' => 20, 'price_to_book' => 3, 'dividend_yield' => 1.5,
        ]);
    }
}

it('applies ordinary rank exits to GOLDBEES even when a defensive gold cash mode is selected', function (string $mode, bool $nextDay) {
    $dates = ['2024-01-01', '2024-01-02', '2024-01-08', '2024-01-09'];
    goldRulesIndex(array_fill_keys($dates, 200));
    foreach ($dates as $date) {
        goldRulesQuote('GOLDBEES', $date, attributes: ['sharpe_return_one_year' => $date < '2024-01-08' ? 20 : 1]);
        goldRulesQuote('ETF2', $date);
    }
    $backtest = goldRulesBacktest(['cash_call' => $mode, 'execute_next_trading_day' => $nextDay]);

    app(RunBacktestAction::class)->execute($backtest);

    $sale = $backtest->trades()->where('symbol', 'GOLDBEES')->where('trade_type', 'sell')->sole();
    expect($sale->date->toDateString())->toBe($nextDay ? '2024-01-09' : '2024-01-08')
        ->and($sale->reason)->toContain('Rank exceeded threshold')
        ->and($backtest->trades()->where('symbol', 'ETF2')->where('trade_type', 'buy')->count())->toBe(1);
})->with(['no_cash_call', 'allocate_to_gold_below_index_dma', 'only_exits_allocate_to_gold_below_index_dma'])
    ->with([false, true]);

it('confirms fixed and trailing stops for ordinary GOLDBEES holdings', function (string $mode, bool $nextDay, bool $trailing) {
    $prices = $trailing ? [100, 100, 120, 107, 106] : [100, 100, 89, 88];
    $closes = [];
    foreach ($prices as $index => $price) {
        $date = Carbon::parse('2024-01-01')->addWeekdays($index)->toDateString();
        goldRulesQuote('GOLDBEES', $date, $price);
        goldRulesQuote('ETF2', $date);
        $closes[$date] = 200;
    }
    goldRulesIndex($closes);
    $backtest = goldRulesBacktest([
        'cash_call' => $mode, 'execute_next_trading_day' => $nextDay,
        'apply_stop_loss' => true, 'trail_stop_loss' => $trailing,
        'stop_loss_proceeds' => 'replace_immediately',
        'apply_hold_above_dma' => true, 'hold_above_dma_period' => 50,
    ]);

    app(RunBacktestAction::class)->execute($backtest);

    $sale = $backtest->trades()->where('symbol', 'GOLDBEES')->where('trade_type', 'sell')->sole();
    expect($sale->date->toDateString())->toBe(array_key_last($closes))
        ->and((float) $sale->price)->toBe($trailing ? 106.0 : 88.0)
        ->and($sale->reason)->toContain($trailing ? 'Trailing stop loss confirmed' : 'Stop loss confirmed')
        ->and($backtest->trades()->where('symbol', 'ETF2')->where('trade_type', 'buy')->sole()->date->toDateString())->toBe(array_key_last($closes));
})->with(['no_cash_call', 'allocate_to_gold_below_index_dma'])->with([false, true])->with([false, true]);

it('keeps defensive gold through stock exit signals and exits it when the index recovers', function (string $mode, bool $nextDay) {
    $prices = ['2024-01-01' => 100, '2024-01-02' => 100, '2024-01-03' => 89, '2024-01-04' => 88, '2024-01-08' => 88, '2024-01-09' => 88];
    $closes = [];
    foreach ($prices as $date => $price) {
        goldRulesQuote('GOLDBEES', $date, $price, ['sharpe_return_one_year' => 1]);
        goldRulesQuote('ETF2', $date);
        $closes[$date] = $date < '2024-01-08' ? 50 : 200;
    }
    goldRulesIndex($closes);
    $backtest = goldRulesBacktest(['cash_call' => $mode, 'apply_stop_loss' => true, 'execute_next_trading_day' => $nextDay]);

    app(RunBacktestAction::class)->execute($backtest);

    $sale = $backtest->trades()->where('symbol', 'GOLDBEES')->where('trade_type', 'sell')->sole();
    expect($sale->date->toDateString())->toBe($nextDay ? '2024-01-09' : '2024-01-08')
        ->and($sale->reason)->toContain('recovered above')
        ->and($backtest->trades()->where('reason', 'like', '%stop loss%')->count())->toBe(0);
})->with(['allocate_to_gold_below_index_dma', 'only_exits_allocate_to_gold_below_index_dma', 'only_exits_allocate_to_gold_above_dma_below_index_dma'])
    ->with([false, true]);

it('adopts existing gold during full defensive rotation and resets its purpose after a full exit', function () {
    $prices = ['2024-01-01' => 100, '2024-01-02' => 100, '2024-01-08' => 100, '2024-01-09' => 89, '2024-01-10' => 88, '2024-01-15' => 100, '2024-01-16' => 89, '2024-01-17' => 88];
    $closes = [];
    foreach ($prices as $date => $price) {
        goldRulesQuote('GOLDBEES', $date, $price);
        goldRulesQuote('ETF2', $date);
        $closes[$date] = $date >= '2024-01-08' && $date < '2024-01-15' ? 50 : 200;
    }
    goldRulesIndex($closes);
    $backtest = goldRulesBacktest(['cash_call' => 'allocate_to_gold_below_index_dma', 'apply_stop_loss' => true]);

    app(RunBacktestAction::class)->execute($backtest);

    $sales = $backtest->trades()->where('symbol', 'GOLDBEES')->where('trade_type', 'sell')->orderBy('id')->get();
    expect($sales)->toHaveCount(2)
        ->and($sales[0]->date->toDateString())->toBe('2024-01-15')
        ->and($sales[0]->reason)->toContain('recovered above')
        ->and($sales[1]->date->toDateString())->toBe('2024-01-17')
        ->and($sales[1]->reason)->toContain('Stop loss confirmed');
});

it('does not repurchase an ordinary gold stop exit as defensive gold on the same day', function (string $mode, bool $rebalanceDay) {
    $dates = $rebalanceDay ? ['2024-01-01', '2024-01-05', '2024-01-08'] : ['2024-01-01', '2024-01-02', '2024-01-03'];
    foreach ($dates as $index => $date) {
        goldRulesQuote('GOLDBEES', $date, [100, 89, 88][$index]);
    }
    goldRulesIndex([$dates[0] => 200, $dates[1] => 50, $dates[2] => 50]);
    $backtest = goldRulesBacktest(['cash_call' => $mode, 'apply_stop_loss' => true, 'stop_loss_proceeds' => 'replace_immediately']);

    app(RunBacktestAction::class)->execute($backtest);

    expect($backtest->trades()->where('trade_type', 'sell')->sole()->date->toDateString())->toBe($dates[2])
        ->and($backtest->trades()->where('trade_type', 'buy')->count())->toBe(1)
        ->and((float) $backtest->dailySnapshots()->orderByDesc('date')->first()->cash)->toBe(8800.0);
})->with(['allocate_to_gold_below_index_dma', 'only_exits_allocate_to_gold_below_index_dma', 'only_exits_allocate_to_gold_above_dma_below_index_dma'])
    ->with([false, true]);

it('includes protected ordinary gold in weighted targets when it fails entry filters', function (string $weightage) {
    foreach (['2024-01-01', '2024-01-02', '2024-01-08', '2024-01-09'] as $date) {
        $later = $date >= '2024-01-08';
        goldRulesQuote('GOLDBEES', $date, $later ? 200 : 100, ['median_volume_one_year' => $later ? 0 : 20000000, 'close_raw' => 100]);
        goldRulesQuote('ETF2', $date, attributes: ['close_raw' => 300]);
    }
    $backtest = goldRulesBacktest([
        'weightage' => $weightage, 'max_stocks_to_hold' => 2, 'worst_rank_held' => 2,
        'apply_hold_above_dma' => true, 'hold_above_dma_period' => 50,
    ]);

    app(RunBacktestAction::class)->execute($backtest);

    $sale = $backtest->trades()->where('symbol', 'GOLDBEES')->where('trade_type', 'sell')->sole();
    expect($sale->reason)->toBe('Weight rebalance adjustment')
        ->and($sale->quantity)->toBe($weightage === 'price_weighted' ? 9 : 12);
})->with(['equal_weight_rebalanced', 'inverse_volatility', 'price_weighted']);

it('keeps ranked ordinary gold above the index DMA without a false recovery sale', function () {
    $dates = ['2024-01-01', '2024-01-02', '2024-01-08', '2024-01-09'];
    goldRulesIndex(array_fill_keys($dates, 200));
    foreach ($dates as $date) {
        goldRulesQuote('GOLDBEES', $date);
        goldRulesQuote('ETF2', $date);
    }
    $backtest = goldRulesBacktest(['cash_call' => 'allocate_to_gold_below_index_dma']);

    app(RunBacktestAction::class)->execute($backtest);

    expect($backtest->trades()->sole()->trade_type)->toBe('buy')
        ->and($backtest->trades()->sole()->symbol)->toBe('GOLDBEES');
});

it('applies BE and missing volatility exits to ordinary gold', function (string $exitType) {
    foreach (['2024-01-01', '2024-01-02', '2024-01-08'] as $date) {
        $later = $date >= '2024-01-08';
        goldRulesQuote('GOLDBEES', $date, attributes: [
            'series' => $later && $exitType === 'BE' ? 'BE' : 'EQ',
            'volatility_one_year' => $later && $exitType === 'volatility' ? null : 0.4,
        ]);
        goldRulesQuote('ETF2', $date);
    }
    $backtest = goldRulesBacktest([
        'max_stocks_to_hold' => 2, 'worst_rank_held' => 2,
        'exit_on_be_series' => $exitType === 'BE',
        'weightage' => $exitType === 'volatility' ? 'inverse_volatility' : 'equal_weight',
    ]);

    app(RunBacktestAction::class)->execute($backtest);

    $sale = $backtest->trades()->where('symbol', 'GOLDBEES')->where('trade_type', 'sell')->sole();
    expect($sale->date->toDateString())->toBe('2024-01-08')
        ->and($sale->reason)->toContain($exitType === 'BE' ? 'Series changed to BE' : 'No volatility data');
})->with(['BE', 'volatility']);

it('marks gold as defensive when forced exit cash adds to an ordinary gold holding', function () {
    $prices = ['2024-01-01' => 100, '2024-01-02' => 100, '2024-01-03' => 89, '2024-01-04' => 88];
    foreach ($prices as $date => $price) {
        goldRulesQuote('GOLDBEES', $date, $price);
        goldRulesQuote('ETF2', $date, attributes: ['series' => $date === '2024-01-01' ? 'EQ' : 'BE']);
    }
    goldRulesIndex(['2024-01-01' => 200, '2024-01-02' => 50, '2024-01-03' => 50, '2024-01-04' => 50]);
    $backtest = goldRulesBacktest([
        'max_stocks_to_hold' => 2, 'worst_rank_held' => 2, 'apply_stop_loss' => true,
        'exit_on_be_series' => true, 'cash_call' => 'only_exits_allocate_to_gold_below_index_dma',
    ]);

    app(RunBacktestAction::class)->execute($backtest);

    expect($backtest->trades()->where('symbol', 'GOLDBEES')->where('trade_type', 'buy')->count())->toBe(2)
        ->and($backtest->trades()->where('symbol', 'GOLDBEES')->where('trade_type', 'sell')->count())->toBe(0)
        ->and((float) $backtest->dailySnapshots()->orderByDesc('date')->first()->total_value)->toBe(8800.0);
});
