<?php

use App\Models\NseIndex;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->indexCsvHeader = 'Index Name,Index Date,Open Index Value,High Index Value,Low Index Value,Closing Index Value,Points Change,Change(%),Volume,Turnover (Rs. Cr.),P/E,P/B,Div Yield';
});

it('imports the uploaded index format and keeps turnover in crores', function () {
    Storage::put('uploads/2026-02-17/index.csv', $this->indexCsvHeader."\n"
        ."Nifty 50,17-02-2026,25637.95,25764.4,25570.3,25725.4,42.65,.17,378436265,27818.81,22.52,3.5,1.2\n"
        ."India VIX,17-02-2026,13.33,13.4775,11.625,12.67,-0.66,-4.93,-,-,-,-,-\n"
        ."Nifty50 Dividend Points,17-02-2026,-,-,-,274.65,0.0,-,-,-,-,-,-\n"
        ."Nifty Total Market,17-02-2026,13146,13214.85,13117.45,13201.95,41.25,.31,2894989822,77688.43,23.95,3.62,1.08\n");

    $this->artisan('nse:import-indices', ['--date' => '2026-02-17'])
        ->expectsOutputToContain('4 indices imported for 2026-02-17.')
        ->assertSuccessful();

    $nifty = NseIndex::query()->where('slug', 'nifty-50')->sole();
    expect($nifty->date->format('Y-m-d'))->toBe('2026-02-17')
        ->and($nifty->only([
            'symbol', 'slug', 'open', 'high', 'low', 'close', 'points_change', 'percentage_change',
            'volume', 'turnover', 'price_to_earnings', 'price_to_book', 'dividend_yield',
        ]))->toBe([
            'symbol' => 'Nifty 50', 'slug' => 'nifty-50', 'open' => '25637.95', 'high' => '25764.40',
            'low' => '25570.30', 'close' => '25725.40', 'points_change' => '42.65', 'percentage_change' => '0.17',
            'volume' => 378436265, 'turnover' => '27818.81', 'price_to_earnings' => '22.52',
            'price_to_book' => '3.50', 'dividend_yield' => '1.20',
        ]);

    $vix = NseIndex::query()->where('slug', 'india-vix')->sole();
    expect($vix->high)->toBe('13.48')
        ->and($vix->points_change)->toBe('-0.66')
        ->and($vix->percentage_change)->toBe('-4.93')
        ->and($vix->volume)->toBe(0)
        ->and($vix->turnover)->toBe('0.00')
        ->and($vix->price_to_earnings)->toBeNull()
        ->and($vix->price_to_book)->toBeNull()
        ->and($vix->dividend_yield)->toBeNull();

    $dividends = NseIndex::query()->where('slug', 'nifty50-dividend-points')->sole();
    expect($dividends->open)->toBeNull()
        ->and($dividends->high)->toBeNull()
        ->and($dividends->low)->toBeNull()
        ->and($dividends->close)->toBe('274.65')
        ->and($dividends->percentage_change)->toBeNull()
        ->and(NseIndex::query()->where('slug', 'nifty-total-market')->value('volume'))->toBe(2894989822);
});

it('imports zero dividend points after the annual reset', function (string $symbol) {
    Storage::put('uploads/2026-04-01/index.csv', $this->indexCsvHeader."\n"
        ."Nifty 50,01-04-2026,22899,22941.3,22618.6,22679.4,348.0,1.56,517954448,41192.2,19.93,3.1,1.37\n"
        ."{$symbol},01-04-2026,-,-,-,0.0,0.0,-,-,-,-,-,-\n"
        ."Nifty Auto,01-04-2026,24557.4,24646.5,24132.4,24238.85,469.25,1.97,138713873,5927.83,28.66,4.15,1.31\n");

    $this->artisan('nse:import-indices', ['--date' => '2026-04-01', '--omit-create' => true])
        ->expectsOutputToContain('3 indices checked. No records saved.')
        ->assertSuccessful();

    expect(NseIndex::query()->count())->toBe(0);

    $this->artisan('nse:import-indices', ['--date' => '2026-04-01'])
        ->expectsOutputToContain('3 indices imported for 2026-04-01.')
        ->assertSuccessful();

    $dividends = NseIndex::query()->where('slug', 'nifty50-dividend-points')->sole();
    expect($dividends->date->format('Y-m-d'))->toBe('2026-04-01')
        ->and($dividends->close)->toBe('0.00')
        ->and($dividends->points_change)->toBe('0.00')
        ->and($dividends->open)->toBeNull()
        ->and($dividends->high)->toBeNull()
        ->and($dividends->low)->toBeNull()
        ->and(NseIndex::query()->count())->toBe(3)
        ->and(NseIndex::query()->where('slug', 'nifty-50')->sole()->close)->toBe('22679.40')
        ->and(NseIndex::query()->where('slug', 'nifty-auto')->sole()->close)->toBe('24238.85');
})->with(['Nifty50 Dividend Points', 'NIFTY50 DIVIDEND POINTS']);

