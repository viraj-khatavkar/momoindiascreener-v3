<?php

use App\Actions\ApplyScreenFiltersAction;
use App\Enums\BacktestStatusEnum;
use App\Models\Backtest;
use App\Models\BacktestSummaryMetric;
use App\Models\Screen;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $owner = User::factory()->create(['name' => 'Public Screens', 'is_paid' => true]);

    foreach (Screen::PUBLIC_SCREENS as $screenId) {
        Screen::factory()->create(['id' => $screenId, 'user_id' => $owner->id, 'name' => 'Public Screen '.$screenId]);
    }
});

it('shows only public screen cards and selected completed backtests to guests', function () {
    Screen::factory()->create(['name' => 'Private Screen']);
    $public = Backtest::factory()->create(['is_public' => true, 'status' => BacktestStatusEnum::Completed]);
    Backtest::factory()->create(['is_public' => true, 'status' => BacktestStatusEnum::Pending]);
    Backtest::factory()->create(['status' => BacktestStatusEnum::Completed]);
    $this->mock(ApplyScreenFiltersAction::class)->shouldNotReceive('execute');

    $this->get('/')->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->component('Welcome')->where('auth.user', null)
        ->has('screens', 3)->where('screenCount', 3)
        ->where('screens.0.id', 1)->where('screens.1.id', 2)->where('screens.2.id', 3)
        ->has('backtests', 1)->where('backtestCount', 1)
        ->where('backtests.0.id', $public->id)
        ->where('backtests.0.url', route('public-backtests.show', $public))
        ->missing('publicScreenPreviews')->missing('personalScreenPreviews'));
});

it('shows public screens owned by the user in their personal screens', function () {
    $owner = User::query()->where('name', 'Public Screens')->firstOrFail();

    $this->actingAs($owner)->get('/')->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->component('Welcome')->has('screens', 3)->where('screenCount', 3));
});

it('shows personal screens and backtests to an unpaid user without including other owners', function () {
    $user = User::factory()->create(['is_paid' => false]);
    $screen = Screen::factory()->create(['user_id' => $user->id, 'name' => 'My Momentum Screen']);
    $backtest = Backtest::factory()->create(['user_id' => $user->id, 'status' => BacktestStatusEnum::Failed]);
    Backtest::factory()->create(['is_public' => true, 'status' => BacktestStatusEnum::Completed]);

    $this->actingAs($user)->get('/')->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->component('Welcome')->where('auth.user.is_paid', false)
        ->has('screens', 1)->where('screenCount', 1)->where('screens.0.id', $screen->id)
        ->has('backtests', 1)->where('backtestCount', 1)->where('backtests.0.id', $backtest->id)
        ->where('backtests.0.url', route('backtests.show', $backtest)));

    $this->get('/backtests/'.$backtest->id)->assertRedirect('/pricing');
});

it('shows all personal run states in one list and only uses completed result summaries', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtests = collect([
        BacktestStatusEnum::Failed, BacktestStatusEnum::Running, BacktestStatusEnum::Pending, BacktestStatusEnum::Completed,
    ])->map(fn (BacktestStatusEnum $status) => Backtest::factory()->create([
        'user_id' => $user->id, 'name' => $status->value.' Strategy', 'status' => $status,
        'progress' => $status === BacktestStatusEnum::Running ? 42 : 0, 'updated_at' => '2026-08-18 12:00:00',
    ]));
    foreach ([$backtests[1], $backtests[3]] as $backtest) {
        BacktestSummaryMetric::create([
            'backtest_id' => $backtest->id, 'cagr' => 0.184, 'max_drawdown' => -0.224, 'total_trades' => 24,
            'total_charges_paid' => 1200, 'final_value' => 7350000,
            'rolling_returns_one_year' => [], 'rolling_returns_three_year' => [], 'rolling_returns_five_year' => [],
        ]);
    }
    Backtest::factory()->create(['status' => BacktestStatusEnum::Completed]);

    $this->actingAs($user)->get('/')->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->component('Welcome')->has('backtests', 4)->where('backtestCount', 4)
        ->where('backtests.0.id', $backtests[3]->id)->where('backtests.0.status', 'completed')
        ->where('backtests.0.summary_metrics.cagr', 0.184)->where('backtests.0.summary_metrics.max_drawdown', -0.224)
        ->where('backtests.1.status', 'pending')->where('backtests.1.summary_metrics', null)
        ->where('backtests.2.status', 'running')->where('backtests.2.progress', 42)->where('backtests.2.summary_metrics', null)
        ->where('backtests.3.status', 'failed')->where('backtests.3.summary_metrics', null));
});

it('bounds personal home lists and includes totals with stable recent ordering', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $screens = Screen::factory()->count(8)->create(['user_id' => $user->id, 'updated_at' => '2026-08-18 12:00:00']);
    $backtests = Backtest::factory()->count(8)->create(['user_id' => $user->id, 'updated_at' => '2026-08-18 12:00:00']);

    $this->actingAs($user)->get('/')->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->has('screens', 6)->where('screenCount', 8)->where('screens.0.id', $screens->last()->id)->where('screens.5.id', $screens[2]->id)
        ->has('backtests', 6)->where('backtestCount', 8)->where('backtests.0.id', $backtests->last()->id)->where('backtests.5.id', $backtests[2]->id));
});

it('keeps a new users lists empty instead of substituting public data', function () {
    Backtest::factory()->create(['is_public' => true, 'status' => BacktestStatusEnum::Completed]);

    $this->actingAs(User::factory()->create())->get('/')->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->has('screens', 0)->where('screenCount', 0)->has('backtests', 0)->where('backtestCount', 0));
});

it('keeps unverified users on the email verification page', function () {
    $this->actingAs(User::factory()->unverified()->create())->get('/')->assertRedirect(route('verification.notice'));
});
