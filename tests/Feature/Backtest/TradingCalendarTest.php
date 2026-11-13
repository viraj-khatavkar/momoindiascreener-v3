<?php

use App\Actions\Backtest\BacktestTradingCalendarAction;
use App\Enums\NseIndexEnum;

it('keeps the exact historical universe calendar including zero quotes', function (NseIndexEnum $index) {
    $membership = [$index->isIndexFieldName() => true];
    createBacktestPriceRow('EARLY', '2024-01-01', $membership);
    createBacktestPriceRow('OUTSIDE', '2024-01-02', ['close_adjusted' => 100]);
    createBacktestPriceRow('ZERO', '2024-01-03', $membership);
    createBacktestPriceRow('FIRST', '2024-01-05', $membership);
    createBacktestPriceRow('SECOND', '2024-01-05', $membership);
    createBacktestPriceRow('LATE', '2024-01-08', $membership);

    $calendar = app(BacktestTradingCalendarAction::class);

    expect($calendar->query($index, '2024-01-02', '2024-01-05')->pluck('date')->all())
        ->toBe(['2024-01-03', '2024-01-05'])
        ->and($calendar->latestDates($index, '2024-01-02', '2024-01-05', 2)->all())
        ->toBe(['2024-01-05', '2024-01-03']);
})->with(NseIndexEnum::cases());

it('applies inclusive date bounds and returns only distinct dates', function (?string $fromDate, ?string $throughDate, array $expected) {
    foreach (['2024-01-02', '2024-01-03', '2024-01-05'] as $date) {
        createBacktestPriceRow('A', $date, ['is_nifty_50' => true]);
        createBacktestPriceRow('B', $date, ['is_nifty_50' => true]);
    }

    $calendar = app(BacktestTradingCalendarAction::class);

    expect($calendar->query(NseIndexEnum::NIFTY_50, $fromDate, $throughDate)->pluck('date')->all())
        ->toBe($expected)
        ->and($calendar->latestDates(NseIndexEnum::NIFTY_50, $fromDate, $throughDate, 2)->all())
        ->toBe(array_slice(array_reverse($expected), 0, 2));
})->with([
    'all dates' => [null, null, ['2024-01-02', '2024-01-03', '2024-01-05']],
    'start only' => ['2024-01-03', null, ['2024-01-03', '2024-01-05']],
    'end only' => [null, '2024-01-03', ['2024-01-02', '2024-01-03']],
    'both bounds' => ['2024-01-03', '2024-01-05', ['2024-01-03', '2024-01-05']],
    'one date' => ['2024-01-03', '2024-01-03', ['2024-01-03']],
    'no dates before start' => ['2024-01-08', null, []],
    'no dates after end' => [null, '2024-01-01', []],
    'reversed bounds' => ['2024-01-05', '2024-01-03', []],
]);

it('does not use another universes dates when the selected universe has no data', function () {
    createBacktestPriceRow('A', '2024-01-02', ['is_nifty_200' => true]);
    createBacktestPriceRow('B', '2024-01-03', ['is_nifty_200' => true]);
    $calendar = app(BacktestTradingCalendarAction::class);

    expect($calendar->query(NseIndexEnum::NIFTY_50)->pluck('date'))->toBeEmpty()
        ->and($calendar->latestDates(NseIndexEnum::NIFTY_50))->toBeEmpty();
});

it('sees newly imported dates on the next lookup without keeping stale calendar data', function () {
    createBacktestPriceRow('A', '2024-01-02', ['is_nifty_50' => true]);
    $calendar = app(BacktestTradingCalendarAction::class);

    expect($calendar->query(NseIndexEnum::NIFTY_50)->pluck('date')->all())->toBe(['2024-01-02'])
        ->and($calendar->latestDates(NseIndexEnum::NIFTY_50)->all())->toBe(['2024-01-02']);

    createBacktestPriceRow('A', '2024-01-03', ['is_nifty_50' => true]);
    createBacktestPriceRow('B', '2024-01-04', ['is_nifty_200' => true]);

    expect($calendar->query(NseIndexEnum::NIFTY_50)->pluck('date')->all())->toBe(['2024-01-02', '2024-01-03'])
        ->and($calendar->latestDates(NseIndexEnum::NIFTY_50)->all())->toBe(['2024-01-03']);
});
