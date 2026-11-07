<?php

use App\Actions\Backtest\CalculateBacktestMetricsAction;
use App\Actions\Backtest\RunBacktestAction;
use App\Enums\BacktestCashCallEnum;
use App\Enums\CorporateActionTypeEnum;
use App\Models\Backtest;
use App\Models\BacktestNseInstrumentPrice;
use Carbon\Carbon;

/** @param array<string, mixed> $settings */
function limitedStockCashBacktest(array $settings = []): Backtest
{
    return Backtest::factory()->create(array_merge([
        'start_date' => '2024-01-08',
        'initial_capital' => 1000000,
        'max_stocks_to_hold' => 10,
        'rebalance_frequency' => 'weekly',
        'rebalance_day' => 1,
        'cash_call' => BacktestCashCallEnum::CashCallIfNotEnoughStocks,
        'cash_return_rate' => 0,
        'brokerage_rate' => 0,
        'stt_rate' => 0,
        'transaction_charges_rate' => 0,
        'sebi_charges_rate' => 0,
        'gst_rate' => 0,
        'stamp_charges_rate' => 0,
    ], $settings));
}

/** @return list<string> */
function seedLimitedStockCashRebalance(int $days = 7): array
{
    $dates = [];
    foreach (range(0, $days - 1) as $day) {
        $date = Carbon::parse('2024-01-08')->addWeekdays($day)->toDateString();
        $dates[] = $date;
        foreach (range('A', 'N') as $index => $symbol) {
            $price = $index >= 6 && $index < 10 && $day >= 2 ? 162.5 : 100;
            $qualifies = match (true) {
                $index < 6 => true,
                $index < 10 => $day < 5,
                $index < 12 => $day >= 5,
                default => $day >= 10,
            };
            createScreenResultRow($symbol, $symbol, $date, [
                'close_adjusted' => $price, 'close_raw' => $price, 't_percent' => 1,
                'sharpe_return_one_year' => 20 - $index,
                'median_volume_one_year' => $qualifies ? 20000000 : 0,
            ]);
        }
    }

    return $dates;
}

it('splits 650000 from four exits between two entries and two cash portions', function (bool $nextDay, string $cashMode, float $expectedCash) {
    $dates = seedLimitedStockCashRebalance();
    $backtest = limitedStockCashBacktest(['execute_next_trading_day' => $nextDay, 'cash_call' => $cashMode]);
    app(RunBacktestAction::class)->execute($backtest);
    app(CalculateBacktestMetricsAction::class)->execute($backtest);

    $initial = $backtest->dailySnapshots()->where('date', $dates[(int) $nextDay])->sole();
    $trades = $backtest->trades()->where('date', $dates[$nextDay ? 6 : 5])->get();
    $snapshot = $backtest->dailySnapshots()->orderByDesc('date')->first();

    expect($initial->holdings_count)->toBe(10)->and((float) $initial->cash)->toBe(0.0)
        ->and($trades->where('trade_type', 'sell'))->toHaveCount(4)
        ->and((float) $trades->where('trade_type', 'sell')->sum('net_amount'))->toBe(650000.0)
        ->and($trades->where('trade_type', 'buy')->pluck('symbol')->all())->toBe(['K', 'L'])
        ->and((float) $trades->where('trade_type', 'buy')->sum('net_amount'))->toBe(650000 - $expectedCash)
        ->and((float) $snapshot->cash)->toBe($expectedCash)
        ->and($snapshot->holdings_count)->toBe(8)
        ->and((float) $snapshot->total_value)->toBe(1250000.0)
        ->and((float) $backtest->summaryMetrics->final_value)->toBe(1250000.0)
        ->and($backtest->summaryMetrics->stock_performance['closed']['count'])->toBe(4)
        ->and($backtest->summaryMetrics->stock_performance['closed']['net_pnl'])->toEqual(250000);
})->with([false, true])->with([
    'keep empty portions as cash' => ['cash_call_if_not_enough_stocks', 325000.0],
    'no cash call stays invested' => ['no_cash_call', 0.0],
]);

