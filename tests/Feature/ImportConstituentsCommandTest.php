<?php

use App\Models\BacktestNseIndexConstituent;

it('fails when no date is provided', function () {
    $this->artisan('backtest:import-constituents')
        ->expectsOutputToContain('Please provide a date')
        ->assertFailed();
});

it('sets index flags from the constituents table, including the new indices', function () {
    $date = '2019-12-20';

    BacktestNseIndexConstituent::insert([
        ['symbol' => 'SMALLA', 'index' => 'nifty_smallcap_250'],
        ['symbol' => 'MIDA', 'index' => 'nifty_midcap_150'],
        ['symbol' => 'BIG', 'index' => 'nifty_50'],
    ]);

    createBacktestPriceRow('SMALLA', $date, ['series' => 'EQ']);
    createBacktestPriceRow('MIDA', $date, ['series' => 'EQ']);
    createBacktestPriceRow('BIG', $date, ['series' => 'EQ']);
    createBacktestPriceRow('OTHER', $date, ['series' => 'EQ']); // not in any index

    $this->artisan('backtest:import-constituents', ['--date' => $date])->assertSuccessful();

    expect(isIndexFlagged('SMALLA', $date, 'is_nifty_smallcap_250'))->toBeTrue()
        ->and(isIndexFlagged('MIDA', $date, 'is_nifty_midcap_150'))->toBeTrue()
        ->and(isIndexFlagged('BIG', $date, 'is_nifty_50'))->toBeTrue()
        ->and(isIndexFlagged('SMALLA', $date, 'is_nifty_midcap_150'))->toBeFalse()
        ->and(isIndexFlagged('OTHER', $date, 'is_nifty_smallcap_250'))->toBeFalse();
});

it('resets a stale flag for a symbol that is no longer a constituent', function () {
    $date = '2019-12-20';

    createBacktestPriceRow('DROPPED', $date, ['series' => 'EQ', 'is_nifty_smallcap_250' => true]);
    // constituents table has no smallcap rows -> DROPPED should be reset to false

    $this->artisan('backtest:import-constituents', ['--date' => $date])->assertSuccessful();

    expect(isIndexFlagged('DROPPED', $date, 'is_nifty_smallcap_250'))->toBeFalse();
});
