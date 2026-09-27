<?php

use App\Models\BacktestNseInstrumentPrice;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('imports the uploaded March 4 UDiFF layout including company names', function () {
    $contents = <<<'CSV'
TradDt,BizDt,Sgmt,Src,FinInstrmTp,FinInstrmId,ISIN,TckrSymb,SctySrs,XpryDt,FininstrmActlXpryDt,StrkPric,OptnTp,FinInstrmNm,OpnPric,HghPric,LwPric,ClsPric,LastPric,PrvsClsgPric,UndrlygPric,SttlmPric,OpnIntrst,ChngInOpnIntrst,TtlTradgVol,TtlTrfVal,TtlNbOfTxsExctd,SsnId,NewBrdLotQty,Rmks,Rsvd01,Rsvd02,Rsvd03,Rsvd04,
2024-03-04,2024-03-04,CM,NSE,STK,9219,INE413G01022,TPLPLASTEH,EQ,,,,,TPL PLASTECH LIMITED,67.55,71.60,66.85,69.30,69.90,67.55,,69.30,,,540034,37588139.10,3376,F1,1,,,,,
2024-03-04,2024-03-04,CM,NSE,STK,10610,INE0CBM01019,USASEEDS,SM,,,,,UPSURGE SEEDS OF AGRI LTD,348.00,355.00,345.00,350.50,351.00,353.00,,350.50,,,4500,1564950.00,14,F1,300,,,,,
CSV;
    Storage::put('uploads/2024-03-04/bhavcopy.csv', $contents);

    $this->artisan('backtest:import-instruments', ['--date' => '2024-03-04'])
        ->expectsOutputToContain('2 instruments imported.')
        ->assertSuccessful();

    $price = BacktestNseInstrumentPrice::where('symbol', 'TPLPLASTEH')->sole();
    $smallPrice = BacktestNseInstrumentPrice::where('symbol', 'USASEEDS')->sole();

    expect($price->date->toDateString())->toBe('2024-03-04')
        ->and($price->name)->toBe('TPL PLASTECH LIMITED')
        ->and($price->series)->toBe('EQ')
        ->and($smallPrice->name)->toBe('UPSURGE SEEDS OF AGRI LTD')
        ->and($smallPrice->series)->toBe('SM')
        ->and((float) $smallPrice->close_raw)->toBe(350.5)
        ->and(BacktestNseInstrumentPrice::count())->toBe(2);

    foreach (['raw', 'adjusted'] as $suffix) {
        expect((float) $price->{"open_{$suffix}"})->toBe(67.55)
            ->and((float) $price->{"high_{$suffix}"})->toBe(71.6)
            ->and((float) $price->{"low_{$suffix}"})->toBe(66.85)
            ->and((float) $price->{"close_{$suffix}"})->toBe(69.3)
            ->and((int) $price->{"volume_shares_{$suffix}"})->toBe(540034)
            ->and((int) $price->{"volume_{$suffix}"})->toBe(37588139)
            ->and((float) $price->{'t_percent'.($suffix === 'raw' ? '_raw' : '')})->toBe(0.0);
    }
});

it('keeps the supported series and excludes rights and non cash market rows', function () {
    $rows = array_map(fn (string $series): array => ['TckrSymb' => "STOCK{$series}", 'SctySrs' => $series], ['EQ', 'BE', 'SM', 'ST', 'SZ', 'BZ']);
    $rows[] = ['TckrSymb' => 'OTHERSEGMENT', 'Sgmt' => 'FO'];
    $rows[] = ['TckrSymb' => 'OTHERSERIES', 'SctySrs' => 'IT'];

    foreach (['-RE', '-RE1', '-RE2', '-RE3'] as $suffix) {
        $rows[] = ['TckrSymb' => 'RIGHTS'.$suffix];
    }

    putUdiffBhavcopy($rows);
    $this->artisan('backtest:import-instruments', ['--date' => '2024-03-04'])->assertSuccessful();

    expect(BacktestNseInstrumentPrice::orderBy('symbol')->pluck('symbol')->all())
        ->toBe(['STOCKBE', 'STOCKBZ', 'STOCKEQ', 'STOCKSM', 'STOCKST', 'STOCKSZ']);
});

it('uses header names and handles whitespace blank lines and quoted company names', function () {
    putUdiffBhavcopy([['TckrSymb' => '  EXAMPLE  ', 'FinInstrmNm' => '  EXAMPLE, LIMITED  ']], reverseHeaders: true);
    $path = 'uploads/2024-03-04/bhavcopy.csv';
    Storage::put($path, "\n\xEF\xBB\xBF".Storage::get($path)."\n");

    $this->artisan('backtest:import-instruments', ['--date' => '2024-03-04'])->assertSuccessful();

    expect(BacktestNseInstrumentPrice::sole()->name)->toBe('EXAMPLE, LIMITED')
        ->and(BacktestNseInstrumentPrice::sole()->symbol)->toBe('EXAMPLE');
});

