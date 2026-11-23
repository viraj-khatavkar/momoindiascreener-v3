<?php

use App\Enums\AdminProcessRunStatusEnum;
use App\Enums\NseIndexEnum;
use App\Models\AdminProcessRun;
use App\Models\BacktestNseIndexConstituent;
use App\Models\NseIndex;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

function constituentCsvRows(TestResponse $response): array
{
    $stream = fopen('php://temp', 'w+');
    fwrite($stream, ltrim($response->streamedContent(), "\xEF\xBB\xBF"));
    rewind($stream);
    $rows = [];
    while (($row = fgetcsv($stream, escape: '')) !== false) {
        $rows[] = $row;
    }
    fclose($stream);

    return $rows;
}

it('keeps constituents public and restricts CSV exports to verified paid accounts', function (?User $user, string $redirect) {
    if ($user !== null) {
        $this->actingAs($user);
    }

    createScreenResultRow('MEMBER', 'Midcap member', '2026-11-17', ['is_nifty_midcap_100' => true]);
    $url = '/nse-index/nifty-midcap-100/constituents';

    $this->get($url)->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->where('constituents.data.0.symbol', 'MEMBER')
        ->where('exportUrl', null));
    $this->get($url.'/export')->assertRedirect($redirect);
})->with([
    'guest' => [null, '/login'],
    'free account' => [fn () => User::factory()->create(['is_paid' => false]), '/pricing'],
    'newsletter-only subscriber' => [fn () => User::factory()->create(['is_newsletter_paid' => true]), '/pricing'],
    'unverified paid account' => [fn () => User::factory()->unverified()->create(['is_paid' => true]), '/email/verify'],
]);

it('links each instrument membership to its dated constituent list', function (NseIndexEnum $index) {
    createScreenResultRow('MEMBER', 'A member', '2026-10-30', [
        'is_nifty_allcap' => false, $index->isIndexFieldName() => true,
    ]);
    $url = '/nse-index/'.$index->slug().'/constituents';

    $this->get('/instruments/MEMBER')->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->has('instrument.indices', 1)
        ->where('instrument.indices.0.name', $index->label())
        ->where('instrument.indices.0.url', $url.'?date=2026-10-30'));
    $this->get($url)->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->component('NseIndexConstituents')
        ->where('indexName', $index->label())
        ->where('date', '2026-10-30')
        ->where('constituents.total', 1)
        ->where('constituents.data.0.symbol', 'MEMBER')
        ->where('constituents.data.0.url', '/instruments/MEMBER'));
})->with(array_values(array_filter(NseIndexEnum::cases(), fn (NseIndexEnum $index): bool => $index !== NseIndexEnum::NIFTY_ETF)));

it('reads dated flags without copying the current membership list into history', function (NseIndexEnum $index) {
    createScreenResultRow('OLD', 'Old member', '2011-01-05', [$index->isIndexFieldName() => true]);
    createScreenResultRow('NEW', 'New member', '2011-01-05');
    createScreenResultRow('OLD', 'Old member', '2026-10-30');
    createScreenResultRow('NEW', 'New member', '2026-10-30', [$index->isIndexFieldName() => true]);
    BacktestNseIndexConstituent::insert(['index' => $index->value, 'symbol' => 'NEW']);
    $url = '/nse-index/'.$index->slug().'/constituents';

    $this->get($url.'?date=2011-01-05')->assertInertia(fn (Assert $page) => $page
        ->where('date', '2011-01-05')->has('constituents.data', 1)->where('constituents.data.0.symbol', 'OLD'));
    $this->get($url)->assertInertia(fn (Assert $page) => $page
        ->where('date', '2026-10-30')->has('constituents.data', 1)->where('constituents.data.0.symbol', 'NEW'));
})->with([NseIndexEnum::NIFTY_FNO, NseIndexEnum::NIFTY_LARGEMIDCAP_250]);

