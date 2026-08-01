<?php

use App\Models\Screen;
use App\Models\User;

it('saves the selected columns and redirects to the edit page', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->put('/screens/'.$screen->id.'/columns', ['columns' => ['beta', 'ma_200']])
        ->assertRedirect('/screens/'.$screen->id.'/edit');

    expect($screen->fresh()->columns)->toBe(['beta', 'ma_200']);
});

it('allows clearing all columns', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->put('/screens/'.$screen->id.'/columns', ['columns' => []])
        ->assertRedirect('/screens/'.$screen->id.'/edit');

    expect($screen->fresh()->columns)->toBe([]);
});

it('rejects column names that are not valid result columns', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create(['user_id' => $user->id]);
    $originalColumns = $screen->columns;

    $this->actingAs($user)
        ->put('/screens/'.$screen->id.'/columns', ['columns' => ['beta', 'not_a_column']])
        ->assertSessionHasErrors('columns.1');

    expect($screen->fresh()->columns)->toBe($originalColumns);
});

it('requires the columns key', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->put('/screens/'.$screen->id.'/columns', [])
        ->assertSessionHasErrors('columns');
});

it('blocks updating columns of screens the user does not own', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create();
    $originalColumns = $screen->columns;

    $this->actingAs($user)
        ->put('/screens/'.$screen->id.'/columns', ['columns' => ['beta']])
        ->assertNotFound();

    expect($screen->fresh()->columns)->toBe($originalColumns);
});

it('redirects the old columns edit page to the screen edit page', function () {
    $user = User::factory()->create();
    $screen = Screen::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get('/screens/'.$screen->id.'/columns/edit')
        ->assertRedirect('/screens/'.$screen->id.'/edit');
});
