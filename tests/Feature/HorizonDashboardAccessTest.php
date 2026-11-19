<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Horizon\Contracts\MetricsRepository;

it('lets a verified admin open Horizon and read its metrics with a web session', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->withSession([Auth::guard('web')->getName() => $admin->getAuthIdentifier()]);
    $this->mock(MetricsRepository::class)->shouldReceive('measuredJobs')->once()->andReturn(['ExampleJob']);

    $this->get(route('horizon.index'))->assertOk()->assertViewIs('horizon::layout');
    $this->getJson(route('horizon.jobs-metrics.index'))->assertOk()->assertExactJson(['ExampleJob']);
});

it('denies Horizon dashboard and API access without a verified admin account', function (?array $attributes) {
    if ($attributes !== null) {
        $this->actingAs(User::factory()->create($attributes));
    }

    $this->get(route('horizon.index'))->assertForbidden();
    $this->getJson(route('horizon.jobs-metrics.index'))->assertForbidden();
})->with([
    'guest' => [null],
    'paid member' => [['is_admin' => false, 'is_paid' => true]],
    'unverified admin' => [['is_admin' => true, 'email_verified_at' => null]],
]);
