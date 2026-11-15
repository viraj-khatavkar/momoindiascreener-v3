<?php

use App\Models\BacktestNseIndexConstituent;
use App\Models\BacktestNseInstrumentPrice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Http::preventStrayRequests();
});

it('imports dated stock contracts including zero trades and excludes index contracts', function () {
    $date = '2026-10-30';
    Storage::put("uploads/{$date}/fno.csv", "TradDt,FinInstrmTp,TckrSymb,XpryDt,TtlTradgVol\n{$date},STF,MEMBER,2026-11-24,0\n{$date},STO,MEMBER,2026-11-24,12\n{$date},STO,SECOND,2026-12-29,0\n{$date},IDF,NIFTY,2026-11-24,100\n");
    $member = createBacktestPriceRow('MEMBER', $date, ['close_raw' => 123, 'beta' => 1.5]);
    $second = createBacktestPriceRow('SECOND', $date);
    $other = createBacktestPriceRow('NIFTY', $date);
    $earlier = createBacktestPriceRow('MEMBER', '2026-10-29');

    $this->artisan('backtest:import-fno-constituents', ['--date' => $date])->assertSuccessful();

    expect($member->fresh()->is_nifty_fno)->toBeTrue()
        ->and($second->fresh()->is_nifty_fno)->toBeTrue()
        ->and($other->fresh()->is_nifty_fno)->toBeFalse()
        ->and($earlier->fresh()->is_nifty_fno)->toBeFalse()
        ->and((float) $member->fresh()->close_raw)->toBe(123.0)
        ->and((float) $member->fresh()->beta)->toBe(1.5)
        ->and(BacktestNseIndexConstituent::where('index', 'nifty_fno')->pluck('symbol')->all())->toBe(['MEMBER', 'SECOND']);
});

it('reads UDiFF CSVs and follows dated symbol chains without a header', function () {
    Storage::put('changes.csv', "Example,OLD,MIDDLE,01-JUN-2025\nExample,MIDDLE,FINAL,01-JAN-2026\nReused,REUSED,DIFFERENT,01-JAN-2009\n");
    Storage::put('udiff.csv', "FinInstrmTp,TckrSymb,TradDt,XpryDt,TtlTradgVol\nSTF,OLD,2025-01-03,2025-01-30,0\nSTO,REUSED,2025-01-03,2025-01-30,1\n");
    $final = createBacktestPriceRow('FINAL', '2025-01-03');
    $reused = createBacktestPriceRow('REUSED', '2025-01-03');
    $different = createBacktestPriceRow('DIFFERENT', '2025-01-03');

    $this->artisan('backtest:import-fno-constituents', [
        '--date' => '2025-01-03', '--file' => Storage::path('udiff.csv'), '--symbol-changes' => Storage::path('changes.csv'),
    ])->assertSuccessful();

    expect($final->fresh()->is_nifty_fno)->toBeTrue()
        ->and($reused->fresh()->is_nifty_fno)->toBeTrue()
        ->and($different->fresh()->is_nifty_fno)->toBeFalse();
});

it('replaces membership only on the imported date and preserves a later current list', function () {
    Storage::put('uploads/2026-10-29/fno.csv', "TradDt,FinInstrmTp,TckrSymb,XpryDt\n2026-10-29,STF,NEW,2026-11-24\n");
    $old = createBacktestPriceRow('OLD', '2026-10-29', ['is_nifty_fno' => true]);
    $new = createBacktestPriceRow('NEW', '2026-10-29');
    $later = createBacktestPriceRow('OLD', '2026-10-30', ['is_nifty_fno' => true]);
    BacktestNseIndexConstituent::insert(['index' => 'nifty_fno', 'symbol' => 'OLD']);

    foreach ([1, 2] as $attempt) {
        $this->artisan('backtest:import-fno-constituents', ['--date' => '2026-10-29'])->assertSuccessful();
    }
    expect($old->fresh()->is_nifty_fno)->toBeFalse()
        ->and($new->fresh()->is_nifty_fno)->toBeTrue()
        ->and($later->fresh()->is_nifty_fno)->toBeTrue()
        ->and(BacktestNseIndexConstituent::sole()->symbol)->toBe('OLD');
});

