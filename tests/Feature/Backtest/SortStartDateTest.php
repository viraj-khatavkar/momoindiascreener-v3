<?php

use App\Actions\Backtest\CalculateBacktestMetricsAction;
use App\Actions\Backtest\RunBacktestAction;
use App\Enums\BacktestStatusEnum;
use App\Jobs\RunBacktestJob;
use App\Models\Backtest;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array<string, string|bool> */
function sortDateFactorSettings(string $sort, int $position): array
{
    if ($position === 1) {
        return ['sort_by' => $sort];
    }

    $factor = $position === 2 ? 'two' : 'three';

    return ['apply_factor_'.$factor => true, 'factor_'.$factor.'_sort_by' => $sort];
}

it('rejects valuation sorts before their first data date without saving or queueing', function (string $sort, int $position, bool $run) {
    Queue::fake();
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->for($user)->create();
    $settings = $backtest->refresh()->getAttributes();
    $priceQueries = [];
    DB::listen(function (QueryExecuted $query) use (&$priceQueries): void {
        if (str_contains($query->sql, 'backtest_nse_instrument_prices')) {
            $priceQueries[] = $query->sql;
        }
    });

    $this->actingAs($user)->put('/backtests/'.$backtest->id, array_merge($backtest->toArray(), sortDateFactorSettings($sort, $position), [
        'start_date' => '2024-02-29', 'run' => $run,
    ]))->assertSessionHasErrors(['start_date' => 'The selected universe and settings require a start date on or after 2024-03-01.']);

    expect($backtest->fresh()->getAttributes())->toBe($settings)->and($priceQueries)->toBeEmpty();
    Queue::assertNothingPushed();
})->with(['marketcap', 'price_to_earnings'])->with([1, 2, 3])->with([false, true]);

it('accepts valuation sorting on and after the first data date', function (string $sort, int $position, string $date) {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->for($user)->create();

    $this->actingAs($user)->put('/backtests/'.$backtest->id, array_merge($backtest->toArray(), sortDateFactorSettings($sort, $position), [
        'start_date' => $date,
    ]))->assertSessionHasNoErrors();

    expect($backtest->fresh()->start_date->toDateString())->toBe($date);
})->with(['marketcap', 'price_to_earnings'])->with([1, 2, 3])->with(['2024-03-01', '2024-03-04']);

it('keeps the original start date available when valuation factors are disabled', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->for($user)->create();

    $this->actingAs($user)->put('/backtests/'.$backtest->id, array_merge($backtest->toArray(), [
        'sort_by' => 'close_raw', 'start_date' => '2011-01-05',
        'apply_factor_two' => false, 'factor_two_sort_by' => 'marketcap',
        'apply_factor_three' => false, 'factor_three_sort_by' => 'price_to_earnings',
    ]))->assertSessionHasNoErrors();

    expect($backtest->fresh()->start_date->toDateString())->toBe('2011-01-05');
});

it('provides the same sort date limits to the settings form', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->for($user)->create();

    $this->actingAs($user)->get('/backtests/'.$backtest->id)->assertInertia(fn (Assert $page) => $page
        ->where('startDateLimits.default', '2011-01-05')
        ->where('startDateLimits.sorts.marketcap', '2024-03-01')
        ->where('startDateLimits.sorts.price_to_earnings', '2024-03-01'));
});

it('executes valuation sorting from its first data date', function (string $sort) {
    foreach (['2024-03-01', '2024-03-04'] as $date) {
        createScreenResultRow('SMALL', 'Small', $date, ['marketcap' => 1000, 'price_to_earnings' => 10]);
        createScreenResultRow('LARGE', 'Large', $date, ['marketcap' => 2000, 'price_to_earnings' => 20]);
    }
    $backtest = Backtest::factory()->create(['start_date' => '2024-03-01', 'sort_by' => $sort, 'max_stocks_to_hold' => 1]);

    app(RunBacktestAction::class)->validateDataAvailability($backtest);
    app(RunBacktestAction::class)->execute($backtest);

    expect($backtest->trades()->where('trade_type', 'buy')->pluck('symbol')->all())->toBe(['LARGE']);
})->with(['marketcap', 'price_to_earnings']);

it('rejects an old saved valuation start date before direct execution', function () {
    $backtest = Backtest::factory()->make(['user_id' => 1, 'start_date' => '2011-01-05', 'sort_by' => 'marketcap']);

    expect(fn () => app(RunBacktestAction::class)->execute($backtest))->toThrow(ValidationException::class, '2024-03-01');
});

it('keeps saved source and dependent results when either valuation start date is invalid', function (string $invalidBacktest) {
    $user = User::factory()->create();
    $source = Backtest::factory()->for($user)->create([
        'start_date' => '2024-03-01', 'status' => BacktestStatusEnum::Running, 'started_at' => now(),
    ]);
    $dependent = Backtest::factory()->for($user)->create([
        'start_date' => '2024-03-01', 'status' => BacktestStatusEnum::Running, 'started_at' => now(),
        'cash_call' => 'full_cash_below_index_dma', 'cash_call_source' => 'backtest', 'cash_call_backtest_id' => $source->id,
    ]);
    ($invalidBacktest === 'source' ? $source : $dependent)->update(['sort_by' => 'price_to_earnings', 'start_date' => '2011-01-05']);
    foreach (['2024-03-01', '2024-03-04'] as $date) {
        createScreenResultRow('AVAILABLE', 'Available', $date);
    }
    foreach ([$source, $dependent] as $backtest) {
        $backtest->dailySnapshots()->create(['date' => '2024-03-01', 'nav' => 100, 'portfolio_value' => 0,
            'cash' => 10000, 'total_value' => 10000, 'holdings_count' => 0]);
    }
    $this->mock(CalculateBacktestMetricsAction::class)->shouldNotReceive('execute');
    $job = new RunBacktestJob($source, dependent: $dependent);

    expect(fn () => app()->call([$job, 'handle']))->toThrow(ValidationException::class, '2024-03-01');

    expect($source->dailySnapshots()->count())->toBe(1)
        ->and($dependent->dailySnapshots()->count())->toBe(1)
        ->and($source->fresh()->status)->toBe(BacktestStatusEnum::Failed)
        ->and($dependent->fresh()->status)->toBe(BacktestStatusEnum::Failed)
        ->and($job->chained)->toBeEmpty();
})->with(['source', 'dependent']);
