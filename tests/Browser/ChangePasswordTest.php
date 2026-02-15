<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('shows validation errors', function () {
    $user = User::factory()->create();

    loginAs($user->email);

    visit('/change-password')
        ->assertPathIs('/change-password')
        ->fill('old_password', '')
        ->fill('new_password', '')
        ->press('Update Password')
        ->assertSee('The old password field is required.')
        ->assertSee('The new password field is required.');
});

it('shows error for incorrect old password', function () {
    $user = User::factory()->create();

    loginAs($user->email);

    visit('/change-password')
        ->assertPathIs('/change-password')
        ->fill('old_password', 'wrong-password')
        ->fill('new_password', 'newpassword123')
        ->press('Update Password')
        ->assertSee('The password is incorrect.');
});

it('changes password successfully', function () {
    $user = User::factory()->create();

    loginAs($user->email);

    visit('/change-password')
        ->assertPathIs('/change-password')
        ->fill('old_password', 'password')
        ->fill('new_password', 'newpassword123')
        ->press('Update Password')
        ->assertSee('Password changed successfully');

    expect(Hash::check('newpassword123', $user->fresh()->password))->toBeTrue();
});
