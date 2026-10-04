<?php

use App\Models\Backtest;
use App\Models\User;

it('saves the weighting option and displays its formula', function (string $weightage, string $example, string $label) {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id]);
    loginAs($user->email);

    $page = visit('/backtests/'.$backtest->id.'?tab=settings')
        ->select('weightage', $weightage)
        ->assertSee($example)
        ->assertSee('Replacement purchases select stocks not already held.')
        ->press('Save')
        ->assertSee('Settings saved.')
        ->refresh()
        ->assertValue('weightage', $weightage)
        ->assertNoJavaScriptErrors();

    expect($backtest->refresh()->weightage->value)->toBe($weightage);

    $page->click('Results')->assertSee($label)->assertNoJavaScriptErrors();
})->with([
    ['rank_weighted', '54.5%, 27.3%, and 18.2%', 'Rank Weighted · 1 / rank · Rebalanced'],
    ['price_weighted', '90.91% and 9.09%', 'Price Weighted · Unadjusted close · Rebalanced'],
]);
