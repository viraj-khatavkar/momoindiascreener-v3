<?php

use App\Models\Screen;
use App\Models\User;
use Illuminate\Support\Arr;

it('shows only examples to guests and shows every saved rule without editable controls', function () {
    $screen = Screen::factory()->create([
        'id' => Screen::PUBLIC_SCREENS[0],
        'name' => 'Example with complete rules',
        'minimum_return_one_year' => 5.5,
        'apply_factor_two' => true,
        'apply_ma' => true,
        'above_ma_200' => true,
        'apply_custom_filter_one' => true,
        'apply_pe' => true,
        'price_to_earnings_from' => 10,
        'price_to_earnings_to' => 25,
        'apply_historical_date' => true,
        'historical_date' => '2026-07-31',
    ]);
    Screen::factory()->create(['id' => 2000, 'name' => 'Secret private screen']);
    createScreenResultRow('ALPHA', 'Alpha', '2026-07-31', ['price_to_earnings' => 15, 'ma_50' => 95]);

    $page = visit('/screens')
        ->assertSee('Example Screens')
        ->assertDontSee('Your Screens')
        ->assertDontSee('Secret private screen')
        ->assertDontSee('Create New Screen')
        ->click('a[aria-label="Open Example with complete rules"]')
        ->assertPathIs('/screens/'.$screen->id)
        ->assertSee('One-year return > 5.5%')
        ->assertSee('Median volume ≥ ₹1,00,00,000')
        ->click('View all rules')
        ->assertQueryStringHas('tab', 'rules')
        ->assertSee('More than 5.5%')
        ->assertSee('Above 200-day MA')
        ->assertSee('Custom filter one')
        ->assertSee('10 to 25 (inclusive)')
        ->assertSee('2026-07-31')
        ->assertSee('Circuit Filters')
        ->assertSee('Percentage of Positive Days Filters')
        ->assertNoJavaScriptErrors();

    expect($page->script('document.querySelectorAll("#screen-panel-rules input, #screen-panel-rules select").length'))->toBe(0);
});

it('keeps results tied to saved rules and supports discard and apply without changing other settings', function () {
    $screen = Screen::factory()->create([
        'name' => 'Saved rules test',
        'minimum_return_one_year' => 5.5,
        'apply_factor_two' => true,
        'apply_factor_three' => true,
        'apply_ma' => true,
        'above_ma_200' => true,
        'apply_custom_filter_one' => true,
        'apply_historical_date' => true,
        'historical_date' => '2026-07-31',
    ]);
    $original = $screen->refresh()->getAttributes();
    createScreenResultRow('ALPHA', 'Alpha', '2026-07-31', ['absolute_return_one_year' => 20, 'ma_50' => 95]);
    createScreenResultRow('BETA', 'Beta', '2026-07-31', ['absolute_return_one_year' => 10, 'ma_50' => 95]);
    $this->actingAs($screen->user);

    $page = visit('/screens/'.$screen->id.'/edit?tab=rules')
        ->assertDisabled('Save and Apply')
        ->assertMissing('#screen-panel-results tbody tr');
    $page->script('window.resultLoads = 0; document.addEventListener("inertia:start", e => { if (e.detail.visit.only.includes("results")) window.resultLoads++; })');
    $page->script('document.getElementById("screen-rules-form").requestSubmit()');
    $page->assertQueryStringHas('tab', 'rules')->assertDontSee('Changes saved. Results updated.')
        ->fill('minimum_return_one_year', '15')->assertEnabled('Save and Apply')
        ->assertSee('Changes not applied')
        ->click('#screen-tab-results')
        ->assertQueryStringHas('tab', 'results')
        ->assertSee('One-year return > 5.5%')
        ->assertSee('ALPHA')->assertSee('BETA')
        ->assertDisabled('Edit Columns');

    expect($page->script('document.querySelector("a[aria-disabled=\"true\"]").hasAttribute("href")'))->toBeFalse();
    expect($page->script('(() => { const event = new Event("beforeunload", { cancelable: true }); window.dispatchEvent(event); return event.defaultPrevented; })()'))->toBeTrue();
    $page->script('window.confirm = () => false');
    $page->click('a[href="/screens"]')->assertPathIs('/screens/'.$screen->id.'/edit');
    $page->click('#screen-tab-rules')->assertValue('minimum_return_one_year', '15')
        ->assertMissing('#screen-panel-results tbody tr')
        ->click('#screen-tab-results')->assertSee('BETA');
    expect($page->script('window.resultLoads'))->toBe(1);
    $page->click('#screen-tab-rules')
        ->press('Discard changes')->assertDontSee('Changes not applied')
        ->assertDisabled('Save and Apply')->assertValue('minimum_return_one_year', (string) $screen->minimum_return_one_year)
        ->fill('minimum_return_one_year', '15')->press('Save and Apply')
        ->assertSee('Changes saved. Results updated.')
        ->assertSee('One-year return > 15%')->assertSee('ALPHA')->assertMissing('a[href="/instruments/BETA"]')
        ->assertEnabled('Edit Columns')->assertNoJavaScriptErrors();

    expect($screen->refresh()->minimum_return_one_year)->toEqual(15)
        ->and(Arr::except($screen->getAttributes(), ['minimum_return_one_year', 'updated_at']))
        ->toBe(Arr::except($original, ['minimum_return_one_year', 'updated_at']));
});

