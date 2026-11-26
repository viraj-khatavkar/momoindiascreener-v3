<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('redirects guests to sign in before viewing portfolio performance', function () {
    $this->get('/my-performance')->assertRedirect(route('login'));
});

it('redirects free users to pricing before viewing portfolio performance', function () {
    $user = User::factory()->create(['is_paid' => false]);

    $this->actingAs($user)->get('/my-performance')->assertRedirect('/pricing');
});

it('requires paid users to verify their email before viewing portfolio performance', function () {
    $user = User::factory()->unverified()->create(['is_paid' => true]);

    $this->actingAs($user)->get('/my-performance')->assertRedirect(route('verification.notice'));
});

it('shows the portfolio performance sheet to verified paid users', function () {
    $user = User::factory()->create(['is_paid' => true]);

    $this->actingAs($user)->get('/my-performance')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('MyPerformance')
            ->where('spreadsheetUrl', 'https://docs.google.com/spreadsheets/d/e/2PACX-1vTqHyY_pvnLDi-LPJCrK7uvXWF_Bl6MYN_vOEpArGw7OKbTf7F03_KRoCmla8lV_vNE5nVj8e6_Qm_9/pubhtml?gid=1561259004&single=true&widget=true&headers=false')
        );
});
