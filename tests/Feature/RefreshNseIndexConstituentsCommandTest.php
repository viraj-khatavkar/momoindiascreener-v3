<?php

use App\Models\BacktestNseIndexConstituent;

it('fails when no date is provided', function () {
    $this->artisan('backtest:refresh-nse-index-constituents')
        ->expectsOutputToContain('Please provide a date')
        ->assertFailed();
});

it('rebuilds the constituents table from the date flags, including the new indices', function () {
    $date = '2019-12-20';

    createBacktestPriceRow('SMALLA', $date, ['is_nifty_smallcap_250' => true]);
    createBacktestPriceRow('SMALLB', $date, ['is_nifty_smallcap_250' => true]);
    createBacktestPriceRow('MIDA', $date, ['is_nifty_midcap_150' => true]);
    createBacktestPriceRow('TWOHUNDRED', $date, ['is_nifty_200' => true]);
    createBacktestPriceRow('BIG', $date, ['is_nifty_50' => true]);
    createBacktestPriceRow('NOINDEX', $date); // flagged for nothing -> excluded

    $this->artisan('backtest:refresh-nse-index-constituents', ['--date' => $date])->assertSuccessful();

    expect(BacktestNseIndexConstituent::where('index', 'nifty_smallcap_250')->orderBy('symbol')->pluck('symbol')->all())
        ->toBe(['SMALLA', 'SMALLB'])
        ->and(BacktestNseIndexConstituent::where('index', 'nifty_midcap_150')->pluck('symbol')->all())->toBe(['MIDA'])
        ->and(BacktestNseIndexConstituent::where('index', 'nifty_200')->pluck('symbol')->all())->toBe(['TWOHUNDRED'])
        ->and(BacktestNseIndexConstituent::where('index', 'nifty_50')->pluck('symbol')->all())->toBe(['BIG'])
        ->and(BacktestNseIndexConstituent::where('symbol', 'NOINDEX')->count())->toBe(0);
});

it('truncates stale constituents before rebuilding', function () {
    BacktestNseIndexConstituent::insert([['symbol' => 'STALE', 'index' => 'nifty_smallcap_250']]);
    createBacktestPriceRow('FRESH', '2019-12-20', ['is_nifty_smallcap_250' => true]);

    $this->artisan('backtest:refresh-nse-index-constituents', ['--date' => '2019-12-20'])->assertSuccessful();

    expect(BacktestNseIndexConstituent::where('index', 'nifty_smallcap_250')->pluck('symbol')->all())->toBe(['FRESH']);
});