it('rejects a wrong-date file without clearing existing membership', function () {
    $member = createBacktestPriceRow('MEMBER', '2026-10-30', ['is_nifty_fno' => true]);
    Storage::put('uploads/2026-10-30/fno.csv', "TradDt,FinInstrmTp,TckrSymb,XpryDt\n2026-10-29,STF,MEMBER,2026-11-24\n");

    $this->artisan('backtest:import-fno-constituents', ['--date' => '2026-10-30'])
        ->expectsOutputToContain('expected 2026-10-30')->assertFailed();
    expect($member->fresh()->is_nifty_fno)->toBeTrue();
});

it('fails on a missing source instead of copying current membership into history', function () {
    createBacktestPriceRow('MEMBER', '2011-01-03');
    BacktestNseIndexConstituent::insert(['index' => 'nifty_fno', 'symbol' => 'MEMBER']);

    $this->artisan('backtest:import-fno-constituents', ['--date' => '2011-01-03'])
        ->expectsOutputToContain('Upload the F&O CSV')->assertFailed();
    expect(isIndexFlagged('MEMBER', '2011-01-03', 'is_nifty_fno'))->toBeFalse();
    Http::assertNothingSent();
});

it('skips the approved special session without changing prices', function () {
    $price = createBacktestPriceRow('EMBASSY', '2024-03-02', ['series' => 'RR']);
    $original = $price->fresh()->getAttributes();

    $this->artisan('backtest:import-fno-constituents', ['--date' => '2024-03-02'])
        ->expectsOutputToContain('2024-03-02: skipped the excluded special session.')
        ->assertSuccessful();

    expect($price->fresh()->getAttributes())->toBe($original);
    Http::assertNothingSent();
});

it('rejects missing price matches without publishing partial membership', function (bool $dryRun) {
    $old = createBacktestPriceRow('OLD', '2026-10-30', ['is_nifty_fno' => true]);
    createBacktestPriceRow('MEMBER', '2026-10-30');
    BacktestNseIndexConstituent::insert(['index' => 'nifty_fno', 'symbol' => 'OLD']);
    Storage::put('uploads/2026-10-30/fno.csv', "TradDt,FinInstrmTp,TckrSymb,XpryDt\n2026-10-30,STF,MEMBER,2026-11-24\n2026-10-30,STF,MISSING,2026-11-24\n");
    $this->artisan('backtest:import-fno-constituents', ['--date' => '2026-10-30', '--dry-run' => $dryRun])
        ->expectsOutputToContain('no matching price row for 2026-10-30: MISSING')->assertFailed();
    expect($old->fresh()->is_nifty_fno)->toBeTrue()
        ->and(isIndexFlagged('MEMBER', '2026-10-30', 'is_nifty_fno'))->toBeFalse()
        ->and(BacktestNseIndexConstituent::sole()->symbol)->toBe('OLD');
})->with([false, true]);

it('checks a complete daily file without changing flags during a dry run', function () {
    $old = createBacktestPriceRow('OLD', '2026-10-30', ['is_nifty_fno' => true]);
    createBacktestPriceRow('MEMBER', '2026-10-30');
    Storage::put('uploads/2026-10-30/fno.csv', "TradDt,FinInstrmTp,TckrSymb,XpryDt\n2026-10-30,STF,MEMBER,2026-11-24\n");
    $this->artisan('backtest:import-fno-constituents', ['--date' => '2026-10-30', '--dry-run' => true])->assertSuccessful();
    expect($old->fresh()->is_nifty_fno)->toBeTrue()
        ->and(isIndexFlagged('MEMBER', '2026-10-30', 'is_nifty_fno'))->toBeFalse();
});

it('rejects ambiguous symbol matches before changing any rows', function () {
    Storage::put('changes.csv', "Example,OLD,NEW,01-JUN-2025\n");
    Storage::put('uploads/2025-01-03/fno.csv', "FinInstrmTp,TckrSymb,TradDt,XpryDt\nSTF,OLD,2025-01-03,2025-01-30\n");
    createBacktestPriceRow('OLD', '2025-01-03');
    createBacktestPriceRow('NEW', '2025-01-03');

    $this->artisan('backtest:import-fno-constituents', ['--date' => '2025-01-03', '--symbol-changes' => Storage::path('changes.csv')])
        ->expectsOutputToContain('Ambiguous F&O symbol')->assertFailed();
    expect(BacktestNseInstrumentPrice::where('is_nifty_fno', true)->count())->toBe(0);
});

