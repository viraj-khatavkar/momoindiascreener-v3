<?php

use App\Actions\Backtest\ApplyBacktestFiltersAction;
use App\Actions\Backtest\RunBacktestAction;
use App\Actions\CreateDefaultBacktestAction;
use App\Enums\BacktestWeightageEnum;
use App\Models\Backtest;
use App\Models\BacktestNseInstrument;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->previousMissingAttributes = Model::preventsAccessingMissingAttributes();
    Model::preventAccessingMissingAttributes();
});
afterEach(function () {
    Model::preventAccessingMissingAttributes($this->previousMissingAttributes);
});

function seedUniqueBacktestEtf(string $symbol, ?string $index, string $date, array $attributes = []): void
{
    BacktestNseInstrument::query()->updateOrCreate(['symbol' => $symbol], ['etf_index' => $index]);
    createScreenResultRow($symbol, $symbol, $date, array_replace([
        'is_etf' => true, 'marketcap' => null, 'price_to_earnings' => null,
    ], $attributes));
}

it('selects liquid ETFs after eligibility and before combined ranking', function (bool $combined) {
    $backtest = Backtest::factory()->create([
        'index' => 'etf', 'unique_etf_per_index' => true,
        'apply_factor_two' => $combined, 'factor_two_sort_by' => 'beta', 'factor_two_sort_direction' => 'asc',
    ]);
    seedUniqueBacktestEtf('LOW', 'nifty-50', '2024-01-08', ['sharpe_return_one_year' => 100, 'median_volume_one_year' => 20000000]);
    seedUniqueBacktestEtf('LIQUID', 'nifty-50', '2024-01-08', ['sharpe_return_one_year' => 30, 'median_volume_one_year' => 30000000]);
    seedUniqueBacktestEtf('INELIGIBLE', 'nifty-50', '2024-01-08', ['absolute_return_one_year' => -50, 'median_volume_one_year' => 90000000]);
    seedUniqueBacktestEtf('BANK', 'nifty-bank', '2024-01-08', ['sharpe_return_one_year' => 10]);
    $results = app(ApplyBacktestFiltersAction::class)->execute($backtest, '2024-01-08');
    expect($results->pluck('symbol')->all())->toBe(['LIQUID', 'BANK'])
        ->and($results->pluck('rank')->all())->toBe([1, 2]);
})->with([false, true]);

it('breaks volume ties by symbol and keeps unmapped ETFs separate', function () {
    $backtest = Backtest::factory()->create(['index' => 'etf', 'unique_etf_per_index' => true]);
    foreach (['B' => 'gold', 'A' => 'gold', 'UNKNOWN' => null, 'EMPTY' => ''] as $symbol => $index) {
        seedUniqueBacktestEtf($symbol, $index, '2024-01-08');
    }
    createScreenResultRow('NO-MASTER', 'No master', '2024-01-08', ['is_etf' => true]);
    expect(app(ApplyBacktestFiltersAction::class)->execute($backtest, '2024-01-08')->pluck('symbol')->all())
        ->toBe(['A', 'EMPTY', 'NO-MASTER', 'UNKNOWN']);
});

it('leaves stock universes and disabled ETF selections unchanged', function (string $index, bool $enabled) {
    $backtest = Backtest::factory()->create(['index' => $index, 'unique_etf_per_index' => $enabled]);
    seedUniqueBacktestEtf('A', 'gold', '2024-01-08');
    seedUniqueBacktestEtf('B', 'gold', '2024-01-08');
    expect(app(ApplyBacktestFiltersAction::class)->execute($backtest, '2024-01-08'))->toHaveCount(2);
})->with([['etf', false], ['nifty_allcap', true]]);

it('uses decision-date ETF volume and fills the next distinct index around a retained holding', function (bool $nextDay, string $retention) {
    $dates = ['2024-01-08', '2024-01-09', '2024-01-10', '2024-01-11', '2024-01-12', '2024-01-15', '2024-01-16'];
    foreach ($dates as $date) {
        $later = $date >= '2024-01-15';
        if (! ($retention === 'missing quote' && $later)) {
            seedUniqueBacktestEtf('OLD', 'nifty-50', $date, [
                'median_volume_one_year' => $later ? 20000000 : 50000000, 'sharpe_return_one_year' => 30,
                'ma_200' => 90,
            ]);
        }
        seedUniqueBacktestEtf('NEW', 'nifty-50', $date, ['median_volume_one_year' => $later ? 60000000 : 20000000, 'sharpe_return_one_year' => 40]);
        seedUniqueBacktestEtf('OTHER', 'nifty-bank', $date, ['absolute_return_one_year' => $later ? 30 : -50]);
    }
    $backtest = Backtest::factory()->create([
        'index' => 'etf', 'unique_etf_per_index' => true, 'start_date' => '2024-01-08',
        'rebalance_frequency' => 'weekly', 'rebalance_day' => 1, 'max_stocks_to_hold' => 2,
        'apply_hold_above_dma' => $retention === 'DMA', 'execute_next_trading_day' => $nextDay,
        'weightage' => BacktestWeightageEnum::EqualWeightRebalanced, 'skip_circuit_trades' => false,
        'cash_call' => 'cash_call_if_not_enough_stocks',
    ]);
    app(RunBacktestAction::class)->execute($backtest);
    expect($backtest->trades()->where('trade_type', 'buy')->pluck('symbol')->unique()->sort()->values()->all())
        ->toBe(['OLD', 'OTHER'])
        ->and($backtest->trades()->where('symbol', 'NEW')->count())->toBe(0);
})->with([false, true])->with(['DMA', 'missing quote']);

