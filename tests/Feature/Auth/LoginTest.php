<?php

use App\Models\User;

it('shows the login page', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/Login'));
});

it('redirects authenticated users away from login', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/login')
        ->assertRedirect('/');
});

it('validates required fields', function () {
    $this->post('/login', [
        'email' => '',
        'password' => '',
    ])
        ->assertSessionHasErrors(['email', 'password']);
});

it('shows error for invalid credentials', function () {
    $this->post('/login', [
        'email' => 'abc@example.com',
        'password' => 'secret@123',
    ])
        ->assertSessionHasErrors(['email']);

    $this->assertGuest();
});

it('logs in a user with valid credentials', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])
        ->assertRedirect('/');

    $this->assertAuthenticated();
});

it('logs in a user with remember me', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
        'remember_me' => true,
    ])
        ->assertRedirect('/');

    $this->assertAuthenticated();
});

it('logs out an authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/logout')
        ->assertRedirect('/login');

    $this->assertGuest();
});
