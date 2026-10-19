<?php

use App\Enums\BacktestStatusEnum;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Backtest;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withHeader('X-Inertia-Version', app(HandleInertiaRequests::class)->version(Request::create('/')) ?? '');
});

it('lets guests view selected completed backtests including deferred results', function () {
    $backtest = Backtest::factory()->create(['is_public' => true, 'status' => BacktestStatusEnum::Completed]);

    $this->get(route('public-backtests.show', $backtest).'?tab=settings')->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('Backtests/Show')
            ->where('readOnly', true)->where('backtest.id', $backtest->id)
            ->missing('backtest.user_id')->missing('backtest.error_message')
            ->loadDeferredProps(['charts', 'trades', 'allocation'], fn (Assert $reload) => $reload
                ->has('dailySnapshots', 0)->has('defaultBenchmark', 0)->has('trades.data', 0)->where('tradeLogSummary.total', 0)));

    $this->get(route('public-backtests.benchmark', $backtest).'?slug=nifty-50')->assertSuccessful()->assertExactJson([]);
    $this->get(route('backtests.show', $backtest))->assertRedirect(route('login'));
});

it('keeps backtests private by default for guests and signed in visitors', function (bool $signedIn) {
    if ($signedIn) {
        $this->actingAs(User::factory()->create(['is_paid' => true]));
    }
    $backtest = Backtest::factory()->create(['status' => BacktestStatusEnum::Completed]);

    expect($backtest->fresh()->is_public)->toBeFalse();
    $this->get(route('public-backtests.show', $backtest))->assertNotFound();
    $this->get(route('public-backtests.benchmark', $backtest).'?slug=nifty-50')->assertNotFound();
})->with([false, true]);

it('does not expose unfinished public backtests', function (BacktestStatusEnum $status) {
    $backtest = Backtest::factory()->create(['is_public' => true, 'status' => $status]);

    $this->get('/')->assertInertia(fn (Assert $page) => $page->has('backtests', 0));
    $this->get(route('public-backtests.show', $backtest))->assertNotFound();
    $this->get(route('public-backtests.benchmark', $backtest).'?slug=nifty-50')->assertNotFound();
})->with([BacktestStatusEnum::Pending, BacktestStatusEnum::Running, BacktestStatusEnum::Failed]);

it('does not grant write access to another users public backtest', function () {
    $backtest = Backtest::factory()->create(['is_public' => true, 'status' => BacktestStatusEnum::Completed]);
    $this->actingAs(User::factory()->create(['is_paid' => true]));

    $this->get(route('public-backtests.show', $backtest))->assertSuccessful();
    $this->get('/backtests/'.$backtest->id.'/edit')->assertNotFound();
    $this->putJson('/backtests/'.$backtest->id, array_replace($backtest->toArray(), ['name' => 'Changed']))->assertNotFound();
    $this->post('/backtests/'.$backtest->id.'/run')->assertNotFound();
    $this->post('/backtests/'.$backtest->id.'/duplicate')->assertNotFound();
    $this->delete('/backtests/'.$backtest->id)->assertNotFound();
    expect($backtest->fresh()->name)->toBe($backtest->name);
});

it('keeps the example flag separate from user strategy settings', function (bool $isPublic) {
    $owner = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->for($owner)->create([
        'is_public' => $isPublic, 'status' => BacktestStatusEnum::Completed,
    ]);

    $this->actingAs($owner)->putJson('/backtests/'.$backtest->id, array_replace($backtest->toArray(), [
        'name' => 'Renamed strategy', 'is_public' => ! $isPublic,
    ]))->assertRedirect();

    expect($backtest->fresh()->name)->toBe('Renamed strategy')
        ->and($backtest->fresh()->is_public)->toBe($isPublic);
})->with([false, true]);

it('removes a backtest from home and all guest result requests when its public flag is cleared', function () {
    $backtest = Backtest::factory()->create(['is_public' => true, 'status' => BacktestStatusEnum::Completed]);
    $this->get('/')->assertInertia(fn (Assert $page) => $page->has('backtests', 1)->where('backtests.0.id', $backtest->id));
    $this->get(route('public-backtests.show', $backtest))->assertSuccessful();
    $backtest->update(['is_public' => false]);

    $this->get('/')->assertInertia(fn (Assert $page) => $page->has('backtests', 0)->where('backtestCount', 0));
    $this->get(route('public-backtests.show', $backtest))->assertNotFound();
    $this->get(route('public-backtests.show', $backtest), [
        'X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'Backtests/Show', 'X-Inertia-Partial-Data' => 'trades',
    ])->assertNotFound();
    $this->get(route('public-backtests.benchmark', $backtest).'?slug=nifty-50')->assertNotFound();
});
