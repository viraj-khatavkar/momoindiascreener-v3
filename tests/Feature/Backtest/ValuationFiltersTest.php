<?php

use App\Actions\Backtest\ApplyBacktestFiltersAction;
use App\Actions\Backtest\RunBacktestAction;
use App\Actions\CreateDefaultBacktestAction;
use App\Models\Backtest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

it('applies inclusive valuation ranges and excludes missing data only while enabled', function (string $flag, string $column, string $prefix) {
    $backtest = Backtest::factory()->create([
        'start_date' => '2024-03-01', $flag => true, $prefix.'_from' => 10, $prefix.'_to' => 20,
    ]);
    foreach (['LOW' => 9, 'MIN' => 10, 'IN' => 15, 'MAX' => 20, 'HIGH' => 21, 'MISSING' => null] as $symbol => $value) {
        createScreenResultRow($symbol, $symbol, '2024-03-01', [$column => $value]);
    }
    $previous = Model::preventsAccessingMissingAttributes();
    Model::preventAccessingMissingAttributes();
    try {
        $action = app(ApplyBacktestFiltersAction::class);
        expect($action->execute($backtest, '2024-03-01')->pluck('symbol')->all())->toBe(['IN', 'MAX', 'MIN']);
        $backtest->$flag = false;
        expect($action->execute($backtest, '2024-03-01'))->toHaveCount(6);
    } finally {
        Model::preventAccessingMissingAttributes($previous);
    }
})->with([
    ['apply_marketcap', 'marketcap', 'marketcap'], ['apply_pe', 'price_to_earnings', 'price_to_earnings'],
]);

it('validates active valuation filter dates and bounds without changing saved settings', function (string $flag, string $prefix) {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->for($user)->create();
    $payload = array_replace($backtest->toArray(), [$flag => true, $prefix.'_from' => 10, $prefix.'_to' => 20]);
    $this->actingAs($user)->put('/backtests/'.$backtest->id, $payload)->assertSessionHasErrors('start_date');
    expect($backtest->fresh()->$flag)->toBeFalse();
    $this->put('/backtests/'.$backtest->id, array_replace($payload, ['start_date' => '2024-03-01', $prefix.'_to' => 9]))
        ->assertSessionHasErrors($prefix.'_to');
    $this->put('/backtests/'.$backtest->id, array_replace($payload, ['start_date' => '2024-03-01']))->assertSessionHasNoErrors();
    expect($backtest->fresh()->$flag)->toBeTrue();
    $backtest->forceFill([$flag => true, 'start_date' => '2024-02-29']);
    expect(fn () => app(RunBacktestAction::class)->validateDataAvailability($backtest))->toThrow(ValidationException::class, '2024-03-01');
    $this->put('/backtests/'.$backtest->id, array_replace($payload, ['index' => 'etf', 'start_date' => '2024-03-01']))
        ->assertSessionHasErrors($flag);
})->with([['apply_marketcap', 'marketcap'], ['apply_pe', 'price_to_earnings']]);

it('uses both ranges for buys and decision-date exits while respecting DMA protection', function (bool $protect) {
    foreach (['2024-03-04', '2024-03-05', '2024-03-11', '2024-03-12'] as $date) {
        $later = $date >= '2024-03-11';
        createScreenResultRow('HELD', 'Held', $date, [
            'marketcap' => $later ? 9000 : 5000, 'price_to_earnings' => $later ? 50 : 15,
            'sharpe_return_one_year' => 30, 'ma_200' => 90,
        ]);
        createScreenResultRow('NEXT', 'Next', $date, ['marketcap' => 5000, 'price_to_earnings' => 15]);
        createScreenResultRow('FAIL', 'Fail', $date, ['marketcap' => 5000, 'price_to_earnings' => 99, 'sharpe_return_one_year' => 100]);
    }
    $backtest = Backtest::factory()->create([
        'start_date' => '2024-03-04', 'apply_marketcap' => true, 'marketcap_from' => 4000, 'marketcap_to' => 6000,
        'apply_pe' => true, 'price_to_earnings_from' => 10, 'price_to_earnings_to' => 20,
        'max_stocks_to_hold' => 1, 'rebalance_frequency' => 'weekly', 'rebalance_day' => 1,
        'execute_next_trading_day' => true, 'apply_hold_above_dma' => $protect, 'skip_circuit_trades' => false,
    ]);
    app(RunBacktestAction::class)->execute($backtest);
    expect($backtest->trades()->where('symbol', 'FAIL')->count())->toBe(0)
        ->and($backtest->trades()->where('trade_type', 'buy')->pluck('symbol')->unique()->values()->all())
        ->toBe($protect ? ['HELD'] : ['HELD', 'NEXT']);
    if (! $protect) {
        $sale = $backtest->trades()->where('trade_type', 'sell')->sole();
        expect($sale->date->toDateString())->toBe('2024-03-12')
            ->and($sale->reason)->toContain('Market cap out of range', 'P/E out of range');
    }
})->with([false, true]);

it('does not activate valuation ranges for default backtests', function () {
    $backtest = app(CreateDefaultBacktestAction::class)->execute('Defaults', User::factory()->create());
    expect($backtest->apply_marketcap)->toBeFalse()->and($backtest->apply_pe)->toBeFalse();
});

it('retains an active market cap date limit when an older client omits its switch', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->for($user)->create(['apply_marketcap' => true, 'start_date' => '2024-03-01']);
    $payload = $backtest->toArray();
    unset($payload['apply_marketcap']);
    $this->actingAs($user)->put('/backtests/'.$backtest->id, array_replace($payload, ['start_date' => '2024-02-29']))
        ->assertSessionHasErrors('start_date');
    expect($backtest->fresh()->apply_marketcap)->toBeTrue()
        ->and($backtest->fresh()->start_date->toDateString())->toBe('2024-03-01');
});