it('uses saved cash at a later rebalance when the remaining slots can be filled', function () {
    $dates = seedLimitedStockCashRebalance(11);
    $backtest = limitedStockCashBacktest();
    app(RunBacktestAction::class)->execute($backtest);

    $buys = $backtest->trades()->where('date', $dates[10])->where('trade_type', 'buy')->get();
    $snapshot = $backtest->dailySnapshots()->where('date', $dates[10])->sole();
    expect($buys->pluck('symbol')->all())->toBe(['M', 'N'])
        ->and((float) $buys->sum('net_amount'))->toBe(325000.0)
        ->and((float) $snapshot->cash)->toBe(0.0)
        ->and($snapshot->holdings_count)->toBe(10)
        ->and($backtest->trades()->whereIn('symbol', range('A', 'F'))->count())->toBe(6);
});

it('includes charges in each new purchase budget and preserves the unused cash portions', function () {
    $dates = seedLimitedStockCashRebalance();
    $backtest = limitedStockCashBacktest(['stt_rate' => 0.1, 'stamp_charges_rate' => 0.015]);
    app(RunBacktestAction::class)->execute($backtest);

    $trades = $backtest->trades()->where('date', $dates[5])->get();
    $previousCash = (float) $backtest->dailySnapshots()->where('date', $dates[4])->sole()->cash;
    $availableCash = $previousCash + (float) $trades->where('trade_type', 'sell')->sum('net_amount');
    $spent = (float) $trades->where('trade_type', 'buy')->sum('net_amount');
    $remainingCash = (float) $backtest->dailySnapshots()->where('date', $dates[5])->sole()->cash;

    expect($spent)->toBeLessThanOrEqual($availableCash / 2)
        ->and($remainingCash)->toBeGreaterThanOrEqual($availableCash / 2)
        ->and(abs($spent + $remainingCash - $availableCash))->toBeLessThan(0.02);
});

it('reserves two out of ten portfolio portions when rebalancing stock weights', function (string $weightage, bool $withCharges) {
    $dates = seedLimitedStockCashRebalance();
    $backtest = limitedStockCashBacktest([
        'weightage' => $weightage,
        'stt_rate' => $withCharges ? 0.1 : 0,
        'stamp_charges_rate' => $withCharges ? 0.015 : 0,
    ]);
    app(RunBacktestAction::class)->execute($backtest);

    $snapshot = $backtest->dailySnapshots()->where('date', $dates[5])->sole();
    $targetCash = (float) $snapshot->total_value * 0.2;
    expect($snapshot->holdings_count)->toBe(8)
        ->and((float) $snapshot->cash)->toBeGreaterThanOrEqual($targetCash)
        ->toBeLessThan($targetCash + 800);
})->with(['equal_weight_rebalanced', 'inverse_volatility', 'rank_weighted', 'price_weighted'])->with([false, true]);

it('keeps an unquoted holding separate from the cash reserve for empty slots', function (string $weightage) {
    $dates = seedLimitedStockCashRebalance();
    BacktestNseInstrumentPrice::query()->where('symbol', 'A')->where('date', $dates[5])->delete();
    $backtest = limitedStockCashBacktest(['weightage' => $weightage]);
    app(RunBacktestAction::class)->execute($backtest);

    $holdingTrades = $backtest->trades()->where('symbol', 'A')->get();
    $snapshot = $backtest->dailySnapshots()->where('date', $dates[5])->sole();
    $retainedValue = $holdingTrades->sole()->quantity * 100;
    $targetCash = ((float) $snapshot->total_value - $retainedValue) * 2 / 9;

    expect($holdingTrades->sole()->trade_type)->toBe('buy')
        ->and($snapshot->holdings_count)->toBe(8)
        ->and((float) $snapshot->cash)->toBeGreaterThanOrEqual(round($targetCash, 2) - 0.01)
        ->toBeLessThan($targetCash + 700);
})->with(['equal_weight_rebalanced', 'inverse_volatility', 'rank_weighted', 'price_weighted']);

