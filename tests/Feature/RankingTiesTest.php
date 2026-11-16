<?php

use App\Actions\ApplyScreenFiltersAction;
use App\Actions\Backtest\ApplyBacktestFiltersAction;
use App\Actions\Backtest\RunBacktestAction;
use App\Enums\ApplyFiltersOnOptionEnum;
use App\Enums\NseIndexEnum;
use App\Models\Backtest;
use App\Models\BacktestNseInstrumentPrice;
use App\Models\Screen;
use Illuminate\Support\Collection;

/**
 * @param  array<string, mixed>  $attributes
 * @return Collection<int, BacktestNseInstrumentPrice>
 */
function rankingTieResults(string $type, array $attributes = []): Collection
{
    $settings = array_merge([
        'sort_by' => 'close_raw',
        'sort_direction' => 'desc',
    ], $attributes);

    if ($type === 'screen') {
        return app(ApplyScreenFiltersAction::class)->execute(Screen::factory()->make($settings), '2026-07-31')->values();
    }

    return app(ApplyBacktestFiltersAction::class)->execute(Backtest::factory()->make($settings), '2026-07-31')->values();
}

it('gives equal factor values the average of the occupied ranks', function (string $type, string $additionalFactor) {
    foreach (['DELTA' => 80, 'GAMMA' => 90, 'BETA' => 90, 'ALPHA' => 100] as $symbol => $price) {
        createScreenResultRow($symbol, $symbol, '2026-07-31', ['close_raw' => $price]);
    }

    $results = rankingTieResults($type, ['apply_factor_'.$additionalFactor => true]);

    expect($results->pluck('symbol')->all())->toBe(['ALPHA', 'BETA', 'GAMMA', 'DELTA'])
        ->and($results->pluck('factor_one_rank')->all())->toBe([1, 2.5, 2.5, 4])
        ->and($results->pluck('factor_'.$additionalFactor.'_rank')->all())->toBe([2.5, 2.5, 2.5, 2.5]);

    if ($type === 'backtest') {
        expect($results->pluck('rank')->all())->toBe([1, 2, 3, 4]);
    }
})->with(['screen', 'backtest'])->with(['two', 'three']);

it('resolves equal combined totals by symbol for every factor order', function (string $type, bool $thirdFactor) {
    foreach (['CHARLIE' => 100, 'BRAVO' => 200, 'ALPHA' => 300] as $symbol => $price) {
        createScreenResultRow($symbol, $symbol, '2026-07-31', [
            'close_raw' => $price,
            'beta' => $price / 100,
        ]);
    }

    $factors = [
        ['close_raw', 'desc'],
        ['beta', 'asc'],
        ['volatility_one_year', 'asc'],
    ];
    $orders = $thirdFactor
        ? [[0, 1, 2], [0, 2, 1], [1, 0, 2], [1, 2, 0], [2, 0, 1], [2, 1, 0]]
        : [[0, 1], [1, 0]];

    foreach ($orders as $order) {
        $results = rankingTieResults($type, [
            'sort_by' => $factors[$order[0]][0],
            'sort_direction' => $factors[$order[0]][1],
            'apply_factor_two' => true,
            'factor_two_sort_by' => $factors[$order[1]][0],
            'factor_two_sort_direction' => $factors[$order[1]][1],
            'apply_factor_three' => $thirdFactor,
            'factor_three_sort_by' => $factors[$order[2] ?? 2][0],
            'factor_three_sort_direction' => $factors[$order[2] ?? 2][1],
        ]);

        expect($results->pluck('symbol')->all())->toBe(['ALPHA', 'BRAVO', 'CHARLIE']);
    }
})->with(['screen', 'backtest'])->with([false, true]);

it('compares stored factor precision before applying the tie rule', function (string $type) {
    foreach (['ALPHA' => '1.0000000002', 'CHARLIE' => '1.0000000001', 'BRAVO' => '1.0000000001'] as $symbol => $beta) {
        createScreenResultRow($symbol, $symbol, '2026-07-31', ['beta' => $beta]);
    }

    $results = rankingTieResults($type, [
        'sort_by' => 'beta',
        'sort_direction' => 'asc',
        'apply_factor_two' => true,
    ]);

    expect($results->pluck('symbol')->all())->toBe(['BRAVO', 'CHARLIE', 'ALPHA'])
        ->and($results->pluck('factor_one_rank')->all())->toBe([1.5, 1.5, 3]);
})->with(['screen', 'backtest']);

