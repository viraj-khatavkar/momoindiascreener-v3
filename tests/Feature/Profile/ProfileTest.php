<?php

use App\Enums\CountryEnum;
use App\Enums\StateEnum;
use App\Models\User;

it('shows the profile edit page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/profile')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Profile/Edit'));
});

it('redirects guests to login', function () {
    $this->get('/profile')
        ->assertRedirect('/login');
});

it('passes profile data to the page', function () {
    $user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'address_line_one' => '123 Main St',
        'city' => 'Mumbai',
        'phone' => '9876543210',
        'postal_code' => '400001',
        'state' => StateEnum::MH,
        'country' => CountryEnum::India,
    ]);

    $this->actingAs($user)
        ->get('/profile')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Profile/Edit')
            ->has('profile', fn ($profile) => $profile
                ->where('name', 'John Doe')
                ->where('email', 'john@example.com')
                ->where('address_line_one', '123 Main St')
                ->where('city', 'Mumbai')
                ->where('phone', '9876543210')
                ->where('postal_code', '400001')
                ->where('state', 'Maharashtra')
                ->where('country', 'india')
                ->etc()
            )
            ->has('states')
            ->has('countries')
        );
});

it('updates the profile successfully', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/profile', [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'address_line_one' => '456 New St',
            'address_line_two' => 'Apt 2',
            'phone' => '9876543210',
            'city' => 'Pune',
            'postal_code' => '411001',
            'state' => StateEnum::MH->value,
            'country' => CountryEnum::India->value,
        ])
        ->assertRedirect('/profile')
        ->assertSessionHas('success', 'Profile updated!');

    $user->refresh();

    expect($user->name)->toBe('Updated Name')
        ->and($user->email)->toBe('updated@example.com')
        ->and($user->address_line_one)->toBe('456 New St')
        ->and($user->address_line_two)->toBe('Apt 2')
        ->and($user->phone)->toBe('9876543210')
        ->and($user->city)->toBe('Pune')
        ->and($user->postal_code)->toBe('411001')
        ->and($user->state)->toBe(StateEnum::MH)
        ->and($user->country)->toBe(CountryEnum::India);
});

it('validates required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/profile', [])
        ->assertSessionHasErrors([
            'name',
            'email',
            'address_line_one',
            'phone',
            'city',
            'postal_code',
            'state',
            'country',
        ]);
});

it('validates email uniqueness excluding current user', function () {
    $existingUser = User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/profile', [
            'name' => 'Test',
            'email' => 'taken@example.com',
            'address_line_one' => '123 St',
            'phone' => '9876543210',
            'city' => 'Mumbai',
            'postal_code' => '400001',
            'state' => StateEnum::MH->value,
            'country' => CountryEnum::India->value,
        ])
        ->assertSessionHasErrors(['email']);
});

it('allows user to keep their own email', function () {
    $user = User::factory()->create(['email' => 'myemail@example.com']);

    $this->actingAs($user)
        ->post('/profile', [
            'name' => 'Updated Name',
            'email' => 'myemail@example.com',
            'address_line_one' => '123 St',
            'phone' => '9876543210',
            'city' => 'Mumbai',
            'postal_code' => '400001',
            'state' => StateEnum::MH->value,
            'country' => CountryEnum::India->value,
        ])
        ->assertRedirect('/profile')
        ->assertSessionHas('success');
});

it('validates postal code is exactly 6 characters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/profile', [
            'name' => 'Test',
            'email' => $user->email,
            'address_line_one' => '123 St',
            'phone' => '9876543210',
            'city' => 'Mumbai',
            'postal_code' => '123',
            'state' => StateEnum::MH->value,
            'country' => CountryEnum::India->value,
        ])
        ->assertSessionHasErrors(['postal_code']);
});

it('validates state must be a valid enum value', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/profile', [
            'name' => 'Test',
            'email' => $user->email,
            'address_line_one' => '123 St',
            'phone' => '9876543210',
            'city' => 'Mumbai',
            'postal_code' => '400001',
            'state' => 'InvalidState',
            'country' => CountryEnum::India->value,
        ])
        ->assertSessionHasErrors(['state']);
});

it('validates country must be a valid enum value', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/profile', [
            'name' => 'Test',
            'email' => $user->email,
            'address_line_one' => '123 St',
            'phone' => '9876543210',
            'city' => 'Mumbai',
            'postal_code' => '400001',
            'state' => StateEnum::MH->value,
            'country' => 'InvalidCountry',
        ])
        ->assertSessionHasErrors(['country']);
});

it('allows address_line_two to be nullable', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/profile', [
            'name' => 'Test',
            'email' => $user->email,
            'address_line_one' => '123 St',
            'address_line_two' => null,
            'phone' => '9876543210',
            'city' => 'Mumbai',
            'postal_code' => '400001',
            'state' => StateEnum::MH->value,
            'country' => CountryEnum::India->value,
        ])
        ->assertRedirect('/profile')
        ->assertSessionHas('success');
});
