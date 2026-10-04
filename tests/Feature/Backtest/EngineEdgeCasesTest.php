<?php

use App\Actions\Backtest\CalculateBacktestMetricsAction;
use App\Actions\Backtest\RunBacktestAction;
use App\Actions\Backtest\StartBacktestRunAction;
use App\Actions\Backtest\StoreMarketCapAllocationAction;
use App\Enums\BacktestStatusEnum;
use App\Enums\CorporateActionTypeEnum;
use App\Jobs\RunBacktestJob;
use App\Models\Backtest;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

/** @param array<string, mixed> $settings */
function edgeBacktest(array $settings = []): Backtest
{
    return Backtest::factory()->create(array_merge([
        'start_date' => '2024-01-08', 'initial_capital' => 10000,
        'max_stocks_to_hold' => 2, 'worst_rank_held' => 100,
        'rebalance_frequency' => 'weekly', 'rebalance_day' => 1,
        'execute_next_trading_day' => false, 'cash_call' => 'no_cash_call',
        'cash_return_rate' => 0, 'brokerage_rate' => 0, 'stt_rate' => 0,
        'transaction_charges_rate' => 0, 'sebi_charges_rate' => 0,
        'gst_rate' => 0, 'stamp_charges_rate' => 0,
    ], $settings));
}

/** @param array<string, mixed> $attributes */
function edgeQuote(string $symbol, string $date, float $price, array $attributes = []): void
{
    createScreenResultRow($symbol, $symbol, $date, array_merge([
        'close_adjusted' => $price, 'close_raw' => $price,
        'sharpe_return_one_year' => $symbol === 'A' ? 5 : 4,
        'volatility_one_year' => 0.4, 'ma_50' => 50, 't_percent' => 1,
    ], $attributes));
}

/** @param list<float|int> $navs */
function edgeSnapshots(Backtest $backtest, array $navs): void
{
    foreach ($navs as $offset => $nav) {
        $backtest->dailySnapshots()->create([
            'date' => '2024-01-'.(8 + $offset), 'nav' => $nav,
            'total_value' => $nav * 100, 'cash' => $nav * 100,
            'portfolio_value' => 0, 'holdings_count' => 0,
        ]);
    }
}

it('keeps shares and their last valid valuation when the execution quote is absent or zero', function (string $weightage, bool $nextDay, ?float $missingQuote) {
    $backtest = edgeBacktest(['max_stocks_to_hold' => 1, 'weightage' => $weightage, 'execute_next_trading_day' => $nextDay]);
    foreach (['2024-01-08', '2024-01-09', '2024-01-15', '2024-01-16'] as $date) {
        edgeQuote('B', $date, 100);
        if ($date < '2024-01-15' || $missingQuote !== null) {
            edgeQuote('A', $date, $date < '2024-01-15' ? 100 : $missingQuote);
        }
    }

    app(RunBacktestAction::class)->execute($backtest);
    app(CalculateBacktestMetricsAction::class)->execute($backtest);

    expect($backtest->trades()->sole()->symbol)->toBe('A')
        ->and((float) $backtest->summaryMetrics->final_value)->toBe(10000.0)
        ->and($backtest->summaryMetrics->stock_performance['open_positions'][0]['unrealized_value'])->toEqual(10000);
})->with(['equal_weight', 'equal_weight_rebalanced', 'inverse_volatility', 'rank_weighted', 'price_weighted'])
    ->with([false, true])->with([null, 0.0]);

it('does not use a missing demerger quote or invalid BE quote to fund a replacement', function (string $exitType) {
    $backtest = edgeBacktest(['max_stocks_to_hold' => 1, 'exit_before_demerger' => true, 'exit_on_be_series' => true]);
    edgeQuote('A', '2024-01-08', 100);
    edgeQuote('B', '2024-01-08', 100);
    edgeQuote('B', '2024-01-09', 100);
    edgeQuote('B', '2024-01-10', 100);
    if ($exitType === 'demerger') {
        createCorporateAction('A', '2024-01-10', ['type' => CorporateActionTypeEnum::DEMERGER]);
    } else {
        edgeQuote('A', '2024-01-09', 0, ['series' => 'BE']);
    }

    app(RunBacktestAction::class)->execute($backtest);

    expect($backtest->trades()->sole()->symbol)->toBe('A')
        ->and((float) $backtest->dailySnapshots()->orderByDesc('date')->first()->cash)->toBe(0.0);
})->with(['demerger', 'BE']);

