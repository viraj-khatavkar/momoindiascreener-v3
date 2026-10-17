<?php

use App\Actions\ApplyScreenFiltersAction;
use App\Enums\NseIndexEnum;
use App\Models\Screen;
use Inertia\Testing\AssertableInertia as Assert;

it('applies inclusive market cap and enabled PE limits to current results', function () {
    $screen = Screen::factory()->create([
        'marketcap_from' => 1000,
        'marketcap_to' => 2000,
        'apply_pe' => true,
        'price_to_earnings_from' => 10,
        'price_to_earnings_to' => 20,
    ]);

    foreach ([
        'LOWER' => [1000, 10],
        'UPPER' => [2000, 20],
        'SMALL' => [999, 15],
        'LARGE' => [2001, 15],
        'LOWPE' => [1500, 9],
        'HIGHPE' => [1500, 21],
        'NOCAP' => [null, 15],
        'NOPE' => [1500, null],
    ] as $symbol => [$marketcap, $priceToEarnings]) {
        createScreenResultRow($symbol, $symbol, '2026-07-31', [
            'marketcap' => $marketcap,
            'price_to_earnings' => $priceToEarnings,
        ]);
    }

    $action = app(ApplyScreenFiltersAction::class);

    expect($action->execute($screen, '2026-07-31')->pluck('symbol')->sort()->values()->all())
        ->toBe(['LOWER', 'UPPER'])
        ->and($action->getValuationFilterWarnings())->toBe([]);
});

it('does not apply PE limits when the PE filter is off', function () {
    $screen = Screen::factory()->create(['apply_pe' => false, 'price_to_earnings_to' => 20]);
    createScreenResultRow('HIGHPE', 'High PE', '2026-07-31', ['price_to_earnings' => 80]);
    createScreenResultRow('NOPE', 'Missing PE', '2026-07-31', ['price_to_earnings' => null]);

    expect(app(ApplyScreenFiltersAction::class)->execute($screen, '2026-07-31'))->toHaveCount(2);
});

it('skips valuation filters for ETFs and keeps the other filters active', function (bool $historical, bool $applyPe) {
    $date = $historical ? '2024-03-01' : '2026-07-31';
    $screen = Screen::factory()->create([
        'index' => NseIndexEnum::NIFTY_ETF,
        'apply_historical_date' => $historical,
        'historical_date' => '2024-03-02',
        'marketcap_from' => 1000,
        'marketcap_to' => 2000,
        'apply_pe' => $applyPe,
        'price_to_earnings_from' => 10,
        'price_to_earnings_to' => 20,
    ]);
    createScreenResultRow('STOCK', 'Stock', $date, ['price_to_earnings' => 15]);
    $etfAttributes = [
        'is_etf' => true,
        'is_nifty_allcap' => false,
        'marketcap' => null,
        'price_to_earnings' => null,
    ];
    createScreenResultRow('ETF', 'ETF', $date, $etfAttributes);
    createScreenResultRow('LOWVOL', 'Low Volume ETF', $date, array_merge($etfAttributes, [
        'median_volume_one_year' => 100,
    ]));

    $action = app(ApplyScreenFiltersAction::class);
    $warnings = ['Market cap data is not available for ETFs. The market cap filter was not applied.'];

    if ($applyPe) {
        $warnings[] = 'P/E data is not available for ETFs. The P/E filter was not applied.';
    }

    expect($action->execute($screen, '2026-07-31')->pluck('symbol')->all())->toBe(['ETF'])
        ->and($action->getValuationFilterWarnings())->toBe($warnings);

    $screen->index = NseIndexEnum::NIFTY_ALLCAP;

    expect($action->execute($screen, '2026-07-31'))->toHaveCount(0)
        ->and($action->getValuationFilterWarnings())->toBe([]);
})->with(['current' => false, 'historical' => true])->with(['PE off' => false, 'PE on' => true]);

