<?php

use App\Enums\NseIndexEnum;
use App\Http\Requests\StoreBacktestRequest;
use App\Models\BacktestNseIndexConstituent;
use App\Models\Screen;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Inertia\Testing\AssertableInertia as Assert;

it('marks additional indices from the maintained table and preserves earlier dates on reruns', function () {
    Http::preventStrayRequests();
    BacktestNseIndexConstituent::insert([
        ['index' => 'nifty_bank', 'symbol' => 'OLD'],
        ['index' => 'nifty_alpha_50', 'symbol' => 'NEW'],
    ]);
    $historical = createBacktestPriceRow('OLD', '2026-11-13', ['series' => 'EQ']);
    $old = createBacktestPriceRow('OLD', '2026-11-16', ['series' => 'EQ', 'is_nifty_fno' => true, 'close_adjusted' => 123]);
    $new = createBacktestPriceRow('NEW', '2026-11-16', ['series' => 'BE', 'is_etf' => true]);

    $this->artisan('backtest:import-constituents', ['--date' => '2026-11-13'])->assertSuccessful();
    expect($historical->fresh()->is_nifty_bank)->toBeFalse();

    $this->artisan('backtest:import-constituents', ['--date' => '2026-11-16'])->assertSuccessful();
    expect($old->fresh()->is_nifty_bank)->toBeTrue()
        ->and($new->fresh()->is_nifty_bank)->toBeFalse()
        ->and($new->fresh()->is_nifty_alpha_50)->toBeTrue();

    BacktestNseIndexConstituent::where('index', 'nifty_bank')->update(['symbol' => 'NEW']);
    foreach (range(1, 2) as $attempt) {
        $this->artisan('backtest:import-constituents', ['--date' => '2026-11-16'])->assertSuccessful();
    }
    expect($old->fresh()->is_nifty_bank)->toBeFalse()
        ->and($new->fresh()->is_nifty_bank)->toBeTrue()
        ->and($historical->fresh()->is_nifty_bank)->toBeFalse()
        ->and($old->fresh()->is_nifty_fno)->toBeTrue()
        ->and($new->fresh()->is_etf)->toBeTrue()
        ->and((float) $old->fresh()->close_adjusted)->toBe(123.0)
        ->and(BacktestNseIndexConstituent::count())->toBe(2);
    Http::assertNothingSent();
});

it('reports constituents with no price and keeps them in the source table', function () {
    BacktestNseIndexConstituent::insert([
        ['index' => 'nifty_bank', 'symbol' => 'PRICED'],
        ['index' => 'nifty_bank', 'symbol' => 'NOQUOTE'],
    ]);
    $price = createBacktestPriceRow('PRICED', '2026-11-16');
    $this->artisan('backtest:import-constituents', ['--date' => '2026-11-16'])
        ->expectsOutputToContain('Nifty Bank — no price for: NOQUOTE')
        ->assertSuccessful();
    expect($price->fresh()->is_nifty_bank)->toBeTrue()
        ->and(BacktestNseIndexConstituent::count())->toBe(2);
});

it('rejects invalid dates and dates without prices', function (string $date, string $message) {
    $price = createBacktestPriceRow('MEMBER', '2026-11-16', ['is_nifty_bank' => true]);
    $this->artisan('backtest:import-constituents', ['--date' => $date])
        ->expectsOutputToContain($message)->assertFailed();
    expect($price->fresh()->is_nifty_bank)->toBeTrue();
})->with([
    ['2026-02-30', 'Please provide a valid date'],
    ['2026-11-17', 'No prices found'],
]);

it('shows additional membership pills and dated constituent pages and exports', function () {
    createScreenResultRow('OLD', 'Earlier member', '2026-11-16', ['is_nifty_allcap' => false, 'is_nifty_bank' => true]);
    createScreenResultRow('NEW', 'Current member', '2026-11-17', ['is_nifty_allcap' => false, 'is_nifty_bank' => true]);
    createScreenResultRow('OLD', 'Earlier member', '2026-11-17', ['is_nifty_allcap' => false]);
    BacktestNseIndexConstituent::insert(['index' => 'nifty_bank', 'symbol' => 'NEW']);

    $this->get('/instruments/NEW')->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->has('instrument.indices', 1)->where('instrument.indices.0.name', 'Nifty Bank')
        ->where('instrument.indices.0.url', '/nse-index/nifty-bank/constituents?date=2026-11-17'));
    $this->get('/instruments/OLD')->assertInertia(fn (Assert $page) => $page->has('instrument.indices', 0));
    $this->get('/nse-index/nifty-bank/constituents?date=2026-11-16')
        ->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->where('date', '2026-11-16')->where('constituents.total', 1)
        ->where('constituents.data.0.symbol', 'OLD'));
    $csv = $this->get('/nse-index/nifty-bank/constituents/export?date=2026-11-16')
        ->assertSuccessful()->assertDownload('nifty-bank-constituents-2026-11-16.csv')->streamedContent();
    expect($csv)->toContain('OLD')->not->toContain('NEW');
    $this->get('/nse-index/nifty-bank/constituents?date=2026-11-13')
        ->assertInertia(fn (Assert $page) => $page->where('constituents.total', 0));
});

it('keeps additional memberships out of screen and backtest choices and validation', function () {
    $screen = Screen::factory()->create();
    createScreenResultRow('MEMBER', 'Member', '2026-11-16');
    $this->actingAs($screen->user)->get('/screens/'.$screen->id.'/edit')
        ->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->has('indices', 16)
        ->where('indices', fn ($indices): bool => ! collect($indices)->contains('id', 'nifty_bank')));
    $this->put('/screens/'.$screen->id, array_replace($screen->toArray(), ['index' => 'nifty_bank']))
        ->assertSessionHasErrors('index');
    expect($screen->fresh()->index)->toBe($screen->index);

    $rules = (new StoreBacktestRequest)->rules();
    expect(Validator::make(['index' => 'nifty_bank'], ['index' => $rules['index']])->fails())->toBeTrue()
        ->and(Validator::make(['index' => NseIndexEnum::NIFTY_1000->value], ['index' => $rules['index']])->passes())->toBeTrue()
        ->and(NseIndexEnum::getOptionsForFilters())->toHaveCount(16);
});

it('rolls back all membership changes if a later index update fails', function () {
    $price = createBacktestPriceRow('MEMBER', '2026-11-16', ['is_nifty_50' => true, 'is_nifty_alpha_50' => true]);
    DB::listen(function (QueryExecuted $query): void {
        if (str_starts_with($query->sql, 'update') && str_contains($query->sql, '`is_nifty_alpha_50`')) {
            throw new RuntimeException('Stop the import');
        }
    });

    expect(fn () => $this->artisan('backtest:import-constituents', ['--date' => '2026-11-16'])->run())
        ->toThrow(RuntimeException::class, 'Stop the import');
    expect($price->fresh()->is_nifty_50)->toBeTrue()
        ->and($price->fresh()->is_nifty_alpha_50)->toBeTrue();
});
