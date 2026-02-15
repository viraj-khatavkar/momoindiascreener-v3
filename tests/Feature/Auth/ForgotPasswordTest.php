<?php

use App\Models\User;
use Illuminate\Support\Facades\Mail;

it('shows the forgot password page', function () {
    $this->get('/forgot-password')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/ForgotPassword'));
});

it('validates email is required', function () {
    $this->post('/forgot-password', [
        'email' => '',
    ])
        ->assertSessionHasErrors(['email']);
});

it('sends a password reset link for existing user', function () {
    Mail::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', [
        'email' => $user->email,
    ])
        ->assertRedirect('forgot-password')
        ->assertSessionHas('success', 'Password Reset Link Sent!');

    $this->assertDatabaseHas('password_reset_tokens', [
        'email' => $user->email,
    ]);
});

it('shows success even for non-existing email', function () {
    $this->post('/forgot-password', [
        'email' => 'nonexistent@example.com',
    ])
        ->assertRedirect('forgot-password')
        ->assertSessionHas('success', 'Password Reset Link Sent!');
});
