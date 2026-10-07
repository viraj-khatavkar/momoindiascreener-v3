<?php

use App\Actions\Backtest\CalculateBacktestMetricsAction;
use App\Actions\Backtest\FindAssumedDelistingExitsAction;
use App\Actions\Backtest\RunBacktestAction;
use App\Actions\Backtest\UpdateAssumedDelistingsAction;
use App\Models\Backtest;
use App\Models\NseIndex;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/** @param array<string, mixed> $settings */
function assumedDelistingBacktest(array $settings = []): Backtest
{
    return Backtest::factory()->create(array_merge([
        'start_date' => '2024-01-08', 'initial_capital' => 10000, 'max_stocks_to_hold' => 1,
        'rebalance_frequency' => 'weekly', 'rebalance_day' => 1,
        'apply_hold_above_dma' => true, 'hold_above_dma_period' => 50,
        'cash_return_rate' => 0, 'brokerage_rate' => 0, 'stt_rate' => 0,
        'transaction_charges_rate' => 0, 'sebi_charges_rate' => 0, 'gst_rate' => 0, 'stamp_charges_rate' => 0,
    ], $settings));
}

/**
 * @param  list<int>  $quotedDays
 * @return Collection<int, Carbon>
 */
function seedAssumedDelistingPrices(int $days, array $quotedDays): Collection
{
    $dates = collect();
    for ($index = 0; $index < $days; $index++) {
        $date = Carbon::parse('2024-01-08')->addWeekdays($index);
        $dates->push($date);
        createScreenResultRow('B', 'B', $date->toDateString(), ['sharpe_return_one_year' => 4, 'ma_50' => 50]);
        if (in_array($index, $quotedDays, true)) {
            createScreenResultRow('A', 'A', $date->toDateString(), [
                'sharpe_return_one_year' => 5, 'ma_50' => 50, 't_percent' => $index === 2 ? 5 : 1,
                'close_adjusted' => $index === 2 ? 110 : 100, 'close_raw' => $index === 2 ? 110 : 100,
            ]);
        }
    }

    return $dates;
}

it('requires 100 consecutive missing market trading days rather than calendar days', function (int $days, ?int $resumesAt, bool $exitExpected) {
    $quotes = $resumesAt === null ? [0, 1] : [0, 1, $resumesAt];
    $dates = seedAssumedDelistingPrices($days, $quotes);
    $exits = findWithSharedAssumedDelistingData($dates);

    expect($exits)->toBe($exitExpected ? [$dates[1]->toDateString() => ['A' => $dates[101]->toDateString()]] : []);
})->with([
    '99 missing days at the end of data' => [101, null, false],
    '100 missing days at the end of data' => [102, null, true],
    'quote resumes after 99 missing days' => [102, 101, false],
    'quote resumes after 100 missing days' => [103, 102, true],
]);

it('checks all quotes after a stock leaves the selected universe', function () {
    $dates = seedAssumedDelistingPrices(103, [0, 1]);
    foreach ($dates->slice(2) as $date) {
        createScreenResultRow('A', 'A', $date->toDateString(), ['is_nifty_allcap' => false]);
    }

    expect(findWithSharedAssumedDelistingData($dates))->toBe([]);
});

it('does not count zero prices as a resumption of trading', function () {
    $dates = seedAssumedDelistingPrices(103, [0, 1]);
    createScreenResultRow('A', 'A', $dates[50]->toDateString(), ['close_adjusted' => 0]);

    expect(findWithSharedAssumedDelistingData($dates))
        ->toBe([$dates[1]->toDateString() => ['A' => $dates[101]->toDateString()]]);
});

it('sells at the last traded close and closes the position across weighting and timing options', function (string $weightage, bool $nextDay) {
    $dates = seedAssumedDelistingPrices(103, [0, 1, 2]);
    $backtest = assumedDelistingBacktest(['weightage' => $weightage, 'execute_next_trading_day' => $nextDay]);
    runWithSharedAssumedDelistingData($backtest);
    app(CalculateBacktestMetricsAction::class)->execute($backtest);

    $sale = $backtest->trades()->where('symbol', 'A')->where('trade_type', 'sell')->sole();
    $replacement = $backtest->trades()->where('symbol', 'B')->where('date', $dates[2]->toDateString())->sole();

    expect($sale->date->toDateString())->toBe($dates[2]->toDateString())
        ->and((float) $sale->price)->toBe(110.0)
        ->and($sale->quantity)->toBe(100)
        ->and($sale->reason)->toStartWith('Assumed delisting')
        ->toContain('100 market trading days', $dates[102]->toDateString(), 'using future data')
        ->and($replacement->trade_type)->toBe('buy')
        ->and($replacement->quantity)->toBe(110)
        ->and($backtest->trades()->where('symbol', 'A')->count())->toBe(2)
        ->and((float) $backtest->summaryMetrics->final_value)->toBe(11000.0)
        ->and($backtest->summaryMetrics->stock_performance['closed']['count'])->toBe(1)
        ->and($backtest->summaryMetrics->stock_performance['closed']['net_pnl'])->toEqual(1000);
})->with(['equal_weight', 'equal_weight_rebalanced', 'inverse_volatility', 'rank_weighted', 'price_weighted'])->with([false, true]);

