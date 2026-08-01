<?php

use App\Models\Screen;
use App\Models\User;

it('edits visible columns through the slide-over panel', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    createScreenResultRow('ALPHA', 'Alpha Industries', '2026-07-31', ['sharpe_return_one_year' => 42.5]);

    $this->actingAs($user);

    visit('/screens/'.$screen->id.'/edit')
        ->assertSee('Beta')
        ->click('Edit Columns')
        ->assertSee('Moving Averages')
        ->assertSee('8 selected')
        ->uncheck('column-beta')
        ->assertSee('7 selected')
        ->click('Apply')
        ->assertDontSee('Beta')
        ->assertNoJavaScriptErrors();
});

it('discards panel changes on cancel', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    createScreenResultRow('ALPHA', 'Alpha Industries', '2026-07-31', ['sharpe_return_one_year' => 42.5]);

    $this->actingAs($user);

    visit('/screens/'.$screen->id.'/edit')
        ->click('Edit Columns')
        ->uncheck('column-beta')
        ->assertSee('7 selected')
        ->click('Cancel')
        ->assertSee('Beta')
        ->click('Edit Columns')
        ->assertSee('8 selected')
        ->assertNoJavaScriptErrors();
});
