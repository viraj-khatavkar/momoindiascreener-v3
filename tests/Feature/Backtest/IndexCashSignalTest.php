<?php

use App\Actions\Backtest\RunBacktestAction;
use App\Actions\Backtest\StartBacktestRunAction;
use App\Enums\BacktestStatusEnum;
use App\Jobs\RunBacktestJob;
use App\Models\Backtest;
use App\Models\NseIndex;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

function cashIndexQuote(string $date, float $close = 100): void
{
    NseIndex::query()->insert([
        'symbol' => 'Nifty 50', 'slug' => 'nifty-50', 'date' => $date,
        'open' => $close, 'high' => $close, 'low' => $close, 'close' => $close,
        'points_change' => 0, 'percentage_change' => 0, 'volume' => 0, 'turnover' => 0,
        'price_to_earnings' => 20, 'price_to_book' => 3, 'dividend_yield' => 1.5,
    ]);
}

/** @param array<string, mixed> $settings */
function cashIndexBacktest(array $settings = []): Backtest
{
    $backtest = Backtest::factory()->create([
        'user_id' => User::factory()->create(['is_paid' => true])->id,
        'start_date' => '2024-01-08', 'initial_capital' => 10000, 'max_stocks_to_hold' => 1,
        'cash_call' => 'full_cash_below_index_dma', 'cash_call_dma_period' => 20,
        'rebalance_frequency' => 'weekly', 'rebalance_day' => 1, 'cash_return_rate' => 0,
        'brokerage_rate' => 0, 'stt_rate' => 0, 'transaction_charges_rate' => 0,
        'sebi_charges_rate' => 0, 'gst_rate' => 0, 'stamp_charges_rate' => 0,
        ...$settings,
    ]);

    for ($offset = $backtest->cash_call_dma_period - 1; $offset > 0; $offset--) {
        cashIndexQuote(Carbon::parse('2024-01-08')->subWeekdays($offset)->toDateString());
    }
    foreach (['2024-01-08', '2024-01-09', '2024-01-10', '2024-01-15', '2024-01-16'] as $date) {
        createScreenResultRow('A', 'A', $date);
        cashIndexQuote($date);
    }

    return $backtest;
}

it('queues invalid index data and fails in the worker without clearing saved results', function (string $problem, bool $nextDay) {
    Queue::fake();
    $backtest = cashIndexBacktest(['execute_next_trading_day' => $nextDay, 'status' => 'completed', 'progress' => 100]);
    $backtest->dailySnapshots()->create([
        'date' => '2023-01-01', 'nav' => 105, 'cash' => 10500, 'portfolio_value' => 0,
        'total_value' => 10500, 'holdings_count' => 0,
    ]);
    $backtest->summaryMetrics()->create([
        'cagr' => 0.05, 'max_drawdown' => 0, 'total_trades' => 0,
        'total_charges_paid' => 0, 'final_value' => 10500,
    ]);

    match ($problem) {
        'missing history' => NseIndex::query()->where('date', '<', '2024-01-08')->delete(),
        'missing first date' => NseIndex::query()->where('date', '2024-01-08')->delete(),
        'missing later date' => NseIndex::query()->where('date', '2024-01-15')->delete(),
        'zero close' => NseIndex::query()->where('date', '2024-01-09')->update(['close' => 0]),
        'negative close' => NseIndex::query()->where('date', '2024-01-09')->update(['close' => -1]),
        'invalid warmup' => NseIndex::query()->where('date', '2024-01-05')->update(['close' => 0]),
        'wrong index' => $backtest->update(['cash_call_index' => 'nifty-500']),
    };

    $this->actingAs($backtest->user)->post('/backtests/'.$backtest->id.'/run')
        ->assertSessionHasNoErrors()->assertRedirect();

    expect($backtest->refresh()->status)->toBe(BacktestStatusEnum::Running)
        ->and($backtest->progress)->toBe(0);
    $job = Queue::pushed(RunBacktestJob::class)->sole();
    expect(fn () => app()->call([$job, 'handle']))->toThrow(ValidationException::class);

    expect($backtest->refresh()->status)->toBe(BacktestStatusEnum::Failed)
        ->and($backtest->error_message)->not->toBeEmpty()
        ->and((float) $backtest->dailySnapshots()->sole()->nav)->toBe(105.0)
        ->and((float) $backtest->summaryMetrics()->sole()->final_value)->toBe(10500.0);
    Queue::assertPushed(RunBacktestJob::class, 1);
})->with(['missing history', 'missing first date', 'missing later date', 'zero close', 'negative close', 'invalid warmup', 'wrong index'])->with([false, true]);

