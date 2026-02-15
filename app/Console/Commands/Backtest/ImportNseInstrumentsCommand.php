<?php

namespace App\Console\Commands\Backtest;

use App\Actions\ReadCsvAction;
use App\Models\BacktestNseInstrumentPrice;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
    protected $description = 'Imports nse instruments from bhavcopy for backtest tables';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Importing instruments from NSE bhavcopy...');
        $date = $this->option('date');

        if (is_null($date)) {
            $this->error('Please provide a date');

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

        $instruments = $this->fetchInstrumentsFromBhavcopy($date);

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
    }

    protected function fetchInstrumentsFromBhavcopy(string $date): array
    {
        /** @var ReadCsvAction $readCsvAction */
        $readCsvAction = app(ReadCsvAction::class);

        $filePath = Storage::path('uploads/'.(new Carbon($date))->format('Y-m-d').'/bhavcopy.csv');
        $this->info($filePath);

        $rows = $readCsvAction->execute($filePath)->toCollection();

        $rows = $rows->filter(function ($row) {
            return in_array($row[1], ['EQ', 'BE', 'SM', 'ST', 'SZ', 'BZ']);
        })->reject(function ($row) {
            return Str::endsWith(trim($row[0]), ['-RE', '-RE1', '-RE2', '-RE3']);
        })->map(function ($row) {
            return [
                'name' => trim($row[0]),
                'series' => trim($row[1]),
                'symbol' => trim($row[0]),
                'open' => trim($row[2]),
                'high' => trim($row[3]),
                'low' => trim($row[4]),
                'close' => trim($row[5]),
                'volume_shares' => trim($row[8]),
                'volume' => trim($row[9]),
            ];
        });

        return $rows->toArray();
    }
}