it('maps headers by name and accepts blank lines BOM and encoded index names', function () {
    Storage::put('uploads/2026-02-17/index.csv', "\n\xEF\xBB\xBF Closing Index Value , Index Name ,Index Date,Open Index Value,High Index Value,Low Index Value,Points Change,Change(%),Volume,Turnover (Rs. Cr.),P/E,P/B,Div Yield\n\n"
        ."\"25,725.40\",Nifty Test \x96 Index,17-02-2026,,,NA,0,0,,N/A,,0,-\n");

    $this->artisan('nse:import-indices', ['--date' => '2026-02-17'])->assertSuccessful();

    $index = NseIndex::query()->sole();
    expect($index->symbol)->toBe('Nifty Test – Index')
        ->and($index->slug)->toBe('nifty-test-index')
        ->and($index->close)->toBe('25725.40')
        ->and($index->open)->toBeNull()
        ->and($index->volume)->toBe(0)
        ->and($index->turnover)->toBe('0.00')
        ->and($index->price_to_book)->toBe('0.00');
});

it('can repeat and complete an import without duplicates or changes to other dates', function () {
    Storage::put('uploads/2026-02-16/index.csv', $this->indexCsvHeader."\nNifty 50,16-02-2026,100,110,90,105,5,5,10,20,21,3,1\n");
    $this->artisan('nse:import-indices', ['--date' => '2026-02-16'])->assertSuccessful();
    $previous = NseIndex::query()->sole()->getAttributes();

    Storage::put('uploads/2026-02-17/index.csv', $this->indexCsvHeader."\nNifty 50,17-02-2026,100,110,90,105,5,5,10,20,21,3,1\n");
    $this->artisan('nse:import-indices', ['--date' => '2026-02-17'])->assertSuccessful();
    $indexId = NseIndex::query()->where('date', '2026-02-17')->sole()->id;

    Storage::put('uploads/2026-02-17/index.csv', $this->indexCsvHeader."\n"
        ."NIFTY 50,17-02-2026,100,120,90,115,15,15,20,40,22,4,2\n"
        ."India VIX,17-02-2026,13.33,13.4775,11.625,12.67,-0.66,-4.93,-,-,-,-,-\n");

    for ($attempt = 0; $attempt < 2; $attempt++) {
        $this->artisan('nse:import-indices', ['--date' => '2026-02-17'])->assertSuccessful();
        $index = NseIndex::query()->findOrFail($indexId);

        expect(NseIndex::query()->count())->toBe(3)
            ->and($index->close)->toBe('115.00')
            ->and($index->symbol)->toBe('NIFTY 50')
            ->and($index->price_to_earnings)->toBe('22.00')
            ->and(NseIndex::query()->where('date', '2026-02-16')->sole()->getAttributes())->toBe($previous);
    }
});

it('checks an import without inserting or updating records', function () {
    Storage::put('uploads/2026-02-17/index.csv', $this->indexCsvHeader."\nNifty 50,17-02-2026,100,110,90,105,5,5,10,20,21,3,1\n");
    $this->artisan('nse:import-indices', ['--date' => '2026-02-17'])->assertSuccessful();
    $before = NseIndex::query()->sole()->getAttributes();

    Storage::put('uploads/2026-02-17/index.csv', $this->indexCsvHeader."\n"
        ."Nifty 50,17-02-2026,100,120,90,115,15,15,20,40,22,4,2\n"
        ."India VIX,17-02-2026,13.33,13.4775,11.625,12.67,-0.66,-4.93,-,-,-,-,-\n");

    $this->artisan('nse:import-indices', ['--date' => '2026-02-17', '--omit-create' => true])
        ->expectsOutputToContain('2 indices checked. No records saved.')
        ->assertSuccessful();

    expect(NseIndex::query()->sole()->getAttributes())->toBe($before);
});

