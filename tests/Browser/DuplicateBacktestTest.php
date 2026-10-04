<?php

use App\Enums\BacktestStatusEnum;
use App\Models\Backtest;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('duplicates from the list and opens the new settings', function () {
    Queue::fake();
    $user = User::factory()->create(['is_paid' => true]);
    $source = Backtest::factory()->for($user)->create([
        'name' => 'Momentum', 'weightage' => 'price_weighted',
        'apply_stop_loss' => true, 'stop_loss_percentage' => 12.5,
    ]);
    loginAs($user->email);

    $page = visit('/backtests')
        ->press('button[aria-label="Duplicate Momentum"]')
        ->assertSee('Backtest copied.')
        ->assertValue('name', 'Momentum (Copy)')
        ->assertValue('weightage', 'price_weighted')
        ->assertValue('stop_loss_percentage', '12.50')
        ->assertNoJavaScriptErrors();

    $copy = $user->backtests()->whereKeyNot($source->id)->sole();
    $page->assertPathIs('/backtests/'.$copy->id)
        ->assertQueryStringHas('tab', 'settings');
    expect($copy->status)->toBe(BacktestStatusEnum::Pending);
    Queue::assertNothingPushed();
});

it('opens a fresh settings form after duplicating from a backtest page', function (int $width, int $height) {
    Queue::fake();
    $user = User::factory()->create(['is_paid' => true]);
    $source = Backtest::factory()->for($user)->create([
        'name' => 'Rank Strategy', 'weightage' => 'rank_weighted', 'max_stocks_to_hold' => 17,
    ]);
    loginAs($user->email);

    $page = visit('/backtests/'.$source->id.'?tab=results')->resize($width, $height)
        ->press('Duplicate')
        ->assertSee('Backtest copied.')
        ->assertValue('name', 'Rank Strategy (Copy)')
        ->assertValue('weightage', 'rank_weighted')
        ->assertAriaAttribute('button[role="tab"]:has-text("Settings")', 'selected', 'true')
        ->assertNoJavaScriptErrors();

    $copy = $user->backtests()->whereKeyNot($source->id)->sole();
    $page->assertPathIs('/backtests/'.$copy->id)
        ->fill('max_stocks_to_hold', '12')
        ->assertDisabled('Duplicate')
        ->assertSee('Save settings before you duplicate this backtest.')
        ->press('Save')
        ->assertSee('Settings saved.')
        ->assertEnabled('Duplicate')
        ->press('Duplicate')
        ->assertSee('Backtest copied.')
        ->assertValue('name', 'Rank Strategy (Copy 2)')
        ->assertValue('max_stocks_to_hold', '12')
        ->assertNoJavaScriptErrors();

    expect($source->refresh()->max_stocks_to_hold)->toBe(17)
        ->and($copy->refresh()->max_stocks_to_hold)->toBe(12)
        ->and($user->backtests()->count())->toBe(3)
        ->and($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
    Queue::assertNothingPushed();
})->with([[1440, 1000], [390, 844]]);
