<?php

use App\Models\BacktestNseCorporateAction;
use App\Models\BacktestNseIndexConstituent;
use App\Models\BacktestNseInstrument;
use App\Models\BacktestNseInstrumentPrice;
use App\Models\MarketIndexAlias;

it('updates the instrument master table when changing a symbol', function () {
    BacktestNseInstrument::create([
        'symbol' => 'OLDETF',
        'name' => 'Old ETF',
        'etf_index' => 'nifty-50',
    ]);

    createBacktestPriceRow('OLDETF', '2020-01-01');
    createCorporateAction('OLDETF', '2020-01-02');
    BacktestNseIndexConstituent::create(['symbol' => 'OLDETF', 'index' => 'nifty-50']);
    BacktestNseIndexConstituent::create(['symbol' => 'OLDETF', 'index' => 'nifty-500']);

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
        ->and(BacktestNseCorporateAction::where('symbol', 'NEWETF')->where('date', '2020-01-02')->exists())->toBeTrue()
        ->and(BacktestNseIndexConstituent::where('symbol', 'OLDETF')->exists())->toBeFalse()
        ->and(BacktestNseIndexConstituent::where('symbol', 'NEWETF')->pluck('index')->sort()->values()->all())
        ->toBe(['nifty-50', 'nifty-500']);
});

it('drops duplicate index constituent rows when the new symbol is already a constituent', function () {
    BacktestNseIndexConstituent::create(['symbol' => 'INFRATEL', 'index' => 'nifty-50']);
    BacktestNseIndexConstituent::create(['symbol' => 'INFRATEL', 'index' => 'nifty-500']);
    BacktestNseIndexConstituent::create(['symbol' => 'INDUSTOWER', 'index' => 'nifty-500']);

    $this->artisan('backtest:change-symbol', [
        '--old-symbol' => 'INFRATEL',
        '--new-symbol' => 'INDUSTOWER',
    ])->assertSuccessful();

    expect(BacktestNseIndexConstituent::where('symbol', 'INFRATEL')->exists())->toBeFalse()
        ->and(BacktestNseIndexConstituent::where('symbol', 'INDUSTOWER')->pluck('index')->sort()->values()->all())
        ->toBe(['nifty-50', 'nifty-500']);
});

it('merges the old instrument row when the new symbol already exists', function () {
    $marketIndexAlias = MarketIndexAlias::factory()->create();

    BacktestNseInstrument::create([
        'symbol' => 'OLDBEES',
        'name' => 'Old Bees',
        'etf_index' => 'gold',
        'market_index_alias_id' => $marketIndexAlias->id,
        'etf_index_source_date' => '2020-01-02',
    ]);

    BacktestNseInstrument::create(['symbol' => 'NEWBEES']);

    $this->artisan('backtest:change-symbol', [
        '--old-symbol' => 'OLDBEES',
        '--new-symbol' => 'NEWBEES',
    ])->assertSuccessful();

    expect(BacktestNseInstrument::where('symbol', 'OLDBEES')->exists())->toBeFalse()
        ->and(BacktestNseInstrument::where('symbol', 'NEWBEES')->count())->toBe(1)
        ->and(BacktestNseInstrument::where('symbol', 'NEWBEES')->value('name'))->toBe('Old Bees')
        ->and(BacktestNseInstrument::where('symbol', 'NEWBEES')->value('etf_index'))->toBe('gold')
        ->and(BacktestNseInstrument::where('symbol', 'NEWBEES')->value('market_index_alias_id'))->toBe($marketIndexAlias->id)
        ->and(BacktestNseInstrument::where('symbol', 'NEWBEES')->value('etf_index_source_date')->toDateString())->toBe('2020-01-02');
});

it('keeps the newer pending index assignment when symbols merge', function () {
    $oldAlias = MarketIndexAlias::factory()->create();
    $newAlias = MarketIndexAlias::factory()->create();

    BacktestNseInstrument::query()->create([
        'symbol' => 'OLDETF',
        'etf_index' => 'gold',
        'market_index_alias_id' => $oldAlias->id,
        'etf_index_source_date' => '2020-01-01',
    ]);
    BacktestNseInstrument::query()->create([
        'symbol' => 'NEWETF',
        'market_index_alias_id' => $newAlias->id,
        'etf_index_source_date' => '2020-02-01',
    ]);

    $this->artisan('backtest:change-symbol', [
        '--old-symbol' => 'OLDETF',
        '--new-symbol' => 'NEWETF',
    ])->assertSuccessful();

    $instrument = BacktestNseInstrument::query()->where('symbol', 'NEWETF')->sole();

    expect($instrument->etf_index)->toBeNull()
        ->and($instrument->market_index_alias_id)->toBe($newAlias->id)
        ->and($instrument->etf_index_source_date->toDateString())->toBe('2020-02-01');
});