it('keeps list and export on the latest finished date', function (AdminProcessRunStatusEnum $status) {
    $this->actingAs(User::factory()->create(['is_paid' => true]));
    createScreenResultRow('READY', 'Completed data', '2026-10-30', ['is_nifty_50' => true]);
    createScreenResultRow('PARTIAL', 'Unfinished data', '2026-11-02', ['is_nifty_50' => true]);
    AdminProcessRun::factory()->create(['process_date' => '2026-11-02', 'status' => $status]);

    $this->get('/nse-index/nifty-50/constituents')->assertInertia(fn (Assert $page) => $page
        ->where('date', '2026-10-30')
        ->where('constituents.data.0.symbol', 'READY')
        ->where('exportUrl', fn (string $url): bool => str_contains($url, 'date=2026-10-30')));
    $response = $this->get('/nse-index/nifty-50/constituents/export?date=2026-11-02')
        ->assertSuccessful()->assertDownload('nifty-50-constituents-2026-10-30.csv');
    expect(array_column(array_slice(constituentCsvRows($response), 1), 1))->toBe(['READY']);
})->with(array_values(array_filter(AdminProcessRunStatusEnum::cases(), fn (AdminProcessRunStatusEnum $status): bool => $status !== AdminProcessRunStatusEnum::Completed)));

it('includes trusts and preserves missing and zero metrics', function () {
    $this->actingAs(User::factory()->create(['is_paid' => true]));
    createScreenResultRow('EMBASSY', 'Embassy', '2026-10-30', [
        'series' => 'RR', 'is_nifty_total_market' => true, 'close_raw' => 200, 'close_adjusted' => 100,
        'marketcap' => 100, 'price_to_earnings' => null, 'absolute_return_one_year' => 0, 'beta' => null,
    ]);
    createScreenResultRow('CUBEINVIT', 'Cube', '2026-10-30', [
        'series' => 'IV', 'is_nifty_total_market' => true, 'marketcap' => null, 'price_to_earnings' => 0, 'absolute_return_one_year' => -10,
    ]);
    createScreenResultRow('OUTSIDE', 'Outside', '2026-10-30', ['marketcap' => 999999]);

    $this->get('/nse-index/nifty-total-market/constituents')->assertInertia(fn (Assert $page) => $page
        ->where('constituents.total', 2)
        ->where('constituents.data.0.symbol', 'EMBASSY')
        ->where('constituents.data.0.close_adjusted', '100.00')
        ->where('constituents.data.0.price_to_earnings', null)
        ->where('constituents.data.0.absolute_return_one_year', fn ($value): bool => (float) $value === 0.0)
        ->where('constituents.data.0.beta', null)
        ->where('constituents.data.1.symbol', 'CUBEINVIT')
        ->where('constituents.data.1.marketcap', null));

    $rows = constituentCsvRows($this->get('/nse-index/nifty-total-market/constituents/export')->assertSuccessful());
    expect($rows[1][3])->toBe('100.00')->and($rows[1][5])->toBe('')->and((float) $rows[1][6])->toBe(0.0)
        ->and($rows[1][7])->toBe('')->and($rows[2][4])->toBe('')->and((float) $rows[2][5])->toBe(0.0);
});

it('paginates the list but exports every matching row with the same search and sort', function () {
    $this->actingAs(User::factory()->create(['is_paid' => true]));
    foreach (range(1, 52) as $number) {
        createScreenResultRow('STOCK'.str_pad((string) $number, 2, '0', STR_PAD_LEFT), 'Example Company', '2026-10-30', [
            'is_nifty_fno' => true, 'marketcap' => 100 + $number,
        ]);
    }
    createScreenResultRow('OUTSIDE', 'Example Company', '2026-10-30');
    createScreenResultRow('UNMATCHED', 'Different name', '2026-10-30', ['is_nifty_fno' => true]);
    $query = '?date=2026-10-30&search=Example&sort=symbol&direction=asc&page=2';

    $this->get('/nse-index/nifty-fno/constituents'.$query)->assertInertia(fn (Assert $page) => $page
        ->where('constituents.total', 52)->has('constituents.data', 2)
        ->where('constituents.data.0.symbol', 'STOCK51')
        ->where('constituents.prev_page_url', fn (string $url): bool => str_contains($url, 'date=2026-10-30') && str_contains($url, 'search=Example'))
        ->where('exportUrl', fn (string $url): bool => str_contains($url, 'search=Example') && ! str_contains($url, 'page=')));
    $response = $this->get('/nse-index/nifty-fno/constituents/export'.$query)
        ->assertSuccessful()->assertDownload('nifty-fno-constituents-2026-10-30.csv');
    $rows = constituentCsvRows($response);
    expect($rows)->toHaveCount(53)->and($rows[0])->toBe(['date', 'symbol', 'name', 'close_adjusted', 'marketcap_crore', 'price_to_earnings', 'absolute_return_one_year', 'beta'])
        ->and($rows[1][1])->toBe('STOCK01')->and($rows[52][1])->toBe('STOCK52');
});

