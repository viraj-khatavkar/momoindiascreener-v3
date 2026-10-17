<?php

use App\Actions\ApplyScreenFiltersAction;
use App\Actions\CreateDefaultScreenAction;
use App\Enums\ApplyFiltersOnOptionEnum;
use App\Enums\NseIndexEnum;
use App\Models\BacktestNseInstrument;
use App\Models\BacktestNseInstrumentPrice;
use App\Models\Screen;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $attributes
 */
function createMappedScreenEtf(string $symbol, ?string $underlyingIndex, array $attributes = [], string $date = '2026-07-31'): BacktestNseInstrumentPrice
{
    BacktestNseInstrument::query()->updateOrCreate(['symbol' => $symbol], ['etf_index' => $underlyingIndex]);

    return createScreenResultRow($symbol, $symbol, $date, array_merge([
        'is_etf' => true,
        'marketcap' => null,
        'price_to_earnings' => null,
    ], $attributes));
}

it('leaves unique ETF selection off for new screens', function () {
    $screen = app(CreateDefaultScreenAction::class)->execute('ETF Screen', User::factory()->create());

    expect($screen->unique_etf_per_index)->toBeFalse()
        ->and($screen->fresh()->unique_etf_per_index)->toBeFalse();
});

it('keeps the highest volume ETF per index in the selected sort order', function (string $sortDirection, array $expected) {
    $screen = Screen::factory()->create([
        'index' => NseIndexEnum::NIFTY_ETF,
        'unique_etf_per_index' => true,
        'sort_direction' => $sortDirection,
        'columns' => ['close_adjusted'],
    ]);
    createMappedScreenEtf('NIFTYLOW', 'nifty-50', ['median_volume_one_year' => 20000000, 'sharpe_return_one_year' => 99]);
    createMappedScreenEtf('NIFTYHIGH', 'nifty-50', ['median_volume_one_year' => 50000000, 'sharpe_return_one_year' => 10]);
    createMappedScreenEtf('GOLDLOW', 'gold', ['median_volume_one_year' => 10000000, 'sharpe_return_one_year' => 60]);
    createMappedScreenEtf('GOLDHIGH', 'gold', ['median_volume_one_year' => 30000000, 'sharpe_return_one_year' => 50]);

    expect(app(ApplyScreenFiltersAction::class)->execute($screen, '2026-07-31')->pluck('symbol')->all())->toBe($expected);
})->with([
    'descending' => ['desc', ['GOLDHIGH', 'NIFTYHIGH']],
    'ascending' => ['asc', ['NIFTYHIGH', 'GOLDHIGH']],
]);

it('keeps all matching ETFs when unique selection is off', function () {
    $screen = Screen::factory()->create(['index' => NseIndexEnum::NIFTY_ETF]);
    createMappedScreenEtf('FIRST', 'nifty-50');
    createMappedScreenEtf('SECOND', 'nifty-50');

    expect(app(ApplyScreenFiltersAction::class)->execute($screen, '2026-07-31'))->toHaveCount(2);
});

it('does not group a stock universe when the ETF option is saved', function () {
    $screen = Screen::factory()->create(['unique_etf_per_index' => true]);
    foreach (['FIRST', 'SECOND'] as $symbol) {
        BacktestNseInstrument::query()->create(['symbol' => $symbol, 'etf_index' => 'nifty-50']);
        createScreenResultRow($symbol, $symbol, '2026-07-31');
    }

    expect(app(ApplyScreenFiltersAction::class)->execute($screen, '2026-07-31'))->toHaveCount(2);
});

it('chooses the highest volume ETF that passes the other filters', function () {
    $screen = Screen::factory()->create(['index' => NseIndexEnum::NIFTY_ETF, 'unique_etf_per_index' => true]);
    createMappedScreenEtf('FILTERED', 'nifty-50', ['median_volume_one_year' => 100000000, 'absolute_return_one_year' => 0]);
    createMappedScreenEtf('MATCH', 'nifty-50', ['median_volume_one_year' => 50000000]);
    createMappedScreenEtf('LESSVOLUME', 'nifty-50', ['median_volume_one_year' => 20000000]);

    expect(app(ApplyScreenFiltersAction::class)->execute($screen, '2026-07-31')->pluck('symbol')->all())->toBe(['MATCH']);
});