it('saves and copies the ETF option while new backtests default it off', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = app(CreateDefaultBacktestAction::class)->execute('ETF backtest', $user);
    expect($backtest->unique_etf_per_index)->toBeFalse();
    $backtest->refresh();
    $this->actingAs($user)->put('/backtests/'.$backtest->id, array_replace($backtest->toArray(), [
        'index' => 'etf', 'unique_etf_per_index' => true,
    ]))->assertSessionHasNoErrors()->assertRedirect('/backtests/'.$backtest->id.'?tab=settings');
    expect($backtest->fresh()->unique_etf_per_index)->toBeTrue();
    $this->post(route('backtests.duplicate', $backtest))->assertRedirect();
    expect($user->backtests()->whereKeyNot($backtest->id)->sole()->unique_etf_per_index)->toBeTrue();
});

it('does not replace a stopped holding with another ETF for an index already held', function () {
    foreach (['2024-01-08', '2024-01-09', '2024-01-10'] as $date) {
        $initial = $date === '2024-01-08';
        seedUniqueBacktestEtf('OLD', 'nifty-50', $date, ['sharpe_return_one_year' => 50, 'median_volume_one_year' => 50000000]);
        seedUniqueBacktestEtf('NEW', 'nifty-50', $date, ['sharpe_return_one_year' => 60, 'median_volume_one_year' => $initial ? 20000000 : 60000000]);
        seedUniqueBacktestEtf('EXIT', 'nifty-bank', $date, [
            'sharpe_return_one_year' => 40, 'close_adjusted' => $initial ? 100 : ($date === '2024-01-09' ? 80 : 70),
        ]);
        seedUniqueBacktestEtf('OTHER', 'nifty-next-50', $date, ['sharpe_return_one_year' => 10]);
    }
    $backtest = Backtest::factory()->create([
        'index' => 'etf', 'unique_etf_per_index' => true, 'start_date' => '2024-01-08',
        'max_stocks_to_hold' => 2, 'skip_circuit_trades' => false, 'rebalance_frequency' => 'weekly', 'rebalance_day' => 1,
        'apply_stop_loss' => true, 'stop_loss_percentage' => 10, 'stop_loss_proceeds' => 'replace_immediately',
    ]);
    app(RunBacktestAction::class)->execute($backtest);
    expect($backtest->trades()->whereDate('date', '2024-01-10')->where('trade_type', 'buy')->pluck('symbol')->all())->toBe(['OTHER'])
        ->and($backtest->trades()->where('symbol', 'NEW')->count())->toBe(0);
});

it('chooses the ETF using decision-date volume when execution is delayed', function () {
    seedUniqueBacktestEtf('A', 'nifty-50', '2024-01-08', ['median_volume_one_year' => 50000000]);
    seedUniqueBacktestEtf('B', 'nifty-50', '2024-01-08', ['median_volume_one_year' => 20000000]);
    seedUniqueBacktestEtf('A', 'nifty-50', '2024-01-09', ['median_volume_one_year' => 20000000, 'close_adjusted' => 110]);
    seedUniqueBacktestEtf('B', 'nifty-50', '2024-01-09', ['median_volume_one_year' => 80000000]);
    $backtest = Backtest::factory()->create([
        'index' => 'etf', 'unique_etf_per_index' => true, 'start_date' => '2024-01-08',
        'max_stocks_to_hold' => 1, 'skip_circuit_trades' => false, 'execute_next_trading_day' => true,
    ]);
    app(RunBacktestAction::class)->execute($backtest);
    $trade = $backtest->trades()->where('trade_type', 'buy')->sole();
    expect($trade->symbol)->toBe('A')->and($trade->date->toDateString())->toBe('2024-01-09');
});
