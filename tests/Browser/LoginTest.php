<?php

use App\Models\User;

it('shows validation errors', function () {
    $page = visit('/login')
        ->fill('email', '')
        ->fill('password', '')
        ->press('Sign In')
        ->assertSee('The email field is required.')
        ->assertSee('The password field is required.');

    $this->assertGuest();
});

it('shows correct message for invalid credentials', function () {
    $page = visit('/login')
        ->fill('email', 'abc@example.com')
        ->fill('password', 'secret@123')
        ->press('Sign In')
        ->assertSee('The provided credentials do not match our records.');

    $this->assertGuest();
});

it('redirects to home page post correct login', function () {
    $user = User::factory()->create();

    visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->press('Sign In')
        ->assertPathIs('/');

    $this->assertAuthenticated();
});

it('logs out an authenticated user', function () {
    $user = User::factory()->create();

    loginAs($user->email);

    visit('/logout')
        ->assertPathIs('/login');

    $this->assertGuest();
});
