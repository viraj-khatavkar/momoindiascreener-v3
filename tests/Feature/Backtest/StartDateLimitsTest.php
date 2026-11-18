<?php

use App\Actions\Backtest\BacktestStartDateAction;
use App\Actions\Backtest\RunBacktestAction;
use App\Enums\NseIndexEnum;
use App\Models\Backtest;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

it('rejects dates before selected universe history without scanning prices or queueing', function (string $index, string $firstDate) {
    Queue::fake();
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->for($user)->create();
    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        if (str_contains($query->sql, 'backtest_nse_instrument_prices')) {
            $queries[] = $query->sql;
        }
    });
    $this->actingAs($user)->put('/backtests/'.$backtest->id, array_replace($backtest->toArray(), [
        'index' => $index, 'start_date' => date('Y-m-d', strtotime($firstDate.' -1 day')), 'run' => true,
    ]))->assertSessionHasErrors(['start_date']);
    expect($queries)->toBeEmpty()->and($backtest->fresh()->index)->toBe(NseIndexEnum::NIFTY_ALLCAP);
    Queue::assertNothingPushed();
    $this->put('/backtests/'.$backtest->id, array_replace($backtest->toArray(), [
        'index' => $index, 'start_date' => $firstDate,
    ]))->assertSessionHasNoErrors();
    expect($backtest->fresh()->start_date->toDateString())->toBe($firstDate);
})->with([
    ['nifty_200', '2014-01-01'], ['nifty_midcap_150', '2017-07-31'],
    ['nifty_smallcap_250', '2017-07-31'], ['nifty_largemidcap_250', '2017-07-31'],
    ['nifty_midsmallcap_400', '2017-07-31'], ['nifty_total_market', '2024-11-01'],
    ['nifty_microcap_250', '2024-11-01'], ['nifty_1000', '2026-11-16'],
]);

it('uses the latest requirement across universe and active ranking factors', function () {
    $dates = app(BacktestStartDateAction::class);
    expect($dates->minimumDate(['index' => 'nifty_200', 'sort_by' => 'marketcap']))->toBe('2024-03-01')
        ->and($dates->minimumDate(['index' => 'nifty_total_market', 'sort_by' => 'marketcap']))->toBe('2024-11-01')
        ->and($dates->minimumDate(['index' => 'nifty_allcap', 'sort_by' => 'close_raw', 'apply_factor_two' => true, 'factor_two_sort_by' => 'return_twelve_minus_two_months']))->toBe('2011-02-08')
        ->and($dates->minimumDate(['index' => 'nifty_allcap', 'sort_by' => 'close_raw', 'apply_factor_two' => false, 'factor_two_sort_by' => 'marketcap']))->toBe('2011-01-05');
});

it('enforces the observed delayed return ranking dates', function (string $sort, string $date) {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->for($user)->create();
    $this->actingAs($user)->put('/backtests/'.$backtest->id, array_replace($backtest->toArray(), [
        'sort_by' => $sort, 'start_date' => '2011-01-05',
    ]))->assertSessionHasErrors(['start_date']);
    $this->put('/backtests/'.$backtest->id, array_replace($backtest->toArray(), [
        'sort_by' => $sort, 'start_date' => $date,
    ]))->assertSessionHasNoErrors();
})->with([['return_twelve_minus_one_months', '2011-01-10'], ['return_twelve_minus_two_months', '2011-02-08']]);

it('checks universe dates before direct execution and before worker results are cleared', function () {
    $backtest = Backtest::factory()->create(['index' => 'nifty_1000', 'start_date' => '2026-11-15']);
    $backtest->dailySnapshots()->create(['date' => '2026-11-16', 'nav' => 100, 'portfolio_value' => 0,
        'cash' => 10000, 'total_value' => 10000, 'holdings_count' => 0]);
    expect(fn () => app(RunBacktestAction::class)->validateDataAvailability($backtest))->toThrow(ValidationException::class, '2026-11-16');
    expect(fn () => app(RunBacktestAction::class)->execute($backtest))->toThrow(ValidationException::class, '2026-11-16');
    expect($backtest->dailySnapshots()->count())->toBe(1)->and($backtest->trades()->count())->toBe(0);
});

it('rejects unavailable ETF valuation ranking only for active factors', function (string $sort, int $position) {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->for($user)->create(['index' => 'etf', 'start_date' => '2024-03-01']);
    $field = $position === 1 ? 'sort_by' : 'factor_'.($position === 2 ? 'two' : 'three').'_sort_by';
    $enabled = $position === 1 ? [] : ['apply_factor_'.($position === 2 ? 'two' : 'three') => true];
    $this->actingAs($user)->put('/backtests/'.$backtest->id, array_replace($backtest->toArray(), $enabled, [$field => $sort]))
        ->assertSessionHasErrors([$field]);
    if ($position !== 1) {
        $this->put('/backtests/'.$backtest->id, array_replace($backtest->toArray(), [$field => $sort]))->assertSessionHasNoErrors();
    }
    $backtest->forceFill(array_replace($enabled, [$field => $sort]));
    expect(fn () => app(RunBacktestAction::class)->validateDataAvailability($backtest))->toThrow(ValidationException::class, 'not available for ETFs');
})->with(['marketcap', 'price_to_earnings'])->with([1, 2, 3]);

it('shares every selectable universe limit with the form', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->for($user)->create();
    $this->actingAs($user)->get('/backtests/'.$backtest->id)->assertInertia(fn (Assert $page) => $page
        ->where('startDateLimits.universes', BacktestStartDateAction::UNIVERSE_START_DATES)
        ->where('startDateLimits.sorts.return_twelve_minus_two_months', '2011-02-08'));
    expect(array_keys(BacktestStartDateAction::UNIVERSE_START_DATES))->toEqualCanonicalizing(array_column(NseIndexEnum::cases(), 'value'));
});
