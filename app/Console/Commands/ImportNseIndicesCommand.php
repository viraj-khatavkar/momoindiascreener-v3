<?php

namespace App\Console\Commands;

use App\Actions\ReadCsvAction;
use App\Models\NseIndex;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ImportNseIndicesCommand extends Command
{
    protected $signature = 'nse:import-indices {--date=} {--O|omit-create}';

    protected $description = 'Import NSE index prices and valuations from the uploaded index.csv';

    /**
     * Execute the console command.
     */
    public function handle(ReadCsvAction $readCsv): int
    {
        $date = $this->option('date');

        if (Validator::make(['date' => $date], ['date' => ['required', 'date_format:Y-m-d']])->fails()) {
            $this->error('Please provide a valid date in YYYY-MM-DD format.');

            return self::FAILURE;
        }

        try {
            $indices = $this->readIndices($date, $readCsv);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('omit-create')) {
            $this->info(count($indices).' indices checked. No records saved.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($indices): void {
            foreach ($indices as $index) {
                NseIndex::query()->updateOrCreate(
                    ['slug' => $index['slug'], 'date' => $index['date']],
                    $index,
                );
            }
        });

        $this->info(count($indices)." indices imported for {$date}.");

        return self::SUCCESS;
    }

    /**
     * @return list<array{symbol: string, slug: string, date: string, open: ?string, high: ?string, low: ?string, close: string, points_change: ?string, percentage_change: ?string, volume: string, turnover: string, price_to_earnings: ?string, price_to_book: ?string, dividend_yield: ?string}>
     */
    private function readIndices(string $date, ReadCsvAction $readCsv): array
    {
        $path = "uploads/{$date}/index.csv";

        if (! Storage::disk('local')->exists($path)) {
            throw new InvalidArgumentException("Upload index.csv for {$date} before you run this command.");
        }

        $fieldHeaders = [
            'symbol' => 'INDEX NAME', 'date' => 'INDEX DATE',
            'open' => 'OPEN INDEX VALUE', 'high' => 'HIGH INDEX VALUE',
            'low' => 'LOW INDEX VALUE', 'close' => 'CLOSING INDEX VALUE',
            'points_change' => 'POINTS CHANGE', 'percentage_change' => 'CHANGE(%)',
            'volume' => 'VOLUME', 'turnover' => 'TURNOVER (RS. CR.)',
            'price_to_earnings' => 'P/E', 'price_to_book' => 'P/B', 'dividend_yield' => 'DIV YIELD',
        ];
        $columns = null;
        $indices = [];
        $fileDate = CarbonImmutable::parse($date)->format('d-m-Y');
        $rows = $readCsv->execute(Storage::disk('local')->path($path), skipFirstRow: false)->toArray();

        foreach ($rows as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1;

            if (implode('', $row) === '') {
                continue;
            }

            if ($columns === null) {
                $headers = array_flip(array_map(fn (string $header): string => Str::upper(Str::squish(ltrim($header, "\xEF\xBB\xBF"))), $row));

                if (array_diff($fieldHeaders, array_keys($headers)) !== []) {
                    throw new InvalidArgumentException('Invalid index.csv headers. Expected: '.implode(', ', $fieldHeaders).'.');
                }

                $columns = array_map(fn (string $header): int => $headers[$header], $fieldHeaders);

                continue;
            }

            $index = [];

            foreach ($columns as $field => $column) {
                if (! array_key_exists($column, $row)) {
                    throw new InvalidArgumentException("Missing {$field} on index.csv row {$rowNumber}.");
                }

                $index[$field] = $row[$column];
            }

            if ($index['date'] !== $fileDate) {
                throw new InvalidArgumentException("Index date on row {$rowNumber} does not match {$date}.");
            }

            $index['slug'] = Str::slug($index['symbol']);

            if ($index['slug'] === '' || Str::length($index['symbol']) > 255 || Str::length($index['slug']) > 255) {
                throw new InvalidArgumentException("Invalid index name on row {$rowNumber}.");
            }

            if (isset($indices[$index['slug']])) {
                throw new InvalidArgumentException("Duplicate index on row {$rowNumber}: {$index['symbol']}.");
            }

            foreach (array_diff(array_keys($fieldHeaders), ['symbol', 'date']) as $field) {
                $index[$field] = $this->parseValue(
                    $index[$field],
                    $field,
                    $rowNumber,
                    allowZeroClose: $index['slug'] === 'nifty50-dividend-points',
                );
            }

            $index['date'] = $date;
            $indices[$index['slug']] = $index;
        }

        if ($indices === []) {
            throw new InvalidArgumentException('No index data found in index.csv.');
        }

        return array_values($indices);
    }

    private function parseValue(string $value, string $field, int $rowNumber, bool $allowZeroClose): ?string
    {
        if ($field !== 'close' && in_array(Str::upper($value), ['', '-', 'NA', 'N/A'], true)) {
            return in_array($field, ['volume', 'turnover'], true) ? '0' : null;
        }

        $value = str_replace(',', '', $value);
        $numericValue = (float) $value;
        $limit = match ($field) {
            'volume' => PHP_INT_MAX,
            'price_to_earnings', 'price_to_book', 'dividend_yield' => 1e8,
            default => 1e18,
        };

        if (! is_numeric($value) || ! is_finite($numericValue) || abs($numericValue) >= $limit
            || ($field === 'close' && $numericValue === 0.0 && ! $allowZeroClose)
            || (in_array($field, ['open', 'high', 'low', 'close', 'volume', 'turnover'], true) && $numericValue < 0)
            || ($field === 'volume' && floor($numericValue) !== $numericValue)) {
            throw new InvalidArgumentException("Invalid {$field} on index.csv row {$rowNumber}.");
        }

        return $value;
    }
}