it('searches each ranking factor by name and lookback and shows the selected combination', function () {
    $screen = Screen::factory()->create(['apply_factor_two' => true, 'apply_factor_three' => true]);
    $this->actingAs($screen->user);
    $search = '#screen-rules-form input[type="search"]';

    visit('/screens/'.$screen->id.'/edit?tab=rules')->resize(390, 844)
        ->press('button[aria-label="Search sort by (factor)"]')->fill($search, 'no such factor')
        ->assertSee('No matching factors.')->assertDisabled('Save and Apply')
        ->fill($search, 'sharpe 12 6 3')
        ->assertVisible('button:text-is("Average Sharpe return (12, 6, 3 and 1 month)")')->keys($search, 'Enter')
        ->assertSelected('sort_by', 'average_sharpe_return_twelve_six_three_months')
        ->assertEnabled('Save and Apply')
        ->press('button[aria-label="Search sort by (factor two)"]')->fill($search, 'rsi 9')
        ->press('button:text-is("RSI 9 months")')->assertSelected('factor_two_sort_by', 'rsi_nine_months')
        ->press('button[aria-label="Search sort by (factor three)"]')->fill($search, 'market cap')
        ->keys($search, 'Enter')->assertSelected('factor_three_sort_by', 'marketcap')
        ->assertSeeIn('#screen-section-multi-factor > button', 'RSI 9 months · Lowest to Highest + Market cap · Lowest to Highest')
        ->press('Discard changes')->assertDisabled('Save and Apply')
        ->assertSelected('sort_by', $screen->sort_by)->assertNoJavaScriptErrors();
});

it('describes draft limits and preserves stock valuations when switching universes', function () {
    $screen = Screen::factory()->create([
        'apply_ma' => true, 'above_ma_200' => true, 'circuits_one_year' => 20,
        'marketcap_from' => 40, 'marketcap_to' => 2000,
        'apply_pe' => true, 'price_to_earnings_from' => 5, 'price_to_earnings_to' => 25,
    ]);
    createScreenResultRow('ALPHA', 'Alpha', '2026-07-31');
    $this->actingAs($screen->user);
    $circuits = 'button:has-text("Circuit Filters")';

    visit('/screens/'.$screen->id.'/edit?tab=rules')
        ->assertSeeIn('button:has-text("Above 200-day MA")', 'Moving Average Filters')
        ->assertSeeIn($circuits, 'One year ≤ 20')
        ->assertValue('circuits_one_year', '20')->assertValue('circuits_one_months', '')
        ->fill('circuits_one_year', '0')->assertSeeIn($circuits, 'One year ≤ 0')
        ->fill('circuits_one_year', '')->assertSeeIn($circuits, 'No limit')
        ->select('index', 'etf')
        ->assertSee('Market cap applies to companies.')->assertSee('P/E uses company earnings.')
        ->assertDisabled('marketcap_from')->assertDisabled('marketcap_to')
        ->assertDisabled('button[role="switch"][disabled]')
        ->assertDisabled('price_to_earnings_from')->assertDisabled('price_to_earnings_to')
        ->select('index', $screen->index->value)
        ->assertEnabled('marketcap_from')->assertValue('marketcap_from', '40')
        ->assertEnabled('price_to_earnings_to')->assertValue('price_to_earnings_to', '25')
        ->press('Save and Apply')->assertSee('Changes saved. Results updated.')
        ->click('#screen-tab-rules')->assertDisabled('Save and Apply')->assertNoJavaScriptErrors();

    expect($screen->refresh()->circuits_one_year)->toEqual(300);
});