it('chooses unique ETFs after the selected decile restriction', function () {
    $screen = Screen::factory()->create([
        'index' => NseIndexEnum::NIFTY_ETF,
        'unique_etf_per_index' => true,
        'apply_filters_on' => ApplyFiltersOnOptionEnum::TOP_DECILE,
    ]);
    for ($position = 1; $position <= 23; $position++) {
        createMappedScreenEtf('ETF'.$position, 'nifty-50', [
            'sharpe_return_one_year' => 100 - $position,
            'median_volume_one_year' => $position * 10000000,
        ]);
    }

    expect(app(ApplyScreenFiltersAction::class)->execute($screen, '2026-07-31')->pluck('symbol')->all())->toBe(['ETF22']);
});

it('uses the median volume from the resolved historical trading date', function () {
    $screen = Screen::factory()->create([
        'index' => NseIndexEnum::NIFTY_ETF,
        'unique_etf_per_index' => true,
        'apply_historical_date' => true,
        'historical_date' => '2024-03-02',
    ]);
    createMappedScreenEtf('FIRST', 'nifty-50', ['median_volume_one_year' => 50000000], '2024-03-01');
    createMappedScreenEtf('SECOND', 'nifty-50', ['median_volume_one_year' => 20000000], '2024-03-01');
    createMappedScreenEtf('FIRST', 'nifty-50', ['median_volume_one_year' => 20000000]);
    createMappedScreenEtf('SECOND', 'nifty-50', ['median_volume_one_year' => 100000000]);

    $action = app(ApplyScreenFiltersAction::class);

    expect($action->execute($screen, '2026-07-31')->pluck('symbol')->all())->toBe(['FIRST']);

    $screen->apply_historical_date = false;

    expect($action->execute($screen, '2026-07-31')->pluck('symbol')->all())->toBe(['SECOND']);
});

it('uses alphabetical symbols to resolve equal median volumes', function () {
    $screen = Screen::factory()->create(['index' => NseIndexEnum::NIFTY_ETF, 'unique_etf_per_index' => true]);
    createMappedScreenEtf('ZETA', 'nifty-50', ['sharpe_return_one_year' => 99]);
    createMappedScreenEtf('ALPHA', 'nifty-50', ['sharpe_return_one_year' => 10]);

    expect(app(ApplyScreenFiltersAction::class)->execute($screen, '2026-07-31')->pluck('symbol')->all())->toBe(['ALPHA']);
});

it('keeps ETFs with missing mappings separate', function () {
    $screen = Screen::factory()->create(['index' => NseIndexEnum::NIFTY_ETF, 'unique_etf_per_index' => true]);
    createMappedScreenEtf('FIRST', null);
    createMappedScreenEtf('SECOND', '');
    createScreenResultRow('MISSING', 'Missing Instrument', '2026-07-31', ['is_etf' => true, 'marketcap' => null]);

    expect(app(ApplyScreenFiltersAction::class)->execute($screen, '2026-07-31')->pluck('symbol')->sort()->values()->all())
        ->toBe(['FIRST', 'MISSING', 'SECOND']);
});

