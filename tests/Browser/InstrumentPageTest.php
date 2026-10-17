<?php

use App\Enums\CorporateActionTypeEnum;

beforeEach(function () {
    createScreenResultRow('ALPHA', 'Alpha Industries', '2026-07-29', [
        'marketcap' => null, 'price_to_earnings' => null, 'absolute_return_one_year' => null, 'rsi_one_year' => null,
    ]);
    createScreenResultRow('ALPHA', 'Alpha Industries', '2026-07-30', [
        'close_adjusted' => 100, 'marketcap' => 5000, 'price_to_earnings' => 10, 'absolute_return_one_year' => -10, 'rsi_one_year' => 0,
    ]);
    createScreenResultRow('ALPHA', 'Alpha Industries', '2026-07-31', [
        'close_adjusted' => 105,
        'marketcap' => 9000,
        'price_to_earnings' => 30,
        'absolute_return_one_year' => 0,
        'rsi_one_year' => 60,
        'absolute_return_one_months' => -6.5,
        'absolute_return_three_months' => 0,
        'absolute_return_six_months' => 12.34,
        'return_twelve_minus_one_months' => 18.34,
        'away_from_high_one_year' => -7.84,
        'ma_20' => 110,
        'ma_50' => 100,
        'ema_20' => 110,
        'ema_50' => 100,
    ]);
    createScreenResultRow('BETA', 'Beta Industries', '2026-07-31', [
        'close_adjusted' => 210,
        'absolute_return_one_year' => -12.34,
    ]);
    createScreenResultRow('BETA', 'Beta Industries', '2026-07-30', ['close_adjusted' => 200]);

    createCorporateAction('ALPHA', '2026-07-20', [
        'type' => CorporateActionTypeEnum::DIVIDEND,
        'description' => 'Interim dividend of Rs 5 per share',
        'dividend' => '5',
        'dividend_adjustment_factor' => '0.95',
    ]);
    createCorporateAction('ALPHA', '2025-07-20', [
        'type' => CorporateActionTypeEnum::DIVIDEND,
        'description' => 'Final dividend of Rs 3 per share',
        'dividend' => '3',
        'dividend_adjustment_factor' => '0.97',
    ]);
    createCorporateAction('ALPHA', '2026-06-01', [
        'type' => CorporateActionTypeEnum::BONUS,
        'description' => 'Bonus issue 1:2',
        'price_adjustment_factor' => '0.666666',
    ]);
});

it('keeps instrument data readable with tabs for company events', function (int $width, int $height) {
    $page = visit('/instruments/ALPHA')
        ->resize($width, $height)
        ->assertSee('Alpha Industries')
        ->assertSee('Data as of 31 Jul 2026')
        ->assertSeeIn('#returns', '-6.50%')
        ->assertSeeIn('#returns dd.text-gray-600 >> nth=0', '0.00%')
        ->assertSeeIn('#returns', '+12.34%')
        ->assertSeeIn('#returns', '+18.34%')
        ->assertSeeIn('#overview', '7.84% below')
        ->assertDontSee('%%')
        ->assertVisible('#price canvas >> nth=0')
        ->press('button[aria-label="Show all available history"]')
        ->assertAriaAttribute('button[aria-label="Show all available history"]', 'pressed', 'true')
        ->press('button[aria-label="Chart indicators"]')
        ->press('button:has-text("SMA 50")')
        ->assertVisible('button[aria-label="Remove SMA 50"]')
        ->press('button[aria-label="Chart indicators"]')
        ->press('button[aria-label="Remove SMA 50"]')
        ->click('a[href="#events"]')
        ->assertFragmentIs('events')
        ->assertVisible('#events')
        ->assertSee('Interim dividend of Rs 5 per share')
        ->assertSee('Final dividend of Rs 3 per share')
        ->assertDontSee('Bonus issue 1:2')
        ->click('[role="tab"]:has-text("Rights, bonus & splits")')
        ->assertAriaAttribute('[role="tab"]:has-text("Rights, bonus & splits")', 'selected', 'true')
        ->assertSee('Bonus issue 1:2')
        ->assertDontSee('Interim dividend of Rs 5 per share')
        ->keys('[role="tab"]:has-text("Rights, bonus & splits")', 'ArrowLeft')
        ->assertAriaAttribute('[role="tab"]:has-text("Dividends")', 'selected', 'true')
        ->assertSee('Interim dividend of Rs 5 per share')
        ->assertNoJavaScriptErrors();

    expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
    expect($page->script('document.querySelectorAll("#events [role=tab]").length'))->toBe(2);
    expect($page->script('document.querySelector("#events").getBoundingClientRect().top >= 64'))->toBeTrue();
    expect($page->script('document.querySelector("#returns .text-rose-700")?.textContent.trim()'))->toBe('-6.50%');
})->with([
    'desktop' => [1440, 1000],
    'mobile' => [390, 844],
]);