it('applies valuation limits from the first available trading date', function (string $historicalDate) {
    $screen = Screen::factory()->create([
        'apply_historical_date' => true,
        'historical_date' => $historicalDate,
        'marketcap_to' => 2000,
        'apply_pe' => true,
        'price_to_earnings_to' => 20,
    ]);
    createScreenResultRow('GOOD', 'Good Stock', '2024-03-01', ['marketcap' => 1500, 'price_to_earnings' => 15]);
    createScreenResultRow('LARGE', 'Large Stock', '2024-03-01', ['marketcap' => 5000, 'price_to_earnings' => 15]);
    createScreenResultRow('HIGHPE', 'High PE', '2024-03-01', ['marketcap' => 1500, 'price_to_earnings' => 80]);
    createScreenResultRow('LATEST', 'Latest Stock', '2026-07-31', ['marketcap' => 1500, 'price_to_earnings' => 15]);

    $action = app(ApplyScreenFiltersAction::class);

    expect($action->execute($screen, '2026-07-31')->pluck('symbol')->all())->toBe(['GOOD'])
        ->and($action->getValuationFilterWarnings())->toBe([]);
})->with(['2024-03-01', '2024-03-02']);

it('shows skipped valuation warnings and a usable saved date on screen pages', function (string $pageType) {
    $screen = Screen::factory()->create([
        'id' => Screen::PUBLIC_SCREENS[0],
        'apply_historical_date' => true,
        'historical_date' => '2024-02-29',
        'marketcap_from' => 1000,
        'marketcap_to' => 2000,
        'apply_pe' => true,
        'price_to_earnings_to' => 20,
    ]);
    createScreenResultRow('HISTORICAL', 'Historical Stock', '2024-02-29', [
        'marketcap' => null,
        'price_to_earnings' => null,
    ]);
    createScreenResultRow('LATEST', 'Latest Stock', '2024-03-01', ['price_to_earnings' => 15]);

    $response = $pageType === 'edit'
        ? $this->actingAs($screen->user)->get('/screens/'.$screen->id.'/edit')
        : $this->get('/screens/'.$screen->id);

    $response->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->where('screen.historical_date', '2024-02-29')
        ->has('results', 1)
        ->where('results.0.symbol', 'HISTORICAL')
        ->where('valuationFilterWarnings', [
            'Market cap data is not available for 2024-02-29. The market cap filter was not applied.',
            'P/E data is not available for 2024-02-29. The P/E filter was not applied.',
        ]));
})->with(['edit', 'public']);

it('checks each valuation field separately on historical dates with missing data', function (array $good, array $excluded, string $warning) {
    $screen = Screen::factory()->create([
        'apply_historical_date' => true,
        'historical_date' => '2024-03-04',
        'marketcap_to' => 2000,
        'apply_pe' => true,
        'price_to_earnings_to' => 20,
    ]);
    createScreenResultRow('GOOD', 'Good Stock', '2024-03-04', $good);
    createScreenResultRow('EXCLUDED', 'Excluded Stock', '2024-03-04', $excluded);

    $action = app(ApplyScreenFiltersAction::class);

    expect($action->execute($screen, '2024-03-04')->pluck('symbol')->all())->toBe(['GOOD'])
        ->and($action->getValuationFilterWarnings())->toBe([$warning]);
})->with([
    'missing market cap' => [
        ['marketcap' => null, 'price_to_earnings' => 15],
        ['marketcap' => null, 'price_to_earnings' => 80],
        'Market cap data is not available for 2024-03-04. The market cap filter was not applied.',
    ],
    'missing PE' => [
        ['marketcap' => 1500, 'price_to_earnings' => null],
        ['marketcap' => 5000, 'price_to_earnings' => null],
        'P/E data is not available for 2024-03-04. The P/E filter was not applied.',
    ],
]);