it('keeps missing values last when sorting either way', function (string $direction, array $expected) {
    foreach (['LOW' => 10, 'HIGH' => 100, 'MISSING' => null] as $symbol => $marketcap) {
        createScreenResultRow($symbol, $symbol, '2026-10-30', ['is_nifty_50' => true, 'marketcap' => $marketcap]);
    }
    $this->get('/nse-index/nifty-50/constituents?sort=marketcap&direction='.$direction)->assertInertia(fn (Assert $page) => $page
        ->where('constituents.data', fn ($rows): bool => collect($rows)->pluck('symbol')->all() === $expected));
})->with([
    ['asc', ['LOW', 'HIGH', 'MISSING']],
    ['desc', ['HIGH', 'LOW', 'MISSING']],
]);

it('escapes spreadsheet formulas in company names while retaining numeric returns', function () {
    $this->actingAs(User::factory()->create(['is_paid' => true]));
    createScreenResultRow('MEMBER', '=1+1', '2026-10-30', ['is_nifty_50' => true, 'absolute_return_one_year' => -10]);
    $rows = constituentCsvRows($this->get('/nse-index/nifty-50/constituents/export')->assertSuccessful());
    expect($rows[1][2])->toBe("'=1+1")->and((float) $rows[1][6])->toBe(-10.0);
});

it('shows an empty list instead of using an older membership snapshot', function () {
    createScreenResultRow('OLD', 'Old member', '2026-10-29', ['is_nifty_fno' => true]);
    createScreenResultRow('CURRENT', 'Current date', '2026-10-30');
    $this->get('/nse-index/nifty-fno/constituents')->assertInertia(fn (Assert $page) => $page
        ->where('date', '2026-10-30')->where('constituents.total', 0)->has('constituents.data', 0));
});

it('handles an empty database with a usable page and CSV header', function () {
    $this->actingAs(User::factory()->create(['is_paid' => true]));
    $this->get('/nse-index/nifty-50/constituents')->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->where('date', null)->where('constituents.total', 0)->where('indexUrl', null));
    expect(constituentCsvRows($this->get('/nse-index/nifty-50/constituents/export')->assertSuccessful()))->toHaveCount(1);
});

it('rejects unsupported indices and invalid query fields', function (string $suffix) {
    $this->actingAs(User::factory()->create(['is_paid' => true]));
    $this->get('/nse-index/unknown/constituents'.$suffix)->assertNotFound();
    $this->get('/nse-index/nifty_fno/constituents'.$suffix)->assertNotFound();
    foreach (['sort' => 'not_a_column', 'direction' => 'invalid', 'date' => '2026-02-30', 'page' => 0, 'search' => str_repeat('x', 101)] as $key => $value) {
        $this->getJson('/nse-index/nifty-50/constituents'.$suffix.'?'.http_build_query([$key => $value]))->assertUnprocessable()->assertJsonValidationErrors($key);
    }
})->with(['', '/export']);

it('links supported index overviews to their constituents', function (string $slug, ?string $expected) {
    NseIndex::insert([
        'symbol' => 'Index', 'slug' => $slug, 'date' => '2026-10-30',
        'open' => 100, 'high' => 100, 'low' => 100, 'close' => 100,
        'points_change' => 0, 'percentage_change' => 0, 'volume' => 0, 'turnover' => 0,
    ]);
    $this->get('/nse-index/'.$slug)->assertSuccessful()->assertInertia(fn (Assert $page) => $page->where('constituentsUrl', $expected));
})->with([
    ['nifty-50', '/nse-index/nifty-50/constituents?date=2026-10-30'],
    ['nifty-bank', '/nse-index/nifty-bank/constituents?date=2026-10-30'],
    ['india-vix', null],
]);
