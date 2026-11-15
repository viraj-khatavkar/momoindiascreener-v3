<?php

namespace App\Actions;

use Carbon\CarbonImmutable;
use RuntimeException;
use Throwable;

class ReadNseFnoMembershipAction
{
    /** @var array<string, string> */
    private array $dateCache = [];

    /** @return list<string> */
    public function execute(string $path, string $date): array
    {
        $this->dateCache = [];
        if (! is_file($path)) {
            throw new RuntimeException("F&O file is missing: {$path}");
        }

        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException('The F&O file could not be read.');
        }
        try {
            return $this->readSymbols($stream, $date);
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  resource  $stream
     * @return list<string>
     */
    private function readSymbols($stream, string $date): array
    {
        $header = fgetcsv($stream, null, ',', '"', '');
        if ($header === false) {
            throw new RuntimeException('The F&O CSV is empty.');
        }
        $header = array_map(fn (?string $value): string => strtoupper(trim(ltrim((string) $value, "\xEF\xBB\xBF"))), $header);
        $columns = ['FININSTRMTP', 'TCKRSYMB', 'TRADDT', 'XPRYDT'];
        if (array_diff($columns, $header) !== []) {
            throw new RuntimeException('Only NSE F&O UDiFF files are supported. Required columns: '.implode(', ', $columns));
        }
        [$typeColumn, $symbolColumn, $dateColumn, $expiryColumn] = array_map(fn (string $column): int => array_search($column, $header, true), $columns);
        $symbols = [];
        $line = 1;
        while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
            $line++;
            if (count($row) === 1 && blank($row[0])) {
                continue;
            }
            foreach ([$typeColumn, $symbolColumn, $dateColumn] as $column) {
                if (! isset($row[$column]) || trim($row[$column]) === '') {
                    throw new RuntimeException("Missing F&O data on CSV line {$line}.");
                }
            }
            $rowDate = $this->parseDate(trim($row[$dateColumn]));
            if ($rowDate !== $date) {
                throw new RuntimeException("F&O CSV line {$line} is dated {$rowDate}; expected {$date}.");
            }
            if (! in_array(trim($row[$typeColumn]), ['STF', 'STO'], true)) {
                continue;
            }
            if (! isset($row[$expiryColumn]) || trim($row[$expiryColumn]) === '') {
                throw new RuntimeException("Missing F&O expiry on CSV line {$line}.");
            }
            if ($this->parseDate(trim($row[$expiryColumn])) < $date) {
                throw new RuntimeException("Expired stock contract on F&O CSV line {$line}.");
            }
            $symbol = trim($row[$symbolColumn]);
            if (preg_match('/^[A-Z0-9&_.\-]+$/D', $symbol) !== 1) {
                throw new RuntimeException("Invalid stock symbol on F&O CSV line {$line}.");
            }
            $symbols[$symbol] = true;
        }
        if ($symbols === []) {
            throw new RuntimeException('The F&O CSV has no stock contracts. No membership was changed.');
        }
        $symbols = array_keys($symbols);
        sort($symbols);

        return $symbols;
    }

    private function parseDate(string $value): string
    {
        if (isset($this->dateCache[$value])) {
            return $this->dateCache[$value];
        }
        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $value);
            if (! $parsed || $parsed->format('Y-m-d') !== $value) {
                throw new RuntimeException('Invalid date.');
            }

            return $this->dateCache[$value] = $parsed->toDateString();
        } catch (Throwable $exception) {
            throw new RuntimeException("Invalid F&O date: {$value}", previous: $exception);
        }
    }
}
