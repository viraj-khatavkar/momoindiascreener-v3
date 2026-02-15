<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('shows the change password page for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/change-password')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('ChangePassword'));
});

it('redirects guests to login', function () {
    $this->get('/change-password')
        ->assertRedirect('/login');
});

it('validates required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/change-password', [
            'old_password' => '',
            'new_password' => '',
        ])
        ->assertSessionHasErrors(['old_password', 'new_password']);
});

it('validates the old password is correct', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/change-password', [
            'old_password' => 'wrong-password',
            'new_password' => 'newpassword123',
        ])
        ->assertSessionHasErrors(['old_password']);
});

it('changes the password successfully', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/change-password', [
            'old_password' => 'password',
            'new_password' => 'newpassword123',
        ])
        ->assertRedirect('/change-password')
        ->assertSessionHas('success', 'Password changed successfully');

    expect(Hash::check('newpassword123', $user->fresh()->password))->toBeTrue();
});
