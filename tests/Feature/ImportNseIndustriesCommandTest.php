<?php

use App\Models\BacktestNseInstrument;
use App\Models\Industry;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Http::preventStrayRequests();
});

it('imports current industries by symbol without changing other instrument or price fields', function () {
    $member = BacktestNseInstrument::create(['symbol' => 'MEMBER', 'name' => 'Keep name', 'etf_index' => 'keep-index']);
    $other = BacktestNseInstrument::create(['symbol' => 'OTHER', 'industry_id' => Industry::factory()->create(['name' => 'Healthcare'])->id]);
    $price = createBacktestPriceRow('MEMBER', '2026-11-16', ['is_nifty_1000' => true, 'close_adjusted' => 123]);
    $originalPrice = $price->fresh()->getAttributes();
    Storage::put('industries.csv', "\xEF\xBB\xBFIndustry,Company Name,Symbol\nFinancial Services,Source name,MEMBER\n");
    $arguments = ['file' => Storage::path('industries.csv')];

    $this->artisan('backtest:import-industries', [...$arguments, '--dry-run' => true])
        ->expectsOutputToContain('1 would be updated')->assertSuccessful();
    expect($member->fresh()->industry_id)->toBeNull()->and(Industry::count())->toBe(1);

    $this->artisan('backtest:import-industries', $arguments)->expectsOutputToContain('1 updated; 0 unchanged')->assertSuccessful();
    $this->artisan('backtest:import-industries', $arguments)->expectsOutputToContain('0 updated; 1 unchanged')->assertSuccessful();
    expect($member->fresh()->industry?->name)->toBe('Financial Services')
        ->and($member->fresh()->name)->toBe('Keep name')
        ->and($member->fresh()->etf_index)->toBe('keep-index')
        ->and($other->fresh()->industry?->name)->toBe('Healthcare')
        ->and($price->fresh()->getAttributes())->toBe($originalPrice)
        ->and(BacktestNseInstrument::count())->toBe(2)
        ->and(Industry::count())->toBe(2);
    Http::assertNothingSent();
});

it('reports unknown symbols without creating instruments', function () {
    $member = BacktestNseInstrument::create(['symbol' => 'MEMBER']);
    Storage::put('industries.csv', "Symbol,Industry\nMEMBER,Healthcare\nUNKNOWN,Services\n");
    $this->artisan('backtest:import-industries', ['file' => Storage::path('industries.csv')])
        ->expectsOutputToContain('1 symbols not found in the instruments table: UNKNOWN')->assertSuccessful();
    expect($member->fresh()->industry?->name)->toBe('Healthcare')->and(BacktestNseInstrument::count())->toBe(1);
});

it('validates the full CSV before updating industries', function (string $contents) {
    $member = BacktestNseInstrument::create(['symbol' => 'MEMBER', 'industry_id' => Industry::factory()->create(['name' => 'Keep industry'])->id]);
    Storage::put('industries.csv', $contents);
    $this->artisan('backtest:import-industries', ['file' => Storage::path('industries.csv')])->assertFailed();
    expect($member->fresh()->industry?->name)->toBe('Keep industry');
})->with([
    'missing industry header' => "Symbol,Company Name\nMEMBER,Company\n",
    'duplicate header' => "Symbol,Industry,Industry\nMEMBER,Services,Healthcare\n",
    'empty file' => '',
    'header only' => "Symbol,Industry\n",
    'blank industry after a valid row' => "Symbol,Industry\nMEMBER,Services\nOTHER,\n",
    'conflicting duplicate' => "Symbol,Industry\nMEMBER,Services\nMEMBER,Healthcare\n",
]);

it('fails when the CSV is missing or has no matching instruments', function () {
    $this->artisan('backtest:import-industries', ['file' => Storage::path('missing.csv')])->assertFailed();
    Storage::put('industries.csv', "Symbol,Industry\nUNKNOWN,Services\n");
    $this->artisan('backtest:import-industries', ['file' => Storage::path('industries.csv')])->assertFailed();
    expect(BacktestNseInstrument::count())->toBe(0);
});

