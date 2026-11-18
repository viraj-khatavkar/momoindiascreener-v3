<?php

use App\Actions\ApplyScreenFiltersAction;
use App\Models\Screen;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('returns only approved examples to guests without loading screen results', function () {
    foreach (Screen::PUBLIC_SCREENS as $id) {
        Screen::factory()->create(['id' => $id]);
    }
    Screen::factory()->create(['name' => 'Private screen']);
    $this->mock(ApplyScreenFiltersAction::class)->shouldNotReceive('execute');

    $this->get('/screens')->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->component('Screens/Index')
        ->has('screens', 0)
        ->where('publicScreens', fn ($screens) => collect($screens)->pluck('id')->sort()->values()->all() === collect(Screen::PUBLIC_SCREENS)->sort()->values()->all())
        ->where('exportUrl', null));
});

it('lists only the members own screens with the most recently updated first', function () {
    $user = User::factory()->create();
    $older = Screen::factory()->for($user)->create(['id' => 2000, 'updated_at' => now()->subDays(2)]);
    $recent = Screen::factory()->for($user)->create(['updated_at' => now()->subDay()]);
    $latestTie = Screen::factory()->for($user)->create(['updated_at' => $recent->updated_at]);
    Screen::factory()->create(['name' => 'Another members private screen']);
    $example = Screen::factory()->create(['id' => Screen::PUBLIC_SCREENS[0]]);

    $this->actingAs($user)->get('/screens')->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->component('Screens/Index')
        ->has('screens', 3)
        ->where('screens.0.id', $latestTie->id)
        ->where('screens.1.id', $recent->id)
        ->where('screens.2.id', $older->id)
        ->has('publicScreens', 1)
        ->where('publicScreens.0.id', $example->id));
});

it('opens the rules tab after creating a screen', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $response = $this->actingAs($user)->post('/screens', ['name' => 'New momentum screen']);
    $screen = $user->screens()->sole();

    $response->assertRedirect(route('screens.edit', ['screen' => $screen, 'tab' => 'rules']));
});
