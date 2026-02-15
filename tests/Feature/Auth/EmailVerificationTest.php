<?php

use App\Models\User;

it('shows the email verification page for unverified users', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get('/email/verify')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/VerifyEmail'));
});

it('redirects guests to login', function () {
    $this->get('/email/verify')
        ->assertRedirect('/login');
});

it('sends a verification notification', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->post('/email/verification-notification')
        ->assertRedirect()
        ->assertSessionHas('success', 'Verification link sent!');
});

it('blocks unverified users from accessing protected routes', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertRedirect('/email/verify');
});
