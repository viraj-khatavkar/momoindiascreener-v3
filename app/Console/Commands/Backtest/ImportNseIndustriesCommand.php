<?php

namespace App\Console\Commands\Backtest;

use App\Models\BacktestNseInstrument;
use App\Models\Industry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ImportNseIndustriesCommand extends Command
{
    protected $signature = 'backtest:import-industries
        {file : Path to an NSE constituent CSV with Symbol and Industry columns}
        {--dry-run : Show the changes without saving them}';

    protected $description = 'Update current instrument industries from a local NSE constituent CSV';

    public function handle(): int
    {
        try {
            $industries = $this->readIndustries((string) $this->argument('file'));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return Command::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        [$matched, $changed, $missing] = DB::transaction(function () use ($industries, $dryRun): array {
            $instruments = BacktestNseInstrument::query()->whereIn('symbol', array_keys($industries))
                ->with('industry:id,name')->lockForUpdate()->get(['id', 'symbol', 'industry_id'])->keyBy('symbol');
            $resolvedIndustries = [];
            $changed = 0;
            foreach ($instruments as $instrument) {
                $industry = $industries[$instrument->symbol];
                if ($instrument->industry?->name === $industry) {
                    continue;
                }
                if (! $dryRun) {
                    $resolvedIndustries[$industry] ??= Industry::findOrCreateByName($industry);
                    $instrument->update(['industry_id' => $resolvedIndustries[$industry]->id]);
                }
                $changed++;
            }

            return [$instruments->count(), $changed, array_values(array_diff(array_keys($industries), $instruments->keys()->all()))];
        });

        $this->info($matched.' instruments matched. '.$changed.($dryRun ? ' would be updated; ' : ' updated; ').($matched - $changed).' unchanged.');
        if ($missing !== []) {
            $this->warn(count($missing).' symbols not found in the instruments table: '.implode(', ', $missing));
        }

        return $matched > 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /** @return array<string, string> */
    private function readIndustries(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException('The industry CSV is missing or cannot be read.');
        }
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new InvalidArgumentException('The industry CSV cannot be opened.');
        }
        $columns = null;
        $industries = [];
        $line = 0;
        try {
            while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
                $line++;
                $row = array_map(function (?string $value): string {
                    $value = (string) $value;

                    return trim(mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'Windows-1252'));
                }, $row);
                if (implode('', $row) === '') {
                    continue;
                }
                if ($columns === null) {
                    $headers = array_map(fn (string $header): string => Str::upper(trim(ltrim($header, "\xEF\xBB\xBF"))), $row);
                    foreach (['SYMBOL', 'INDUSTRY'] as $required) {
                        if (count(array_filter($headers, fn (string $header): bool => $header === $required)) !== 1) {
                            throw new InvalidArgumentException('The CSV must contain one Symbol column and one Industry column.');
                        }
                    }
                    $columns = array_flip($headers);

                    continue;
                }
                $symbol = Str::upper($row[$columns['SYMBOL']] ?? '');
                $industry = $row[$columns['INDUSTRY']] ?? '';
                if ($symbol === '' || $industry === '' || Str::length($symbol) > 255 || Str::length($industry) > 255) {
                    throw new InvalidArgumentException('Missing or invalid Symbol or Industry on CSV row '.$line.'.');
                }
                if (isset($industries[$symbol]) && $industries[$symbol] !== $industry) {
                    throw new InvalidArgumentException('Conflicting industries for '.$symbol.' in the CSV.');
                }
                $industries[$symbol] = $industry;
            }
        } finally {
            fclose($stream);
        }
        if ($industries === []) {
            throw new InvalidArgumentException('The CSV contains no industries.');
        }

        return $industries;
    }
}
