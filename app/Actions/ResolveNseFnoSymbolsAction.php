<?php

namespace App\Actions;

use Carbon\CarbonImmutable;
use RuntimeException;

class ResolveNseFnoSymbolsAction
{
    /** @var array<string, list<array{new_symbol: string, date: string}>> */
    private array $changes = [];

    private ?string $loadedPath = null;

    /**
     * @param  list<string>  $sourceSymbols
     * @param  list<string>  $priceSymbols
     * @return array{mappings: array<string, string>, missing: list<string>}
     */
    public function execute(array $sourceSymbols, string $date, array $priceSymbols, ?string $changesPath = null): array
    {
        $this->loadChanges($changesPath);
        $available = array_fill_keys($priceSymbols, true);
        $mappings = [];
        $missing = [];
        foreach ($sourceSymbols as $sourceSymbol) {
            $candidates = [$sourceSymbol];
            $cursor = $sourceSymbol;
            $effectiveAfter = $date;
            while (true) {
                $change = collect($this->changes[$cursor] ?? [])
                    ->first(fn (array $change): bool => $change['date'] >= $effectiveAfter);
                if ($change === null) {
                    break;
                }
                if (in_array($change['new_symbol'], $candidates, true)) {
                    throw new RuntimeException("A symbol-change cycle needs review: {$sourceSymbol} on {$date}.");
                }
                $candidates[] = $cursor = $change['new_symbol'];
                $effectiveAfter = $change['date'];
            }
            $matches = array_values(array_filter(array_unique($candidates), fn (string $symbol): bool => isset($available[$symbol])));
            if (count($matches) > 1) {
                throw new RuntimeException("Ambiguous F&O symbol {$sourceSymbol} on {$date}: ".implode(', ', $matches));
            }
            if ($matches === []) {
                $missing[] = $sourceSymbol;
            } else {
                $mappings[$sourceSymbol] = $matches[0];
            }
        }
        if (count(array_unique($mappings)) !== count($mappings)) {
            throw new RuntimeException("Two F&O source symbols map to the same price symbol on {$date}. Review the symbol changes.");
        }

        return ['mappings' => $mappings, 'missing' => $missing];
    }

    private function loadChanges(?string $path): void
    {
        if ($path === $this->loadedPath) {
            return;
        }
        $this->changes = [];
        if ($path === null) {
            $this->loadedPath = null;

            return;
        }
        if (! is_file($path)) {
            throw new RuntimeException("Symbol-change CSV is missing: {$path}");
        }
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException('The symbol-change CSV could not be read.');
        }
        try {
            while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
                if (count($row) === 1 && blank($row[0])) {
                    continue;
                }
                if (count($row) < 4) {
                    throw new RuntimeException('The symbol-change CSV must contain name, old symbol, new symbol, and date.');
                }
                [$name, $old, $new, $date] = array_map(fn (?string $value): string => trim((string) $value), array_slice($row, 0, 4));
                if (str_contains(strtoupper($old), 'SYMBOL') && str_contains(strtoupper($new), 'SYMBOL')) {
                    continue;
                }
                $effective = CarbonImmutable::createFromFormat('!d-M-Y', $date);
                if (! $effective || strtoupper($effective->format('d-M-Y')) !== strtoupper($date) || $old === '' || $new === '') {
                    throw new RuntimeException("Invalid symbol change for {$old}.");
                }
                if ($old !== $new) {
                    $this->changes[$old][] = ['new_symbol' => $new, 'date' => $effective->toDateString()];
                }
            }
        } finally {
            fclose($stream);
        }
        foreach ($this->changes as &$changes) {
            usort($changes, fn (array $first, array $second): int => $first['date'] <=> $second['date']);
        }
        unset($changes);
        $this->loadedPath = $path;
    }
}
