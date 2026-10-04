<?php

use App\Models\Backtest;
use App\Models\User;

it('saves stop loss controls and displays the selected rules', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id]);

    loginAs($user->email);

    $page = visit('/backtests/'.$backtest->id.'?tab=settings')
        ->assertMissing('[name="stop_loss_percentage"]')
        ->click('Enable Stock Stop Loss')
        ->assertVisible('[name="stop_loss_percentage"]')
        ->fill('stop_loss_percentage', '12.5')
        ->click('Trail Below Highest Close')
        ->select('stop_loss_proceeds', 'replace_immediately')
        ->press('Save')
        ->assertSee('Settings saved.')
        ->refresh()
        ->assertValue('stop_loss_percentage', '12.50')
        ->assertValue('stop_loss_proceeds', 'replace_immediately')
        ->assertNoJavaScriptErrors();

    expect($backtest->refresh()->apply_stop_loss)->toBeTrue()
        ->and($backtest->trail_stop_loss)->toBeTrue()
        ->and((float) $backtest->stop_loss_percentage)->toBe(12.5);

    $page->click('Results')
        ->assertSee('12.5% below highest close since entry')
        ->assertSee('Buy next stock on exit day')
        ->assertSee('exit at the next trading day')
        ->assertNoJavaScriptErrors();
});