it('reads quoted CSV fields and Windows-1252 industry names', function () {
    $member = BacktestNseInstrument::create(['symbol' => 'MEMBER']);
    Storage::put('industries.csv', "Symbol,Industry\nMEMBER,\"Oil, Gas \x96 Services\"\n");
    $this->artisan('backtest:import-industries', ['file' => Storage::path('industries.csv')])->assertSuccessful();
    expect($member->fresh()->industry?->name)->toBe('Oil, Gas – Services');
});

it('reuses industry ids and keeps stored slugs when importing again', function () {
    $industry = Industry::factory()->create(['name' => 'Financial Services', 'slug' => 'finance']);
    BacktestNseInstrument::create(['symbol' => 'ONE']);
    BacktestNseInstrument::create(['symbol' => 'TWO']);
    Storage::put('industries.csv', "Symbol,Industry\nONE,Financial Services\nTWO,Financial Services\n");

    $this->artisan('backtest:import-industries', ['file' => Storage::path('industries.csv')])->assertSuccessful();
    $this->artisan('backtest:import-industries', ['file' => Storage::path('industries.csv')])->assertSuccessful();

    expect(BacktestNseInstrument::where('industry_id', $industry->id)->count())->toBe(2)
        ->and(Industry::count())->toBe(1)->and($industry->fresh()->slug)->toBe('finance');
});

it('assigns separate slugs when different names produce the same slug', function () {
    BacktestNseInstrument::create(['symbol' => 'ONE']);
    BacktestNseInstrument::create(['symbol' => 'TWO']);
    Storage::put('industries.csv', "Symbol,Industry\nONE,Consumer Services\nTWO,Consumer-Services\n");

    $this->artisan('backtest:import-industries', ['file' => Storage::path('industries.csv')])->assertSuccessful();

    expect(Industry::orderBy('id')->pluck('slug')->all())->toBe(['consumer-services', 'consumer-services-2'])
        ->and(BacktestNseInstrument::where('symbol', 'ONE')->value('industry_id'))
        ->not->toBe(BacktestNseInstrument::where('symbol', 'TWO')->value('industry_id'));
});

it('preserves industries when the daily process copies instruments', function () {
    $member = BacktestNseInstrument::create(['symbol' => 'MEMBER', 'industry_id' => Industry::factory()->create(['name' => 'Financial Services'])->id]);
    createBacktestPriceRow('MEMBER', '2026-11-17');
    createBacktestPriceRow('NEW', '2026-11-17');
    $this->artisan('backtest:copy-instruments', ['--date' => '2026-11-17'])->assertSuccessful();
    expect($member->fresh()->industry?->name)->toBe('Financial Services')
        ->and(BacktestNseInstrument::where('symbol', 'NEW')->sole()->industry?->name)->toBeNull();
});

it('preserves industries during a symbol rename or merge', function (bool $targetExists, ?string $targetIndustry, string $expected) {
    BacktestNseInstrument::create(['symbol' => 'OLD', 'industry_id' => Industry::factory()->create(['name' => 'Financial Services'])->id]);
    if ($targetExists) {
        BacktestNseInstrument::create(['symbol' => 'NEW', 'industry_id' => $targetIndustry === null ? null : Industry::factory()->create(['name' => $targetIndustry])->id]);
    }
    $this->artisan('backtest:change-symbol', ['--old-symbol' => 'OLD', '--new-symbol' => 'NEW'])->assertSuccessful();
    expect(BacktestNseInstrument::where('symbol', 'OLD')->exists())->toBeFalse()
        ->and(BacktestNseInstrument::where('symbol', 'NEW')->sole()->industry?->name)->toBe($expected);
})->with([
    'rename' => [false, null, 'Financial Services'],
    'merge into empty value' => [true, null, 'Financial Services'],
    'retain target value' => [true, 'Healthcare', 'Healthcare'],
]);
