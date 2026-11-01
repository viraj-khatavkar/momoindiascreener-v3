<?php

use App\Actions\ResolveMarketIndexAliasAction;
use App\Enums\MarketIndexAliasStatusEnum;
use App\Models\BacktestNseInstrument;
use App\Models\BacktestNseInstrumentPrice;
use App\Models\MarketIndex;
use App\Models\MarketIndexAlias;
use Database\Seeders\MarketIndexSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Fake the disk so the command never touches the real uploads/ files.
    Storage::fake('local');
    $this->seed(MarketIndexSeeder::class);
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

it('refreshes etf indexes even when every symbol for the date already exists', function () {
    BacktestNseInstrument::create([
        'symbol' => 'NIFTYBEES',
        'etf_index' => 'stale-index',
    ]);

    createBacktestPrice('NIFTYBEES', '2019-12-20');

    putEtfCsv('2019-12-20', [['NIFTYBEES', 'NIFTY 50']]);

    $this->artisan('backtest:copy-instruments', ['--date' => '2019-12-20'])
        ->expectsOutputToContain('0 instruments copied. 1 already present.')
        ->expectsOutputToContain('1 ETF labels matched to approved indexes')
        ->assertSuccessful();

    expect(BacktestNseInstrument::count())->toBe(1)
        ->and(BacktestNseInstrument::where('symbol', 'NIFTYBEES')->value('etf_index'))->toBe('nifty-50');
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

it('resolves known index variants from the database registry', function () {
    $resolver = app(ResolveMarketIndexAliasAction::class);
    $knownMappings = [
        'NIFTY 50' => 'nifty-50',
        'NIFTY' => 'nifty-50',
        'NIFTY50' => 'nifty-50',
        '  nifty   50  ' => 'nifty-50',
        'NIFTY50 EQUAL WEIGHT INDEX' => 'nifty-50-equal-weight',
        'NIFTY BANK INDEX' => 'nifty-bank',
        'NIFTY FINANCIAL SERVICES TOTAL RETURN INDEX' => 'nifty-financial-services',
        'NIFTY NEXT 50 ETF' => 'nifty-next-50',
        'S&P BSE 500 INDEX' => 'bse-500',
        'SENSEX' => 'sensex',
        'BSE SENSEX INDEX' => 'sensex',
        'S&P 500 TOP 50 TOTAL RETURN INDEX' => 'sp-500-top-50',
        'NIFTY BHARAT BOND' => 'nifty-bharat-bond',
        'NIFTY ALPHA LOW-VOLATILITY 30 INDEX' => 'nifty-alpha-low-volatility-30',
        'NIFTY IT INDEX' => 'nifty-it',
        'NIFTY IT TRI' => 'nifty-it',
        'NIFTYIT' => 'nifty-it',
        'NIFTY AAA BOND PLUS SDL APR 2026 50:50 INDEX' => 'nifty-aaa-bond-plus-sdl-apr-2026',
        'NIFTY CONSUMPTION INDEX' => 'nifty-india-consumption',
        'NIFTY INDIA CONSUMPTION INDEX' => 'nifty-india-consumption',
        'NIFTY FMCG INDEX' => 'nifty-fmcg',
        'NIFTY HEALTHCARE INDEX' => 'nifty-healthcare',
        'NIFTY HEALTHCARE TRI' => 'nifty-healthcare',
        'NIFTY PHARMA INDEX' => 'nifty-pharma',
    ];

    foreach ($knownMappings as $sourceLabel => $expectedSlug) {
        $marketIndexAlias = $resolver->execute($sourceLabel, 'TESTETF', '2019-12-20');

        expect($marketIndexAlias->status)->toBe(MarketIndexAliasStatusEnum::Approved)
            ->and($marketIndexAlias->marketIndex?->slug)->toBe($expectedSlug);
    }
});

it('queues an unknown index for review and does not apply an unverified slug', function () {
    createBacktestPrice('NEWETF', '2019-12-20');

    putEtfCsv('2019-12-20', [['NEWETF', 'NIFTY BRAND NEW INDEX']]);

    $this->artisan('backtest:copy-instruments', ['--date' => '2019-12-20'])
        ->expectsOutputToContain('1 ETF index label(s) need review')
        ->expectsOutputToContain('"NIFTY BRAND NEW INDEX" (NEWETF) => nifty-brand-new')
        ->expectsOutputToContain('Admin > ETF Index Mappings')
        ->assertSuccessful();

    $marketIndexAlias = MarketIndexAlias::query()->where('normalized_label', 'NIFTY BRAND NEW INDEX')->sole();
    $instrument = BacktestNseInstrument::query()->where('symbol', 'NEWETF')->sole();

    expect($marketIndexAlias->status)->toBe(MarketIndexAliasStatusEnum::Pending)
        ->and($marketIndexAlias->suggested_slug)->toBe('nifty-brand-new')
        ->and($marketIndexAlias->sample_symbol)->toBe('NEWETF')
        ->and($instrument->etf_index)->toBeNull()
        ->and($instrument->market_index_alias_id)->toBe($marketIndexAlias->id);
});

it('imports ETF labels from Windows-1252 and UTF-8 CSV files', function (string $sourceLabel) {
    createBacktestPrice('GROWWNIFTY', '2025-07-22');
    putEtfCsv('2025-07-22', [['GROWWNIFTY', $sourceLabel]]);

    $this->artisan('backtest:copy-instruments', ['--date' => '2025-07-22'])
        ->doesntExpectOutputToContain('need review')
        ->assertSuccessful();

    $marketIndexAlias = MarketIndexAlias::query()
        ->where('normalized_label', "NIFTY 50 INDEX \u{2013} TRI")
        ->sole();
    $instrument = BacktestNseInstrument::query()->where('symbol', 'GROWWNIFTY')->sole();

    expect($marketIndexAlias->source_label)->toBe("NIFTY 50 INDEX \u{2013} TRI")
        ->and($marketIndexAlias->status)->toBe(MarketIndexAliasStatusEnum::Approved)
        ->and($marketIndexAlias->sample_symbol)->toBe('GROWWNIFTY')
        ->and($instrument->etf_index)->toBe('nifty-50')
        ->and($instrument->market_index_alias_id)->toBe($marketIndexAlias->id);
})->with([
    'Windows-1252' => ["NIFTY 50 INDEX \x96 TRI"],
    'UTF-8' => ["NIFTY 50 INDEX \u{2013} TRI"],
]);

it('approves an existing index after it removes a return type suffix', function () {
    $marketIndex = MarketIndex::query()->create([
        'name' => 'Nifty Brand New',
        'slug' => 'nifty-brand-new',
        'provider' => 'NSE',
    ]);
    createBacktestPrice('NEWETF', '2019-12-20');
    putEtfCsv('2019-12-20', [['NEWETF', 'NIFTY BRAND NEW INDEX']]);

    $this->artisan('backtest:copy-instruments', ['--date' => '2019-12-20'])->assertSuccessful();

    $marketIndexAlias = MarketIndexAlias::query()->where('normalized_label', 'NIFTY BRAND NEW INDEX')->sole();

    expect($marketIndexAlias->status)->toBe(MarketIndexAliasStatusEnum::Approved)
        ->and($marketIndexAlias->suggested_market_index_id)->toBe($marketIndex->id)
        ->and($marketIndexAlias->suggested_slug)->toBe('nifty-brand-new');
});

it('records the first and last date for one pending alias', function () {
    createBacktestPrice('NEWETF', '2019-12-20');
    createBacktestPrice('NEWETF', '2019-12-21');
    putEtfCsv('2019-12-20', [['NEWETF', 'NIFTY BRAND NEW INDEX']]);
    putEtfCsv('2019-12-21', [['NEWETF', 'NIFTY BRAND NEW INDEX']]);

    $this->artisan('backtest:copy-instruments', ['--date' => '2019-12-20'])->assertSuccessful();
    $this->artisan('backtest:copy-instruments', ['--date' => '2019-12-21'])->assertSuccessful();

    $marketIndexAlias = MarketIndexAlias::query()->where('normalized_label', 'NIFTY BRAND NEW INDEX')->sole();

    expect($marketIndexAlias->first_seen_on->toDateString())->toBe('2019-12-20')
        ->and($marketIndexAlias->last_seen_on->toDateString())->toBe('2019-12-21')
        ->and(MarketIndexAlias::query()->where('normalized_label', 'NIFTY BRAND NEW INDEX')->count())->toBe(1);
});

it('automatically approves a new label when its slug exactly matches an index', function () {
    MarketIndex::query()->create([
        'name' => 'Nifty Brand New Index',
        'slug' => 'nifty-brand-new',
        'provider' => 'NSE',
    ]);
    createBacktestPrice('NEWETF', '2019-12-20');
    putEtfCsv('2019-12-20', [['NEWETF', 'NIFTY BRAND NEW INDEX']]);

    $this->artisan('backtest:copy-instruments', ['--date' => '2019-12-20'])
        ->doesntExpectOutputToContain('need review')
        ->assertSuccessful();

    expect(BacktestNseInstrument::query()->where('symbol', 'NEWETF')->value('etf_index'))
        ->toBe('nifty-brand-new')
        ->and(MarketIndexAlias::query()->where('normalized_label', 'NIFTY BRAND NEW INDEX')->value('status'))
        ->toBe(MarketIndexAliasStatusEnum::Approved);
});

it('does not let an older file replace a newer index assignment', function () {
    createBacktestPrice('TESTETF', '2019-12-20');
    createBacktestPrice('TESTETF', '2019-12-21');
    putEtfCsv('2019-12-20', [['TESTETF', 'GOLD']]);
    putEtfCsv('2019-12-21', [['TESTETF', 'NIFTY IT']]);

    $this->artisan('backtest:copy-instruments', ['--date' => '2019-12-21'])->assertSuccessful();
    $this->artisan('backtest:copy-instruments', ['--date' => '2019-12-20'])->assertSuccessful();

    $instrument = BacktestNseInstrument::query()->where('symbol', 'TESTETF')->sole();

    expect($instrument->etf_index)->toBe('nifty-it')
        ->and($instrument->etf_index_source_date->toDateString())->toBe('2019-12-21');
});

it('maps new fund and return-type labels to one stored underlying', function (string $label, string $slug) {
    createBacktestPrice('NEWETF', '2025-10-13');
    putEtfCsv('2025-10-13', [['NEWETF', $label]]);
    $count = MarketIndex::query()->count();

    $this->artisan('backtest:copy-instruments', ['--date' => '2025-10-13'])
        ->doesntExpectOutputToContain('need review')->assertSuccessful();

    expect(BacktestNseInstrument::query()->where('symbol', 'NEWETF')->value('etf_index'))->toBe($slug)
        ->and(MarketIndex::query()->count())->toBe($count);
})->with([
    ['ZERODHA GOLD ETF', 'gold'],
    ['PHYSICAL PRICE OF SILVER', 'silver'],
    ['HDFC NIFTY MIDCAP 150 ETF', 'nifty-midcap-150'],
    ['NIFTY INDIA MANUFACTURING TRI', 'nifty-india-manufacturing'],
    ['NIFTY INDIA MANUFACTURING TOTAL RETURN INDEX', 'nifty-india-manufacturing'],
]);

it('uses an approved alias when a different source spelling has the same canonical key', function () {
    $index = MarketIndex::factory()->create(['slug' => 'custom-underlying']);
    MarketIndexAlias::factory()->create([
        'source_label' => 'NIFTY NEW THEME INDEX', 'normalized_label' => 'NIFTY NEW THEME INDEX',
        'market_index_id' => $index->id, 'status' => MarketIndexAliasStatusEnum::Approved,
    ]);

    $resolved = app(ResolveMarketIndexAliasAction::class)->execute('NIFTY NEW THEME TRI', 'NEWETF', '2025-10-13');

    expect($resolved->status)->toBe(MarketIndexAliasStatusEnum::Approved)
        ->and($resolved->market_index_id)->toBe($index->id);
});

it('does not apply a generic label mapping to another ETF', function () {
    $gold = MarketIndex::query()->where('slug', 'gold')->sole();
    MarketIndexAlias::factory()->create([
        'source_label' => 'COMMODITY', 'normalized_label' => 'COMMODITY', 'symbol' => 'GROWWGOLD',
        'market_index_id' => $gold->id, 'status' => MarketIndexAliasStatusEnum::Approved,
    ]);
    createBacktestPrice('GROWWGOLD', '2025-10-13');
    createBacktestPrice('NEWSILVER', '2025-10-13');
    putEtfCsv('2025-10-13', [['GROWWGOLD', 'COMMODITY'], ['NEWSILVER', 'COMMODITY']]);

    $this->artisan('backtest:copy-instruments', ['--date' => '2025-10-13'])->assertSuccessful();

    expect(BacktestNseInstrument::query()->where('symbol', 'GROWWGOLD')->value('etf_index'))->toBe('gold')
        ->and(BacktestNseInstrument::query()->where('symbol', 'NEWSILVER')->value('etf_index'))->toBeNull()
        ->and(MarketIndexAlias::query()->where('normalized_label', 'COMMODITY')->count())->toBe(2);
});

it('retains a known underlying when a new label is unknown or conflicts', function (string $label) {
    BacktestNseInstrument::query()->create(['symbol' => 'KNOWNETF', 'etf_index' => 'gold', 'etf_index_source_date' => '2025-10-10']);
    createBacktestPrice('KNOWNETF', '2025-10-13');
    putEtfCsv('2025-10-13', [['KNOWNETF', $label]]);

    $this->artisan('backtest:copy-instruments', ['--date' => '2025-10-13'])
        ->expectsOutputToContain('1 ETF index label(s) need review')->assertSuccessful();
    $this->artisan('backtest:copy-instruments', ['--date' => '2025-10-13'])->assertSuccessful();

    $instrument = BacktestNseInstrument::query()->where('symbol', 'KNOWNETF')->sole();
    expect($instrument->etf_index)->toBe('gold')
        ->and($instrument->marketIndexAlias->status)->toBe(MarketIndexAliasStatusEnum::Pending);
})->with(['COMMODITY', 'NEW UNKNOWN BENCHMARK', 'NIFTY MIDCAP 100 TRI']);

it('keeps an ignored equivalent label ignored', function () {
    $alias = MarketIndexAlias::factory()->create([
        'source_label' => 'NIFTY MIDCAP 150 TRI', 'normalized_label' => 'NIFTY MIDCAP 150 TRI',
        'status' => MarketIndexAliasStatusEnum::Ignored,
    ]);

    expect(app(ResolveMarketIndexAliasAction::class)->execute($alias->source_label, 'TESTETF', '2025-10-13')->status)
        ->toBe(MarketIndexAliasStatusEnum::Ignored);
});

it('does not guess when approved mappings for one normalized label disagree', function () {
    foreach (['NIFTY TEST TRI', 'NIFTY TEST TOTAL RETURN INDEX'] as $label) {
        MarketIndexAlias::factory()->create([
            'source_label' => $label, 'normalized_label' => $label,
            'market_index_id' => MarketIndex::factory()->create()->id, 'status' => MarketIndexAliasStatusEnum::Approved,
        ]);
    }

    expect(app(ResolveMarketIndexAliasAction::class)->execute('NIFTY TEST INDEX', 'TESTETF', '2025-10-13')->status)
        ->toBe(MarketIndexAliasStatusEnum::Pending);
});

it('reports matched labels even when a repeat import does not change a record', function () {
    createBacktestPrice('NIFTYBEES', '2025-10-13');
    putEtfCsv('2025-10-13', [['NIFTYBEES', 'NIFTY 50']]);

    $this->artisan('backtest:copy-instruments', ['--date' => '2025-10-13'])->assertSuccessful();
    $this->artisan('backtest:copy-instruments', ['--date' => '2025-10-13'])
        ->expectsOutputToContain('1 ETF labels matched to approved indexes from 1 ETFs')
        ->assertSuccessful();
});
