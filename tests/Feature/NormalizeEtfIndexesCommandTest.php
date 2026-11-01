<?php

use App\Enums\MarketIndexAliasStatusEnum;
use App\Models\BacktestNseInstrument;
use App\Models\MarketIndex;
use App\Models\MarketIndexAlias;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('merges equivalent indexes and repairs aliases and instruments with a backup', function () {
    $canonical = MarketIndex::factory()->create(['name' => 'Nifty India Manufacturing', 'slug' => 'nifty-india-manufacturing']);
    $duplicate = MarketIndex::factory()->create(['name' => 'Nifty India Manufacturing Total Return Index', 'slug' => 'nifty-india-manufacturing-total-return-index']);
    $approved = MarketIndexAlias::factory()->create([
        'source_label' => 'NIFTY INDIA MANUFACTURING TOTAL RETURN INDEX',
        'normalized_label' => 'NIFTY INDIA MANUFACTURING TOTAL RETURN INDEX',
        'market_index_id' => $duplicate->id, 'suggested_market_index_id' => $duplicate->id,
        'status' => MarketIndexAliasStatusEnum::Approved,
    ]);
    $pending = MarketIndexAlias::factory()->create([
        'source_label' => 'NIFTY INDIA MANUFACTURING TRI', 'normalized_label' => 'NIFTY INDIA MANUFACTURING TRI',
        'suggested_market_index_id' => $duplicate->id,
    ]);
    $ignored = MarketIndexAlias::factory()->create([
        'source_label' => 'NIFTY INDIA MANUFACTURING INDEX TRI', 'normalized_label' => 'NIFTY INDIA MANUFACTURING INDEX TRI',
        'status' => MarketIndexAliasStatusEnum::Ignored,
    ]);
    BacktestNseInstrument::query()->create(['symbol' => 'FIRST', 'etf_index' => $duplicate->slug, 'market_index_alias_id' => $approved->id, 'etf_index_source_date' => '2025-10-13']);
    BacktestNseInstrument::query()->create(['symbol' => 'SECOND', 'market_index_alias_id' => $pending->id, 'etf_index_source_date' => '2025-10-13']);
    BacktestNseInstrument::query()->create(['symbol' => 'LEGACY', 'etf_index' => $duplicate->slug]);

    $this->artisan('backtest:normalize-etf-indexes', ['--apply' => true])
        ->expectsOutputToContain('1 duplicate index records merged')->assertSuccessful();

    expect(MarketIndex::query()->count())->toBe(1)
        ->and($approved->fresh()->market_index_id)->toBe($canonical->id)
        ->and($pending->fresh()->status)->toBe(MarketIndexAliasStatusEnum::Approved)
        ->and($pending->fresh()->suggested_market_index_id)->toBe($canonical->id)
        ->and($ignored->fresh()->status)->toBe(MarketIndexAliasStatusEnum::Ignored)
        ->and(BacktestNseInstrument::query()->pluck('etf_index')->unique()->all())->toBe(['nifty-india-manufacturing'])
        ->and(BacktestNseInstrument::query()->where('symbol', 'FIRST')->value('etf_index_source_date')->toDateString())->toBe('2025-10-13');

    $backups = Storage::disk('local')->files('backups');
    expect($backups)->toHaveCount(1);
    $backup = json_decode(Storage::disk('local')->get($backups[0]), true);
    expect($backup['market_indices'])->toHaveCount(2)
        ->and($backup['backtest_nse_instruments'][0]['etf_index'])->toBe($duplicate->slug);

    $this->artisan('backtest:normalize-etf-indexes', ['--apply' => true])
        ->expectsOutputToContain('0 duplicate index records merged')->assertSuccessful();
    expect(MarketIndex::query()->count())->toBe(1);
});

