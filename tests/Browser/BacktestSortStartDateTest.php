<?php

use App\Models\Backtest;
use App\Models\User;

it('updates the earliest start date when a valuation sort is selected', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->for($user)->create(['start_date' => '2011-01-05']);
    loginAs($user->email);

    $page = visit('/backtests/'.$backtest->id.'?tab=settings')
        ->assertAttribute('#start-date-input', 'min', '2011-01-05')
        ->select('sort_by', 'marketcap')
        ->assertAttribute('#start-date-input', 'min', '2024-03-01')
        ->assertSeeIn('[data-test="sort-start-date-notice"]', '01 Mar 2024')
        ->assertValue('start_date', '2024-03-01')
        ->fill('start_date', '2025-01-01')
        ->press('Save')
        ->assertSee('Settings saved.')
        ->assertValue('start_date', '2025-01-01')
        ->select('sort_by', 'price_to_earnings')
        ->assertAttribute('#start-date-input', 'min', '2024-03-01')
        ->assertValue('start_date', '2024-03-01')
        ->fill('start_date', '2024-02-29');

    expect($page->script('document.querySelector("#start-date-input").validity.rangeUnderflow'))->toBeTrue();

    $page->fill('start_date', '2024-03-01')
        ->select('sort_by', 'close_raw')
        ->assertAttribute('#start-date-input', 'min', '2011-01-05')
        ->assertNoJavaScriptErrors();

    expect($backtest->fresh()->start_date->toDateString())->toBe('2025-01-01');
});

it('applies the date limit only while a secondary valuation factor is enabled', function (string $factor) {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->for($user)->create([
        'start_date' => '2011-01-05', 'factor_two_sort_by' => 'marketcap', 'factor_three_sort_by' => 'price_to_earnings',
    ]);
    loginAs($user->email);

    visit('/backtests/'.$backtest->id.'?tab=settings')
        ->assertAttribute('#start-date-input', 'min', '2011-01-05')
        ->press('#backtest-section-multiFactor > button')
        ->click('Apply Factor '.$factor)
        ->assertAttribute('#start-date-input', 'min', '2024-03-01')
        ->assertValue('start_date', '2024-03-01')
        ->click('Apply Factor '.$factor)
        ->assertAttribute('#start-date-input', 'min', '2011-01-05')
        ->assertNoJavaScriptErrors();
})->with(['Two', 'Three']);

it('keeps a saved later date when opening a valuation strategy', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->for($user)->create(['sort_by' => 'marketcap', 'start_date' => '2025-01-01']);
    loginAs($user->email);

    visit('/backtests/'.$backtest->id.'?tab=settings')
        ->assertValue('start_date', '2025-01-01')
        ->assertAttribute('#start-date-input', 'min', '2024-03-01')
        ->assertNoJavaScriptErrors();
});
