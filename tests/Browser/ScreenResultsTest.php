<?php

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
