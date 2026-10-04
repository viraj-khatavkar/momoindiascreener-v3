<?php

use App\Actions\Backtest\CalculateBacktestMetricsAction;
use App\Enums\BacktestStatusEnum;
use App\Models\Backtest;
use App\Models\User;

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
        ->assertSeeIn('[data-test="sortino-ratio"]', $savedSortino)
        ->press('Monthly')
        ->assertSeeIn('#bt-monthly tbody tr:first-child td:nth-child(2)', '-1.0%')
        ->assertSeeIn('#bt-monthly tbody tr:first-child td:last-child', '-1.0%')
        ->assertNoJavaScriptErrors();

    $page->press('Drawdown')
        ->assertSeeIn('#bt-drawdown tbody tr:first-child td:first-child', '-2.0%')
        ->assertNoJavaScriptErrors();
});

it('shows a clear data error when a run or retry has no trading data', function (BacktestStatusEnum $status, string $button) {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id, 'status' => $status]);
    loginAs($user->email);

    visit('/backtests/'.$backtest->id)
        ->press($button)
        ->assertSeeIn('#start_date-error', 'At least two trading dates are required')
        ->assertNoJavaScriptErrors();

    expect($backtest->refresh()->status)->toBe($status);
})->with([
    [BacktestStatusEnum::Pending, 'Run Backtest'],
    [BacktestStatusEnum::Failed, 'Retry run'],
]);
