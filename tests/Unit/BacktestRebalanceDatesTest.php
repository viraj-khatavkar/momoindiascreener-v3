<?php

use App\Actions\Backtest\BacktestRebalanceDatesAction;
use App\Models\Backtest;
use Carbon\Carbon;
use Tests\TestCase;

uses(TestCase::class);

it('selects the opening session and the calendar dates for the saved schedule', function (
    string $frequency, string $start, int $day, ?int $month, array $sessions, array $expected,
) {
    $backtest = new Backtest(['rebalance_frequency' => $frequency, 'start_date' => $start, 'rebalance_day' => $day, 'rebalance_month' => $month]);
    $dates = collect($sessions)->map(fn (string $date) => Carbon::parse($date));

    expect(array_keys((new BacktestRebalanceDatesAction)->execute($backtest, $dates)))->toBe($expected);
})->with([
    'weekly holiday then incomplete week' => ['weekly', '2024-01-08', 5, null,
        ['2024-01-08', '2024-01-11', '2024-01-15', '2024-01-18'], ['2024-01-08', '2024-01-11']],
    'older month-end setting across leap February' => ['monthly', '2024-01-08', 31, null,
        ['2024-01-08', '2024-01-31', '2024-02-29', '2024-03-27', '2024-03-28'], ['2024-01-08', '2024-01-31', '2024-02-29']],
    'alternate Mondays across ISO week 53, with a holiday and missing week' => ['fortnightly', '2020-12-25', 1, null,
        ['2020-12-28', '2021-01-04', '2021-01-12', '2021-01-18', '2021-02-01', '2021-02-08'], ['2020-12-28', '2021-01-12', '2021-02-08']],
    'weekday before start, first available data afterwards' => ['fortnightly', '2024-01-03', 2, null,
        ['2024-01-15', '2024-01-16', '2024-01-23', '2024-01-30'], ['2024-01-15', '2024-01-23']],
    'fortnightly closure and incomplete scheduled Friday' => ['fortnightly', '2024-01-08', 5, null,
        ['2024-01-08', '2024-01-11', '2024-01-19', '2024-01-25'], ['2024-01-08', '2024-01-11']],
    'quarterly starts in October despite a May opening and July quotes' => ['quarterly', '2024-05-01', 10, 10,
        ['2024-05-02', '2024-07-10', '2024-10-11', '2025-01-10', '2025-03-10', '2025-04-10'], ['2024-05-02', '2024-10-11', '2025-01-10', '2025-04-10']],
    'six-month cycle skips a missing target month and keeps its phase' => ['six_monthly', '2024-01-01', 10, 4,
        ['2024-01-02', '2024-04-10', '2024-07-10', '2024-11-11', '2025-04-10'], ['2024-01-02', '2024-04-10', '2025-04-10']],
    'confirmed yearly month end vs incomplete final month' => ['yearly', '2023-01-01', 28, 2,
        ['2023-01-02', '2023-02-27', '2023-03-01', '2024-02-27'], ['2023-01-02', '2023-02-27']],
    'first chosen date has passed for the year' => ['quarterly', '2024-04-12', 10, 4,
        ['2024-04-12', '2024-07-10', '2024-10-10', '2025-01-10', '2025-04-10', '2025-07-10'], ['2024-04-12', '2025-04-10', '2025-07-10']],
    'the opening and the scheduled target land on the same trading day' => ['yearly', '2024-04-07', 7, 4,
        ['2024-04-08', '2024-04-09', '2025-04-07'], ['2024-04-08', '2025-04-07']],
]);