it('restores protected equal weights only when equal weight rebalanced is selected', function (string $weightage, bool $nextDay, bool $allExcluded) {
    $backtest = edgeBacktest(['weightage' => $weightage, 'apply_hold_above_dma' => true, 'hold_above_dma_period' => 50, 'execute_next_trading_day' => $nextDay]);
    foreach (['2024-01-08', '2024-01-09', '2024-01-15', '2024-01-16'] as $date) {
        $later = $date >= '2024-01-15';
        edgeQuote('A', $date, $later ? 200 : 100, ['median_volume_one_year' => $later ? 0 : 20000000]);
        edgeQuote('B', $date, 100, ['median_volume_one_year' => $later && $allExcluded ? 0 : 20000000]);
    }

    app(RunBacktestAction::class)->execute($backtest);

    expect((int) $backtest->trades()->where('symbol', 'A')->where('trade_type', 'sell')->sum('quantity'))->toBe($weightage === 'equal_weight' ? 0 : 12)
        ->and((int) $backtest->trades()->where('symbol', 'B')->where('trade_type', 'buy')->sum('quantity'))->toBe($weightage === 'equal_weight' ? 50 : 74);
})->with(['equal_weight', 'equal_weight_rebalanced'])->with([false, true])->with([false, true]);

it('reserves the value of a holding without a quote during weighted allocations', function (string $weightage) {
    $backtest = edgeBacktest(['weightage' => $weightage, 'initial_capital' => 9000]);
    edgeQuote('A', '2024-01-08', 100);
    edgeQuote('B', '2024-01-08', 100);
    edgeQuote('B', '2024-01-15', 200);

    app(RunBacktestAction::class)->execute($backtest);

    expect($backtest->trades()->count())->toBe(2)
        ->and((float) $backtest->dailySnapshots()->orderByDesc('date')->first()->cash)->toBe(0.0);
})->with(['equal_weight_rebalanced', 'inverse_volatility', 'rank_weighted', 'price_weighted']);

it('restores volatility and price weights when every holding fails entry filters', function (string $weightage, bool $nextDay) {
    $backtest = edgeBacktest([
        'weightage' => $weightage, 'initial_capital' => 12000,
        'apply_hold_above_dma' => true, 'hold_above_dma_period' => 50,
        'execute_next_trading_day' => $nextDay,
    ]);
    foreach (['2024-01-08', '2024-01-09', '2024-01-15', '2024-01-16'] as $date) {
        $later = $date >= '2024-01-15';
        $volume = $later ? 0 : 20000000;
        edgeQuote('A', $date, 100, [
            'median_volume_one_year' => $volume,
            'volatility_one_year' => $later ? 0.8 : 0.4,
        ]);
        edgeQuote('B', $date, 100, [
            'median_volume_one_year' => $volume,
            'close_raw' => $later ? 100 : 200,
        ]);
    }
    app(RunBacktestAction::class)->execute($backtest);
    $adjustments = $backtest->trades()->whereDate('date', $nextDay ? '2024-01-16' : '2024-01-15')->get()->keyBy('symbol');

    expect($adjustments)->toHaveCount(2)
        ->and($adjustments['A']->trade_type)->toBe($weightage === 'inverse_volatility' ? 'sell' : 'buy')
        ->and((int) $adjustments['A']->quantity)->toBe(20)
        ->and($adjustments['B']->trade_type)->toBe($weightage === 'inverse_volatility' ? 'buy' : 'sell')
        ->and((int) $adjustments['B']->quantity)->toBe(20)
        ->and((float) $backtest->dailySnapshots()->orderByDesc('date')->first()->total_value)->toBe(12000.0);
})->with(['inverse_volatility', 'price_weighted'])->with([false, true]);

