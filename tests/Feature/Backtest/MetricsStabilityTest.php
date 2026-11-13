<?php

use App\Actions\Backtest\CalculateBacktestMetricsAction;
use App\Actions\Backtest\StartBacktestRunAction;
use App\Enums\BacktestStatusEnum;
use App\Models\Backtest;
use Carbon\Carbon;

/** @param list<float|int> $navs */
function saveStabilitySnapshots(Backtest $backtest, array $navs): void
{
    foreach ($navs as $index => $nav) {
        $backtest->dailySnapshots()->create([
            'date' => Carbon::parse('2024-01-01')->addWeekdays($index),
            'nav' => $nav, 'total_value' => $nav * 1000, 'cash' => $nav * 1000,
            'portfolio_value' => 0, 'holdings_count' => 0,
        ]);
    }
}

it('completes a cash only source and its dependent with unavailable constant return ratios', function (float $cashRate) {
    $dates = [];
    for ($index = 0; $index < 60; $index++) {
        $date = Carbon::parse('2024-01-01')->addWeekdays($index)->toDateString();
        $dates[] = $date;
        createScreenResultRow('SOURCE', 'Source stock', $date, ['is_nifty_200' => true]);
        createScreenResultRow('TARGET', 'Target stock', $date, ['is_nifty_50' => true]);
    }
    $settings = [
        'start_date' => $dates[0], 'initial_capital' => 100000,
        'max_stocks_to_hold' => 1, 'worst_rank_held' => 1,
        'rebalance_frequency' => 'weekly', 'rebalance_day' => 1,
        'cash_return_rate' => $cashRate, 'brokerage_rate' => 0, 'stt_rate' => 0,
        'transaction_charges_rate' => 0, 'sebi_charges_rate' => 0,
        'gst_rate' => 0, 'stamp_charges_rate' => 0,
    ];
    $source = Backtest::factory()->create([
        ...$settings, 'index' => 'nifty_200', 'minimum_return_one_year' => 100,
    ]);
    $dependent = Backtest::factory()->create([
        ...$settings, 'user_id' => $source->user_id, 'index' => 'nifty_50',
        'start_date' => $dates[20], 'cash_call' => 'full_cash_below_index_dma',
        'cash_call_source' => 'backtest', 'cash_call_backtest_id' => $source->id,
        'cash_call_dma_period' => 20,
    ]);

    app(StartBacktestRunAction::class)->execute($dependent);

    expect($source->refresh()->status)->toBe(BacktestStatusEnum::Completed)
        ->and($dependent->refresh()->status)->toBe(BacktestStatusEnum::Completed)
        ->and($source->trades()->count())->toBe(0)
        ->and($source->summaryMetrics->k_ratio)->toBeNull()
        ->and($source->summaryMetrics->sharpe_ratio)->toBeNull()
        ->and($source->summaryMetrics->sortino_ratio)->toBeNull()
        ->and((float) $source->summaryMetrics->final_value)->toEqualWithDelta(100000 * pow(1 + $cashRate / 100, 60 / 252), 0.01)
        ->and($dependent->summaryMetrics)->not->toBeNull();
})->with([0.0, 6.0, 20.0]);

it('uses the compounded daily cash rate for portfolio Sharpe and preserves ordinary K ratios', function (float $cashRate) {
    $backtest = Backtest::factory()->create(['cash_return_rate' => $cashRate]);
    saveStabilitySnapshots($backtest, [100, 99, 100.98]);

    app(CalculateBacktestMetricsAction::class)->execute($backtest);

    /** Daily returns are -1% and +2%, with sample standard deviation 3% / sqrt(2). */
    $dailyTarget = pow(1 + $cashRate / 100, 1 / 252) - 1;
    $expectedSharpe = round((0.005 - $dailyTarget) / (0.03 / sqrt(2)) * sqrt(252), 4);
    /** Closed form for the three-point log-NAV regression t-statistic. */
    $expectedK = round(sqrt(3) * (log(100.98) - log(100)) / abs(log(100) - 2 * log(99) + log(100.98)), 4);

    expect((float) $backtest->summaryMetrics->sharpe_ratio)->toBe($expectedSharpe)
        ->and((float) $backtest->summaryMetrics->k_ratio)->toBe($expectedK);

    $backtest->update(['cash_return_rate' => 15]);
    expect((float) $backtest->summaryMetrics()->sole()->sharpe_ratio)->toBe($expectedSharpe);
})->with([0.0, 6.0, 20.0]);

it('saves unavailable ratios instead of exceeding their database field limits', function () {
    $backtest = Backtest::factory()->create(['cash_return_rate' => 0]);
    $navs = [];
    for ($index = 0; $index < 10; $index++) {
        $navs[] = 100 * pow(1.01, $index) + ($index === 5 ? 0.00001 : 0);
    }
    saveStabilitySnapshots($backtest, $navs);

    app(CalculateBacktestMetricsAction::class)->execute($backtest);

    expect($backtest->summaryMetrics->k_ratio)->toBeNull()
        ->and($backtest->summaryMetrics->sharpe_ratio)->toBeNull()
        ->and((float) $backtest->summaryMetrics->final_value)->toEqualWithDelta(end($navs) * 1000, 0.01);
});

it('keeps measurable small return variation instead of suppressing all low volatility ratios', function () {
    $backtest = Backtest::factory()->create(['cash_return_rate' => 0]);
    saveStabilitySnapshots($backtest, [100, 100.0001, 100.0003, 100.0002]);

    app(CalculateBacktestMetricsAction::class)->execute($backtest);

    expect($backtest->summaryMetrics->sharpe_ratio)->not->toBeNull()
        ->and((float) $backtest->summaryMetrics->sharpe_ratio)->toBeGreaterThan(0)
        ->and($backtest->summaryMetrics->sortino_ratio)->not->toBeNull()
        ->and($backtest->summaryMetrics->k_ratio)->not->toBeNull();
});

it('completes short runs with large finite CAGR values or an unavailable nonfinite CAGR', function (float $lastPrice) {
    createScreenResultRow('A', 'A', '2024-01-01');
    createScreenResultRow('A', 'A', '2024-01-02', ['close_adjusted' => $lastPrice, 'close_raw' => $lastPrice]);
    $backtest = Backtest::factory()->create([
        'start_date' => '2024-01-01', 'initial_capital' => 100000, 'max_stocks_to_hold' => 1,
        'cash_return_rate' => 0, 'brokerage_rate' => 0, 'stt_rate' => 0,
        'transaction_charges_rate' => 0, 'sebi_charges_rate' => 0, 'gst_rate' => 0, 'stamp_charges_rate' => 0,
    ]);

    app(StartBacktestRunAction::class)->execute($backtest);

    $expectedCagr = pow($lastPrice / 100, 365.25) - 1;
    expect($backtest->refresh()->status)->toBe(BacktestStatusEnum::Completed)
        ->and((float) $backtest->summaryMetrics->final_value)->toBe($lastPrice * 1000);
    if (is_finite($expectedCagr)) {
        expect((float) $backtest->summaryMetrics->cagr)->toEqualWithDelta(round($expectedCagr, 4), max(0.00001, abs($expectedCagr) * 1e-12));
    } else {
        expect($backtest->summaryMetrics->cagr)->toBeNull();
    }
})->with([101.0, 110.0, 120.0, 1000.0]);