it('previews repairs without saving any database or backup changes', function () {
    $index = MarketIndex::factory()->create(['name' => 'Nifty India Manufacturing Total Return Index', 'slug' => 'nifty-india-manufacturing-total-return-index']);
    $before = $index->fresh()->getAttributes();

    $this->artisan('backtest:normalize-etf-indexes')
        ->expectsOutputToContain('Preview only. No changes saved.')->assertSuccessful();

    expect($index->fresh()->getAttributes())->toBe($before)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('gives each ETF its own mapping for a generic legacy label', function () {
    $index = MarketIndex::factory()->create(['name' => 'Gold', 'slug' => 'gold']);
    $alias = MarketIndexAlias::factory()->create([
        'source_label' => 'COMMODITY', 'normalized_label' => 'COMMODITY', 'sample_symbol' => 'FIRST',
        'market_index_id' => $index->id, 'status' => MarketIndexAliasStatusEnum::Approved,
    ]);
    foreach (['FIRST', 'SECOND'] as $symbol) {
        BacktestNseInstrument::query()->create(['symbol' => $symbol, 'market_index_alias_id' => $alias->id]);
    }

    $this->artisan('backtest:normalize-etf-indexes', ['--apply' => true])->assertSuccessful();
    $this->artisan('backtest:normalize-etf-indexes', ['--apply' => true])->assertSuccessful();

    expect(MarketIndexAlias::query()->where('normalized_label', 'COMMODITY')->orderBy('symbol')->pluck('symbol')->all())->toBe(['FIRST', 'SECOND'])
        ->and(BacktestNseInstrument::query()->where('symbol', 'FIRST')->sole()->marketIndexAlias->symbol)->toBe('FIRST')
        ->and(BacktestNseInstrument::query()->where('symbol', 'SECOND')->sole()->marketIndexAlias->symbol)->toBe('SECOND');
});

it('repairs the source error for MOLOWVOL without changing the shared Midcap 100 mapping', function () {
    $lowVol = MarketIndex::factory()->create(['name' => 'BSE Low Volatility', 'slug' => 'bse-low-volatility']);
    $midcap = MarketIndex::factory()->create(['name' => 'Nifty Midcap 100', 'slug' => 'nifty-midcap-100']);
    MarketIndexAlias::factory()->create([
        'source_label' => 'S&P BSE LOW VOLATILITY TOTAL RETURN INDEX', 'normalized_label' => 'S&P BSE LOW VOLATILITY TOTAL RETURN INDEX',
        'market_index_id' => $lowVol->id, 'status' => MarketIndexAliasStatusEnum::Approved,
    ]);
    $alias = MarketIndexAlias::factory()->create([
        'source_label' => 'NIFTY MIDCAP 100 TRI', 'normalized_label' => 'NIFTY MIDCAP 100 TRI', 'sample_symbol' => 'MOLOWVOL',
    ]);
    foreach (['MOLOWVOL', 'MIDCAPETF'] as $symbol) {
        BacktestNseInstrument::query()->create(['symbol' => $symbol, 'market_index_alias_id' => $alias->id]);
    }

    $this->artisan('backtest:normalize-etf-indexes', ['--apply' => true])->assertSuccessful();

    expect(BacktestNseInstrument::query()->where('symbol', 'MOLOWVOL')->value('etf_index'))->toBe($lowVol->slug)
        ->and(BacktestNseInstrument::query()->where('symbol', 'MIDCAPETF')->value('etf_index'))->toBe($midcap->slug)
        ->and(MarketIndexAlias::query()->where('normalized_label', 'NIFTY MIDCAP 100 TRI')->count())->toBe(2);
});

it('preserves an existing scoped review when converting a legacy generic label', function () {
    $legacy = MarketIndexAlias::factory()->create(['source_label' => 'INDEX', 'normalized_label' => 'INDEX', 'sample_symbol' => 'ETF']);
    $scoped = MarketIndexAlias::factory()->create(['source_label' => 'INDEX', 'normalized_label' => 'INDEX', 'symbol' => 'ETF', 'status' => MarketIndexAliasStatusEnum::Ignored]);
    BacktestNseInstrument::query()->create(['symbol' => 'ETF', 'market_index_alias_id' => $legacy->id]);

    $this->artisan('backtest:normalize-etf-indexes', ['--apply' => true])->assertSuccessful();

    expect($scoped->fresh()->status)->toBe(MarketIndexAliasStatusEnum::Ignored)
        ->and(BacktestNseInstrument::query()->where('symbol', 'ETF')->value('market_index_alias_id'))->toBe($scoped->id);
});

it('separates distinct indexes that had an incorrect legacy mapping', function (string $label, string $oldName, string $oldSlug, string $slug) {
    $oldIndex = MarketIndex::factory()->create(['name' => $oldName, 'slug' => $oldSlug]);
    $alias = MarketIndexAlias::factory()->create([
        'source_label' => $label, 'normalized_label' => $label,
        'market_index_id' => $oldIndex->id, 'status' => MarketIndexAliasStatusEnum::Approved,
    ]);
    BacktestNseInstrument::query()->create(['symbol' => 'ETF', 'etf_index' => $oldSlug, 'market_index_alias_id' => $alias->id]);

    $this->artisan('backtest:normalize-etf-indexes', ['--apply' => true])->assertSuccessful();

    expect($oldIndex->fresh()->slug)->toBe($oldSlug)
        ->and(MarketIndex::query()->count())->toBe(2)
        ->and($alias->fresh()->marketIndex->slug)->toBe($slug)
        ->and(BacktestNseInstrument::query()->where('symbol', 'ETF')->value('etf_index'))->toBe($slug);
})->with([
    ['HANG SENG TECH TOTAL RETURN INDEX', 'Hang Seng', 'hang-seng', 'hang-seng-tech'],
    ['CRISIL 10 YEAR GILT INDEX', 'G-Sec 10', 'gsec-10', 'crisil-10-yr-gilt'],
]);
