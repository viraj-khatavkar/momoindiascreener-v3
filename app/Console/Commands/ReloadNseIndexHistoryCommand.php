<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Throwable;

class ReloadNseIndexHistoryCommand extends Command
{
    protected $signature = 'nse:reload-index-history
        {file : Sorted JSON Lines file with the complete checked index history}
        {manifest : JSON manifest with file counts, checksum and current table fingerprint}
        {--apply : Replace all index rows after every check passes}';

    protected $description = 'Check and reload complete NSE index history in one database transaction';

    public const COLUMNS = [
        'symbol', 'slug', 'date', 'open', 'high', 'low', 'close',
        'points_change', 'percentage_change', 'volume', 'turnover',
        'price_to_earnings', 'price_to_book', 'dividend_yield',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $path = (string) $this->argument('file');
            $manifest = $this->readManifest((string) $this->argument('manifest'));
            $this->checkFile($path, $manifest);

            if (self::currentFingerprint() !== $manifest['current_fingerprint']) {
                throw new RuntimeException('The current table changed after the comparison. Repeat the comparison before import.');
            }

            $this->info("Checked {$manifest['row_count']} rows for {$manifest['index_count']} indices.");

            if (! $this->option('apply')) {
                $this->info('Check complete. No database rows were changed.');

                return self::SUCCESS;
            }

            DB::transaction(function () use ($path, $manifest): void {
                if (self::currentFingerprint(lock: true) !== $manifest['current_fingerprint']) {
                    throw new RuntimeException('The current table changed before import. No rows were replaced.');
                }

                DB::table('nse_indices')->delete();
                $batch = [];

                foreach ($this->rows($path) as $row) {
                    $batch[] = $row;

                    if (count($batch) === 1000) {
                        DB::table('nse_indices')->insert($batch);
                        $batch = [];
                    }
                }

                if ($batch !== []) {
                    DB::table('nse_indices')->insert($batch);
                }

                if (hash_file('sha256', $path) !== $manifest['sha256']
                    || DB::table('nse_indices')->count() !== $manifest['row_count']
                    || DB::table('nse_indices')->distinct()->count('slug') !== $manifest['index_count']) {
                    throw new RuntimeException('The final import check failed. The transaction was rolled back.');
                }
            });

            $this->info("Replaced nse_indices with {$manifest['row_count']} checked rows.");

            return self::SUCCESS;
        } catch (QueryException) {
            $this->error('The database import failed. Database changes were rolled back.');
        } catch (RuntimeException|JsonException $exception) {
            $this->error($exception->getMessage());
        } catch (Throwable) {
            $this->error('The import failed. Database changes were rolled back.');
        }

        return self::FAILURE;
    }

    public static function currentFingerprint(bool $lock = false): string
    {
        $hash = hash_init('sha256');
        $query = DB::table('nse_indices')->select(['id', ...self::COLUMNS]);

        if ($lock) {
            $query->lockForUpdate();
        }

        foreach ($query->lazyById(3000) as $row) {
            $values = array_map(fn (string $column): string => $row->{$column} === null ? '\\N' : (string) $row->{$column}, ['id', ...self::COLUMNS]);
            hash_update($hash, implode("\t", $values)."\n");
        }

        return hash_final($hash);
    }

    /**
     * @return array{row_count: int, index_count: int, sha256: string, current_fingerprint: string}
     */
    private function readManifest(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('The manifest file cannot be read.');
        }

        $manifest = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($manifest)
            || ($manifest['source_complete'] ?? null) !== true
            || ($manifest['comparison_complete'] ?? null) !== true
            || ($manifest['missing_current_rows'] ?? null) !== 0
            || ($manifest['source_issues'] ?? null) !== 0
            || ! is_int($manifest['row_count'] ?? null) || $manifest['row_count'] < 1
            || ! is_int($manifest['index_count'] ?? null) || $manifest['index_count'] < 1
            || ! is_string($manifest['sha256'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/D', $manifest['sha256'])
            || ! is_string($manifest['current_fingerprint'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/D', $manifest['current_fingerprint'])) {
            throw new RuntimeException('A complete source and comparison manifest is required.');
        }

        return $manifest;
    }

    /**
     * @param  array{row_count: int, index_count: int, sha256: string, current_fingerprint: string}  $manifest
     */
    private function checkFile(string $path, array $manifest): void
    {
        if (! is_file($path) || ! is_readable($path) || hash_file('sha256', $path) !== $manifest['sha256']) {
            throw new RuntimeException('The history file is missing or its checksum changed.');
        }

        $count = 0;
        $indices = 0;
        $previousSlug = null;

        foreach ($this->rows($path) as $row) {
            $count++;

            if ($row['slug'] !== $previousSlug) {
                $indices++;
                $previousSlug = $row['slug'];
            }
        }

        if ($count !== $manifest['row_count'] || $indices !== $manifest['index_count']) {
            throw new RuntimeException('The history file counts do not match the manifest.');
        }
    }

    /**
     * @return Generator<int, array<string, string|null>>
     */
    private function rows(string $path): Generator
    {
        $file = fopen($path, 'r');

        if ($file === false) {
            throw new RuntimeException('The history file cannot be opened.');
        }

        $lineNumber = 0;
        $previousKey = null;

        try {
            while (($line = fgets($file)) !== false) {
                $lineNumber++;
                $row = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

                if (! is_array($row) || array_diff(self::COLUMNS, array_keys($row)) !== []
                    || array_diff(array_keys($row), self::COLUMNS) !== []) {
                    throw new RuntimeException("Invalid columns on history row {$lineNumber}.");
                }

                if (! is_string($row['symbol']) || trim($row['symbol']) === '' || Str::length($row['symbol']) > 255
                    || ! is_string($row['slug']) || Str::length($row['slug']) > 255
                    || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $row['slug'])
                    || ! is_string($row['date']) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $row['date'])
                    || CarbonImmutable::createFromFormat('!Y-m-d', $row['date'])->format('Y-m-d') !== $row['date']) {
                    throw new RuntimeException("Invalid index name or date on history row {$lineNumber}.");
                }

                $key = $row['slug']."\t".$row['date'];

                if ($previousKey !== null && strcmp($key, $previousKey) <= 0) {
                    throw new RuntimeException("Duplicate or unsorted index date on history row {$lineNumber}.");
                }

                $previousKey = $key;

                foreach (array_diff(self::COLUMNS, ['symbol', 'slug', 'date']) as $field) {
                    $this->checkValue($row[$field], $field, $row['slug'], $lineNumber);
                }

                foreach (['open', 'high', 'low'] as $field) {
                    $row[$field] ??= $row['close'];
                }

                yield $row;
            }

            if (! feof($file)) {
                throw new RuntimeException('The history file could not be read completely.');
            }
        } finally {
            fclose($file);
        }
    }

    private function checkValue(mixed $value, string $field, string $slug, int $lineNumber): void
    {
        if ($value === null && $field !== 'close') {
            return;
        }

        $digits = in_array($field, ['price_to_earnings', 'price_to_book', 'dividend_yield'], true) ? 8 : 18;
        $pattern = $field === 'volume' ? '/^\d{1,18}$/D' : '/^-?\d{1,'.$digits.'}\.\d{2}$/D';

        if (! is_string($value) || ! preg_match($pattern, $value)
            || (in_array($field, ['open', 'high', 'low', 'close', 'volume', 'turnover'], true) && (float) $value < 0)
            || ($field === 'close' && (float) $value === 0.0 && $slug !== 'nifty50-dividend-points')) {
            throw new RuntimeException("Invalid {$field} on history row {$lineNumber}.");
        }
    }
}
