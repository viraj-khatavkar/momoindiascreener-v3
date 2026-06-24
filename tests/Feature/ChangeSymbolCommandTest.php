<?php

use App\Models\BacktestNseCorporateAction;
use App\Models\BacktestNseInstrument;
use App\Models\BacktestNseInstrumentPrice;

it('updates the instrument master table when changing a symbol', function () {
    BacktestNseInstrument::create([
        'symbol' => 'OLDETF',
        'name' => 'Old ETF',
        'etf_index' => 'nifty-50',
    ]);

    createBacktestPriceRow('OLDETF', '2020-01-01');
    createCorporateAction('OLDETF', '2020-01-02');

    $this->artisan('backtest:change-symbol', [
        '--old-symbol' => 'OLDETF',
        '--new-symbol' => 'NEWETF',
    ])->assertSuccessful();

    expect(BacktestNseInstrument::where('symbol', 'OLDETF')->exists())->toBeFalse()
        ->and(BacktestNseInstrument::where('symbol', 'NEWETF')->value('name'))->toBe('Old ETF')
        ->and(BacktestNseInstrument::where('symbol', 'NEWETF')->value('etf_index'))->toBe('nifty-50')
        ->and(BacktestNseInstrumentPrice::where('symbol', 'OLDETF')->exists())->toBeFalse()
        ->and(BacktestNseInstrumentPrice::where('symbol', 'NEWETF')->where('date', '2020-01-01')->exists())->toBeTrue()
        ->and(BacktestNseCorporateAction::where('symbol', 'OLDETF')->exists())->toBeFalse()
        ->and(BacktestNseCorporateAction::where('symbol', 'NEWETF')->where('date', '2020-01-02')->exists())->toBeTrue();
});

it('merges the old instrument row when the new symbol already exists', function () {
    BacktestNseInstrument::create([
        'symbol' => 'OLDBEES',
        'name' => 'Old Bees',
        'etf_index' => 'gold',
    ]);

    BacktestNseInstrument::create(['symbol' => 'NEWBEES']);

    $this->artisan('backtest:change-symbol', [
        '--old-symbol' => 'OLDBEES',
        '--new-symbol' => 'NEWBEES',
    ])->assertSuccessful();

    expect(BacktestNseInstrument::where('symbol', 'OLDBEES')->exists())->toBeFalse()
        ->and(BacktestNseInstrument::where('symbol', 'NEWBEES')->count())->toBe(1)
        ->and(BacktestNseInstrument::where('symbol', 'NEWBEES')->value('name'))->toBe('Old Bees')
        ->and(BacktestNseInstrument::where('symbol', 'NEWBEES')->value('etf_index'))->toBe('gold');
});
