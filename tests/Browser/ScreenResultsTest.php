<?php

use App\Enums\NseIndexEnum;
use App\Models\BacktestNseInstrument;
use App\Models\Screen;
use App\Models\User;

it('renders the results grid with formatted values, quick search and custom sort', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create([
        'user_id' => $user->id,
        'minimum_return_one_year' => -100,
    ]);

    createScreenResultRow('ALPHA', 'Alpha Industries', '2026-07-31', [
        'sharpe_return_one_year' => 99.9,
        'absolute_return_one_year' => 45.2,
    ]);
    createScreenResultRow('BETA', 'Beta Corp', '2026-07-31', [
        'sharpe_return_one_year' => 10.1,
        'absolute_return_one_year' => -12.34,
    ]);

    $this->actingAs($user);

    visit('/screens/'.$screen->id.'/edit')
        ->assertNoJavaScriptErrors()
        ->assertSee('ALPHA')
        ->assertSee('Beta Corp')
        ->assertSee('+45.20')
        ->assertSee('-12.34')
        ->click('1Yr Return %')
        ->assertSee('Reset custom sort')
        ->click('Reset custom sort')
        ->assertDontSee('Reset custom sort')
        ->fill('results_search', 'alpha')
        ->assertSee('Alpha Industries')
        ->assertDontSee('Beta Corp')
        ->fill('results_search', 'ZZZ')
        ->assertSee('No stocks match')
        ->click('Clear search')
        ->assertSee('Beta Corp');
});

it('shows an empty state when no stocks match the filters', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    createScreenResultRow('LOWVOL', 'Low Volume Stock', '2026-07-31', ['median_volume_one_year' => 100]);

    $this->actingAs($user);

    visit('/screens/'.$screen->id.'/edit')
        ->assertNoJavaScriptErrors()
        ->assertSee('No stocks matched your filters');
});

it('shows per-factor columns with ranks for multi-factor screens', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create([
        'user_id' => $user->id,
        'apply_factor_two' => true,
    ]);

    createScreenResultRow('ALPHA', 'Alpha Industries', '2026-07-31', [
        'sharpe_return_one_year' => 99,
        'volatility_one_year' => 0.9,
    ]);
    createScreenResultRow('BETA', 'Beta Corp', '2026-07-31', [
        'sharpe_return_one_year' => 50,
        'volatility_one_year' => 0.01,
    ]);

    $this->actingAs($user);

    visit('/screens/'.$screen->id.'/edit')
        ->assertNoJavaScriptErrors()
        ->assertSee('combined rank of')
        ->assertSee('VOLATILITY 1 YEAR')
        ->assertSee('#1');
});

it('shows unavailable valuation warnings and the saved historical date', function () {
    $screen = Screen::factory()->create([
        'apply_historical_date' => true,
        'historical_date' => '2024-02-29',
        'apply_pe' => true,
    ]);
    createScreenResultRow('HISTORICAL', 'Historical Stock', '2024-02-29', ['marketcap' => null, 'price_to_earnings' => null]);

    $this->actingAs($screen->user);

    visit('/screens/'.$screen->id.'/edit')
        ->assertNoJavaScriptErrors()
        ->assertSee('The market cap filter was not applied.')
        ->assertSee('The P/E filter was not applied.')
        ->assertSee('HISTORICAL')
        ->click('Show More Filters')
        ->click('Historical Ranks')
        ->assertValue('historical_date', '2024-02-29');
});

it('shows ETF results and warnings for unsupported valuation filters', function () {
    $screen = Screen::factory()->create([
        'index' => NseIndexEnum::NIFTY_ETF,
        'apply_pe' => true,
    ]);
    createScreenResultRow('STOCK', 'Stock', '2026-07-31', ['price_to_earnings' => 15]);
    createScreenResultRow('TESTETF', 'Test ETF', '2026-07-31', [
        'is_etf' => true,
        'is_nifty_allcap' => false,
        'marketcap' => null,
        'price_to_earnings' => null,
    ]);

    $this->actingAs($screen->user);

    visit('/screens/'.$screen->id.'/edit')
        ->assertNoJavaScriptErrors()
        ->assertSee('Market cap data is not available for ETFs. The market cap filter was not applied.')
        ->assertSee('P/E data is not available for ETFs. The P/E filter was not applied.')
        ->assertSee('TESTETF')
        ->assertDontSee('No stocks matched your filters');
});

it('saves the one ETF per underlying index option and can turn it off again', function () {
    $screen = Screen::factory()->create();
    createScreenResultRow('STOCK', 'Stock', '2026-07-31');

    foreach (['LOWVOLUME' => 20000000, 'HIGHVOLUME' => 50000000] as $symbol => $volume) {
        BacktestNseInstrument::query()->create(['symbol' => $symbol, 'etf_index' => 'nifty-50']);
        createScreenResultRow($symbol, $symbol, '2026-07-31', [
            'is_etf' => true,
            'marketcap' => null,
            'median_volume_one_year' => $volume,
        ]);
    }

    $this->actingAs($screen->user);

    visit('/screens/'.$screen->id.'/edit')
        ->assertNoJavaScriptErrors()
        ->assertDontSee('One ETF per underlying index')
        ->select('index', 'etf')
        ->assertSee('One ETF per underlying index')
        ->click('One ETF per underlying index')
        ->press('Update & Apply Filters')
        ->assertSee('HIGHVOLUME')
        ->assertDontSee('LOWVOLUME');

    expect($screen->fresh()->unique_etf_per_index)->toBeTrue();

    visit('/screens/'.$screen->id.'/edit')
        ->assertSee('HIGHVOLUME')
        ->assertDontSee('LOWVOLUME')
        ->click('One ETF per underlying index')
        ->press('Update & Apply Filters')
        ->assertSee('HIGHVOLUME')
        ->assertSee('LOWVOLUME')
        ->assertNoJavaScriptErrors();

    expect($screen->fresh()->unique_etf_per_index)->toBeFalse();
});