it('keeps earlier trades unchanged when later dates complete a rebalance period', function (string $frequency, int $day, string $laterDate) {
    $settings = ['weightage' => 'equal_weight_rebalanced', 'rebalance_frequency' => $frequency, 'rebalance_day' => $day];
    $short = edgeBacktest($settings);
    foreach (['2024-01-08', '2024-01-09', '2024-01-10'] as $date) {
        edgeQuote('A', $date, $date === '2024-01-10' ? 200 : 100);
        edgeQuote('B', $date, 100);
    }
    app(RunBacktestAction::class)->execute($short);
    expect($short->trades()->whereDate('date', '2024-01-10')->count())->toBe(0);

    edgeQuote('A', $laterDate, 200);
    edgeQuote('B', $laterDate, 100);
    $long = edgeBacktest($settings);
    app(RunBacktestAction::class)->execute($long);
    $columns = ['symbol', 'date', 'trade_type', 'quantity', 'price'];
    expect($long->trades()->where('date', '<=', '2024-01-10')->get($columns)->toArray())->toBe($short->trades()->get($columns)->toArray())
        ->and($long->trades()->where('date', $laterDate)->count())->toBe(2);
})->with([['weekly', 5, '2024-01-12'], ['monthly', 20, '2024-01-22']]);

it('uses the holiday fallback only for a period followed by later trading data', function (string $frequency, int $day, string $end, string $next) {
    $backtest = edgeBacktest(['weightage' => 'equal_weight_rebalanced', 'rebalance_frequency' => $frequency, 'rebalance_day' => $day]);
    foreach (['2024-01-08', $end, $next] as $date) {
        edgeQuote('A', $date, $date === '2024-01-08' ? 100 : 200);
        edgeQuote('B', $date, 100);
    }
    app(RunBacktestAction::class)->execute($backtest);
    expect($backtest->trades()->whereDate('date', $end)->count())->toBe(2);
})->with([['weekly', 5, '2024-01-11', '2024-01-15'], ['monthly', 31, '2024-01-30', '2024-02-01']]);

it('includes entry charges in drawdown and ulcer index from initial capital', function () {
    $backtest = edgeBacktest(['max_stocks_to_hold' => 1, 'brokerage_rate' => 1]);
    edgeQuote('A', '2024-01-08', 100);
    edgeQuote('A', '2024-01-09', 100);
    app(RunBacktestAction::class)->execute($backtest);
    app(CalculateBacktestMetricsAction::class)->execute($backtest);
    expect((float) $backtest->summaryMetrics->max_drawdown)->toBe(-0.0099)
        ->and((float) $backtest->summaryMetrics->ulcer_index)->toBe(0.99);
});

it('uses the most recent recovered peak for the drawdown period', function () {
    $backtest = edgeBacktest();
    edgeSnapshots($backtest, [100, 90, 100, 80]);
    app(CalculateBacktestMetricsAction::class)->execute($backtest);
    expect((float) $backtest->summaryMetrics->max_drawdown)->toBe(-0.2)
        ->and($backtest->summaryMetrics->max_drawdown_start_date->toDateString())->toBe('2024-01-10');
});

it('uses the same daily target in both parts of Sortino and saves the result', function (float $target) {
    $backtest = edgeBacktest(['cash_return_rate' => $target]);
    edgeSnapshots($backtest, [100, 99, 100.98]);
    app(CalculateBacktestMetricsAction::class)->execute($backtest);
    $dailyTarget = pow(1 + $target / 100, 1 / 252) - 1;
    $expected = round((0.005 - $dailyTarget) / (abs(-0.01 - $dailyTarget) / sqrt(2)) * sqrt(252), 4);
    expect((float) $backtest->summaryMetrics->sortino_ratio)->toBe($expected);
    $backtest->update(['cash_return_rate' => 15]);
    expect((float) $backtest->summaryMetrics()->sole()->sortino_ratio)->toBe($expected);
})->with([0.0, 6.0]);

