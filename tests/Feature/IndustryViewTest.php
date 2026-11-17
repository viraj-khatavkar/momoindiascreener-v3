<?php

use App\Enums\AdminProcessRunStatusEnum;
use App\Models\AdminProcessRun;
use App\Models\BacktestNseInstrument;
use App\Models\Industry;
use Inertia\Testing\AssertableInertia as Assert;

it('links a stock to its industry using the stored slug', function () {
    $industry = Industry::factory()->create(['name' => 'Oil, Gas & Consumable Fuels', 'slug' => 'oil-gas-consumable-fuels']);
    $industry->instruments()->create(['symbol' => 'MEMBER']);
    createBacktestPriceRow('MEMBER', '2026-11-16');

    $this->get('/instruments/MEMBER')->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->where('industry.name', 'Oil, Gas & Consumable Fuels')
        ->where('industry.url', '/industries/oil-gas-consumable-fuels'));

    $industry->update(['name' => 'Updated display name']);
    $this->get('/industries/oil-gas-consumable-fuels')->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->component('IndustryView')->where('industry.name', 'Updated display name')->where('stocks.total', 1));
});

it('leaves the industry empty when a stock has no classification or master row', function (bool $masterExists) {
    if ($masterExists) {
        BacktestNseInstrument::create(['symbol' => 'UNKNOWN']);
    }
    createBacktestPriceRow('UNKNOWN', '2026-11-16');
    $this->get('/instruments/UNKNOWN')->assertSuccessful()->assertInertia(fn (Assert $page) => $page->where('industry', null));
})->with([true, false]);

it('lists only industry members and retains members with no current price', function () {
    $industry = Industry::factory()->create();
    foreach (['READY', 'OLDER', 'NOQUOTE'] as $symbol) {
        $industry->instruments()->create(['symbol' => $symbol, 'name' => $symbol.' Company']);
    }
    Industry::factory()->create()->instruments()->create(['symbol' => 'OUTSIDE']);
    createBacktestPriceRow('READY', '2026-11-16', ['close_adjusted' => 123, 'price_to_earnings' => 0, 'absolute_return_one_year' => -5]);
    createBacktestPriceRow('OLDER', '2026-11-13', ['close_adjusted' => 456]);
    createBacktestPriceRow('OUTSIDE', '2026-11-16');

    $this->get('/industries/'.$industry->slug.'?sort=symbol&direction=desc')->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->where('date', '2026-11-16')->where('stocks.total', 3)
        ->where('stocks.data.0.symbol', 'READY')->where('stocks.data.0.url', '/instruments/READY')
        ->where('stocks.data.0.close_adjusted', '123.00')
        ->where('stocks.data.0.price_to_earnings', fn ($value): bool => (float) $value === 0.0)
        ->where('stocks.data.0.absolute_return_one_year', fn ($value): bool => (float) $value === -5.0)
        ->where('stocks.data.1.symbol', 'OLDER')->where('stocks.data.1.close_adjusted', null)
        ->where('stocks.data.2.symbol', 'NOQUOTE')->where('stocks.data.2.url', null));
});

it('excludes prices from unfinished daily processes', function (AdminProcessRunStatusEnum $status) {
    $industry = Industry::factory()->create();
    $industry->instruments()->create(['symbol' => 'MEMBER']);
    createBacktestPriceRow('MEMBER', '2026-11-16', ['close_adjusted' => 100]);
    createBacktestPriceRow('MEMBER', '2026-11-17', ['close_adjusted' => 200]);
    AdminProcessRun::factory()->create(['process_date' => '2026-11-17', 'status' => $status]);

    $this->get('/industries/'.$industry->slug)->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->where('date', '2026-11-16')->where('stocks.data.0.close_adjusted', '100.00'));
})->with(array_values(array_filter(AdminProcessRunStatusEnum::cases(), fn (AdminProcessRunStatusEnum $status): bool => $status !== AdminProcessRunStatusEnum::Completed)));

