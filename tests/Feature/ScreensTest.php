<?php

use App\Models\Screen;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shows screen results ordered by the sort factor on the edit page', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    createScreenResultRow('ALPHA', 'Alpha Industries', '2026-07-31', ['sharpe_return_one_year' => 12.5]);
    createScreenResultRow('BETA', 'Beta Corp', '2026-07-31', ['sharpe_return_one_year' => 99.9]);

    $this->actingAs($user)
        ->get('/screens/'.$screen->id.'/edit')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Screens/Edit')
            ->has('results', 2)
            ->where('results.0.symbol', 'BETA')
            ->where('results.1.symbol', 'ALPHA')
            ->has('columns', 8)
            ->where('columns.0.name', 'close_adjusted')
            ->has('availableColumns', 9)
            ->where('availableColumns.0.name', 'Price & Liquidity')
            ->where('availableColumns.0.columns.0.id', 'close_adjusted')
            ->where('availableColumns.0.columns.0.name', 'Last Close'));
});

it('excludes stocks that fail the screen filters', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    createScreenResultRow('GOOD', 'Good Stock', '2026-07-31');
    createScreenResultRow('LOWVOL', 'Low Volume Stock', '2026-07-31', ['median_volume_one_year' => 100]);

    $this->actingAs($user)
        ->get('/screens/'.$screen->id.'/edit')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('results', 1)
            ->where('results.0.symbol', 'GOOD'));
});

it('returns empty results when no stock passes the filters', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    createScreenResultRow('LOWVOL', 'Low Volume Stock', '2026-07-31', ['median_volume_one_year' => 100]);

    $this->actingAs($user)
        ->get('/screens/'.$screen->id.'/edit')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->has('results', 0));
});

it('orders results by combined rank and includes factor ranks for multi-factor screens', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create([
        'user_id' => $user->id,
        'apply_factor_two' => true,
    ]);

    // Factor one: sharpe desc — ALPHA #1, BETA #2, GAMMA #3.
    // Factor two (factory default): volatility asc — BETA #1, GAMMA #2, ALPHA #3.
    // Combined: BETA 3, ALPHA 4, GAMMA 5.
    createScreenResultRow('ALPHA', 'Alpha Industries', '2026-07-31', [
        'sharpe_return_one_year' => 99,
        'volatility_one_year' => 0.9,
    ]);
    createScreenResultRow('BETA', 'Beta Corp', '2026-07-31', [
        'sharpe_return_one_year' => 50,
        'volatility_one_year' => 0.01,
    ]);
    createScreenResultRow('GAMMA', 'Gamma Ltd', '2026-07-31', [
        'sharpe_return_one_year' => 10,
        'volatility_one_year' => 0.02,
    ]);

    $this->actingAs($user)
        ->get('/screens/'.$screen->id.'/edit')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('results', 3)
            ->where('results.0.symbol', 'BETA')
            ->where('results.0.factor_one_rank', 2)
            ->where('results.0.factor_two_rank', 1)
            ->where('results.1.symbol', 'ALPHA')
            ->where('results.1.factor_one_rank', 1)
            ->where('results.1.factor_two_rank', 3)
            ->where('results.2.symbol', 'GAMMA'));
});

it('blocks editing screens the user does not own', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create();

    $this->actingAs($user)
        ->get('/screens/'.$screen->id.'/edit')
        ->assertNotFound();
});