it('sorts mobile results without changing screen ranks and keeps labels unique', function () {
    $screen = Screen::factory()->create(['columns' => ['close_raw']]);
    createScreenResultRow('ALPHA', 'Alpha', '2026-07-31', ['sharpe_return_one_year' => 20, 'close_raw' => 100]);
    createScreenResultRow('BETA', 'Beta', '2026-07-31', ['sharpe_return_one_year' => 10, 'close_raw' => 1000]);
    $this->actingAs($screen->user);

    $page = visit('/screens/'.$screen->id.'/edit')->resize(390, 844)
        ->assertDisabled('results_direction')
        ->select('results_sort', 'close_raw')
        ->assertSee('Display order: Unadjusted Close · Highest to Lowest')
        ->assertSee('Row values: Unadjusted Close')
        ->assertSee('1,000.00')
        ->assertEnabled('results_direction');
    expect($page->script('document.querySelector("tbody tr").innerText'))->toContain('2', 'BETA');
    $page->select('results_direction', 'asc');
    expect($page->script('document.querySelector("tbody tr").innerText'))->toContain('1', 'ALPHA');
    $page->click('Reset custom sort')->assertDisabled('results_direction')->click('#screen-tab-rules')
        ->press('#screen-section-multi-factor > button')->click('Apply Factor Two')->click('Apply Factor Three')->assertNoJavaScriptErrors();
    expect($page->script('(() => { const ids = [...document.querySelectorAll("[id]")].map(el => el.id); return ids.length === new Set(ids).size; })()'))->toBeTrue();
    expect($page->script('[...document.querySelectorAll("#screen-rules-form input, #screen-rules-form select")].every(el => el.labels.length > 0 && el.labels[0].textContent.trim().length > 0)'))->toBeTrue();
    expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
});

it('shows field errors on the rules tab and keeps the draft after a failed save', function () {
    $screen = Screen::factory()->create();
    createScreenResultRow('ALPHA', 'Alpha', '2026-07-31');
    $this->actingAs($screen->user);

    visit('/screens/'.$screen->id.'/edit?tab=rules')
        ->fill('name', '')
        ->fill('minimum_return_one_year', '22')
        ->press('Save and Apply')
        ->assertSee('Check these fields before you save:')
        ->assertSee('The name field is required.')
        ->assertValue('minimum_return_one_year', '22')
        ->assertSee('Changes not applied')
        ->press('Discard changes')
        ->assertValue('name', $screen->name)
        ->assertDontSee('The name field is required.')
        ->assertNoJavaScriptErrors();

    expect($screen->refresh()->minimum_return_one_year)->toEqual(6.5);
});

it('finds and sorts personal screens before the examples', function () {
    $user = User::factory()->create();
    $alpha = Screen::factory()->for($user)->create(['id' => 2000, 'name' => 'Alpha screen', 'updated_at' => now()->subDays(2)]);
    Screen::factory()->for($user)->create(['name' => 'Zeta screen', 'updated_at' => now()->subDay()]);
    Screen::factory()->create(['id' => Screen::PUBLIC_SCREENS[0], 'name' => 'Example screen']);
    Screen::factory()->create(['name' => 'Private from another member']);
    createScreenResultRow('ALPHA', 'Alpha', '2026-07-31');
    $this->actingAs($user);

    $page = visit('/screens')->assertSee('Your Screens')->assertSee('Example Screens')->assertDontSee('Private from another member');
    expect($page->script('document.querySelector("section h2").textContent'))->toBe('Your Screens');
    expect($page->script('document.querySelector("article h3").textContent.trim()'))->toBe('Zeta screen');
    $page->select('screens_sort', 'name');
    expect($page->script('document.querySelector("article h3").textContent.trim()'))->toBe('Alpha screen');
    $page->fill('screens_search', 'Alpha')->assertDontSee('Zeta screen')->assertSee('Alpha screen')
        ->click('a[aria-label="Edit Alpha screen"]')->assertPathIs('/screens/'.$alpha->id.'/edit')
        ->assertValue('name', 'Alpha screen')->assertNoJavaScriptErrors();
});

