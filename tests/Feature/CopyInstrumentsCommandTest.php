<?php

use App\Console\Commands\Backtest\CopyInstrumentsCommand;
use App\Models\BacktestNseInstrument;
use App\Models\BacktestNseInstrumentPrice;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Fake the disk so the command never touches the real uploads/ files.
    Storage::fake('local');
});

function createBacktestPrice(string $symbol, string $date): BacktestNseInstrumentPrice
{
    return BacktestNseInstrumentPrice::create([
        'date' => $date,
        'symbol' => $symbol,
        'open_adjusted' => 0,
        'high_adjusted' => 0,
        'low_adjusted' => 0,
        'close_adjusted' => 0,
        'volume_adjusted' => 0,
        'volume_shares_adjusted' => 0,
        'open_raw' => 0,
        'high_raw' => 0,
        'low_raw' => 0,
        'close_raw' => 0,
        'volume_raw' => 0,
        'volume_shares_raw' => 0,
        't_percent' => 0,
        't_percent_raw' => 0,
    ]);
}

/**
 * Write a fake NSE etf.csv (symbol = column 2, underlying index = column 14).
 *
 * @param  array<int, array{0: string, 1: string}>  $rows  pairs of [symbol, underlying]
 */
function putEtfCsv(string $date, array $rows): void
{
    $lines = ['MARKET,SERIES,SYMBOL,SECURITY,PREVIOUS CLOSE PRICE,OPEN PRICE,HIGH PRICE,LOW PRICE,CLOSE PRICE,NET TRADED VALUE,NET TRADED QTY,TRADES,52 WEEK HIGH,52 WEEK LOW,UNDERLYING'];

    foreach ($rows as [$symbol, $underlying]) {
        $lines[] = "N,EQ,{$symbol},DESC,0,0,0,0,0,0,0,0,0,0,{$underlying}";
    }

    Storage::put("uploads/{$date}/etf.csv", implode("\n", $lines));
}

it('fails when no date is provided', function () {
    $this->artisan('backtest:copy-instruments')
        ->expectsOutputToContain('Please provide a date')
        ->assertFailed();
});

it('copies only the symbols present on the given date', function () {
    createBacktestPrice('RELIANCE', '2019-12-19');
    createBacktestPrice('RELIANCE', '2019-12-20');
    createBacktestPrice('TCS', '2019-12-20');
    createBacktestPrice('INFY', '2019-12-19'); // only traded on the 19th

    $this->artisan('backtest:copy-instruments', ['--date' => '2019-12-20'])->assertSuccessful();

    expect(BacktestNseInstrument::orderBy('symbol')->pluck('symbol')->all())
        ->toBe(['RELIANCE', 'TCS']);
});

it('does not insert symbols that are already present', function () {
    BacktestNseInstrument::create(['symbol' => 'RELIANCE']);
    createBacktestPrice('RELIANCE', '2019-12-20');
    createBacktestPrice('TCS', '2019-12-20');

    $this->artisan('backtest:copy-instruments', ['--date' => '2019-12-20'])->assertSuccessful();

    expect(BacktestNseInstrument::count())->toBe(2)
        ->and(BacktestNseInstrument::orderBy('symbol')->pluck('symbol')->all())
        ->toBe(['RELIANCE', 'TCS']);
});

it('is idempotent across repeated runs for the same date', function () {
    createBacktestPrice('RELIANCE', '2019-12-20');
    createBacktestPrice('TCS', '2019-12-20');

    $this->artisan('backtest:copy-instruments', ['--date' => '2019-12-20'])->assertSuccessful();
    $this->artisan('backtest:copy-instruments', ['--date' => '2019-12-20'])->assertSuccessful();

    expect(BacktestNseInstrument::count())->toBe(2);
});

it('populates normalized etf_index from the date file and leaves non-etfs null', function () {
    createBacktestPrice('NIFTYBEES', '2019-12-20');
    createBacktestPrice('HDFCNIFETF', '2019-12-20');
    createBacktestPrice('GOLDBEES', '2019-12-20');
    createBacktestPrice('RELIANCE', '2019-12-20'); // not an etf

    putEtfCsv('2019-12-20', [
        ['NIFTYBEES', 'NIFTY 50'],
        ['HDFCNIFETF', 'NIFTY'],   // variant of the same index
        ['GOLDBEES', 'GOLD'],
    ]);

    $this->artisan('backtest:copy-instruments', ['--date' => '2019-12-20'])->assertSuccessful();

    expect(BacktestNseInstrument::where('symbol', 'NIFTYBEES')->value('etf_index'))->toBe('nifty-50')
        ->and(BacktestNseInstrument::where('symbol', 'HDFCNIFETF')->value('etf_index'))->toBe('nifty-50')
        ->and(BacktestNseInstrument::where('symbol', 'GOLDBEES')->value('etf_index'))->toBe('gold')
        ->and(BacktestNseInstrument::where('symbol', 'RELIANCE')->value('etf_index'))->toBeNull();
});

it('only reads the etf file for the given date', function () {
    createBacktestPrice('NIFTYBEES', '2019-12-20');

    putEtfCsv('2019-12-19', [['NIFTYBEES', 'STALE INDEX']]);
    putEtfCsv('2019-12-20', [['NIFTYBEES', 'NIFTY 50']]);

    $this->artisan('backtest:copy-instruments', ['--date' => '2019-12-20'])->assertSuccessful();

    expect(BacktestNseInstrument::where('symbol', 'NIFTYBEES')->value('etf_index'))->toBe('nifty-50');
});

it('normalizes known index variants and slugifies unknown indexes', function () {
    $command = new CopyInstrumentsCommand;

    expect($command->normalizeIndex('NIFTY 50'))->toBe('nifty-50')
        ->and($command->normalizeIndex('NIFTY'))->toBe('nifty-50')
        ->and($command->normalizeIndex('NIFTY50'))->toBe('nifty-50')
        ->and($command->normalizeIndex('  nifty   50  '))->toBe('nifty-50')
        ->and($command->normalizeIndex('NIFTY NEXT 50 ETF'))->toBe('nifty-next-50')
        ->and($command->normalizeIndex('S&P BSE 500 INDEX'))->toBe('bse-500')
        ->and($command->normalizeIndex('NIFTY BHARAT BOND'))->toBe('nifty-bharat-bond')
        ->and($command->isKnownIndex('NIFTY BHARAT BOND'))->toBeTrue()
        ->and($command->normalizeIndex('SOME BRAND NEW INDEX'))->toBe('some-brand-new-index');
});

it('warns about unmapped indexes but still applies the fallback slug', function () {
    createBacktestPrice('NEWETF', '2019-12-20');

    putEtfCsv('2019-12-20', [['NEWETF', 'NIFTY BRAND NEW INDEX']]);

    $this->artisan('backtest:copy-instruments', ['--date' => '2019-12-20'])
        ->expectsOutputToContain('1 unmapped ETF index')
        ->expectsOutputToContain('"NIFTY BRAND NEW INDEX" => nifty-brand-new-index')
        ->assertSuccessful();

    expect(BacktestNseInstrument::where('symbol', 'NEWETF')->value('etf_index'))->toBe('nifty-brand-new-index');
});
