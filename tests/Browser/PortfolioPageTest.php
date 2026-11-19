<?php

use App\Models\BacktestNseInstrument;
use App\Models\Portfolio;
use App\Models\User;

it('creates a portfolio and manages its holdings on desktop and mobile', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    BacktestNseInstrument::query()->create(['symbol' => 'ALPHA', 'name' => 'Alpha Industries']);
    createScreenResultRow('ALPHA', 'Alpha Industries', '2026-07-31', ['close_raw' => 120]);
    createScreenResultRow('ALPHA', 'Alpha Industries', '2026-07-29', ['close_raw' => 90]);
    createScreenResultRow('MARKET', 'Market', '2026-07-30');
    $this->actingAs($admin);

    $page = visit('/admin/portfolios')
        ->assertSee('No portfolios yet')
        ->click('Create Portfolio')
        ->assertPathIs('/admin/portfolios/create')
        ->fill('name', 'My Growth Portfolio')
        ->click('button[type="submit"]')
        ->assertSee('Portfolio created.')
        ->assertSee('No holdings yet')
        ->assertMissing('[name="symbol"]')
        ->press('Add holding')
        ->fill('symbol', 'ALP')
        ->click('ALPHA')
        ->assertSee('Market price ₹120.00')
        ->fill('quantity', '10')
        ->fill('price', '100')
        ->press('Add to portfolio')
        ->assertSee('Holding added.')
        ->assertSee('₹1,200.00')
        ->assertSee('+20.00%')
        ->assertNoJavaScriptErrors();

    $page->click('[aria-label="Rename portfolio"]')
        ->fill('name', 'Renamed Growth')
        ->press('Save name')
        ->assertSee('Portfolio name updated.')
        ->assertSee('Renamed Growth')
        ->click('[aria-label="Edit ALPHA holding"]')
        ->fill('quantity', '5')
        ->fill('price', '110')
        ->press('Save holding')
        ->assertSee('Holding updated.')
        ->assertSee('₹600.00');

    $page->click('button[aria-label^="Change valuation date"]')
        ->fill('date', '2026-07-30')
        ->press('Apply date')
        ->assertSee('Earlier price')
        ->assertSee('₹450.00')
        ->assertSee('-18.18%')
        ->resize(390, 844)
        ->assertNoJavaScriptErrors();

    expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();

    $page->click('[aria-label="Edit ALPHA holding"]')
        ->click('[aria-label="Remove ALPHA holding"]')
        ->assertSee('Remove ALPHA (5 shares at ₹110.00 each)')
        ->press('Keep holding')
        ->assertValue('quantity', '5')
        ->click('[aria-label="Remove ALPHA holding"]')
        ->press('Remove holding')
        ->assertSee('Holding removed.')
        ->assertSee('No holdings yet')
        ->assertNoJavaScriptErrors();

    $portfolio = Portfolio::query()->where('user_id', $admin->id)->sole();
    expect($portfolio->name)->toBe('Renamed Growth')
        ->and($portfolio->holdings()->count())->toBe(0);
});

it('shows holding validation errors without losing the entered form data', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $portfolio = Portfolio::factory()->for($admin)->create();
    $this->actingAs($admin);

    visit('/admin/portfolios/'.$portfolio->id.'/edit')
        ->press('Add holding')
        ->fill('symbol', 'UNKNOWN')
        ->fill('quantity', '0')
        ->fill('price', '-1')
        ->press('Add to portfolio')
        ->assertSee('The selected symbol is invalid.')
        ->assertSee('The quantity field must be at least 1.')
        ->assertSee('The price field must be at least 0.01.')
        ->assertValue('symbol', 'UNKNOWN')
        ->assertNoJavaScriptErrors();
});

it('finds purchase entries and keeps the selected entry in view while editing', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $portfolio = Portfolio::factory()->for($admin)->create(['name' => 'Momentum portfolio']);
    createScreenResultRow('ALPHA', 'Alpha Industries', '2026-07-31', ['close_raw' => 120]);
    createScreenResultRow('BETA', 'Beta Chemicals', '2026-07-31', ['close_raw' => 90]);
    createCorporateAction('BETA', '2026-07-31', ['type' => 'dividend', 'dividend' => '5']);
    $portfolio->holdings()->create(['symbol' => 'ALPHA', 'quantity' => 10, 'price' => 100]);
    $first = $portfolio->holdings()->create(['symbol' => 'BETA', 'quantity' => 20, 'price' => 85]);
    $second = $portfolio->holdings()->create(['symbol' => 'BETA', 'quantity' => 30, 'price' => 95]);
    $portfolio->holdings()->create(['symbol' => 'MISSING', 'quantity' => 5, 'price' => 50]);
    $this->actingAs($admin);

    $page = visit('/admin/portfolios/'.$portfolio->id.'/edit')->resize(1440, 900)
        ->assertSee('3 stocks · 4 purchase entries')
        ->assertSee('Known portfolio value')
        ->assertSee('1 entry has no price')
        ->assertSee('No price')
        ->assertSee('₹5,700.00');

    $page->script("Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText: async value => { window.copiedValue = value } } })");
    $page->click('[aria-label="Copy known portfolio value"]')->assertSee('Value copied.');
    expect($page->script('window.copiedValue'))->toBe('5700.00');

    $page
        ->click('details > summary')
        ->assertSeeIn('details[open]', 'dividend')
        ->assertSeeIn('details[open]', '₹5.00')
        ->click('details > summary')
        ->assertVisible('table > thead')
        ->fill('holding-search', 'no match')
        ->assertSee('No holdings match')
        ->click('[aria-label="Clear search"]')
        ->fill('holding-search', 'chemicals')
        ->assertSee('2 of 4 entries')
        ->assertMissing('a[href="/instruments/ALPHA"]')
        ->click('[data-test="holding-'.$second->id.'"] button')
        ->assertValue('quantity', '30')
        ->fill('quantity', '40')
        ->press('Save holding')
        ->assertMissing('[role="dialog"]')
        ->assertValue('holding-search', 'chemicals')
        ->assertSeeIn('[data-test="holding-'.$first->id.'"]', '₹1,800.00')
        ->assertSeeIn('[data-test="holding-'.$second->id.'"]', '₹3,600.00');

    $page->resize(390, 844)
        ->assertMissing('table > thead')
        ->assertSeeIn('[data-test="holding-'.$second->id.'"]', 'Buy ₹95.00')
        ->click('[data-test="holding-'.$second->id.'"] button')
        ->fill('quantity', '99')
        ->press('Cancel')
        ->assertMissing('[role="dialog"]')
        ->assertVisible('[data-test="holding-'.$second->id.'"] button:focus')
        ->assertValue('holding-search', 'chemicals')
        ->assertNoJavaScriptErrors();

    expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue()
        ->and($second->refresh()->quantity)->toBe(40);

    $page->resize(320, 640)
        ->fill('holding-search', 'BETA')
        ->click('button[aria-label^="Change valuation date"]')
        ->assertVisible('#valuation-date');
    expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();

    $page->fill('date', '2026-07-30')
        ->press('Apply date')
        ->assertSee('4 entries have no price')
        ->assertMissing('#valuation-date')
        ->assertValue('holding-search', 'BETA')
        ->assertSee('2 of 4 entries')
        ->click('button[aria-label^="Change valuation date"]')
        ->click('Use latest')
        ->assertSee('1 entry has no price')
        ->assertMissing('#valuation-date')
        ->assertValue('holding-search', 'BETA')
        ->assertNoJavaScriptErrors();
});
