<?php

use App\Actions\ApplyScreenFiltersAction;
use App\Actions\DuplicateScreenAction;
use App\Models\Screen;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Screen::factory()->create(['id' => max(Screen::PUBLIC_SCREENS), 'name' => 'Example Screen']);
});

it('copies every saved screen setting into an independent private screen for the paid user', function (?int $publicId) {
    $user = User::factory()->create(['is_paid' => true]);
    $settings = [
        'name' => 'Momentum',
        'index' => 'etf',
        'unique_etf_per_index' => true,
        'sort_by' => 'close_raw',
        'sort_direction' => 'asc',
        'apply_factor_two' => true,
        'factor_two_sort_by' => 'volatility_one_year',
        'apply_factor_three' => true,
        'factor_three_sort_by' => 'beta',
        'apply_ma' => true,
        'above_ma_200' => true,
        'ignore_above_beta' => 1.25,
        'apply_pe' => true,
        'price_to_earnings_from' => 5,
        'price_to_earnings_to' => 30,
        'apply_custom_filter_one' => true,
        'apply_historical_date' => true,
        'historical_date' => '2024-03-01',
        'columns' => ['close_raw', 'marketcap', 'beta'],
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDay(),
    ];

    if ($publicId === null) {
        $source = Screen::factory()->for($user)->create($settings);
    } elseif ($publicId === max(Screen::PUBLIC_SCREENS)) {
        $source = Screen::findOrFail($publicId);
        $source->update($settings);
    } else {
        $source = Screen::factory()->create(array_merge($settings, ['id' => $publicId]));
    }

    $original = $source->refresh()->getAttributes();
    $beforeCount = Screen::count();
    $this->mock(ApplyScreenFiltersAction::class)->shouldNotReceive('execute');

    $response = $this->actingAs($user)->post(route('screens.duplicate', $source), [
        'user_id' => $source->user_id,
        'name' => 'Untrusted name',
        'sort_by' => 'marketcap',
        'columns' => [],
    ]);

    $copy = $user->screens()->whereKeyNot($source->id)->sole();
    $except = ['id', 'name', 'user_id', 'created_at', 'updated_at'];

    $response->assertRedirect(route('screens.edit', $copy))->assertSessionHas('success');
    expect(Screen::count())->toBe($beforeCount + 1)
        ->and($copy->id)->not->toBeIn(Screen::PUBLIC_SCREENS)
        ->and($copy->user_id)->toBe($user->id)
        ->and($copy->name)->toBe('Momentum (Copy)')
        ->and(Arr::except($copy->getAttributes(), $except))->toBe(Arr::except($original, $except))
        ->and($copy->created_at->gt($source->created_at))->toBeTrue()
        ->and($copy->updated_at->gt($source->updated_at))->toBeTrue()
        ->and($source->refresh()->getAttributes())->toBe($original);

    $this->put('/screens/'.$copy->id, array_replace($copy->toArray(), ['name' => 'My own screen']))
        ->assertSessionHasNoErrors()->assertRedirect(route('screens.edit', $copy));

    expect($copy->refresh()->name)->toBe('My own screen')
        ->and($source->refresh()->getAttributes())->toBe($original);

    $this->get('/screens/'.$copy->id)->assertNotFound();
    $this->actingAs(User::factory()->create(['is_paid' => true]))
        ->get(route('screens.edit', $copy))->assertNotFound();
})->with(['own private screen' => null, 'first example' => 1, 'second example' => 2, 'third example' => 3]);

it('numbers repeated copies and copies of copies within the new owners screens', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $source = Screen::findOrFail(max(Screen::PUBLIC_SCREENS));
    Screen::factory()->create(['name' => 'Example Screen (Copy)']);

    foreach (['Example Screen (Copy)', 'Example Screen (Copy 2)', 'Example Screen (Copy 3)'] as $name) {
        $this->actingAs($user)->post(route('screens.duplicate', $source))->assertRedirect();
        $source = $user->screens()->latest('id')->firstOrFail();
        expect($source->name)->toBe($name);
    }
});

it('keeps long Unicode screen copy names within the name limit', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $source = Screen::factory()->for($user)->create(['name' => str_repeat('株', 250)]);

    foreach ([' (Copy)', ' (Copy 2)'] as $suffix) {
        $this->actingAs($user)->post(route('screens.duplicate', $source))->assertRedirect();
        $copy = $user->screens()->latest('id')->firstOrFail();
        expect(Str::length($copy->name))->toBe(250)
            ->and($copy->name)->toBe(str_repeat('株', 250 - Str::length($suffix)).$suffix);
    }
});

it('does not retain the source owners loaded relation on the copy', function () {
    $source = Screen::findOrFail(max(Screen::PUBLIC_SCREENS))->load('user');
    $owner = User::factory()->create(['is_paid' => true]);

    $copy = app(DuplicateScreenAction::class)->execute($source, $owner);

    expect($copy->relationLoaded('user'))->toBeFalse()
        ->and($source->relationLoaded('user'))->toBeTrue()
        ->and($copy->user->is($owner))->toBeTrue()
        ->and($source->user_id)->not->toBe($owner->id);
});

it('blocks copying another users private screen', function () {
    $source = Screen::factory()->create();
    $user = User::factory()->create(['is_paid' => true]);
    $beforeCount = Screen::count();

    $this->actingAs($user)->post(route('screens.duplicate', $source))->assertNotFound();

    expect(Screen::count())->toBe($beforeCount);
});

it('requires paid access to copy both own and example screens', function (bool $example) {
    $user = User::factory()->create(['is_paid' => false]);
    $source = $example ? Screen::findOrFail(max(Screen::PUBLIC_SCREENS)) : Screen::factory()->for($user)->create();
    $beforeCount = Screen::count();

    $this->actingAs($user)->post(route('screens.duplicate', $source))->assertRedirect('/pricing');

    expect($user->can('duplicate', $source))->toBeFalse()
        ->and(Screen::count())->toBe($beforeCount);
})->with([false, true]);

it('requires authentication and a verified email before copying screens', function (bool $unverified) {
    if ($unverified) {
        $this->actingAs(User::factory()->unverified()->create(['is_paid' => true]));
    }

    $source = Screen::findOrFail(max(Screen::PUBLIC_SCREENS));
    $this->post(route('screens.duplicate', $source))->assertRedirect($unverified ? '/email/verify' : '/login');

    expect(Screen::count())->toBe(1);
})->with([false, true]);

it('offers a copy link on example pages only to paid users', function (?bool $paid) {
    if ($paid !== null) {
        $this->actingAs(User::factory()->create(['is_paid' => $paid]));
    }

    createScreenResultRow('ALPHA', 'Alpha', '2026-07-31');
    $source = Screen::findOrFail(max(Screen::PUBLIC_SCREENS));

    $this->get('/screens/'.$source->id)->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->component('Screens/Show')
        ->where('duplicateUrl', $paid ? route('screens.duplicate', $source) : null));
})->with(['guest' => null, 'free member' => false, 'paid member' => true]);

it('offers a copy link on the owners screen only with paid access', function (bool $paid) {
    $user = User::factory()->create(['is_paid' => $paid]);
    $source = Screen::factory()->for($user)->create();
    createScreenResultRow('ALPHA', 'Alpha', '2026-07-31');

    $this->actingAs($user)->get(route('screens.edit', $source))->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->component('Screens/Edit')
        ->where('duplicateUrl', $paid ? route('screens.duplicate', $source) : null));
})->with([false, true]);
