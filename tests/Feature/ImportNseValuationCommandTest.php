<?php

use App\Models\BacktestNseInstrumentPrice;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('imports market cap in crores for the matching date symbol and series and can run again', function () {
    $price = createBacktestPriceRow('20MICRONS', '2024-03-01', ['series' => 'EQ', 'price_to_earnings' => 15]);
    $smallPrice = createBacktestPriceRow('SMALL', '2024-03-01', ['series' => 'SM']);
    $otherDate = createBacktestPriceRow('20MICRONS', '2024-02-29', ['series' => 'EQ', 'marketcap' => 8]);
    $otherSeries = createBacktestPriceRow('WRONGSERIES', '2024-03-01', ['series' => 'BE', 'marketcap' => 9]);

    Storage::put('uploads/2024-03-01/marketcap.csv', "\xEF\xBB\xBFTrade Date,Symbol,Series,Market Cap(Rs.)  \n01 MAR 2024, 20MICRONS , EQ , 5262981773.30 \n01 MAR 2024,SMALL,SM,1234567.89\n01 MAR 2024,WRONGSERIES,EQ,10000000\n01 MAR 2024,Total, ,388932321251789.44\n");

    for ($attempt = 0; $attempt < 2; $attempt++) {
        $this->artisan('backtest:import-marketcap', ['--date' => '2024-03-01'])
            ->expectsOutputToContain('2 price rows. 1 file rows had no matching price row.')
            ->assertSuccessful();

        expect((int) $price->fresh()->marketcap)->toBe(526)
            ->and((int) $smallPrice->fresh()->marketcap)->toBe(0)
            ->and((float) $price->fresh()->price_to_earnings)->toBe(15.0)
            ->and((float) $otherDate->fresh()->marketcap)->toBe(8.0)
            ->and((float) $otherSeries->fresh()->marketcap)->toBe(9.0)
            ->and(BacktestNseInstrumentPrice::count())->toBe(4);
    }
});

it('imports adjusted PE and handles blank lines and missing values', function () {
    $price = createBacktestPriceRow('CIPLA', '2024-03-01', ['series' => 'EQ', 'marketcap' => 25]);
    $otherSeries = createBacktestPriceRow('OTHER', '2024-03-01', ['series' => 'BE']);
    $blank = createBacktestPriceRow('BLANK', '2024-03-01', ['price_to_earnings' => 8]);
    $missing = createBacktestPriceRow('MISSING', '2024-03-01', ['price_to_earnings' => 9]);
    $zero = createBacktestPriceRow('ZERO', '2024-03-01');
    $negative = createBacktestPriceRow('NEGATIVE', '2024-03-01');
    $otherDate = createBacktestPriceRow('CIPLA', '2024-02-29', ['price_to_earnings' => 10]);
    Storage::put('uploads/2024-03-01/price_to_earnings.csv', "\nSYMBOL,SYMBOL P/E,ADJUSTED P/E\n\nCIPLA  ,31.92  ,29.88\nOTHER,31.92,29.88\nBLANK,123,\nMISSING,20,-\nZERO,10,0\nNEGATIVE,1,-2.5\nUNKNOWN,1,2\n");

    for ($attempt = 0; $attempt < 2; $attempt++) {
        $this->artisan('backtest:import-price-to-earnings', ['--date' => '2024-03-01'])
            ->assertSuccessful();

        expect((float) $price->fresh()->price_to_earnings)->toBe(29.88)
            ->and((float) $otherSeries->fresh()->price_to_earnings)->toBe(29.88)
            ->and((float) $price->fresh()->marketcap)->toBe(25.0)
            ->and($blank->fresh()->price_to_earnings)->toBeNull()
            ->and($missing->fresh()->price_to_earnings)->toBeNull()
            ->and((float) $zero->fresh()->price_to_earnings)->toBe(0.0)
            ->and((float) $negative->fresh()->price_to_earnings)->toBe(-2.5)
            ->and((float) $otherDate->fresh()->price_to_earnings)->toBe(10.0)
            ->and(BacktestNseInstrumentPrice::count())->toBe(7);
    }
});