it('uses the resolved trading date for valuation availability and clears warnings between screens', function () {
    $historicalScreen = Screen::factory()->create([
        'apply_historical_date' => true,
        'historical_date' => '2024-03-01',
        'apply_pe' => true,
    ]);
    $currentScreen = Screen::factory()->create();
    createScreenResultRow('HISTORICAL', 'Historical Stock', '2024-02-29', ['marketcap' => null]);

    $action = app(ApplyScreenFiltersAction::class);

    expect($action->execute($historicalScreen, '2024-02-29'))->toHaveCount(1)
        ->and($action->getValuationFilterWarnings())->toBe([
            'Market cap data is not available for 2024-02-29. The market cap filter was not applied.',
            'P/E data is not available for 2024-02-29. The P/E filter was not applied.',
        ]);

    createScreenResultRow('CURRENT', 'Current Stock', '2024-03-04');

    expect($action->execute($currentScreen, '2024-03-04'))->toHaveCount(1)
        ->and($action->getValuationFilterWarnings())->toBe([]);
});

it('does not treat missing valuations in the selected index as missing market data', function () {
    $screen = Screen::factory()->create([
        'index' => NseIndexEnum::NIFTY_50,
        'apply_historical_date' => true,
        'historical_date' => '2024-03-01',
        'apply_pe' => true,
    ]);
    createScreenResultRow('ININDEX', 'In Index', '2024-03-01', ['is_nifty_50' => true, 'price_to_earnings' => null]);
    createScreenResultRow('OUTSIDE', 'Outside Index', '2024-03-01', ['price_to_earnings' => 15]);

    $action = app(ApplyScreenFiltersAction::class);

    expect($action->execute($screen, '2024-03-01'))->toHaveCount(0)
        ->and($action->getValuationFilterWarnings())->toBe([]);
});

it('uses adjusted closing prices for every moving average comparison', function (string $direction, int $period) {
    $screen = Screen::factory()->create(['apply_ma' => true, $direction.'_ma_'.$period => true]);
    createScreenResultRow('ABOVE', 'Above MA', '2026-07-31', [
        'close_raw' => 200, 'close_adjusted' => 100, 'ma_'.$period => 75,
    ]);
    createScreenResultRow('BELOW', 'Below MA', '2026-07-31', [
        'close_raw' => 100, 'close_adjusted' => 50, 'ma_'.$period => 75,
    ]);

    expect(app(ApplyScreenFiltersAction::class)->execute($screen, '2026-07-31')->pluck('symbol')->all())
        ->toBe([strtoupper($direction)]);
})->with(['above', 'below'])->with([200, 100, 50, 20]);

it('keeps raw closing prices for the rupee price range', function () {
    $screen = Screen::factory()->create(['price_from' => 90, 'price_to' => 110]);
    createScreenResultRow('INRANGE', 'In Range', '2026-07-31', ['close_raw' => 100, 'close_adjusted' => 50]);
    createScreenResultRow('OUTSIDE', 'Outside Range', '2026-07-31', ['close_raw' => 200, 'close_adjusted' => 100]);

    expect(app(ApplyScreenFiltersAction::class)->execute($screen, '2026-07-31')->pluck('symbol')->all())->toBe(['INRANGE']);
});

it('includes only the selected series and none when both are off', function (bool $eq, bool $be, array $expected) {
    $screen = Screen::factory()->create(['series_eq' => $eq, 'series_be' => $be]);
    foreach (['EQ', 'BE', 'SM'] as $series) {
        createScreenResultRow($series, $series, '2026-07-31', ['series' => $series]);
    }

    expect(app(ApplyScreenFiltersAction::class)->execute($screen, '2026-07-31')->pluck('symbol')->sort()->values()->all())
        ->toBe($expected);
})->with([
    'both' => [true, true, ['BE', 'EQ']],
    'EQ' => [true, false, ['EQ']],
    'BE' => [false, true, ['BE']],
    'neither' => [false, false, []],
]);

it('saves and reloads a historical date without changing its calendar date', function () {
    $screen = Screen::factory()->create();
    createScreenResultRow('HISTORICAL', 'Historical Stock', '2024-02-29', ['marketcap' => null]);

    $this->actingAs($screen->user)
        ->put('/screens/'.$screen->id, array_merge($screen->toArray(), [
            'apply_historical_date' => true,
            'historical_date' => '2024-02-29',
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect('/screens/'.$screen->id.'/edit');

    $this->get('/screens/'.$screen->id.'/edit')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->where('screen.historical_date', '2024-02-29'));
});