it('excludes failed filters and missing active factors before assigning average ranks', function (string $type, string $additionalFactor) {
    createScreenResultRow('ALPHA', 'Alpha', '2026-07-31', ['close_raw' => 150]);
    createScreenResultRow('BRAVO', 'Bravo', '2026-07-31', ['close_raw' => 100]);
    createScreenResultRow('FILTERED', 'Filtered', '2026-07-31', ['close_raw' => 300, 'median_volume_one_year' => 0]);
    createScreenResultRow('MISSING', 'Missing', '2026-07-31', [
        'close_raw' => 200,
        $additionalFactor === 'two' ? 'volatility_one_year' : 'beta' => null,
    ]);

    $results = rankingTieResults($type, ['apply_factor_'.$additionalFactor => true]);

    expect($results->pluck('symbol')->all())->toBe(['ALPHA', 'BRAVO'])
        ->and($results->pluck('factor_one_rank')->all())->toBe([1, 2])
        ->and($results->pluck('factor_'.$additionalFactor.'_rank')->all())->toBe([1.5, 1.5]);
})->with(['screen', 'backtest'])->with(['two', 'three']);

it('handles empty and single-stock combined rankings', function (string $type) {
    expect(rankingTieResults($type, ['apply_factor_two' => true]))->toBeEmpty();

    createScreenResultRow('ONLY', 'Only', '2026-07-31');
    $result = rankingTieResults($type, ['apply_factor_two' => true, 'apply_factor_three' => true])->sole();

    expect($result->factor_one_rank)->toBe(1)
        ->and($result->factor_two_rank)->toBe(1)
        ->and($result->factor_three_rank)->toBe(1);
})->with(['screen', 'backtest']);

it('uses symbol order for single-factor ties in either direction', function (string $type, string $direction) {
    foreach (['BRAVO' => 100, 'ALPHA' => 100, 'HIGH' => 110, 'LOW' => 90] as $symbol => $price) {
        createScreenResultRow($symbol, $symbol, '2026-07-31', ['close_raw' => $price]);
    }

    $results = rankingTieResults($type, ['sort_direction' => $direction]);

    expect($results->pluck('symbol')->all())->toBe($direction === 'asc'
        ? ['LOW', 'ALPHA', 'BRAVO', 'HIGH']
        : ['HIGH', 'ALPHA', 'BRAVO', 'LOW']);
})->with(['screen', 'backtest'])->with(['asc', 'desc']);

it('keeps fixed decile counts and uses primary factor ties before combined ranking', function (string $type, string $direction, bool $combined) {
    foreach (['ZULU', 'ECHO', 'DELTA', 'CHARLIE', 'BRAVO', 'ALPHA'] as $symbol) {
        createScreenResultRow($symbol, $symbol, '2026-07-31', [
            'is_nifty_50' => true,
            'volatility_one_year' => $symbol === 'ZULU' ? 0.001 : 0.02,
        ]);
    }

    $results = rankingTieResults($type, [
        'index' => NseIndexEnum::NIFTY_50,
        'sort_direction' => $direction,
        'apply_filters_on' => ApplyFiltersOnOptionEnum::TOP_DECILE,
        'apply_factor_two' => $combined,
    ]);

    expect($results->pluck('symbol')->all())->toBe(['ALPHA', 'BRAVO', 'CHARLIE', 'DELTA', 'ECHO']);
})->with(['screen', 'backtest'])->with(['asc', 'desc'])->with([false, true]);

it('uses final integer ranks for purchases and reciprocal weights after tied factor ranks', function (string $weightage) {
    foreach (['CHARLIE', 'BRAVO', 'ALPHA'] as $symbol) {
        createScreenResultRow($symbol, $symbol, '2026-07-31');
    }

    $backtest = Backtest::factory()->create([
        'start_date' => '2026-07-31',
        'initial_capital' => 90000,
        'max_stocks_to_hold' => 2,
        'weightage' => $weightage,
        'apply_factor_two' => true,
        'cash_return_rate' => 0,
        'brokerage_rate' => 0,
        'stt_rate' => 0,
        'transaction_charges_rate' => 0,
        'sebi_charges_rate' => 0,
        'gst_rate' => 0,
        'stamp_charges_rate' => 0,
    ]);

    app(RunBacktestAction::class)->execute($backtest);

    $buys = $backtest->trades()->where('trade_type', 'buy')->get()->keyBy('symbol');

    expect($buys->keys()->sort()->values()->all())->toBe(['ALPHA', 'BRAVO'])
        ->and((float) $buys['ALPHA']->gross_amount)->toBe($weightage === 'rank_weighted' ? 60000.0 : 45000.0)
        ->and((float) $buys['BRAVO']->gross_amount)->toBe($weightage === 'rank_weighted' ? 30000.0 : 45000.0)
        ->and((float) $backtest->dailySnapshots()->sole()->total_value)->toBe(90000.0);
})->with(['equal_weight', 'rank_weighted']);