it('checks factor data before grouping and ranks only the selected ETFs', function (bool $factorThree) {
    $screen = Screen::factory()->create([
        'index' => NseIndexEnum::NIFTY_ETF,
        'unique_etf_per_index' => true,
        'apply_factor_two' => true,
        'apply_factor_three' => $factorThree,
    ]);
    createMappedScreenEtf('LOWVOLUME', 'nifty-50', [
        'median_volume_one_year' => 20000000, 'sharpe_return_one_year' => 99, 'volatility_one_year' => 0.001, 'beta' => 0.1,
    ]);
    createMappedScreenEtf('NIFTY', 'nifty-50', [
        'median_volume_one_year' => 50000000, 'sharpe_return_one_year' => 10, 'volatility_one_year' => 0.9, 'beta' => 1.5,
    ]);
    createMappedScreenEtf('GOLD', 'gold', [
        'median_volume_one_year' => 40000000, 'sharpe_return_one_year' => 50, 'volatility_one_year' => 0.05, 'beta' => 0.5,
    ]);
    createMappedScreenEtf('NOFACTOR', 'nifty-50', [
        'median_volume_one_year' => 100000000,
        $factorThree ? 'beta' : 'volatility_one_year' => null,
    ]);

    $results = app(ApplyScreenFiltersAction::class)->execute($screen, '2026-07-31')->values();

    expect($results->pluck('symbol')->all())->toBe(['GOLD', 'NIFTY'])
        ->and($results->pluck('factor_one_rank')->all())->toBe([1, 2])
        ->and($results->pluck('factor_two_rank')->all())->toBe([1, 2]);

    if ($factorThree) {
        expect($results->pluck('factor_three_rank')->all())->toBe([1, 2]);
    }
})->with(['two factors' => false, 'three factors' => true]);

it('saves and reloads the unique ETF option', function (bool $enabled) {
    $screen = Screen::factory()->create([
        'index' => NseIndexEnum::NIFTY_ETF,
        'unique_etf_per_index' => ! $enabled,
    ]);
    createMappedScreenEtf('FIRST', 'nifty-50');
    createMappedScreenEtf('SECOND', 'nifty-50');

    $this->actingAs($screen->user)
        ->put('/screens/'.$screen->id, array_merge($screen->toArray(), ['unique_etf_per_index' => $enabled]))
        ->assertSessionHasNoErrors()
        ->assertRedirect('/screens/'.$screen->id.'/edit');

    expect($screen->fresh()->unique_etf_per_index)->toBe($enabled);

    $this->get('/screens/'.$screen->id.'/edit')->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->where('screen.unique_etf_per_index', $enabled)
        ->has('results', $enabled ? 1 : 2));
})->with([true, false]);

it('rejects invalid unique ETF settings', function () {
    $screen = Screen::factory()->create();

    $this->actingAs($screen->user)
        ->put('/screens/'.$screen->id, array_merge($screen->toArray(), ['unique_etf_per_index' => 'invalid']))
        ->assertSessionHasErrors('unique_etf_per_index');

    expect($screen->fresh()->unique_etf_per_index)->toBeFalse();
});

it('shows the same unique ETFs on edit and public pages and in the CSV export', function () {
    $screen = Screen::factory()->create([
        'id' => Screen::PUBLIC_SCREENS[0],
        'index' => NseIndexEnum::NIFTY_ETF,
        'unique_etf_per_index' => true,
    ]);
    createMappedScreenEtf('LOWVOLUME', 'nifty-50', ['median_volume_one_year' => 20000000, 'sharpe_return_one_year' => 99]);
    createMappedScreenEtf('NIFTY', 'nifty-50', ['median_volume_one_year' => 50000000, 'sharpe_return_one_year' => 10]);
    createMappedScreenEtf('GOLD', 'gold', ['median_volume_one_year' => 40000000, 'sharpe_return_one_year' => 50]);

    $this->get('/screens/'.$screen->id)->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->where('screen.unique_etf_per_index', true)
        ->has('results', 2)
        ->where('results.0.symbol', 'GOLD')
        ->where('results.1.symbol', 'NIFTY'));

    $this->actingAs($screen->user)->get('/screens/'.$screen->id.'/edit')
        ->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->has('results', 2)
        ->where('results.0.symbol', 'GOLD')
        ->where('results.1.symbol', 'NIFTY'));

    $response = $this->get('/screens/'.$screen->id.'/csv')->assertSuccessful();
    $lines = preg_split('/\r\n|\n|\r/', trim($response->streamedContent()));

    expect($lines)->toHaveCount(3)
        ->and(str_getcsv($lines[1], escape: '')[1])->toBe('GOLD')
        ->and(str_getcsv($lines[2], escape: '')[1])->toBe('NIFTY');
});