it('counts small positive returns below the cash target as downside', function () {
    $backtest = edgeBacktest(['cash_return_rate' => 6]);
    edgeSnapshots($backtest, [100, 100.01, 100.02]);
    app(CalculateBacktestMetricsAction::class)->execute($backtest);
    expect($backtest->summaryMetrics->sortino_ratio)->not->toBeNull()
        ->and((float) $backtest->summaryMetrics->sortino_ratio)->toBeLessThan(0);
});

it('shows no Sortino value when there is no downside', function () {
    $backtest = edgeBacktest();
    edgeSnapshots($backtest, [100, 101, 102]);
    app(CalculateBacktestMetricsAction::class)->execute($backtest);
    expect($backtest->summaryMetrics->sortino_ratio)->toBeNull();
});

it('rejects insufficient data before clearing saved results or queueing work', function (bool $oneDay) {
    Queue::fake();
    $backtest = edgeBacktest(['status' => BacktestStatusEnum::Completed]);
    edgeSnapshots($backtest, [100, 101]);
    app(CalculateBacktestMetricsAction::class)->execute($backtest);
    if ($oneDay) {
        edgeQuote('A', '2024-01-08', 100);
    }
    expect(fn () => app(StartBacktestRunAction::class)->execute($backtest))->toThrow(ValidationException::class, 'Insufficient trading data');
    expect($backtest->refresh()->status)->toBe(BacktestStatusEnum::Completed)
        ->and($backtest->summaryMetrics()->count())->toBe(1)
        ->and($backtest->dailySnapshots()->count())->toBe(2);
    Queue::assertNothingPushed();
})->with([false, true]);

it('fails an already queued run with insufficient data instead of marking it complete', function (bool $oneDay) {
    $backtest = edgeBacktest();
    if ($oneDay) {
        edgeQuote('A', '2024-01-08', 100);
    }
    expect(fn () => (new RunBacktestJob($backtest))->handle(app(RunBacktestAction::class), app(CalculateBacktestMetricsAction::class), app(StoreMarketCapAllocationAction::class)))
        ->toThrow(ValidationException::class, 'Insufficient trading data');
    expect($backtest->refresh()->status)->toBe(BacktestStatusEnum::Failed)
        ->and($backtest->summaryMetrics()->exists())->toBeFalse()
        ->and($backtest->trades()->exists())->toBeFalse();
})->with([false, true]);

it('returns a clear validation error through the run endpoint without removing results', function () {
    $backtest = edgeBacktest(['status' => BacktestStatusEnum::Completed]);
    $backtest->user->update(['is_paid' => true]);
    edgeSnapshots($backtest, [100, 101]);
    $this->actingAs($backtest->user)->postJson('/backtests/'.$backtest->id.'/run')
        ->assertUnprocessable()->assertJsonValidationErrors('start_date');
    expect($backtest->refresh()->status)->toBe(BacktestStatusEnum::Completed)
        ->and($backtest->dailySnapshots()->count())->toBe(2);
});

it('throws instead of silently skipping metrics when snapshots are incomplete', function (bool $oneDay) {
    $backtest = edgeBacktest();
    if ($oneDay) {
        edgeSnapshots($backtest, [100]);
    }
    expect(fn () => app(CalculateBacktestMetricsAction::class)->execute($backtest))
        ->toThrow(RuntimeException::class, 'Insufficient trading data');
})->with([false, true]);

it('continues to the next rank when the planned purchase has no execution quote', function (string $weightage) {
    $backtest = edgeBacktest(['weightage' => $weightage, 'max_stocks_to_hold' => 1, 'execute_next_trading_day' => true]);
    edgeQuote('A', '2024-01-08', 100);
    edgeQuote('B', '2024-01-08', 100);
    edgeQuote('B', '2024-01-09', 100);
    app(RunBacktestAction::class)->execute($backtest);
    expect($backtest->trades()->sole()->symbol)->toBe('B');
})->with(['equal_weight', 'equal_weight_rebalanced', 'inverse_volatility', 'rank_weighted', 'price_weighted']);
