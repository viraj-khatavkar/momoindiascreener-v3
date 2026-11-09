<?php

use App\Models\Screen;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Pest Browser 4.3 does not parse multipart uploads in its Laravel HTTP server.
 * Submit the real file through Laravel before opening the browser page.
 */
function uploadRebalanceBrowserPortfolio(): void
{
    test()->post(route('rebalance-tracker.upload'), [
        'csv' => UploadedFile::fake()->createWithContent('portfolio.csv', file_get_contents(public_path('sample-portfolio.csv'))),
    ])->assertRedirect(route('rebalance-tracker.index'))->assertSessionHasNoErrors();
}

it('analyzes an uploaded portfolio and clears old results when inputs change', function () {
    $user = User::factory()->create(['is_paid' => true]);
    Screen::factory()->for($user)->create(['name' => 'Momentum Portfolio']);
    $symbols = ['RELIANCE', 'TCS', 'HDFCBANK', 'INFY', 'ICICIBANK', 'HINDUNILVR', 'SBIN', 'BHARTIARTL', 'KOTAKBANK', 'WIPRO'];
    foreach ($symbols as $index => $symbol) {
        createScreenResultRow($symbol, $symbol, '2026-07-31', ['sharpe_return_one_year' => 50 - $index]);
    }
    createScreenResultRow('NEWENTRY', 'New Entry', '2026-07-31', ['sharpe_return_one_year' => 100]);
    $this->actingAs($user);
    uploadRebalanceBrowserPortfolio();

    $page = visit('/rebalance-tracker')
        ->assertSee('Rebalance Tracker')
        ->assertDontSee('Analysis Results')
        ->assertSee('10 unique symbols')
        ->fill('worst_rank_held', '3')
        ->press('Analyze Portfolio')
        ->assertSee('Exits (8)')
        ->assertSee('Inside WRH (2)')
        ->assertSee('Entries (1)')
        ->assertSee('NEWENTRY')
        ->assertPathIs('/rebalance-tracker')
        ->assertNoJavaScriptErrors();

    $page->script('Object.defineProperty(navigator, "clipboard", { configurable: true, value: { writeText: async (text) => { window.copiedSymbols = text; } } })');
    $page->click('[aria-label="Copy Entries"]')->assertSee('Entries copied.');
    expect($page->script('window.copiedSymbols'))->toBe('NEWENTRY');

    $page->resize(390, 844)
        ->assertSee('Analysis Results')
        ->assertNoJavaScriptErrors();

    expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();

    $page->fill('worst_rank_held', '4')
        ->assertDontSee('Analysis Results')
        ->press('Analyze Portfolio')
        ->assertSee('Inside WRH (3)')
        ->attach('csv', public_path('sample-portfolio.csv'))
        ->assertDontSee('Analysis Results')
        ->assertDontSee('Analyze Portfolio')
        ->assertNoJavaScriptErrors();
});

it('blocks analysis when uploaded symbols have no current price', function () {
    $user = User::factory()->create(['is_paid' => true]);
    Screen::factory()->for($user)->create();
    createScreenResultRow('RELIANCE', 'Reliance', '2026-07-31');
    $this->actingAs($user);
    uploadRebalanceBrowserPortfolio();

    visit('/rebalance-tracker')
        ->assertSee('9 symbols were not found')
        ->fill('worst_rank_held', '10')
        ->assertDisabled('Analyze Portfolio')
        ->assertDontSee('Analysis Results')
        ->assertNoJavaScriptErrors();
});
