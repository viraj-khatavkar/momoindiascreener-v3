<?php

use App\Enums\BacktestStatusEnum;
use App\Models\Backtest;
use App\Models\BacktestSummaryMetric;
use App\Models\Screen;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $owner = User::factory()->create([
        'name' => 'Public Screens',
        'is_paid' => true,
    ]);

    foreach (Screen::PUBLIC_SCREENS as $screenId) {
        Screen::factory()->create([
            'id' => $screenId,
            'user_id' => $owner->id,
            'name' => 'Public Screen '.$screenId,
        ]);
    }
});

it('shows current public screen results to guests', function () {
    createScreenResultRow('ALPHA', 'Alpha Industries', '2026-08-18', [
        'sharpe_return_one_year' => 12.5,
    ]);
    createScreenResultRow('BETA', 'Beta Corp', '2026-08-18', [
        'sharpe_return_one_year' => 99.9,
    ]);

    $this->get('/')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('auth.user', null)
            ->missing('latestMarketDate')
            ->has('publicScreens', 3)
            ->where('publicScreens.0.id', 1)
            ->where('publicScreens.1.id', 2)
            ->where('publicScreens.2.id', 3)
            ->has('personalScreens', 0)
            ->where('personalScreenCount', 0)
            ->has('backtestActivity', 0)
            ->has('recentBacktests', 0)
            ->missing('publicScreenPreviews')
            ->loadDeferredProps('public-screens', fn (Assert $reload) => $reload
                ->has('publicScreenPreviews', 3)
                ->where('publicScreenPreviews.0.screen_id', 1)
                ->where('publicScreenPreviews.0.result_count', 2)
                ->where('publicScreenPreviews.0.result_date', '2026-08-18')
                ->where('publicScreenPreviews.0.top_results.0.symbol', 'BETA')
                ->where('publicScreenPreviews.0.top_results.1.symbol', 'ALPHA'))
        );
});

it('shows public screens owned by the user in their personal screens', function () {
    $owner = User::query()
        ->where('name', 'Public Screens')
        ->firstOrFail();

    $this->actingAs($owner)
        ->get('/')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->has('personalScreens', 3)
            ->where('personalScreenCount', 3)
        );
});

it('shows personal screens but not backtests to an unpaid user', function () {
    $user = User::factory()->create(['is_paid' => false]);
    $screen = Screen::factory()->create([
        'user_id' => $user->id,
        'name' => 'My Momentum Screen',
    ]);
    Backtest::factory()->create([
        'user_id' => $user->id,
        'status' => BacktestStatusEnum::Failed,
    ]);
    createScreenResultRow('MOMO', 'Momentum Ltd', '2026-08-18');

    $this->actingAs($user)
        ->get('/')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('auth.user.id', $user->id)
            ->where('auth.user.is_paid', false)
            ->has('personalScreens', 1)
            ->where('personalScreens.0.id', $screen->id)
            ->where('personalScreens.0.name', 'My Momentum Screen')
            ->where('personalScreenCount', 1)
            ->has('backtestActivity', 0)
            ->has('recentBacktests', 0)
            ->missing('personalScreenPreviews')
            ->loadDeferredProps('personal-screens', fn (Assert $reload) => $reload
                ->has('personalScreenPreviews', 1)
                ->where('personalScreenPreviews.0.screen_id', $screen->id)
                ->where('personalScreenPreviews.0.result_count', 1)
                ->where('personalScreenPreviews.0.top_results.0.symbol', 'MOMO'))
        );
});

it('shows active and recent backtests to a paid user', function () {
    $user = User::factory()->create(['is_paid' => true]);

    $failed = Backtest::factory()->create([
        'user_id' => $user->id,
        'name' => 'Failed Strategy',
        'status' => BacktestStatusEnum::Failed,
    ]);
    $running = Backtest::factory()->create([
        'user_id' => $user->id,
        'name' => 'Running Strategy',
        'status' => BacktestStatusEnum::Running,
        'progress' => 42,
    ]);
    $pending = Backtest::factory()->create([
        'user_id' => $user->id,
        'name' => 'Queued Strategy',
        'status' => BacktestStatusEnum::Pending,
    ]);
    $completed = Backtest::factory()->create([
        'user_id' => $user->id,
        'name' => 'Completed Strategy',
        'status' => BacktestStatusEnum::Completed,
        'completed_at' => '2026-08-18 12:00:00',
    ]);

    BacktestSummaryMetric::create([
        'backtest_id' => $completed->id,
        'cagr' => 0.184,
        'max_drawdown' => -0.224,
        'total_trades' => 24,
        'total_charges_paid' => 1200,
        'final_value' => 7350000,
        'rolling_returns_one_year' => [],
        'rolling_returns_three_year' => [],
        'rolling_returns_five_year' => [],
        'stock_performance' => [],
    ]);

    $this->actingAs($user)
        ->get('/')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('auth.user.is_paid', true)
            ->has('backtestActivity', 3)
            ->where('backtestActivity.0.id', $failed->id)
            ->where('backtestActivity.0.status', 'failed')
            ->where('backtestActivity.1.id', $running->id)
            ->where('backtestActivity.1.progress', 42)
            ->where('backtestActivity.2.id', $pending->id)
            ->where('backtestActivity.2.status', 'pending')
            ->has('recentBacktests', 1)
            ->where('recentBacktests.0.id', $completed->id)
            ->where('recentBacktests.0.summary_metrics.cagr', 0.184)
            ->where('recentBacktests.0.summary_metrics.max_drawdown', -0.224)
        );
});