it('rounds raw market cap to whole crores', function (string $rawValue, int $expected) {
    $price = createBacktestPriceRow('EXAMPLE', '2024-03-01', ['series' => 'EQ']);
    Storage::put('uploads/2024-03-01/marketcap.csv', "Trade Date,Symbol,Series,Market Cap(Rs.)\n01 MAR 2024,EXAMPLE,EQ,\"{$rawValue}\"\n");

    $this->artisan('backtest:import-marketcap', ['--date' => '2024-03-01'])->assertSuccessful();

    expect((int) $price->fresh()->marketcap)->toBe($expected);
})->with([
    ['0', 0],
    ['1,00,00,000', 1],
    ['14999999.99', 1],
    ['15000000.00', 2],
    ['19921781943183.80', 1992178],
]);

it('requires a valid date', function (string $command, ?string $date) {
    $this->artisan($command, $date === null ? [] : ['--date' => $date])
        ->expectsOutputToContain('Please provide a valid date')
        ->assertFailed();
})->with(['backtest:import-marketcap', 'backtest:import-price-to-earnings'])
    ->with([null, '2024-02-30', '../2024-03-01']);

it('requires the uploaded file', function (string $command, string $filename) {
    $this->artisan($command, ['--date' => '2024-03-01'])
        ->expectsOutputToContain("Upload {$filename}.csv")
        ->assertFailed();
})->with([
    ['backtest:import-marketcap', 'marketcap'],
    ['backtest:import-price-to-earnings', 'price_to_earnings'],
]);

it('requires the instrument import first', function (string $command, string $filename) {
    Storage::put("uploads/2024-03-01/{$filename}.csv", 'data');

    $this->artisan($command, ['--date' => '2024-03-01'])
        ->expectsOutputToContain('Import instruments for 2024-03-01')
        ->assertFailed();
})->with([
    ['backtest:import-marketcap', 'marketcap'],
    ['backtest:import-price-to-earnings', 'price_to_earnings'],
]);

it('does not save partial imports when a file is invalid', function (string $filename, string $command, string $contents, string $error) {
    $price = createBacktestPriceRow('CIPLA', '2024-03-01', [
        'series' => 'EQ', 'marketcap' => 10, 'price_to_earnings' => 20,
    ]);
    Storage::put("uploads/2024-03-01/{$filename}.csv", $contents);

    $this->artisan($command, ['--date' => '2024-03-01'])
        ->expectsOutputToContain($error)
        ->assertFailed();

    expect((float) $price->fresh()->marketcap)->toBe(10.0)
        ->and((float) $price->fresh()->price_to_earnings)->toBe(20.0);
})->with([
    'wrong file' => ['marketcap', 'backtest:import-marketcap', "SYMBOL,ADJUSTED P/E\nCIPLA,25", 'Invalid marketcap.csv headers'],
    'wrong date' => ['marketcap', 'backtest:import-marketcap', "Trade Date,Symbol,Series,Market Cap(Rs.)\n01 MAR 2024,CIPLA,EQ,25000000\n29 FEB 2024,OTHER,EQ,10000000", 'does not match 2024-03-01'],
    'negative cap' => ['marketcap', 'backtest:import-marketcap', "Trade Date,Symbol,Series,Market Cap(Rs.)\n01 MAR 2024,CIPLA,EQ,-10000000", 'Invalid numeric value'],
    'invalid number' => ['price_to_earnings', 'backtest:import-price-to-earnings', "SYMBOL,ADJUSTED P/E\nCIPLA,25\nOTHER,invalid", 'Invalid numeric value'],
    'empty file' => ['price_to_earnings', 'backtest:import-price-to-earnings', "\nSYMBOL,ADJUSTED P/E\n", 'No instrument data'],
    'no match' => ['price_to_earnings', 'backtest:import-price-to-earnings', "SYMBOL,ADJUSTED P/E\nUNKNOWN,25", 'No file rows match'],
    'short row' => ['price_to_earnings', 'backtest:import-price-to-earnings', "SYMBOL,ADJUSTED P/E\nCIPLA,25\nOTHER", 'Invalid column count'],
    'overflow' => ['price_to_earnings', 'backtest:import-price-to-earnings', "SYMBOL,ADJUSTED P/E\nCIPLA,1000000", 'exceeds the column limit'],
    'conflicting duplicate' => ['price_to_earnings', 'backtest:import-price-to-earnings', "SYMBOL,ADJUSTED P/E\nCIPLA,25\nCIPLA,26", 'Conflicting values'],
]);
