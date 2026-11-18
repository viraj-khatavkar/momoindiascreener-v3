<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->admin = User::factory()->create(['is_admin' => true, 'is_paid' => true]);
    $this->member = User::factory()->create();
});

it('lets an admin use a member account and return to the user list', function (): void {
    $page = $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk();
    $users = collect($page->inertiaProps('users.data'))->keyBy('id');
    expect($users[$this->admin->id]['ghost_url'])->toBeNull()
        ->and($page->inertiaProps('auth.returnToAdminUrl'))->toBeNull();

    $this->withSession(['rebalance.portfolio' => ['admin data']]);
    $adminSessionId = session()->getId();
    $adminToken = session()->token();
    $this->withCookie(config('session.cookie'), $adminSessionId)
        ->post($users[$this->member->id]['ghost_url'])
        ->assertRedirectToRoute('home')
        ->assertSessionMissing('rebalance.portfolio')
        ->assertCookieMissing(Auth::getRecallerName());
    $this->assertAuthenticatedAs($this->member);
    $memberSessionId = session()->getId();
    $memberToken = session()->token();
    expect($memberToken)->not->toBe($adminToken)
        ->and($memberSessionId)->not->toBe($adminSessionId);

    Auth::forgetGuards();
    $this->withCookie(config('session.cookie'), $memberSessionId);
    $profile = $this->get('/profile')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('auth.user.id', $this->member->id)
        ->where('auth.user.is_admin', false)
        ->where('profile.email', $this->member->email)
        ->where('auth.returnToAdminUrl', route('ghosting.destroy')));
    expect($profile->inertiaPage()['clearHistory'])->toBeTrue()
        ->and($profile->inertiaPage()['encryptHistory'])->toBeTrue();
    $this->get(route('admin.users.index'))->assertNotFound();
    $this->get(route('rebalance-tracker.index'))->assertRedirect('/pricing');
    $this->post(route('admin.users.ghost', $this->member))->assertNotFound();

    $this->withSession(['rebalance.portfolio' => ['member data']])
        ->delete($profile->inertiaProps('auth.returnToAdminUrl'), ['admin_id' => $this->member->id])
        ->assertRedirectToRoute('admin.users.index')
        ->assertSessionMissing('rebalance.portfolio')
        ->assertSessionMissing('ghosting');
    expect(session()->token())->not->toBe($memberToken)
        ->and(session()->getId())->not->toBe($memberSessionId);

    Auth::forgetGuards();
    $this->withCookie(config('session.cookie'), session()->getId())
        ->get(route('admin.users.index'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('auth.user.id', $this->admin->id)
        ->where('auth.returnToAdminUrl', null));
});

it('requires a verified admin to start ghosting', function (): void {
    $start = route('admin.users.ghost', $this->member);
    $this->post($start)->assertRedirectToRoute('login');
    $this->actingAs($this->member)->post($start)->assertNotFound();
    $this->actingAs(User::factory()->unverified()->create(['is_admin' => true]))
        ->post($start)->assertRedirectToRoute('verification.notice');
});

it('excludes the acting admin and other admin accounts', function (): void {
    $otherAdmin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->admin);
    $this->post(route('admin.users.ghost', $this->admin))->assertForbidden();
    $this->post(route('admin.users.ghost', $otherAdmin))->assertForbidden()->assertSessionMissing('ghosting');
    $this->assertAuthenticatedAs($this->admin);
});

it('requires an active ghost session to return to an admin account', function (): void {
    $this->delete(route('ghosting.destroy'))->assertRedirectToRoute('login');
    $this->actingAs($this->member)
        ->delete(route('ghosting.destroy'), ['admin_id' => $this->admin->id])
        ->assertForbidden();
    $this->assertAuthenticatedAs($this->member);
});

it('can return from an unverified member account', function (): void {
    $member = User::factory()->unverified()->create();
    $this->actingAs($this->admin)->post(route('admin.users.ghost', $member))->assertRedirectToRoute('home');
    $this->get(route('home'))->assertRedirectToRoute('verification.notice');
    $this->get(route('verification.notice'))->assertInertia(fn (Assert $page) => $page
        ->where('auth.user.id', $member->id)
        ->where('auth.returnToAdminUrl', route('ghosting.destroy')));
    $this->delete(route('ghosting.destroy'))->assertRedirectToRoute('admin.users.index');
    $this->assertAuthenticatedAs($this->admin);
});

it('ends ghosting when account access changes', function (string $account, ?array $changes): void {
    $this->actingAs($this->admin)->post(route('admin.users.ghost', $this->member))->assertRedirectToRoute('home');
    if ($changes === null) {
        $this->{$account}->delete();
    } else {
        $this->{$account}->update($changes);
    }

    Auth::forgetGuards();
    $this->withCookie(config('session.cookie'), session()->getId())
        ->get('/profile')->assertRedirectToRoute('login')->assertSessionMissing('ghosting');
    $this->assertGuest();
})->with([
    'admin access revoked' => ['admin', ['is_admin' => false]],
    'admin email unverified' => ['admin', ['email_verified_at' => null]],
    'admin removed' => ['admin', null],
    'member promoted to admin' => ['member', ['is_admin' => true]],
    'member removed' => ['member', null],
]);

it('signs out of ghosting without changing the member remember token', function (): void {
    $rememberToken = $this->member->getRememberToken();
    $this->actingAs($this->admin)->post(route('admin.users.ghost', $this->member))->assertRedirectToRoute('home');
    $this->get(route('auth.logout'))->assertRedirectToRoute('login')->assertSessionMissing('ghosting');
    $this->assertGuest();
    expect($this->member->fresh()->getRememberToken())->toBe($rememberToken);
});
