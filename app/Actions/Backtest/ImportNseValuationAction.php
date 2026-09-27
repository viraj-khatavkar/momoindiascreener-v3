<?php

namespace App\Actions\Backtest;

use App\Enums\NseFileEnum;
use App\Models\BacktestNseInstrumentPrice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ImportNseValuationAction
{
    /**
     * @return array{matched: int, unmatched: int}
     */
    public function execute(?string $date, NseFileEnum $file): array
    {
        if (Validator::make(['date' => $date], ['date' => ['required', 'date_format:Y-m-d']])->fails()) {
            throw new InvalidArgumentException('Please provide a valid date in YYYY-MM-DD format.');
        }

        if (! in_array($file, [NseFileEnum::Marketcap, NseFileEnum::PriceToEarnings], true)) {
            throw new InvalidArgumentException('Unsupported valuation file.');
        }

        $path = "uploads/{$date}/{$file->value}.csv";

        if (! Storage::disk('local')->exists($path)) {
            throw new InvalidArgumentException("Upload {$file->value}.csv for {$date} before you run this command.");
        }

        $prices = BacktestNseInstrumentPrice::query()
            ->where('date', $date)
            ->get(['id', 'symbol', 'series']);

        if ($prices->isEmpty()) {
            throw new InvalidArgumentException("Import instruments for {$date} before you run this command.");
        }

        $values = $this->readValues(Storage::disk('local')->path($path), $date, $file);
        $pricesByKey = $prices->groupBy(fn (BacktestNseInstrumentPrice $price): string => $file === NseFileEnum::Marketcap
            ? "{$price->symbol}|{$price->series}"
            : $price->symbol);
        $matchedKeys = array_intersect(array_keys($values), $pricesByKey->keys()->all());

        if ($matchedKeys === []) {
            throw new InvalidArgumentException('No file rows match the imported instruments for this date.');
        }

        return DB::transaction(function () use ($values, $pricesByKey, $file, $matchedKeys): array {
            $matched = 0;

            foreach ($matchedKeys as $key) {
                $priceIds = $pricesByKey->get($key)->pluck('id')->all();
                BacktestNseInstrumentPrice::query()
                    ->whereIn('id', $priceIds)
                    ->update([$file->value => $values[$key]]);
                $matched += count($priceIds);
            }

            return ['matched' => $matched, 'unmatched' => count($values) - count($matchedKeys)];
        });
    }

    /**
     * @return array<string, string|null>
     */
    private function readValues(string $path, string $date, NseFileEnum $file): array
    {
        $stream = fopen($path, 'r');

        if ($stream === false) {
            throw new InvalidArgumentException("Cannot read {$file->value}.csv.");
        }

        $isMarketcap = $file === NseFileEnum::Marketcap;
        $requiredHeaders = $isMarketcap
            ? ['TRADE DATE', 'SYMBOL', 'SERIES', 'MARKET CAP(RS.)']
            : ['SYMBOL', 'ADJUSTED P/E'];
        $headers = null;
        $values = [];
        $rowNumber = 0;

        try {
            while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
                $rowNumber++;
                $row = array_map(fn (?string $value): string => trim($value ?? ''), $row);

                if (implode('', $row) === '') {
                    continue;
                }

                if ($headers === null) {
                    $headers = array_map(fn (string $header): string => Str::upper(Str::squish(ltrim($header, "\xEF\xBB\xBF"))), $row);

                    if (array_diff($requiredHeaders, $headers) !== []) {
                        throw new InvalidArgumentException("Invalid {$file->value}.csv headers. Expected: ".implode(', ', $requiredHeaders).'.');
                    }

                    continue;
                }

                if (count($row) !== count($headers)) {
                    throw new InvalidArgumentException("Invalid column count on row {$rowNumber}.");
                }

                $record = array_combine($headers, $row);
                $symbol = $record['SYMBOL'];

                if ($isMarketcap && ! in_array($record['SERIES'], ['EQ', 'BE', 'SM', 'ST', 'SZ', 'BZ'], true)) {
                    continue;
                }

                if ($symbol === '') {
                    throw new InvalidArgumentException("Missing symbol on row {$rowNumber}.");
                }

                if ($isMarketcap && Str::upper($record['TRADE DATE']) !== Str::upper(CarbonImmutable::parse($date)->format('d M Y'))) {
                    throw new InvalidArgumentException("Trade date on row {$rowNumber} does not match {$date}.");
                }

                $rawValue = $record[$isMarketcap ? 'MARKET CAP(RS.)' : 'ADJUSTED P/E'];
                $value = $this->parseValue($rawValue, $isMarketcap, $rowNumber);
                $key = $isMarketcap ? "{$symbol}|{$record['SERIES']}" : $symbol;

                if (array_key_exists($key, $values) && $values[$key] !== $value) {
                    throw new InvalidArgumentException("Conflicting values for {$symbol} on row {$rowNumber}.");
                }

                $values[$key] = $value;
            }
        } finally {
            fclose($stream);
        }

        if ($values === []) {
            throw new InvalidArgumentException("No instrument data found in {$file->value}.csv.");
        }

        return $values;
    }

    private function parseValue(string $value, bool $isMarketcap, int $rowNumber): ?string
    {
        if (in_array(Str::upper($value), ['', '-', 'NA', 'N/A'], true)) {
            return null;
        }

        $value = str_replace(',', '', $value);

        if (! is_numeric($value) || ! is_finite((float) $value) || ($isMarketcap && (float) $value < 0)) {
            throw new InvalidArgumentException("Invalid numeric value on row {$rowNumber}.");
        }

        $converted = round((float) $value / ($isMarketcap ? 10_000_000 : 1), $isMarketcap ? 0 : 2);

        if (abs($converted) >= ($isMarketcap ? PHP_INT_MAX : 1e6)) {
            throw new InvalidArgumentException("Value on row {$rowNumber} exceeds the column limit.");
        }

        return number_format($converted, $isMarketcap ? 0 : 2, '.', '');
    }
}
