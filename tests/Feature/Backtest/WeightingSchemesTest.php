<?php

use App\Actions\Backtest\RunBacktestAction;
use App\Enums\CorporateActionTypeEnum;
use App\Models\Backtest;
use App\Models\User;
use Carbon\Carbon;

/** @param array<string, mixed> $settings */
function weightingBacktest(string $weightage, array $settings = []): Backtest
{
    return Backtest::factory()->create(array_merge([
        'user_id' => User::factory()->create()->id,
        'weightage' => $weightage,
        'start_date' => '2024-01-08',
        'initial_capital' => 90000,
        'max_stocks_to_hold' => 2,
        'worst_rank_held' => 100,
        'rebalance_frequency' => 'weekly',
        'rebalance_day' => 1,
        'execute_next_trading_day' => false,
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
 * @param  list<int|float>  $closes
 * @param  array<string, mixed>  $attributes
 * @param  array<int, array<string, mixed>>  $dailyAttributes
 * @return list<string>
 */
function seedWeightingStock(string $symbol, array $closes, array $attributes = [], array $dailyAttributes = []): array
{
    $dates = [];

    foreach ($closes as $offset => $close) {
        $date = Carbon::parse('2024-01-08')->addWeekdays($offset)->toDateString();
        $dates[] = $date;
        createScreenResultRow($symbol, $symbol, $date, array_merge([
            'close_adjusted' => $close,
            'close_raw' => $close,
            'sharpe_return_one_year' => $symbol === 'A' ? 5 : 4,
            'volatility_one_year' => 0.4,
            'ma_50' => 50,
            't_percent' => 1,
        ], $attributes, $dailyAttributes[$offset] ?? []));
    }

    return $dates;
}

it('allocates reciprocal rank weights to ranks one two and three', function () {
    seedWeightingStock('A', [100]);
    seedWeightingStock('B', [100]);
    seedWeightingStock('C', [100], ['sharpe_return_one_year' => 3]);
    $backtest = weightingBacktest('rank_weighted', ['initial_capital' => 110000, 'max_stocks_to_hold' => 3]);

    app(RunBacktestAction::class)->execute($backtest);

    $buys = $backtest->trades()->where('trade_type', 'buy')->get()->keyBy('symbol');
    foreach (['A' => 60000, 'B' => 30000, 'C' => 20000] as $symbol => $target) {
        expect((float) $buys[$symbol]->gross_amount)->toBeGreaterThanOrEqual($target - 100)
            ->toBeLessThanOrEqual($target);
    }
    expect((float) $backtest->dailySnapshots()->sole()->total_value)->toBe(110000.0)
        ->and((float) $backtest->dailySnapshots()->sole()->cash)->toBeLessThanOrEqual(300);
});

it('allocates price weights from raw closes rather than adjusted closes', function () {
    seedWeightingStock('A', [100], ['close_raw' => 1000]);
    seedWeightingStock('B', [100], ['close_raw' => 100]);
    $backtest = weightingBacktest('price_weighted', ['initial_capital' => 110000]);

    app(RunBacktestAction::class)->execute($backtest);

    $buys = $backtest->trades()->where('trade_type', 'buy')->get()->keyBy('symbol');
    expect((float) $buys['A']->gross_amount)->toBe(100000.0)
        ->and((float) $buys['B']->gross_amount)->toBe(10000.0)
        ->and((float) $backtest->dailySnapshots()->sole()->cash)->toBe(0.0);
});

it('restores reciprocal rank weights only on scheduled rebalance days', function () {
    $changes = array_fill(1, 5, ['sharpe_return_one_year' => 1]);
    $dates = seedWeightingStock('A', array_fill(0, 6, 100), [], $changes);
    seedWeightingStock('B', array_fill(0, 6, 100));
    $backtest = weightingBacktest('rank_weighted');

    app(RunBacktestAction::class)->execute($backtest);

    $adjustments = $backtest->trades()->where('date', $dates[5])->get()->keyBy('symbol');
    expect($backtest->trades()->whereBetween('date', [$dates[1], $dates[4]])->count())->toBe(0)
        ->and($adjustments)->toHaveCount(2)
        ->and($adjustments['A']->trade_type)->toBe('sell')
        ->and((int) $adjustments['A']->quantity)->toBe(300)
        ->and($adjustments['B']->trade_type)->toBe('buy')
        ->and((int) $adjustments['B']->quantity)->toBe(300);
});

it('restores price weights after the raw price ratio changes', function () {
    $changes = array_fill(1, 5, ['close_raw' => 500]);
    $dates = seedWeightingStock('A', array_fill(0, 6, 100), ['close_raw' => 1000], $changes);
    seedWeightingStock('B', array_fill(0, 6, 100));
    $backtest = weightingBacktest('price_weighted', ['initial_capital' => 66000]);

    app(RunBacktestAction::class)->execute($backtest);

    $adjustments = $backtest->trades()->where('date', $dates[5])->get()->keyBy('symbol');
    expect($backtest->trades()->whereBetween('date', [$dates[1], $dates[4]])->count())->toBe(0)
        ->and((int) $adjustments['A']->quantity)->toBe(50)
        ->and($adjustments['A']->trade_type)->toBe('sell')
        ->and((int) $adjustments['B']->quantity)->toBe(50)
        ->and($adjustments['B']->trade_type)->toBe('buy');
});

it('uses decision-date weighting inputs and execution-date trade prices', function (string $weightage) {
    seedWeightingStock('A', [100, 200], ['close_raw' => 200], [1 => ['close_raw' => 100, 'sharpe_return_one_year' => 1]]);
    seedWeightingStock('B', [100, 100], ['close_raw' => 100], [1 => ['close_raw' => 1000]]);
    $backtest = weightingBacktest($weightage, ['execute_next_trading_day' => true]);

    app(RunBacktestAction::class)->execute($backtest);

    $buys = $backtest->trades()->where('trade_type', 'buy')->get()->keyBy('symbol');
    expect((int) $buys['A']->quantity)->toBe(300)
        ->and((float) $buys['A']->price)->toBe(200.0)
        ->and((int) $buys['B']->quantity)->toBe(300)
        ->and($buys['A']->date->toDateString())->toBe('2024-01-09');
})->with(['rank_weighted', 'price_weighted']);

it('uses actual ranks after an entry restriction skips a higher ranked stock', function () {
    seedWeightingStock('A', [100], ['t_percent' => 5]);
    seedWeightingStock('B', [100]);
    seedWeightingStock('C', [100], ['sharpe_return_one_year' => 3]);
    $backtest = weightingBacktest('rank_weighted', ['initial_capital' => 100000]);

    app(RunBacktestAction::class)->execute($backtest);

    $buys = $backtest->trades()->get()->keyBy('symbol');
    expect($buys->keys()->all())->toBe(['B', 'C'])
        ->and((float) $buys['B']->gross_amount)->toBe(60000.0)
        ->and((float) $buys['C']->gross_amount)->toBe(40000.0);
});

it('skips entries with an invalid price weighting reference before filling slots', function () {
    seedWeightingStock('A', [100], ['close_raw' => 0]);
    seedWeightingStock('B', [100]);
    seedWeightingStock('C', [100], ['sharpe_return_one_year' => 3]);
    $backtest = weightingBacktest('price_weighted');

    app(RunBacktestAction::class)->execute($backtest);

    expect($backtest->trades()->where('trade_type', 'buy')->pluck('symbol')->all())->toBe(['B', 'C'])
        ->and((float) $backtest->dailySnapshots()->sole()->cash)->toBe(0.0);
});

it('reserves the value of a dma-protected holding when its current rank is missing', function () {
    $dates = seedWeightingStock('A', array_fill(0, 6, 100), [], [5 => ['median_volume_one_year' => 0]]);
    seedWeightingStock('B', array_fill(0, 6, 100));
    seedWeightingStock('C', array_fill(0, 6, 100), ['sharpe_return_one_year' => 3]);
    $backtest = weightingBacktest('rank_weighted', [
        'initial_capital' => 110000,
        'max_stocks_to_hold' => 3,
        'apply_hold_above_dma' => true,
        'hold_above_dma_period' => 50,
    ]);

    app(RunBacktestAction::class)->execute($backtest);

    $adjustments = $backtest->trades()->where('date', $dates[5])->get()->keyBy('symbol');
    expect($backtest->trades()->where('symbol', 'A')->count())->toBe(1)
        ->and($adjustments['B']->trade_type)->toBe('buy')
        ->and((int) $adjustments['B']->quantity)->toBe(33)
        ->and($adjustments['C']->trade_type)->toBe('sell')
        ->and((int) $adjustments['C']->quantity)->toBeGreaterThanOrEqual(32)
        ->and((float) $backtest->dailySnapshots()->where('date', $dates[5])->sole()->total_value)->toBe(110000.0);
});

it('uses the raw weighting price of a dma-protected holding outside the filtered rank list', function () {
    $dates = seedWeightingStock('A', array_fill(0, 6, 100), [], [5 => ['median_volume_one_year' => 0, 'close_raw' => 400]]);
    seedWeightingStock('B', array_fill(0, 6, 100));
    $backtest = weightingBacktest('price_weighted', [
        'initial_capital' => 100000,
        'apply_hold_above_dma' => true,
        'hold_above_dma_period' => 50,
    ]);

    app(RunBacktestAction::class)->execute($backtest);

    $adjustments = $backtest->trades()->where('date', $dates[5])->get()->keyBy('symbol');
    expect($adjustments['A']->trade_type)->toBe('buy')
        ->and((int) $adjustments['A']->quantity)->toBe(300)
        ->and($adjustments['B']->trade_type)->toBe('sell')
        ->and((int) $adjustments['B']->quantity)->toBe(300);
});

it('keeps proportional cash under the existing limited-stock cash rule', function (string $weightage) {
    seedWeightingStock('A', [100], ['close_raw' => 200]);
    seedWeightingStock('B', [100]);
    $backtest = weightingBacktest($weightage, [
        'initial_capital' => 120000,
        'max_stocks_to_hold' => 4,
        'cash_call' => 'cash_call_if_not_enough_stocks',
    ]);

    app(RunBacktestAction::class)->execute($backtest);

    expect((float) $backtest->dailySnapshots()->sole()->cash)->toBe(60000.0);
})->with(['rank_weighted', 'price_weighted']);

it('keeps cash and net asset value consistent after weighted trades with charges', function (string $weightage) {
    $dates = seedWeightingStock('A', array_fill(0, 6, 100), ['close_raw' => 200], [5 => ['close_raw' => 50, 'sharpe_return_one_year' => 1]]);
    seedWeightingStock('B', array_fill(0, 6, 100));
    $backtest = weightingBacktest($weightage, ['brokerage_rate' => 0.5, 'stt_rate' => 0.1, 'gst_rate' => 18]);

    app(RunBacktestAction::class)->execute($backtest);

    $snapshot = $backtest->dailySnapshots()->where('date', $dates[5])->sole();
    $costs = (float) $backtest->trades()->sum('total_charges');
    expect((float) $snapshot->cash)->toBeGreaterThanOrEqual(0)
        ->and(abs((float) $snapshot->total_value + $costs - 90000))->toBeLessThan(0.02)
        ->and($backtest->trades()->where('date', $dates[5])->where('trade_type', 'sell')->exists())->toBeTrue()
        ->and($backtest->trades()->where('date', $dates[5])->where('trade_type', 'buy')->exists())->toBeTrue();
})->with(['rank_weighted', 'price_weighted']);

it('allows for buy charges once when sizing weighted orders', function (string $weightage, int $aQuantity, int $bQuantity) {
    seedWeightingStock('A', [100], ['close_raw' => 200, 'volatility_one_year' => 0.2]);
    seedWeightingStock('B', [100], ['volatility_one_year' => 0.4]);
    $backtest = weightingBacktest($weightage, ['initial_capital' => 126000, 'brokerage_rate' => 5, 'gst_rate' => 0]);

    app(RunBacktestAction::class)->execute($backtest);

    $buys = $backtest->trades()->where('trade_type', 'buy')->get()->groupBy('symbol');
    expect((int) $buys['A']->sum('quantity'))->toBe($aQuantity)
        ->and((int) $buys['B']->sum('quantity'))->toBe($bQuantity)
        ->and((float) $backtest->trades()->sum('total_charges'))->toBe(6000.0)
        ->and((float) $backtest->dailySnapshots()->sole()->cash)->toBe(0.0);
})->with([
    ['rank_weighted', 800, 400],
    ['price_weighted', 800, 400],
    ['inverse_volatility', 800, 400],
    ['equal_weight_rebalanced', 600, 600],
]);

it('never adds to held stocks when spending forced-exit replacement proceeds', function (string $weightage, string $exitType, bool $hasCandidate) {
    $dates = seedWeightingStock('A', [100, 89, 88, 88], [], $exitType === 'BE' ? [2 => ['series' => 'BE'], 3 => ['series' => 'BE']] : []);
    seedWeightingStock('B', array_fill(0, 4, 100));
    if ($hasCandidate) {
        seedWeightingStock('C', array_fill(0, 4, 371), ['sharpe_return_one_year' => 3]);
    }
    if ($exitType === 'demerger') {
        createCorporateAction('A', $dates[3], ['type' => CorporateActionTypeEnum::DEMERGER->value]);
    }
    $backtest = weightingBacktest($weightage, [
        'apply_stop_loss' => $exitType === 'stop loss',
        'stop_loss_percentage' => 10,
        'stop_loss_proceeds' => 'replace_immediately',
        'exit_on_be_series' => $exitType === 'BE',
    ]);

    app(RunBacktestAction::class)->execute($backtest);

    expect($backtest->trades()->where('symbol', 'B')->where('trade_type', 'buy')->count())->toBe(1)
        ->and($backtest->trades()->where('date', $dates[2])->where('trade_type', 'buy')->pluck('symbol')->all())->toBe($hasCandidate ? ['C'] : [])
        ->and((float) $backtest->dailySnapshots()->where('date', $dates[2])->sole()->cash)->toBeGreaterThan(0);
})->with(['rank_weighted', 'price_weighted'])->with(['stop loss', 'BE', 'demerger'])->with([false, true]);