it('shows members before prices exist and supports an empty industry', function () {
    $industry = Industry::factory()->create();
    $this->get('/industries/'.$industry->slug)->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->where('date', null)->where('stocks.total', 0));
    $industry->instruments()->create(['symbol' => 'NEW']);
    $this->get('/industries/'.$industry->slug)->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->where('date', null)->where('stocks.total', 1)->where('stocks.data.0.close_adjusted', null));
});

it('searches and paginates all industry members with stable sorting', function () {
    $industry = Industry::factory()->create();
    foreach (range(1, 52) as $number) {
        $industry->instruments()->create(['symbol' => 'STOCK'.str_pad((string) $number, 2, '0', STR_PAD_LEFT), 'name' => 'Example Company']);
    }
    $industry->instruments()->create(['symbol' => 'OTHER', 'name' => 'Other Company']);
    $this->get('/industries/'.$industry->slug.'?search=Example&sort=symbol&direction=asc&page=2')
        ->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->where('stocks.total', 52)->has('stocks.data', 2)->where('stocks.data.0.symbol', 'STOCK51')
        ->where('stocks.data.1.symbol', 'STOCK52')
        ->where('stocks.prev_page_url', fn (string $url): bool => str_contains($url, 'search=Example') && str_contains($url, 'sort=symbol')));
    $this->get('/industries/'.$industry->slug.'?search=STOCK01')->assertInertia(fn (Assert $page) => $page->where('stocks.total', 1));
    $this->get('/industries/'.$industry->slug.'?search=%25')->assertInertia(fn (Assert $page) => $page->where('stocks.total', 0));
});

it('sorts numeric values with missing values last in both directions', function (string $direction, array $expected) {
    $industry = Industry::factory()->create();
    foreach (['SMALL' => 0, 'LARGE' => 100, 'MISSING' => null] as $symbol => $marketcap) {
        $industry->instruments()->create(['symbol' => $symbol]);
        createBacktestPriceRow($symbol, '2026-11-16', ['marketcap' => $marketcap]);
    }
    $this->get('/industries/'.$industry->slug.'?sort=marketcap&direction='.$direction)->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->where('stocks.data', fn ($rows): bool => $rows->pluck('symbol')->all() === $expected));
})->with([
    ['asc', ['SMALL', 'LARGE', 'MISSING']],
    ['desc', ['LARGE', 'SMALL', 'MISSING']],
]);

it('rejects invalid industry filters', function (array $filters, string $field) {
    $industry = Industry::factory()->create();
    $this->getJson('/industries/'.$industry->slug.'?'.http_build_query($filters))->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    [['sort' => 'industry_id'], 'sort'],
    [['direction' => 'wrong'], 'direction'],
    [['page' => 0], 'page'],
    [['search' => str_repeat('a', 101)], 'search'],
]);

it('returns not found for an unknown industry slug', function () {
    $industry = Industry::factory()->create(['slug' => 'information-technology']);
    $this->get('/industries/missing-industry')->assertNotFound();
    $this->get('/industries/'.$industry->id)->assertNotFound();
});

it('displays searches and sorts by quote company names with a master name fallback', function () {
    $industry = Industry::factory()->create();
    $industry->instruments()->create(['symbol' => 'QUOTED', 'name' => null]);
    $industry->instruments()->create(['symbol' => 'RENAMED', 'name' => 'A former name']);
    $industry->instruments()->create(['symbol' => 'MASTER', 'name' => 'M master name']);
    createBacktestPriceRow('QUOTED', '2026-11-16', ['name' => 'A quote name']);
    createBacktestPriceRow('RENAMED', '2026-11-16', ['name' => 'Z current name']);

    $this->get('/industries/'.$industry->slug.'?sort=name&direction=asc')->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->where('stocks.data.0.name', 'A quote name')->where('stocks.data.1.name', 'M master name')->where('stocks.data.2.name', 'Z current name'));
    $this->get('/industries/'.$industry->slug.'?search=quote')->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->where('stocks.total', 1)->where('stocks.data.0.symbol', 'QUOTED'));
    $this->get('/industries/'.$industry->slug.'?search=former')->assertSuccessful()->assertInertia(fn (Assert $page) => $page->where('stocks.total', 0));
});
