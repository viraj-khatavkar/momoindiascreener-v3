<?php

use App\Enums\CountryEnum;
use App\Enums\StateEnum;
use App\Models\User;

it('shows validation errors on empty submission', function () {
    $user = User::factory()->create();

    loginAs($user->email);

    visit('/profile')
        ->assertPathIs('/profile')
        ->fill('name', '')
        ->fill('email', '')
        ->fill('address_line_one', '')
        ->fill('phone', '')
        ->fill('city', '')
        ->fill('postal_code', '')
        ->press('Update')
        ->assertSee('The name field is required.')
        ->assertSee('The email field is required.')
        ->assertSee('The address line one field is required.')
        ->assertSee('The phone field is required.')
        ->assertSee('The city field is required.')
        ->assertSee('The postal code field is required.');
});

it('updates profile successfully', function () {
    $user = User::factory()->create([
        'state' => StateEnum::MH,
        'country' => CountryEnum::India,
    ]);

    loginAs($user->email);

    visit('/profile')
        ->assertPathIs('/profile')
        ->fill('name', 'Updated Name')
        ->fill('email', 'updated@example.com')
        ->fill('address_line_one', '456 New Street')
        ->fill('address_line_two', 'Floor 2')
        ->fill('phone', '9876543210')
        ->fill('city', 'Pune')
        ->fill('postal_code', '411001')
        ->select('state', StateEnum::KA->value)
        ->select('country', CountryEnum::India->value)
        ->press('Update')
        ->assertSee('Profile updated!');

    $user->refresh();

    expect($user->name)->toBe('Updated Name')
        ->and($user->email)->toBe('updated@example.com')
        ->and($user->address_line_one)->toBe('456 New Street')
        ->and($user->city)->toBe('Pune');
});

it('shows profile page with pre-filled data', function () {
    $user = User::factory()->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'address_line_one' => '789 Oak Ave',
        'city' => 'Delhi',
        'phone' => '1234567890',
        'postal_code' => '110001',
        'state' => StateEnum::DL,
        'country' => CountryEnum::India,
    ]);

    loginAs($user->email);

    visit('/profile')
        ->assertPathIs('/profile')
        ->assertSee('Profile')
        ->assertSee('Update your profile information.')
        ->assertNoJavaScriptErrors();
});
