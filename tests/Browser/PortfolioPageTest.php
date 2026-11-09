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
        ->fill('symbol', 'ALP')
        ->click('ALPHA')
        ->assertSee('CMP: ₹120.00')
        ->fill('quantity', '10')
        ->fill('price', '100')
        ->press('Add to Portfolio')
        ->assertSee('Holding added.')
        ->assertSee('₹1,200.00')
        ->assertSee('+20.00%')
        ->assertNoJavaScriptErrors();

    $page->fill('name', 'Renamed Growth')
        ->press('Save Name')
        ->assertSee('Portfolio name updated.')
        ->click('[aria-label="Edit ALPHA holding"]')
        ->fill('quantity', '5')
        ->fill('price', '110')
        ->press('Save Holding')
        ->assertSee('Holding updated.')
        ->assertSee('₹600.00');

    $page->fill('date', '2026-07-30')
        ->press('Apply Date')
        ->assertSee('Earlier price')
        ->assertSee('₹450.00')
        ->assertSee('-18.18%')
        ->resize(390, 844)
        ->assertNoJavaScriptErrors();

    expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();

    $page->click('[aria-label="Remove ALPHA holding"]')
        ->assertSee('Remove ALPHA (5 shares)')
        ->press('Remove Holding')
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
        ->fill('symbol', 'UNKNOWN')
        ->fill('quantity', '0')
        ->fill('price', '-1')
        ->press('Add to Portfolio')
        ->assertSee('The selected symbol is invalid.')
        ->assertSee('The quantity field must be at least 1.')
        ->assertSee('The price field must be at least 0.01.')
        ->assertValue('symbol', 'UNKNOWN')
        ->assertNoJavaScriptErrors();
});