it('keeps all capital in cash when no stocks qualify at a rebalance', function () {
    foreach (range(0, 5) as $day) {
        $date = Carbon::parse('2024-01-08')->addWeekdays($day)->toDateString();
        createScreenResultRow('A', 'A', $date, ['t_percent' => 1, 'median_volume_one_year' => $day < 5 ? 20000000 : 0]);
    }
    $backtest = limitedStockCashBacktest(['max_stocks_to_hold' => 1]);
    app(RunBacktestAction::class)->execute($backtest);

    $snapshot = $backtest->dailySnapshots()->orderByDesc('date')->first();
    expect($snapshot->holdings_count)->toBe(0)
        ->and((float) $snapshot->cash)->toBe(1000000.0)
        ->and($backtest->trades()->where('trade_type', 'sell')->count())->toBe(1);
});

it('keeps a cash portion when two forced exits have only one eligible replacement', function (string $exitType, string $cashMode, float $cashFraction) {
    $dates = [];
    foreach (range(0, 3) as $day) {
        $date = Carbon::parse('2024-01-08')->addWeekdays($day)->toDateString();
        $dates[] = $date;
        foreach (range('A', 'E') as $index => $symbol) {
            $exits = $index < 2;
            $price = $exitType === 'stop_loss' && $exits && $day > 0 ? ($day === 1 ? 89 : 88) : 100;
            createScreenResultRow($symbol, $symbol, $date, [
                'close_adjusted' => $price, 'close_raw' => $price, 't_percent' => 1,
                'sharpe_return_one_year' => 20 - $index,
                'series' => $exitType === 'BE' && $exits && $day >= 2 ? 'BE' : 'EQ',
                'median_volume_one_year' => $symbol === 'E' && $day < 2 ? 0 : 20000000,
            ]);
        }
    }
    if ($exitType === 'demerger') {
        foreach (['A', 'B'] as $symbol) {
            createCorporateAction($symbol, $dates[3], ['type' => CorporateActionTypeEnum::DEMERGER]);
        }
    }
    $backtest = limitedStockCashBacktest([
        'max_stocks_to_hold' => 4,
        'cash_call' => $cashMode,
        'exit_on_be_series' => $exitType === 'BE',
        'apply_stop_loss' => $exitType === 'stop_loss',
        'stop_loss_proceeds' => 'replace_immediately',
    ]);
    app(RunBacktestAction::class)->execute($backtest);

    $trades = $backtest->trades()->where('date', $dates[2])->get();
    $proceeds = (float) $trades->where('trade_type', 'sell')->sum('net_amount');
    $snapshot = $backtest->dailySnapshots()->where('date', $dates[2])->sole();
    expect($trades->where('trade_type', 'sell'))->toHaveCount(2)
        ->and($trades->where('trade_type', 'buy')->pluck('symbol')->all())->toBe(['E'])
        ->and((float) $trades->where('trade_type', 'buy')->sum('net_amount'))->toBe($proceeds * (1 - $cashFraction))
        ->and((float) $snapshot->cash)->toBe($proceeds * $cashFraction)
        ->and($snapshot->holdings_count)->toBe(3)
        ->and($backtest->trades()->whereIn('symbol', ['C', 'D'])->count())->toBe(2);
})->with(['stop_loss', 'BE', 'demerger'])->with([
    'keep empty portions as cash' => ['cash_call_if_not_enough_stocks', 0.5],
    'no cash call stays invested' => ['no_cash_call', 0.0],
]);

it('does not spend the reserved portion on a stock whose execution quote is missing', function () {
    $dates = seedLimitedStockCashRebalance();
    BacktestNseInstrumentPrice::query()->where('symbol', 'K')->where('date', $dates[6])->delete();
    $backtest = limitedStockCashBacktest(['execute_next_trading_day' => true]);
    app(RunBacktestAction::class)->execute($backtest);

    $trades = $backtest->trades()->where('date', $dates[6])->where('trade_type', 'buy')->get();
    $snapshot = $backtest->dailySnapshots()->where('date', $dates[6])->sole();
    expect($trades->pluck('symbol')->all())->toBe(['L'])
        ->and((float) $trades->sum('net_amount'))->toBe(162500.0)
        ->and((float) $snapshot->cash)->toBe(487500.0);
});