it('rejects ZIP files in the daily command without changing saved flags', function (string $filename) {
    $member = createBacktestPriceRow('MEMBER', '2026-10-30', ['is_nifty_fno' => true]);
    $path = Storage::path($filename);
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('bhav.csv', "TradDt,FinInstrmTp,TckrSymb,XpryDt\n2026-10-30,STF,MEMBER,2026-11-24\n");
    $zip->close();
    $this->artisan('backtest:import-fno-constituents', ['--date' => '2026-10-30', '--file' => $path])
        ->expectsOutputToContain('Only NSE F&O UDiFF files are supported')->assertFailed();
    expect($member->fresh()->is_nifty_fno)->toBeTrue();
})->with(['archive.zip', 'renamed.csv']);

it('rejects UDiFF files with invalid dates or expiries before changing membership', function (string $row) {
    $member = createBacktestPriceRow('MEMBER', '2026-10-30', ['is_nifty_fno' => true]);
    Storage::put('uploads/2026-10-30/fno.csv', "TradDt,FinInstrmTp,TckrSymb,XpryDt\n{$row}\n");

    $this->artisan('backtest:import-fno-constituents', ['--date' => '2026-10-30'])->assertFailed();
    expect($member->fresh()->is_nifty_fno)->toBeTrue();
})->with([
    'wrong date' => ['2026-10-29,STF,MEMBER,2026-11-24'],
    'missing expiry' => ['2026-10-30,STF,MEMBER,'],
    'expired contract' => ['2026-10-30,STF,MEMBER,2026-10-27'],
    'legacy date format' => ['30-OCT-2026,STF,MEMBER,24-NOV-2026'],
]);

it('rejects legacy and settlement CSVs without changing saved history', function (string $csv) {
    $price = createBacktestPriceRow('MEMBER', '2012-05-14', ['is_nifty_fno' => true]);
    $original = $price->fresh()->getAttributes();
    Storage::put('uploads/2012-05-14/fno.csv', $csv);

    $this->artisan('backtest:import-fno-constituents', ['--date' => '2012-05-14'])
        ->expectsOutputToContain('Only NSE F&O UDiFF files are supported')->assertFailed();
    expect(isIndexFlagged('MEMBER', '2012-05-14', 'is_nifty_fno'))->toBeTrue()
        ->and($price->fresh()->getAttributes())->toBe($original);
})->with([
    'legacy' => ["INSTRUMENT,SYMBOL,TIMESTAMP,EXPIRY_DT\nFUTSTK,MEMBER,14-MAY-2012,31-MAY-2012\n"],
    'two-digit legacy year' => ["INSTRUMENT,SYMBOL,TIMESTAMP,EXPIRY_DT\nFUTSTK,MEMBER,14-May-12,31-May-12\n\n*Updated\n"],
    'settlement' => ["DATE,INSTRUMENT,UNDERLYING,EXPIRY DATE\n14-MAY-2012,FUTSTK,MEMBER,31-MAY-2012\n"],
]);

it('requires a dated file on a rerun without clearing saved flags', function () {
    $price = createBacktestPriceRow('MEMBER', '2011-01-03', ['is_nifty_fno' => true]);
    $original = $price->fresh()->getAttributes();
    $this->artisan('backtest:import-fno-constituents', ['--date' => '2011-01-03'])
        ->expectsOutputToContain('Upload the F&O CSV')->assertFailed();
    expect($price->fresh()->getAttributes())->toBe($original);
    Http::assertNothingSent();
});

it('requires a valid date before reading files or changing membership', function (?string $date) {
    $this->artisan('backtest:import-fno-constituents', ['--date' => $date])
        ->expectsOutputToContain('Use --date=YYYY-MM-DD.')->assertFailed();
})->with([null, '30-OCT-2026', '2026-02-30']);
