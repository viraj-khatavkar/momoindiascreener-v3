<?php

use App\Models\PasswordResetToken;
use App\Models\User;
use Illuminate\Support\Facades\Date;

it('shows the reset password page with valid token', function () {
    $user = User::factory()->create();

    $token = PasswordResetToken::create([
        'email' => $user->email,
        'token' => 'valid-token',
        'created_at' => Date::now(),
    ]);

    $this->get('/reset-password?token=valid-token')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Auth/ResetPassword')
            ->where('token', 'valid-token')
        );
});

it('redirects to forgot password with expired token', function () {
    $user = User::factory()->create();

    PasswordResetToken::create([
        'email' => $user->email,
        'token' => 'expired-token',
        'created_at' => Date::now()->subMinutes(61),
    ]);

    $this->get('/reset-password?token=expired-token')
        ->assertRedirect('forgot-password');
});

it('redirects to forgot password with invalid token', function () {
    $this->get('/reset-password?token=invalid-token')
        ->assertRedirect('forgot-password');
});

it('resets the password with valid token', function () {
    $user = User::factory()->create();

    PasswordResetToken::create([
        'email' => $user->email,
        'token' => 'valid-token',
        'created_at' => Date::now(),
    ]);

    $this->post('/reset-password', [
        'token' => 'valid-token',
        'password' => 'newpassword123',
    ])
        ->assertRedirect('login')
        ->assertSessionHas('success', 'New password set successfully!');

    $this->assertDatabaseMissing('password_reset_tokens', [
        'email' => $user->email,
    ]);
});

it('validates required fields for reset', function () {
    $this->post('/reset-password', [
        'token' => '',
        'password' => '',
    ])
        ->assertSessionHasErrors(['token', 'password']);
});

it('marks email as verified after password reset', function () {
    $user = User::factory()->unverified()->create();

    PasswordResetToken::create([
        'email' => $user->email,
        'token' => 'valid-token',
        'created_at' => Date::now(),
    ]);

    $this->post('/reset-password', [
        'token' => 'valid-token',
        'password' => 'newpassword123',
    ]);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});