it('keeps tab links and restores an unapplied draft after browser Back', function () {
    $screen = Screen::factory()->create();
    createScreenResultRow('ALPHA', 'Alpha', '2026-07-31');
    $this->actingAs($screen->user);

    $page = visit('/screens/'.$screen->id.'/edit')
        ->click('#screen-tab-rules')->assertQueryStringHas('tab', 'rules')
        ->fill('minimum_return_one_year', '22')
        ->select('median_volume_option', 'custom')->assertValue('median_volume_option', 'custom')
        ->fill('median_volume_one_year', '1234567');
    $page->script('window.confirm = () => true');
    $page->click('a[href="/screens"]')->assertPathIs('/screens')
        ->back()->assertPathIs('/screens/'.$screen->id.'/edit')
        ->assertQueryStringHas('tab', 'rules')
        ->assertValue('minimum_return_one_year', '22')
        ->assertValue('median_volume_one_year', '1234567')
        ->assertSee('Changes not applied')
        ->press('Discard changes')
        ->assertDontSee('Changes not applied')
        ->click('#screen-tab-results')->assertQueryStringHas('tab', 'results')
        ->refresh()->assertSee('Saved rules')->assertSee('ALPHA')->assertNoJavaScriptErrors();

    expect($screen->refresh()->minimum_return_one_year)->toEqual(6.5);
});

it('keeps screen controls aligned and usable across viewport sizes', function (int $width) {
    $screen = Screen::factory()->create([
        'name' => 'Momentum screen with a longer name for layout checks',
        'apply_ema' => true,
        'above_ema_50' => true,
        'apply_factor_two' => true,
        'apply_custom_filter_one' => true,
        'sort_by' => 'average_sharpe_return_twelve_nine_six_three_months',
    ]);
    createScreenResultRow('ALPHA', 'Alpha', '2026-07-31', [
        'ema_50' => 90,
        'ma_50' => 95,
        'average_sharpe_return_twelve_nine_six_three_months' => 10,
    ]);
    $this->actingAs($screen->user);

    $page = visit('/screens/'.$screen->id.'/edit?tab=rules')->resize($width, 900)
        ->assertVisible('input[name="name"]')->assertNoJavaScriptErrors();
    $dimensions = $page->script('(() => {
        const fields = ["name", "index", "sort_by", "sort_direction"].map(name => {
            const element = document.querySelector(`#screen-rules-form [name="${name}"]`);
            const rect = element.getBoundingClientRect();
            return { top: rect.top, height: rect.height };
        });
        return { fields, fits: document.documentElement.scrollWidth <= window.innerWidth };
    })()');
    expect($dimensions['fits'])->toBeTrue();
    foreach ($dimensions['fields'] as $field) {
        expect($field['height'])->toEqual($width < 640 ? 44 : 40);
    }
    if ($width >= 640) {
        expect($dimensions['fields'][0]['top'])->toEqual($dimensions['fields'][1]['top'])
            ->and($dimensions['fields'][2]['top'])->toEqual($dimensions['fields'][3]['top']);
    }

    $page->screenshot(false, 'screen-rules-'.$width)
        ->click('#screen-tab-results')->assertSee('ALPHA')
        ->press('Edit Columns')->assertSee('Choose which columns appear');
    expect($page->script('(() => { const rect = document.querySelector("[role=dialog]").getBoundingClientRect(); return rect.left >= 0 && rect.right <= window.innerWidth; })()'))->toBeTrue();
    $page->screenshot(false, 'screen-columns-'.$width)->press('Close panel')
        ->click('a[href="/screens"]')->assertSee('Your Screens');
    expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
    $page->screenshot(false, 'screen-list-'.$width)->assertNoJavaScriptErrors();
})->with([1440, 768, 390, 320]);