it('skips a new entry on the assumed exit date and buys the next rank', function (bool $nextDay) {
    $lastQuote = (int) $nextDay;
    seedAssumedDelistingPrices(101 + $lastQuote, range(0, $lastQuote));
    $backtest = assumedDelistingBacktest(['execute_next_trading_day' => $nextDay]);
    runWithSharedAssumedDelistingData($backtest);

    expect($backtest->trades()->sole()->symbol)->toBe('B');
})->with([false, true]);

it('charges the normal sale costs and keeps NAV and position profit consistent', function () {
    seedAssumedDelistingPrices(103, [0, 1, 2]);
    $backtest = assumedDelistingBacktest(['stt_rate' => 1]);
    runWithSharedAssumedDelistingData($backtest);
    app(CalculateBacktestMetricsAction::class)->execute($backtest);
    $sale = $backtest->trades()->where('trade_type', 'sell')->sole();

    expect($sale->quantity)->toBe(99)
        ->and((float) $sale->stt)->toBe(108.9)
        ->and((float) $backtest->summaryMetrics->final_value)->toBe(10676.1)
        ->and($backtest->summaryMetrics->stock_performance['closed']['net_pnl'])->toEqual(782.1);
});

it('applies the cash-call rule to proceeds from an assumed exit', function (string $cashCall, bool $goldAboveDma, ?string $replacement) {
    $dates = seedAssumedDelistingPrices(103, [0, 1, 2]);
    foreach ($dates as $date) {
        createScreenResultRow('GOLDBEES', 'Gold', $date->toDateString(), ['is_nifty_allcap' => false, 'ma_50' => $goldAboveDma ? 50 : 150]);
    }
    for ($offset = -30; $offset < 103; $offset++) {
        $close = $offset > 0 ? 80 : 100;
        NseIndex::insert([
            'symbol' => 'Nifty 50', 'slug' => 'nifty-50', 'date' => Carbon::parse('2024-01-08')->addWeekdays($offset)->toDateString(),
            'open' => $close, 'high' => $close, 'low' => $close, 'close' => $close,
            'points_change' => 0, 'percentage_change' => 0, 'volume' => 0, 'turnover' => 0,
            'price_to_earnings' => 20, 'price_to_book' => 3, 'dividend_yield' => 1.5,
        ]);
    }
    $backtest = assumedDelistingBacktest(['cash_call' => $cashCall, 'cash_call_dma_period' => 20]);
    runWithSharedAssumedDelistingData($backtest);

    expect($backtest->trades()->where('trade_type', 'buy')->whereDate('date', $dates[2]->toDateString())->pluck('symbol')->all())
        ->toBe($replacement === null ? [] : [$replacement]);
})->with([
    ['no_cash_call', true, 'B'],
    ['full_cash_below_index_dma', true, null],
    ['only_exits_below_index_dma', true, null],
    ['allocate_to_gold_below_index_dma', true, 'GOLDBEES'],
    ['only_exits_allocate_to_gold_below_index_dma', true, 'GOLDBEES'],
    ['only_exits_allocate_to_gold_above_dma_below_index_dma', true, 'GOLDBEES'],
    ['only_exits_allocate_to_gold_above_dma_below_index_dma', false, null],
]);

it('keeps replacement purchases separate from held stocks and skips another assumed exit', function (bool $nextDay) {
    $dates = seedAssumedDelistingPrices(106, range(0, 5));
    foreach ($dates as $index => $date) {
        createScreenResultRow('C', 'C', $date->toDateString(), ['sharpe_return_one_year' => 3, 'ma_50' => 50]);
        if (in_array($index, [4, 5], true)) {
            createScreenResultRow('D', 'D', $date->toDateString(), ['sharpe_return_one_year' => 4.5, 'ma_50' => 50]);
        }
    }
    $backtest = assumedDelistingBacktest(['max_stocks_to_hold' => 2, 'execute_next_trading_day' => $nextDay]);
    runWithSharedAssumedDelistingData($backtest);

    expect($backtest->trades()->where('symbol', 'B')->count())->toBe(1)
        ->and($backtest->trades()->where('symbol', 'D')->exists())->toBeFalse()
        ->and($backtest->trades()->where('trade_type', 'buy')->whereDate('date', $dates[5]->toDateString())->pluck('symbol')->all())->toBe(['C'])
        ->and($backtest->trades()->where('symbol', 'A')->where('trade_type', 'sell')->sole()->date->toDateString())->toBe($dates[5]->toDateString());
})->with([false, true]);

it('allows a new position after prices resume and the stock qualifies again', function () {
    $dates = seedAssumedDelistingPrices(108, [0, 1, ...range(102, 107)]);
    $backtest = assumedDelistingBacktest(['apply_hold_above_dma' => false, 'worst_rank_held' => 1]);
    runWithSharedAssumedDelistingData($backtest);

    expect($backtest->trades()->where('symbol', 'A')->where('trade_type', 'buy')->count())->toBe(2)
        ->and($backtest->trades()->where('symbol', 'A')->whereDate('date', $dates[105]->toDateString())->sole()->trade_type)->toBe('buy');
});

function runWithSharedAssumedDelistingData(Backtest $backtest): void
{
    app(UpdateAssumedDelistingsAction::class)->execute();
    app(RunBacktestAction::class)->execute($backtest);
}

/** @param Collection<int, Carbon> $dates */
function findWithSharedAssumedDelistingData(Collection $dates): array
{
    app(UpdateAssumedDelistingsAction::class)->execute();

    return app(FindAssumedDelistingExitsAction::class)->execute(assumedDelistingBacktest(), $dates);
}