it('checks new UDiFF symbols without creating records', function () {
    $existing = createBacktestPriceRow('EXISTING', '2024-03-01', ['name' => 'Existing company']);
    putUdiffBhavcopy([['TckrSymb' => 'EXISTING'], ['TckrSymb' => 'NEWSTOCK']]);

    $this->artisan('backtest:import-instruments', ['--date' => '2024-03-04', '--omit-create' => true])
        ->expectsOutputToContain('No match found for NEWSTOCK')
        ->doesntExpectOutputToContain('No match found for EXISTING')
        ->expectsOutputToContain('2 instruments checked. No records created.')
        ->assertSuccessful();

    expect(BacktestNseInstrumentPrice::count())->toBe(1)
        ->and($existing->fresh()->name)->toBe('Existing company');
});

it('rejects the old bhavcopy format', function () {
    Storage::put('uploads/2024-03-04/bhavcopy.csv', "SYMBOL,SERIES,OPEN,HIGH,LOW,CLOSE,LAST,PREVCLOSE,TOTTRDQTY,TOTTRDVAL,TIMESTAMP\nEXAMPLE,EQ,10,12,9,11,11,10,100,1100,04-MAR-2024");

    $this->artisan('backtest:import-instruments', ['--date' => '2024-03-04'])
        ->expectsOutputToContain('Upload a UDiFF bhavcopy file.')
        ->assertFailed();

    expect(BacktestNseInstrumentPrice::count())->toBe(0);
});

it('rejects invalid file rows before saving any instruments', function (array $attributes, string $message) {
    putUdiffBhavcopy([['TckrSymb' => 'VALID'], ['TckrSymb' => 'INVALID', ...$attributes]]);

    $this->artisan('backtest:import-instruments', ['--date' => '2024-03-04'])
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect(BacktestNseInstrumentPrice::count())->toBe(0);
})->with([
    'wrong date' => [['TradDt' => '2024-03-01'], 'does not match 2024-03-04'],
    'missing symbol' => [['TckrSymb' => ''], 'Missing symbol'],
    'invalid close' => [['ClsPric' => '-'], 'Invalid close'],
    'negative volume' => [['TtlTradgVol' => '-1'], 'Invalid volume_shares'],
]);

it('does not mark an empty import as successful', function () {
    putUdiffBhavcopy([['Sgmt' => 'FO']]);

    $this->artisan('backtest:import-instruments', ['--date' => '2024-03-04'])
        ->expectsOutputToContain('No supported instruments found')
        ->assertFailed();
});

it('requires an uploaded file', function () {
    $this->artisan('backtest:import-instruments', ['--date' => '2024-03-04'])
        ->expectsOutputToContain('Upload bhavcopy.csv for 2024-03-04')
        ->assertFailed();
});

it('requires a valid date', function (?string $date) {
    $this->artisan('backtest:import-instruments', $date === null ? [] : ['--date' => $date])->assertFailed();
})->with([null, '2024-02-30', '../2024-03-04']);

it('prevents an import when price rows already exist for the date', function () {
    $price = createBacktestPriceRow('EXAMPLE', '2024-03-04', ['name' => 'Original name']);
    putUdiffBhavcopy([['TckrSymb' => 'EXAMPLE', 'FinInstrmNm' => 'New name']]);

    $this->artisan('backtest:import-instruments', ['--date' => '2024-03-04'])
        ->expectsOutputToContain('Price record already exists for 2024-03-04')
        ->assertFailed();

    expect(BacktestNseInstrumentPrice::count())->toBe(1)
        ->and($price->fresh()->name)->toBe('Original name');
});

/**
 * @param  list<array<string, string>>  $rows
 */
function putUdiffBhavcopy(array $rows, bool $reverseHeaders = false): void
{
    $defaults = [
        'TradDt' => '2024-03-04', 'Sgmt' => 'CM', 'TckrSymb' => 'EXAMPLE', 'SctySrs' => 'EQ',
        'FinInstrmNm' => 'Example Limited', 'OpnPric' => '10', 'HghPric' => '12', 'LwPric' => '9',
        'ClsPric' => '11', 'TtlTradgVol' => '100', 'TtlTrfVal' => '1100',
    ];

    if ($reverseHeaders) {
        $defaults = array_reverse($defaults, true);
    }

    $stream = fopen('php://temp', 'r+');
    fputcsv($stream, array_keys($defaults), escape: '');

    foreach ($rows as $row) {
        fputcsv($stream, array_replace($defaults, $row), escape: '');
    }

    rewind($stream);
    Storage::put('uploads/2024-03-04/bhavcopy.csv', stream_get_contents($stream));
    fclose($stream);
}
