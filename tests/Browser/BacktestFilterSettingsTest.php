<?php

use App\Models\Backtest;
use App\Models\Screen;
use App\Models\User;

it('sets the start date from the universe and the latest active filter limit', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->for($user)->create();
    $this->actingAs($user);
    $page = visit('/backtests/'.$backtest->id.'?tab=settings')
        ->select('index', 'nifty_200')->assertValue('start_date', '2014-01-01')
        ->assertAttribute('#start-date-input', 'min', '2014-01-01')
        ->assertSeeIn('[data-test="universe-start-date-notice"]', '01 Jan 2014')
        ->press('#backtest-section-valuation > button')->click('Apply Market Cap Filter')
        ->assertValue('start_date', '2024-03-01')->assertAttribute('#start-date-input', 'min', '2024-03-01')
        ->fill('marketcap_from', '1000')->fill('marketcap_to', '20000')
        ->click('Apply P/E Filter')->fill('price_to_earnings_from', '5')->fill('price_to_earnings_to', '25')
        ->select('index', 'nifty_total_market')->assertValue('start_date', '2024-11-01')
        ->select('index', 'nifty_1000')->assertValue('start_date', '2026-11-16')
        ->assertAttribute('#start-date-input', 'min', '2026-11-16')
        ->select('index', 'nifty_midcap_150')->assertValue('start_date', '2024-03-01')
        ->press('Save')->assertSee('Settings saved.')->assertNoJavaScriptErrors();
    expect($backtest->fresh()->apply_marketcap)->toBeTrue()->and($backtest->fresh()->apply_pe)->toBeTrue()
        ->and($backtest->fresh()->start_date->toDateString())->toBe('2024-03-01');
    $page->click('Apply Market Cap Filter')->click('Apply P/E Filter')
        ->assertAttribute('#start-date-input', 'min', '2017-07-31')
        ->fill('start_date', '2017-07-30');
    expect($page->script('document.querySelector("#start-date-input").validity.rangeUnderflow'))->toBeTrue();
});

it('offers unique ETF selection only for the ETF universe and saves the choice', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->for($user)->create();
    $this->actingAs($user);
    visit('/backtests/'.$backtest->id.'?tab=settings')->resize(390, 844)
        ->assertDontSee('One ETF per underlying index')->select('index', 'etf')
        ->click('One ETF per underlying index')->press('Save')->assertSee('Settings saved.')
        ->assertNoJavaScriptErrors();
    expect($backtest->fresh()->unique_etf_per_index)->toBeTrue();
});

it('applies screen EMA rules and displays them in the saved summary', function () {
    $screen = Screen::factory()->create();
    createScreenResultRow('ABOVE', 'Above', '2026-07-31', ['ema_50' => 90]);
    createScreenResultRow('BELOW', 'Below', '2026-07-31', ['ema_50' => 110]);
    $this->actingAs($screen->user);
    visit('/screens/'.$screen->id.'/edit')->assertSee('ABOVE')->assertSee('BELOW')
        ->click('#screen-tab-rules')->press('Exponential Moving Average Filters')
        ->click('Apply Exponential Moving Average Filters')->click('Above 50-day EMA')
        ->press('Save and Apply')->assertSee('Changes saved. Results updated.')
        ->assertSee('ABOVE')->assertMissing('a[href="/instruments/BELOW"]')->assertSee('EMA filters on')
        ->assertNoJavaScriptErrors();
    expect($screen->fresh()->apply_ema)->toBeTrue()->and($screen->fresh()->above_ema_50)->toBeTrue();
});
