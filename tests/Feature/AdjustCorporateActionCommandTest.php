<?php

/**
 * The price columns rewritten by the adjust commands.
 *
 * @return string[]
 */
function priceAdjustableColumns(): array
{
    return [
        'open_adjusted', 'high_adjusted', 'low_adjusted', 'close_adjusted',
        'ma_200', 'ma_100', 'ma_50', 'ma_20',
        'ema_200', 'ema_100', 'ema_50', 'ema_20',
        'high_one_year', 'high_all_time',
    ];
}

it('fails when no date is provided', function () {
    $this->artisan('backtest:adjust-corporate-action')
        ->expectsOutputToContain('Please provide a date')
        ->assertFailed();
});

it('divides all prior history by the price adjustment factor', function () {
    $columns = array_fill_keys(priceAdjustableColumns(), 100);

    $past = createBacktestPriceRow('TCS', '2020-01-24', $columns);
    $exDate = createBacktestPriceRow('TCS', '2020-01-27', $columns);
    $later = createBacktestPriceRow('TCS', '2020-01-28', $columns);
    $otherSymbol = createBacktestPriceRow('WIPRO', '2020-01-24', $columns);

    $action = createCorporateAction('TCS', '2020-01-27', [
        'price_adjustment_factor' => '0.5',
    ]);

    $this->artisan('backtest:adjust-corporate-action', ['--date' => '2020-01-27'])
        ->expectsOutputToContain('Adjusting corporate action for TCS')
        ->assertSuccessful();

    foreach (priceAdjustableColumns() as $column) {
        expect((float) $past->fresh()->{$column})->toBe(200.0)
            ->and((float) $exDate->fresh()->{$column})->toBe(100.0)
            ->and((float) $later->fresh()->{$column})->toBe(100.0)
            ->and((float) $otherSymbol->fresh()->{$column})->toBe(100.0);
    }

    expect($action->fresh()->price_adjustment_applied_at)->not->toBeNull();
});

it('leaves null analytics columns null', function () {
    $past = createBacktestPriceRow('TCS', '2020-01-24', ['close_adjusted' => 100]);

    createCorporateAction('TCS', '2020-01-27', [
        'price_adjustment_factor' => '0.5',
    ]);

    $this->artisan('backtest:adjust-corporate-action', ['--date' => '2020-01-27'])->assertSuccessful();

    $fresh = $past->fresh();

    expect((float) $fresh->close_adjusted)->toBe(200.0)
        ->and($fresh->ma_200)->toBeNull()
        ->and($fresh->high_all_time)->toBeNull();
});

it('does not adjust the same action twice', function () {
    $past = createBacktestPriceRow('TCS', '2020-01-24', array_fill_keys(priceAdjustableColumns(), 100));

    createCorporateAction('TCS', '2020-01-27', [
        'price_adjustment_factor' => '0.5',
    ]);

    $this->artisan('backtest:adjust-corporate-action', ['--date' => '2020-01-27'])->assertSuccessful();
    $this->artisan('backtest:adjust-corporate-action', ['--date' => '2020-01-27'])->assertSuccessful();

    expect((float) $past->fresh()->close_adjusted)->toBe(200.0);
});

it('changes nothing on a dry run', function () {
    $past = createBacktestPriceRow('TCS', '2020-01-24', array_fill_keys(priceAdjustableColumns(), 100));

    $action = createCorporateAction('TCS', '2020-01-27', [
        'price_adjustment_factor' => '0.5',
    ]);

    $this->artisan('backtest:adjust-corporate-action', ['--date' => '2020-01-27', '--dry-run' => true])
        ->expectsOutputToContain('Adjusting corporate action for TCS')
        ->assertSuccessful();

    expect((float) $past->fresh()->close_adjusted)->toBe(100.0)
        ->and($action->fresh()->price_adjustment_applied_at)->toBeNull();
});

it('ignores actions without a price adjustment factor', function () {
    $past = createBacktestPriceRow('TCS', '2020-01-24', array_fill_keys(priceAdjustableColumns(), 100));

    createCorporateAction('TCS', '2020-01-27', [
        'dividend' => '2',
        'dividend_adjustment_factor' => '0.5',
    ]);

    $this->artisan('backtest:adjust-corporate-action', ['--date' => '2020-01-27'])->assertSuccessful();

    expect((float) $past->fresh()->close_adjusted)->toBe(100.0);
});