it('rejects a missing market date inside the DMA warmup instead of substituting an older quote', function () {
    $backtest = cashIndexBacktest();
    cashIndexQuote(Carbon::parse('2024-01-08')->subWeekdays(20)->toDateString());
    createScreenResultRow('A', 'A', '2024-01-05');
    NseIndex::query()->where('date', '2024-01-05')->delete();

    expect(fn () => app(StartBacktestRunAction::class)->execute($backtest))
        ->toThrow(ValidationException::class, '2024-01-05');
    expect($backtest->trades()->count())->toBe(0);
});

it('checks index data again in the worker before producing trades', function () {
    Queue::fake();
    $backtest = cashIndexBacktest();
    app(StartBacktestRunAction::class)->execute($backtest);
    NseIndex::query()->where('date', '2024-01-09')->delete();

    expect(fn () => app()->call([new RunBacktestJob($backtest), 'handle']))
        ->toThrow(ValidationException::class, '2024-01-09');

    expect($backtest->refresh()->status)->toBe(BacktestStatusEnum::Failed)
        ->and($backtest->error_message)->toContain('2024-01-09')
        ->and($backtest->trades()->count())->toBe(0)
        ->and($backtest->dailySnapshots()->count())->toBe(0);
});

it('requires valid index data for every DMA cash mode even when the engine is called directly', function (string $mode) {
    $backtest = cashIndexBacktest(['cash_call' => $mode]);
    NseIndex::query()->where('date', '2024-01-09')->delete();

    expect(fn () => app(RunBacktestAction::class)->execute($backtest))
        ->toThrow(ValidationException::class, '2024-01-09');
    expect($backtest->trades()->count())->toBe(0);
})->with([
    'full_cash_below_index_dma', 'only_exits_below_index_dma', 'allocate_to_gold_below_index_dma',
    'only_exits_allocate_to_gold_below_index_dma', 'only_exits_allocate_to_gold_above_dma_below_index_dma',
]);

it('uses complete index history and decision date signals for each supported DMA period', function (int $period, bool $nextDay) {
    $backtest = cashIndexBacktest(['cash_call_dma_period' => $period, 'execute_next_trading_day' => $nextDay]);
    NseIndex::query()->whereBetween('date', ['2024-01-08', '2024-01-10'])->update(['close' => 50]);
    NseIndex::query()->where('date', '2024-01-15')->update(['close' => 200]);
    NseIndex::query()->where('date', '2024-01-16')->update(['close' => 1]);

    app(StartBacktestRunAction::class)->execute($backtest);

    expect($backtest->refresh()->status)->toBe(BacktestStatusEnum::Completed)
        ->and($backtest->trades()->sole()->date->toDateString())->toBe($nextDay ? '2024-01-16' : '2024-01-15');
})->with([20, 50, 100, 200])->with([false, true]);

it('does not require index quotes for cash modes that do not use an index', function (string $mode) {
    $backtest = cashIndexBacktest(['cash_call' => $mode]);
    NseIndex::query()->delete();

    app(StartBacktestRunAction::class)->execute($backtest);

    expect($backtest->refresh()->status)->toBe(BacktestStatusEnum::Completed)
        ->and($backtest->trades()->count())->toBeGreaterThan(0);
})->with(['no_cash_call', 'cash_call_if_not_enough_stocks']);

it('does not read index observations beyond the captured data end date', function () {
    $backtest = cashIndexBacktest();
    NseIndex::query()->where('date', '2024-01-16')->update(['close' => 0]);

    app(RunBacktestAction::class)->validateDataAvailability($backtest, '2024-01-15');
    app(RunBacktestAction::class)->execute($backtest, '2024-01-15');

    expect($backtest->dailySnapshots()->orderByDesc('date')->first()->date->toDateString())->toBe('2024-01-15');
});
