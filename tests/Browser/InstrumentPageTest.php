<?php

use App\Enums\CorporateActionTypeEnum;

beforeEach(function () {
    createScreenResultRow('ALPHA', 'Alpha Industries', '2026-07-30', ['close_adjusted' => 100]);
    createScreenResultRow('ALPHA', 'Alpha Industries', '2026-07-31', [
        'close_adjusted' => 105,
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
        ->assertSeeIn('#returns dd.text-gray-600', '0.00%')
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
