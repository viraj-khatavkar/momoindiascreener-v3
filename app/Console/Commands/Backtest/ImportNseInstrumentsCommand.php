<?php

namespace App\Console\Commands\Backtest;

use App\Actions\Backtest\InvalidateAssumedDelistingsAction;
use App\Models\BacktestNseInstrumentPrice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ImportNseInstrumentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backtest:import-instruments {--date=} {--O|omit-create}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import NSE instruments and company names from UDiFF bhavcopy for backtest tables';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Importing instruments from NSE bhavcopy...');
        $date = $this->option('date');

        if (is_null($date)) {
            $this->error('Please provide a date');

            return Command::FAILURE;
        }

        if (Validator::make(['date' => $date], ['date' => ['date_format:Y-m-d']])->fails()) {
            $this->error('Please provide a valid date in YYYY-MM-DD format.');

            return Command::FAILURE;
        }

        $omitCreate = $this->option('omit-create');

        $hasPriceRecordForDate = BacktestNseInstrumentPrice::query()
            ->where('date', $date)
            ->exists();

        if ($hasPriceRecordForDate) {
            $this->error('Price record already exists for '.$date);

            return Command::FAILURE;
        }

        try {
            $instruments = $this->fetchInstrumentsFromBhavcopy($date);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return Command::FAILURE;
        }

        DB::transaction(function () use ($instruments, $omitCreate, $date): void {
            if (! $omitCreate) {
                app(InvalidateAssumedDelistingsAction::class)->execute($date);
            }

            foreach ($instruments as $instrument) {
                $backtestNseInstrumentPriceDoesntExist = BacktestNseInstrumentPrice::query()
                    ->where('symbol', $instrument['symbol'])
                    ->doesntExist();

                if ($backtestNseInstrumentPriceDoesntExist) {
                    $this->info('No match found for '.$instrument['symbol']);
                }

                if ($omitCreate) {
                    continue;
                }

                BacktestNseInstrumentPrice::create([
                    'date' => $date,
                    'symbol' => $instrument['symbol'],
                    'name' => $instrument['name'],
                    'series' => $instrument['series'],
                    'open_adjusted' => $instrument['open'],
                    'high_adjusted' => $instrument['high'],
                    'low_adjusted' => $instrument['low'],
                    'close_adjusted' => $instrument['close'],
                    'volume_adjusted' => $instrument['volume'],
                    'volume_shares_adjusted' => $instrument['volume_shares'],
                    'open_raw' => $instrument['open'],
                    'high_raw' => $instrument['high'],
                    'low_raw' => $instrument['low'],
                    'close_raw' => $instrument['close'],
                    'volume_raw' => $instrument['volume'],
                    'volume_shares_raw' => $instrument['volume_shares'],
                    't_percent_raw' => 0,
                    't_percent' => 0,
                ]);
            }
        });

        $this->info(count($instruments).($omitCreate ? ' instruments checked. No records created.' : ' instruments imported.'));

        return Command::SUCCESS;
    }

    /**
     * @return list<array{symbol: string, series: string, name: string, open: string, high: string, low: string, close: string, volume_shares: string, volume: string}>
     */
    protected function fetchInstrumentsFromBhavcopy(string $date): array
    {
        $path = "uploads/{$date}/bhavcopy.csv";

        if (! Storage::disk('local')->exists($path)) {
            throw new InvalidArgumentException("Upload bhavcopy.csv for {$date} before you run this command.");
        }

        $filePath = Storage::disk('local')->path($path);
        $this->info($filePath);
        $stream = fopen($filePath, 'r');

        if ($stream === false) {
            throw new InvalidArgumentException('Cannot read bhavcopy.csv.');
        }

        $columns = null;
        $instruments = [];
        $rowNumber = 0;

        try {
            while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
                $rowNumber++;
                $row = array_map(fn (?string $value): string => trim($value ?? ''), $row);

                if (implode('', $row) === '') {
                    continue;
                }

                if ($columns === null) {
                    $headers = array_flip(array_map(fn (string $header): string => Str::upper(ltrim($header, "\xEF\xBB\xBF")), $row));
                    $fieldHeaders = [
                        'symbol' => 'TCKRSYMB', 'series' => 'SCTYSRS', 'name' => 'FININSTRMNM',
                        'open' => 'OPNPRIC', 'high' => 'HGHPRIC', 'low' => 'LWPRIC', 'close' => 'CLSPRIC',
                        'volume_shares' => 'TTLTRADGVOL', 'volume' => 'TTLTRFVAL', 'date' => 'TRADDT', 'segment' => 'SGMT',
                    ];

                    if (array_diff($fieldHeaders, array_keys($headers)) !== []) {
                        throw new InvalidArgumentException('Unsupported bhavcopy headers. Upload a UDiFF bhavcopy file.');
                    }

                    $columns = array_map(fn (string $header): int => $headers[$header], $fieldHeaders);

                    continue;
                }

                $instrument = [];

                foreach ($columns as $field => $index) {
                    if (! array_key_exists($index, $row)) {
                        throw new InvalidArgumentException("Missing {$field} on bhavcopy row {$rowNumber}.");
                    }

                    $instrument[$field] = $row[$index];
                }

                if (! in_array($instrument['series'], ['EQ', 'BE', 'SM', 'ST', 'SZ', 'BZ'], true)
                    || Str::endsWith($instrument['symbol'], ['-RE', '-RE1', '-RE2', '-RE3'])
                    || $instrument['segment'] !== 'CM') {
                    continue;
                }

                if ($instrument['date'] !== $date) {
                    throw new InvalidArgumentException("Trade date on bhavcopy row {$rowNumber} does not match {$date}.");
                }

                if ($instrument['symbol'] === '') {
                    throw new InvalidArgumentException("Missing symbol on bhavcopy row {$rowNumber}.");
                }

                foreach (['open', 'high', 'low', 'close', 'volume_shares', 'volume'] as $field) {
                    if (! is_numeric($instrument[$field]) || ! is_finite((float) $instrument[$field]) || (float) $instrument[$field] < 0) {
                        throw new InvalidArgumentException("Invalid {$field} on bhavcopy row {$rowNumber}.");
                    }
                }

                unset($instrument['date'], $instrument['segment']);
                $instruments[] = $instrument;
            }
        } finally {
            fclose($stream);
        }

        if ($instruments === []) {
            throw new InvalidArgumentException('No supported instruments found in bhavcopy.csv.');
        }

        return $instruments;
    }
}
