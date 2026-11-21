<?php

use App\Actions\Backtest\CalculateBacktestMetricsAction;
use App\Enums\BacktestStatusEnum;
use App\Jobs\RunBacktestJob;
use App\Models\Backtest;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

it('includes initial costs in monthly returns and displays the saved Sortino after settings change', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id, 'status' => BacktestStatusEnum::Completed, 'cash_return_rate' => 6]);
    foreach (['2024-01-08' => 99, '2024-01-09' => 98, '2024-01-10' => 99] as $date => $nav) {
        $backtest->dailySnapshots()->create([
            'date' => $date, 'nav' => $nav, 'portfolio_value' => 0, 'cash' => $nav * 50000,
            'total_value' => $nav * 50000, 'holdings_count' => 0,
        ]);
    }
    app(CalculateBacktestMetricsAction::class)->execute($backtest);
    $savedSortino = number_format((float) $backtest->summaryMetrics->sortino_ratio, 2);
    $backtest->update(['cash_return_rate' => 20]);
    loginAs($user->email);

    $page = visit('/backtests/'.$backtest->id)
        ->assertAttributeContains('[data-test="performance-metrics"] > div:first-child', 'class', 'text-red-700')
        ->assertAttributeContains('[data-test="performance-metrics"] > div:nth-child(4)', 'class', 'text-red-700')
        ->click('#bt-overview [data-test="risk-statistics"] summary')
        ->assertSeeIn('[data-test="sortino-ratio"]', $savedSortino)
        ->assertAttributeContains('[data-test="sortino-ratio"]', 'class', 'text-red-700')
        ->press('Monthly returns')
        ->assertSeeIn('#bt-monthly tbody tr:first-child td:first-of-type', '-1.0%')
        ->assertSeeIn('#bt-monthly tbody tr:first-child td:last-child', '-1.0%')
        ->assertNoJavaScriptErrors();

    $page->press('Returns & risk')
        ->assertSeeIn('#bt-drawdown tbody tr:first-child td:first-child', '-2.0%')
        ->assertNoJavaScriptErrors();
});

it('shows a failed run with its data error when the worker finds no trading data', function (BacktestStatusEnum $status) {
    Queue::fake();
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id, 'status' => $status]);
    loginAs($user->email);

    $page = visit('/backtests/'.$backtest->id)
        ->press('[data-test="save-and-run"]')
        ->assertDisabled('[data-test="save-and-run"]')
        ->assertNoJavaScriptErrors();

    Queue::assertPushed(RunBacktestJob::class, function (RunBacktestJob $job): bool {
        expect(fn () => app()->call([$job, 'handle']))->toThrow(ValidationException::class);

        return true;
    });
    $page->refresh()->assertSee('This run failed. It will not retry.')
        ->press('Show technical details')
        ->assertSee('At least two trading dates are required')
        ->assertEnabled('[data-test="save-and-run"]')
        ->assertNoJavaScriptErrors();

    expect($backtest->refresh()->status)->toBe(BacktestStatusEnum::Failed);
    Queue::assertPushed(RunBacktestJob::class, 1);
})->with([BacktestStatusEnum::Pending, BacktestStatusEnum::Failed]);

it('shows unavailable CAGR and derived metrics without reporting a zero return', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create([
        'user_id' => $user->id, 'name' => 'Unavailable annual return', 'status' => BacktestStatusEnum::Completed,
    ]);
    foreach (['2024-01-08' => 100, '2024-01-09' => 1000] as $date => $nav) {
        $backtest->dailySnapshots()->create([
            'date' => $date, 'nav' => $nav, 'portfolio_value' => 0, 'cash' => $nav * 50000,
            'total_value' => $nav * 50000, 'holdings_count' => 0,
        ]);
    }
    app(CalculateBacktestMetricsAction::class)->execute($backtest);
    expect($backtest->summaryMetrics->cagr)->toBeNull();
    loginAs($user->email);

    visit('/backtests/'.$backtest->id)
        ->assertSeeIn('[data-test="performance-metrics"] > div:first-child', '—')
        ->assertAttributeContains('[data-test="performance-metrics"] > div:first-child', 'class', 'text-slate-900')
        ->assertDontSeeIn('[data-test="performance-metrics"] > div:first-child', '0.00')
        ->assertSeeIn('[data-test="performance-metrics"] > div:nth-child(2)', '0.00%')
        ->assertAttributeContains('[data-test="performance-metrics"] > div:nth-child(2)', 'class', 'text-slate-900')
        ->assertSeeIn('[data-test="performance-metrics"] > div:nth-child(3)', '—')
        ->assertAttributeContains('[data-test="performance-metrics"] > div:nth-child(3)', 'class', 'text-slate-900')
        ->assertDontSee('Infinity')
        ->assertDontSee('NaN')
        ->assertNoJavaScriptErrors();

    visit('/backtests')
        ->assertSeeIn('tbody tr:first-child td:nth-child(3)', '—')
        ->assertNoJavaScriptErrors();

    visit('/')->assertSee('Unavailable annual return')->assertNoJavaScriptErrors();
});

it('shows an index history error when a DMA run cannot form its cash signal', function () {
    Queue::fake();
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create([
        'user_id' => $user->id, 'start_date' => '2024-01-08', 'cash_call' => 'full_cash_below_index_dma',
    ]);
    foreach (['2024-01-08', '2024-01-09'] as $date) {
        createScreenResultRow('A', 'A', $date);
    }
    loginAs($user->email);

    $page = visit('/backtests/'.$backtest->id)
        ->press('[data-test="save-and-run"]')
        ->assertDisabled('[data-test="save-and-run"]');
    Queue::assertPushed(RunBacktestJob::class, function (RunBacktestJob $job): bool {
        expect(fn () => app()->call([$job, 'handle']))->toThrow(ValidationException::class);

        return true;
    });
    $page->refresh()->press('Show technical details')
        ->assertSee('The selected index needs 50 trading days of prices')
        ->assertNoJavaScriptErrors();

    expect($backtest->refresh()->status)->toBe(BacktestStatusEnum::Failed)
        ->and($backtest->trades()->count())->toBe(0);
});
