<?php

use App\Enums\CorporateActionTypeEnum;

function createDividendActionRow(string $symbol, string $date, array $attributes = [])
{
    return createCorporateAction($symbol, $date, array_merge([
        'type' => CorporateActionTypeEnum::DIVIDEND,
        'description' => 'Corporate Action: EQ '.$symbol.' DIV RS 2 PER SH',
        'dividend' => '2',
    ], $attributes));
}

it('fails when no date is provided', function () {
    $this->artisan('backtest:calculate-dividend-adjustment-factor')
        ->expectsOutputToContain('Please provide a date')
        ->assertFailed();
});

it('calculates the factor from the previous close', function () {
    createBacktestPriceRow('TCS', '2020-01-24', ['close_adjusted' => 100]);
    createBacktestPriceRow('TCS', '2020-01-27', ['close_adjusted' => 98]);
    $action = createDividendActionRow('TCS', '2020-01-27');

    $this->artisan('backtest:calculate-dividend-adjustment-factor', ['--date' => '2020-01-27'])
        ->assertSuccessful();

    expect((float) $action->fresh()->dividend_adjustment_factor)->toBe(0.98);
});

it('uses the previous trading day across gaps', function () {
    createBacktestPriceRow('TCS', '2020-01-22', ['close_adjusted' => 50]);
    createBacktestPriceRow('TCS', '2020-01-24', ['close_adjusted' => 200]);
    $action = createDividendActionRow('TCS', '2020-01-27');

    $this->artisan('backtest:calculate-dividend-adjustment-factor', ['--date' => '2020-01-27'])
        ->assertSuccessful();

    expect((float) $action->fresh()->dividend_adjustment_factor)->toBe(0.99);
});

it('only lists the symbols on a dry run', function () {
    createBacktestPriceRow('TCS', '2020-01-24', ['close_adjusted' => 100]);
    $action = createDividendActionRow('TCS', '2020-01-27');

    $this->artisan('backtest:calculate-dividend-adjustment-factor', ['--date' => '2020-01-27', '--dry-run' => true])
        ->expectsOutputToContain('Calculating dividend adjustment factor for TCS')
        ->assertSuccessful();

    expect($action->fresh()->dividend_adjustment_factor)->toBeNull();
});

it('skips symbols without an earlier price record', function () {
    $action = createDividendActionRow('TCS', '2020-01-27');

    $this->artisan('backtest:calculate-dividend-adjustment-factor', ['--date' => '2020-01-27'])
        ->expectsOutputToContain('No earlier price record for TCS, skipping')
        ->assertSuccessful();

    expect($action->fresh()->dividend_adjustment_factor)->toBeNull();
});

it('does not recalculate factors that were already applied', function () {
    createBacktestPriceRow('TCS', '2020-01-24', ['close_adjusted' => 100]);
    $action = createDividendActionRow('TCS', '2020-01-27', [
        'dividend_adjustment_factor' => '0.9',
        'dividend_adjustment_applied_at' => now(),
    ]);

    $this->artisan('backtest:calculate-dividend-adjustment-factor', ['--date' => '2020-01-27'])
        ->expectsOutputToContain('Dividend adjustment already applied for TCS, skipping')
        ->assertSuccessful();

    expect($action->fresh()->dividend_adjustment_factor)->toBe('0.9');
});

it('ignores actions on other dates and actions without a dividend', function () {
    createBacktestPriceRow('TCS', '2020-01-24', ['close_adjusted' => 100]);
    $otherDate = createDividendActionRow('TCS', '2020-01-20');
    $bonus = createCorporateAction('WIPRO', '2020-01-27', [
        'type' => CorporateActionTypeEnum::BONUS,
        'description' => 'Corporate Action: EQ WIPRO BONUS 1:2',
    ]);

    $this->artisan('backtest:calculate-dividend-adjustment-factor', ['--date' => '2020-01-27'])
        ->assertSuccessful();

    expect($otherDate->fresh()->dividend_adjustment_factor)->toBeNull()
        ->and($bonus->fresh()->dividend_adjustment_factor)->toBeNull();
});
