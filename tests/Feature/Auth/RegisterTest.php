<?php

use App\Models\User;

it('shows the register page', function () {
    $this->get('/register')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/Register'));
});

it('redirects authenticated users away from register', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/register')
        ->assertRedirect('/');
});

it('validates required fields', function () {
    $this->post('/register', [
        'name' => '',
        'email' => '',
        'password' => '',
    ])
        ->assertSessionHasErrors(['name', 'email', 'password']);
});

it('validates email is unique', function () {
    $user = User::factory()->create();

    $this->post('/register', [
        'name' => 'Test User',
        'email' => $user->email,
        'password' => 'password123',
    ])
        ->assertSessionHasErrors(['email']);
});

it('validates password minimum length', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'short',
    ])
        ->assertSessionHasErrors(['password']);
});

it('registers a new user', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
    ])
        ->assertRedirect('/profile');

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);
});
