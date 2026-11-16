<?php

use App\Models\Screen;
use App\Models\User;

it('copies an example screen into the paid users editable private screen', function () {
    $source = Screen::factory()->create([
        'id' => max(Screen::PUBLIC_SCREENS),
        'name' => 'Example Momentum',
        'sort_by' => 'close_raw',
        'sort_direction' => 'asc',
    ]);
    $user = User::factory()->create(['is_paid' => true]);
    createScreenResultRow('ALPHA', 'Alpha', '2026-07-31');
    loginAs($user->email);

    $page = visit('/screens/'.$source->id)
        ->press('Duplicate Screen')
        ->assertSee('Screen copied.')
        ->assertSee('Example Momentum (Copy)')
        ->assertValue('sort_by', 'close_raw')
        ->assertValue('sort_direction', 'asc')
        ->click('Show More Filters')
        ->assertValue('name', 'Example Momentum (Copy)')
        ->assertNoJavaScriptErrors();

    $copy = $user->screens()->sole();
    $page->assertPathIs('/screens/'.$copy->id.'/edit');
    expect($copy->id)->not->toBeIn(Screen::PUBLIC_SCREENS)
        ->and($copy->user_id)->toBe($user->id)
        ->and($source->refresh()->name)->toBe('Example Momentum');
});

it('opens fresh copy settings and requires changes to be saved before another copy', function (int $width, int $height) {
    $user = User::factory()->create(['is_paid' => true]);
    $source = Screen::factory()->for($user)->create([
        'id' => max(Screen::PUBLIC_SCREENS) + 1,
        'name' => 'My Momentum',
        'sort_by' => 'close_raw',
    ]);
    createScreenResultRow('ALPHA', 'Alpha', '2026-07-31');
    loginAs($user->email);

    $page = visit('/screens/'.$source->id.'/edit')->resize($width, $height)
        ->press('Duplicate Screen')
        ->assertSee('My Momentum (Copy)')
        ->click('Show More Filters')
        ->assertValue('name', 'My Momentum (Copy)')
        ->select('sort_direction', 'asc')
        ->assertDisabled('Duplicate Screen')
        ->assertSee('Save changes before you duplicate this screen.')
        ->press('Update & Apply Filters')
        ->assertEnabled('Duplicate Screen')
        ->press('Duplicate Screen')
        ->assertSee('My Momentum (Copy 2)')
        ->assertValue('sort_direction', 'asc')
        ->click('Show More Filters')
        ->assertValue('name', 'My Momentum (Copy 2)')
        ->assertNoJavaScriptErrors();

    $copy = $user->screens()->where('name', 'My Momentum (Copy 2)')->sole();
    $page->assertPathIs('/screens/'.$copy->id.'/edit');
    expect($source->refresh()->sort_direction)->toBe('desc')
        ->and($user->screens()->count())->toBe(3)
        ->and($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
})->with([[1440, 1000], [390, 844]]);

it('hides the copy button from free members on example and private screens', function () {
    $source = Screen::factory()->create(['id' => max(Screen::PUBLIC_SCREENS)]);
    $user = User::factory()->create(['is_paid' => false]);
    $private = Screen::factory()->for($user)->create();
    createScreenResultRow('ALPHA', 'Alpha', '2026-07-31');
    loginAs($user->email);

    visit('/screens/'.$source->id)
        ->assertDontSee('Duplicate Screen')
        ->assertNoJavaScriptErrors();
    visit('/screens/'.$private->id.'/edit')
        ->assertDontSee('Duplicate Screen')
        ->assertNoJavaScriptErrors();
});
