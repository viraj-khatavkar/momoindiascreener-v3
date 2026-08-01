<?php

use App\Models\BacktestNseCorporateAction;
use App\Models\BacktestNseInstrumentPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Browser');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', fn () => $this->toBe(1));

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function loginAs($email, $password = 'password')
{
    return visit('/login')
        ->fill('email', $email)
        ->fill('password', $password)
        ->press('Sign In')
        ->assertPathIs('/');
}

/**
 * Create a backtest_nse_instrument_prices row with the required NOT NULL price
 * columns zeroed. Pass $attributes to set flags (is_nifty_*) or analytics columns.
 *
 * @param  array<string, mixed>  $attributes
 */
function createBacktestPriceRow(string $symbol, string $date, array $attributes = []): BacktestNseInstrumentPrice
{
    return BacktestNseInstrumentPrice::create(array_merge([
        'date' => $date,
        'symbol' => $symbol,
        'open_adjusted' => 0,
        'high_adjusted' => 0,
        'low_adjusted' => 0,
        'close_adjusted' => 0,
        'volume_adjusted' => 0,
        'volume_shares_adjusted' => 0,
        'open_raw' => 0,
        'high_raw' => 0,
        'low_raw' => 0,
        'close_raw' => 0,
        'volume_raw' => 0,
        'volume_shares_raw' => 0,
        't_percent' => 0,
        't_percent_raw' => 0,
    ], $attributes));
}

/**
 * Create a price row that passes every default ScreenFactory filter (allcap
 * membership, volume, minimum return, circuits, positive days, away from high).
 * Override $attributes to change factor values or make the row fail a filter.
 *
 * @param  array<string, mixed>  $attributes
 */
function createScreenResultRow(string $symbol, string $name, string $date, array $attributes = []): BacktestNseInstrumentPrice
{
    return createBacktestPriceRow($symbol, $date, array_merge([
        'name' => $name,
        'series' => 'EQ',
        'is_nifty_allcap' => true,
        'close_raw' => 100,
        'close_adjusted' => 100,
        'absolute_return_one_year' => 50,
        'sharpe_return_one_year' => 10,
        'median_volume_one_year' => 20000000,
        'away_from_high_one_year' => -5,
        'away_from_high_all_time' => -10,
        'positive_days_percent_one_year' => 55,
        'positive_days_percent_nine_months' => 55,
        'positive_days_percent_six_months' => 55,
        'positive_days_percent_three_months' => 55,
        'positive_days_percent_one_months' => 55,
        'circuits_one_year' => 0,
        'circuits_nine_months' => 0,
        'circuits_six_months' => 0,
        'circuits_three_months' => 0,
        'circuits_one_months' => 0,
        'volatility_one_year' => 0.02,
        'beta' => 1.1,
        'marketcap' => 5000,
        'ma_200' => 90,
    ], $attributes));
}

/**
 * Create a backtest_nse_corporate_actions row. Pass $attributes to set the type,
 * description, dividend, adjustment factors or applied timestamps.
 *
 * @param  array<string, mixed>  $attributes
 */
function createCorporateAction(string $symbol, string $date, array $attributes = []): BacktestNseCorporateAction
{
    return BacktestNseCorporateAction::create(array_merge([
        'date' => $date,
        'symbol' => $symbol,
        'series' => 'EQ',
    ], $attributes));
}

function isIndexFlagged(string $symbol, string $date, string $column): bool
{
    return (bool) BacktestNseInstrumentPrice::query()
        ->where('symbol', $symbol)
        ->where('date', $date)
        ->value($column);
}