it('requires a valid index import date', function (?string $date) {
    $this->artisan('nse:import-indices', $date === null ? [] : ['--date' => $date])
        ->expectsOutputToContain('Please provide a valid date in YYYY-MM-DD format.')
        ->assertFailed();
})->with([null, '', '2026-02-30', '17-02-2026', '../2026-02-17']);

it('requires an uploaded index file', function () {
    $this->artisan('nse:import-indices', ['--date' => '2026-02-17'])
        ->expectsOutputToContain('Upload index.csv for 2026-02-17')
        ->assertFailed();
});

it('rejects files with missing headers or no index data', function (string $content, string $message) {
    Storage::put('uploads/2026-02-17/index.csv', $content === 'header-only' ? $this->indexCsvHeader : $content);

    $this->artisan('nse:import-indices', ['--date' => '2026-02-17'])
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect(NseIndex::query()->count())->toBe(0);
})->with([
    ['Index Name,Index Date,Close', 'Invalid index.csv headers.'],
    ['', 'No index data found'],
    ['header-only', 'No index data found'],
]);

it('rejects an invalid later row before saving any index data', function (string $invalidRow, string $message) {
    Storage::put('uploads/2026-02-17/index.csv', $this->indexCsvHeader."\n"
        ."Nifty 50,17-02-2026,100,110,90,105,5,5,10,20,21,3,1\n{$invalidRow}\n");

    $this->artisan('nse:import-indices', ['--date' => '2026-02-17'])
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect(NseIndex::query()->count())->toBe(0);
})->with([
    'wrong date' => ['Other,16-02-2026,100,110,90,105,5,5,10,20,21,3,1', 'Index date on row 3 does not match'],
    'wrong date format' => ['Other,2026-02-17,100,110,90,105,5,5,10,20,21,3,1', 'Index date on row 3 does not match'],
    'missing column' => ['Other,17-02-2026,100,110,90,105,5,5,10,20,21,3', 'Missing dividend_yield'],
    'empty name' => [',17-02-2026,100,110,90,105,5,5,10,20,21,3,1', 'Invalid index name'],
    'duplicate index' => ['NIFTY 50,17-02-2026,100,110,90,105,5,5,10,20,21,3,1', 'Duplicate index'],
    'missing close' => ['Other,17-02-2026,100,110,90,-,5,5,10,20,21,3,1', 'Invalid close'],
    'zero close' => ['Other,17-02-2026,100,110,90,0,5,5,10,20,21,3,1', 'Invalid close'],
    'negative close' => ['Other,17-02-2026,100,110,90,-1,5,5,10,20,21,3,1', 'Invalid close'],
    'negative dividend points' => ['Nifty50 Dividend Points,17-02-2026,-,-,-,-1,0.0,-,-,-,-,-,-', 'Invalid close'],
    'missing dividend points' => ['Nifty50 Dividend Points,17-02-2026,-,-,-,-,0.0,-,-,-,-,-,-', 'Invalid close'],
    'invalid dividend points' => ['Nifty50 Dividend Points,17-02-2026,-,-,-,invalid,0.0,-,-,-,-,-,-', 'Invalid close'],
    'invalid price' => ['Other,17-02-2026,invalid,110,90,105,5,5,10,20,21,3,1', 'Invalid open'],
    'negative volume' => ['Other,17-02-2026,100,110,90,105,5,5,-10,20,21,3,1', 'Invalid volume'],
    'fractional volume' => ['Other,17-02-2026,100,110,90,105,5,5,1.5,20,21,3,1', 'Invalid volume'],
    'overflow' => ['Other,17-02-2026,100,110,90,105,5,5,10,20,1e8,3,1', 'Invalid price_to_earnings'],
    'non finite' => ['Other,17-02-2026,100,110,90,1e999,5,5,10,20,21,3,1', 'Invalid close'],
]);