it('updates the instrument and its deferred data after a search', function () {
    $page = visit('/instruments/ALPHA')
        ->assertVisible('#price canvas >> nth=0')
        ->click('[role="tab"]:has-text("Rights, bonus & splits")')
        ->assertSee('Bonus issue 1:2')
        ->fill('input[placeholder="Search instruments..."]', 'BETA')
        ->click('BETA — Beta Industries')
        ->assertPathIs('/instruments/BETA')
        ->assertSee('Beta Industries')
        ->assertSee('₹210.00')
        ->assertSeeIn('#returns', '-12.34%')
        ->assertVisible('#price canvas >> nth=0')
        ->assertSee('No dividends are recorded for BETA.')
        ->click('[role="tab"]:has-text("Rights, bonus & splits")')
        ->assertSee('No rights, bonus or split events are recorded for BETA.')
        ->assertDontSee('Interim dividend of Rs 5 per share')
        ->assertNoJavaScriptErrors();

    expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
});

it('shows one history chart at a time with a shared period and accurate medians', function (int $width, int $height) {
    $page = visit('/instruments/ALPHA')->resize($width, $height)
        ->assertVisible('#price canvas >> nth=0')
        ->press('button[aria-label="Show all available history"]')
        ->click('#price [role="tab"]:has-text("1Y return")')
        ->assertVisible('[data-metric="absolute_return_one_year"] canvas >> nth=0')
        ->assertSeeIn('[data-testid="metric-value"]', '0.00%')
        ->assertSeeIn('[data-testid="metric-median"]', '-5.00%')
        ->assertAriaAttribute('[role="tabpanel"]:visible button[aria-label="Show all available history"]', 'pressed', 'true')
        ->click('#price [role="tab"]:has-text("P/E")')
        ->assertSeeIn('[data-testid="metric-value"]', '30.00')
        ->assertSeeIn('[data-testid="metric-median"]', '20.00')
        ->assertSee('2 values in view')
        ->click('#price [role="tab"]:has-text("Market cap")')
        ->assertSeeIn('[data-testid="metric-value"]', '₹9,000.00 cr')
        ->assertSeeIn('[data-testid="metric-median"]', '₹7,000.00 cr')
        ->click('#price [role="tab"]:has-text("1Y RSI")')
        ->assertSeeIn('[data-testid="metric-value"]', '60.00')
        ->assertSeeIn('[data-testid="metric-median"]', '30.00')
        ->press('button[aria-label="Show median line"]')
        ->assertAriaAttribute('button[aria-label="Show median line"]', 'pressed', 'false')
        ->keys('#price [role="tab"]:has-text("1Y RSI")', 'ArrowLeft')
        ->assertAriaAttribute('#price [role="tab"]:has-text("Market cap")', 'selected', 'true')
        ->click('#price [role="tab"]:has-text("Price")')
        ->assertAriaAttribute('[role="tabpanel"]:visible button[aria-label="Show all available history"]', 'pressed', 'true')
        ->assertNoJavaScriptErrors();

    expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
    expect($page->script('document.querySelectorAll("#price [role=tab]").length'))->toBe(5);
    expect($page->script('[...document.querySelectorAll("#price [role=tabpanel]")].filter((panel) => panel.getBoundingClientRect().height > 0).length'))->toBe(1);
})->with(['desktop' => [1440, 1000], 'mobile' => [390, 844]]);

it('updates the median for the selected period and resets a chart view', function () {
    for ($day = 1; $day <= 30; $day++) {
        createScreenResultRow('WINDOW', 'Window Test', sprintf('2026-06-%02d', $day), ['price_to_earnings' => $day]);
    }

    visit('/instruments/WINDOW')
        ->click('#price [role="tab"]:has-text("P/E")')
        ->press('[role="tabpanel"]:visible button[aria-label="Show 1 month"]')
        ->assertSeeIn('[data-testid="metric-median"]', '19.50')
        ->assertSee('22 values in view')
        ->press('[role="tabpanel"]:visible button[aria-label="Show all available history"]')
        ->assertSeeIn('[data-testid="metric-median"]', '15.50')
        ->assertSee('30 values in view')
        ->assertNoJavaScriptErrors();
});

it('shows a clear empty state when a metric has no history', function () {
    visit('/instruments/BETA')
        ->click('#price [role="tab"]:has-text("P/E")')
        ->assertSee('No price to earnings history is available.')
        ->assertSeeIn('[data-testid="metric-value"]', '—')
        ->assertSeeIn('[data-testid="metric-median"]', '—')
        ->assertNoJavaScriptErrors();
});

it('omits valuation charts for ETFs', function () {
    createScreenResultRow('GOLDBEES', 'Gold ETF', '2026-07-31', ['is_etf' => true, 'marketcap' => null]);

    $page = visit('/instruments/GOLDBEES')
        ->assertVisible('#price [role="tab"]:has-text("Price")')
        ->assertDontSeeIn('#price [role="tablist"]', 'P/E')
        ->assertDontSeeIn('#price [role="tablist"]', 'Market cap')
        ->click('#price [role="tab"]:has-text("1Y return")')
        ->assertSee('Rolling 1-year return')
        ->assertNoJavaScriptErrors();

    expect($page->script('document.querySelectorAll("#price [role=tab]").length'))->toBe(3);
});
