<?php

/**
 * The price columns rewritten by the adjust commands.
 *
 * @return string[]
 */
function dividendAdjustableColumns(): array
{
    return [
        'open_adjusted', 'high_adjusted', 'low_adjusted', 'close_adjusted',
        'ma_200', 'ma_100', 'ma_50', 'ma_20',
        'ema_200', 'ema_100', 'ema_50', 'ema_20',
        'high_one_year', 'high_all_time',
    ];
}

it('fails when no date is provided', function () {
    $this->artisan('backtest:adjust-dividends')
        ->expectsOutputToContain('Please provide a date')
        ->assertFailed();
});

it('multiplies all prior history by the dividend adjustment factor', function () {
    $columns = array_fill_keys(dividendAdjustableColumns(), 100);

    $past = createBacktestPriceRow('TCS', '2020-01-24', $columns);
    $exDate = createBacktestPriceRow('TCS', '2020-01-27', $columns);
    $later = createBacktestPriceRow('TCS', '2020-01-28', $columns);
    $otherSymbol = createBacktestPriceRow('WIPRO', '2020-01-24', $columns);

    $action = createCorporateAction('TCS', '2020-01-27', [
        'dividend' => '2',
        'dividend_adjustment_factor' => '0.5',
    ]);

    $this->artisan('backtest:adjust-dividends', ['--date' => '2020-01-27'])
        ->expectsOutputToContain('Adjusting dividend action for TCS')
        ->assertSuccessful();

    foreach (dividendAdjustableColumns() as $column) {
        expect((float) $past->fresh()->{$column})->toBe(50.0)
            ->and((float) $exDate->fresh()->{$column})->toBe(100.0)
            ->and((float) $later->fresh()->{$column})->toBe(100.0)
            ->and((float) $otherSymbol->fresh()->{$column})->toBe(100.0);
    }

    expect($action->fresh()->dividend_adjustment_applied_at)->not->toBeNull();
});

it('leaves null analytics columns null', function () {
    $past = createBacktestPriceRow('TCS', '2020-01-24', ['close_adjusted' => 100]);

    createCorporateAction('TCS', '2020-01-27', [
        'dividend' => '2',
        'dividend_adjustment_factor' => '0.5',
    ]);

    $this->artisan('backtest:adjust-dividends', ['--date' => '2020-01-27'])->assertSuccessful();

    $fresh = $past->fresh();

    expect((float) $fresh->close_adjusted)->toBe(50.0)
        ->and($fresh->ma_200)->toBeNull()
        ->and($fresh->high_all_time)->toBeNull();
});

it('does not adjust the same action twice', function () {
    $past = createBacktestPriceRow('TCS', '2020-01-24', array_fill_keys(dividendAdjustableColumns(), 100));

    createCorporateAction('TCS', '2020-01-27', [
        'dividend' => '2',
        'dividend_adjustment_factor' => '0.5',
    ]);

    $this->artisan('backtest:adjust-dividends', ['--date' => '2020-01-27'])->assertSuccessful();
    $this->artisan('backtest:adjust-dividends', ['--date' => '2020-01-27'])->assertSuccessful();

    expect((float) $past->fresh()->close_adjusted)->toBe(50.0);
});

it('changes nothing on a dry run', function () {
    $past = createBacktestPriceRow('TCS', '2020-01-24', array_fill_keys(dividendAdjustableColumns(), 100));

    $action = createCorporateAction('TCS', '2020-01-27', [
        'dividend' => '2',
        'dividend_adjustment_factor' => '0.5',
    ]);

    $this->artisan('backtest:adjust-dividends', ['--date' => '2020-01-27', '--dry-run' => true])
        ->expectsOutputToContain('Adjusting dividend action for TCS')
        ->assertSuccessful();

    expect((float) $past->fresh()->close_adjusted)->toBe(100.0)
        ->and($action->fresh()->dividend_adjustment_applied_at)->toBeNull();
});

it('ignores actions without a dividend adjustment factor', function () {
    $past = createBacktestPriceRow('TCS', '2020-01-24', array_fill_keys(dividendAdjustableColumns(), 100));

    createCorporateAction('TCS', '2020-01-27', [
        'price_adjustment_factor' => '0.5',
    ]);

    $this->artisan('backtest:adjust-dividends', ['--date' => '2020-01-27'])->assertSuccessful();

    expect((float) $past->fresh()->close_adjusted)->toBe(100.0);
});
