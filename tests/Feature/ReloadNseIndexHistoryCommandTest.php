<?php

use App\Console\Commands\ReloadNseIndexHistoryCommand;
use App\Models\NseIndex;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    DB::table('nse_indices')->insert(historyImportRow(['date' => '2024-12-31', 'close' => '90.00']));
});

/** @return array<string, string|null> */
function historyImportRow(array $attributes = []): array
{
    return array_merge([
        'symbol' => 'Nifty 50', 'slug' => 'nifty-50', 'date' => '2025-01-02',
        'open' => '98.00', 'high' => '102.00', 'low' => '97.00', 'close' => '100.00',
        'points_change' => null, 'percentage_change' => null,
        'volume' => '1000', 'turnover' => '12.34',
        'price_to_earnings' => '22.35', 'price_to_book' => '3.21', 'dividend_yield' => '1.23',
    ], $attributes);
}

/**
 * @param  list<array<string, mixed>>  $rows
 * @param  array<string, mixed>  $overrides
 * @return array{file: string, manifest: string}
 */
function historyImportFiles(array $rows, array $overrides = []): array
{
    $contents = implode("\n", array_map(fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $rows))."\n";
    Storage::put('history.jsonl', $contents);
    Storage::put('manifest.json', json_encode(array_merge([
        'source_complete' => true,
        'comparison_complete' => true,
        'missing_current_rows' => 0,
        'source_issues' => 0,
        'row_count' => count($rows),
        'index_count' => count(array_unique(array_column($rows, 'slug'))),
        'sha256' => hash('sha256', $contents),
        'current_fingerprint' => ReloadNseIndexHistoryCommand::currentFingerprint(),
    ], $overrides), JSON_THROW_ON_ERROR));

    return ['file' => Storage::path('history.jsonl'), 'manifest' => Storage::path('manifest.json')];
}

it('checks the complete file without changing the current table', function () {
    $before = NseIndex::query()->sole()->getAttributes();
    $files = historyImportFiles([historyImportRow()]);

    $this->artisan('nse:reload-index-history', $files)
        ->expectsOutputToContain('Checked 1 rows for 1 indices.')
        ->expectsOutputToContain('No database rows were changed.')
        ->assertSuccessful();

    expect(NseIndex::query()->sole()->getAttributes())->toBe($before);
});

it('replaces all rows and fills missing OHL values from close while keeping missing valuations null', function () {
    $files = historyImportFiles([
        historyImportRow(['open' => null, 'high' => null, 'low' => null, 'price_to_earnings' => null, 'price_to_book' => null, 'dividend_yield' => null]),
        historyImportRow(['symbol' => 'Nifty50 Dividend Points', 'slug' => 'nifty50-dividend-points', 'date' => '2025-04-01', 'open' => null, 'high' => null, 'low' => null, 'close' => '0.00']),
    ]);

    $this->artisan('nse:reload-index-history', [...$files, '--apply' => true])->assertSuccessful();

    expect(NseIndex::query()->count())->toBe(2)
        ->and(NseIndex::query()->whereDate('date', '2024-12-31')->exists())->toBeFalse();

    $index = NseIndex::query()->where('slug', 'nifty-50')->sole();
    expect($index->open)->toBe('100.00')
        ->and($index->high)->toBe('100.00')
        ->and($index->low)->toBe('100.00')
        ->and($index->close)->toBe('100.00')
        ->and($index->price_to_earnings)->toBeNull()
        ->and($index->price_to_book)->toBeNull()
        ->and($index->dividend_yield)->toBeNull()
        ->and($index->turnover)->toBe('12.34');

    $dividends = NseIndex::query()->where('slug', 'nifty50-dividend-points')->sole();
    expect($dividends->only(['open', 'high', 'low', 'close']))->toBe(array_fill_keys(['open', 'high', 'low', 'close'], '0.00'));
});

it('rejects incomplete or changed import files before clearing current data', function (array $overrides) {
    $before = NseIndex::query()->sole()->getAttributes();
    $files = historyImportFiles([historyImportRow()], $overrides);

    $this->artisan('nse:reload-index-history', [...$files, '--apply' => true])->assertFailed();

    expect(NseIndex::query()->sole()->getAttributes())->toBe($before);
})->with([
    'incomplete source' => [['source_complete' => false]],
    'incomplete comparison' => [['comparison_complete' => false]],
    'missing old rows' => [['missing_current_rows' => 1]],
    'source errors' => [['source_issues' => 1]],
    'wrong checksum' => [['sha256' => str_repeat('0', 64)]],
    'wrong row count' => [['row_count' => 2]],
    'wrong index count' => [['index_count' => 2]],
]);

it('rejects stale comparisons when any current value changes', function () {
    $files = historyImportFiles([historyImportRow()]);
    DB::table('nse_indices')->update(['price_to_book' => '4.00']);

    $this->artisan('nse:reload-index-history', [...$files, '--apply' => true])
        ->expectsOutputToContain('Repeat the comparison before import.')
        ->assertFailed();

    expect(NseIndex::query()->sole()->price_to_book)->toBe('4.00');
});

it('rejects a malformed later row before clearing current data', function (array $attributes) {
    $before = NseIndex::query()->sole()->getAttributes();
    $files = historyImportFiles([historyImportRow(), historyImportRow(array_merge(['date' => '2025-01-03'], $attributes))]);

    $this->artisan('nse:reload-index-history', [...$files, '--apply' => true])->assertFailed();

    expect(NseIndex::query()->sole()->getAttributes())->toBe($before);
})->with([
    'duplicate date' => [['date' => '2025-01-02']],
    'unsorted date' => [['date' => '2024-01-01']],
    'invalid date' => [['date' => '2025-02-30']],
    'missing close' => [['close' => null]],
    'zero normal index' => [['close' => '0.00']],
    'negative close' => [['close' => '-1.00']],
    'negative open' => [['open' => '-1.00']],
    'invalid valuation' => [['price_to_earnings' => 'NaN']],
    'valuation overflow' => [['price_to_earnings' => '100000000.00']],
    'wrong precision' => [['close' => '100.001']],
    'extra column' => [['id' => 123]],
]);

it('rolls back the deletion when a later insert fails', function () {
    $before = NseIndex::query()->sole()->getAttributes();
    $files = historyImportFiles(array_map(fn (int $day): array => historyImportRow([
        'date' => CarbonImmutable::parse('2020-01-01')->addDays($day)->format('Y-m-d'),
    ]), range(0, 1000)));
    $connection = DB::connection();
    $insertCount = 0;
    $connection->beforeExecuting(function (string $query) use (&$insertCount): void {
        if (str_starts_with($query, 'insert into `nse_indices`') && ++$insertCount === 2) {
            throw new RuntimeException('Test insert failure.');
        }
    });

    $this->artisan('nse:reload-index-history', [...$files, '--apply' => true])->assertFailed();

    expect(NseIndex::query()->sole()->getAttributes())->toBe($before);
});
