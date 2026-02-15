<?php

namespace App\Console\Commands\Backtest;

use App\Actions\ReadCsvAction;
use App\Models\BacktestNseInstrumentPrice;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MarkNseEtfsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backtest:mark-etfs {--date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Marks etfs in backtest nse instruments from etf.csv';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Marking ETFs for NSE...');
        $date = $this->option('date');

        if (is_null($date)) {
            $this->error('Please provide a date');

            return Command::FAILURE;
        }

        $etfs = $this->fetchEtfs($date);

        BacktestNseInstrumentPrice::query()
            ->where('date', $date)
            ->whereIn('symbol', $etfs)
            ->update(['is_etf' => true]);

        $etfsCounts = BacktestNseInstrumentPrice::query()
            ->where('date', $date)
            ->where('is_etf', true)
            ->count();

        $this->info($etfsCounts.' etfs marked');
    }

    protected function fetchEtfs($date): array
    {
        /** @var ReadCsvAction $readCsvAction */
        $readCsvAction = app(ReadCsvAction::class);

        $filePath = Storage::path('uploads/'.(new Carbon($date))->format('Y-m-d').'/etf.csv');
        $this->info($filePath);

        $rows = $readCsvAction->execute($filePath)->toCollection();

        $rows = $rows->map(function ($row) {
            return [
                'symbol' => trim($row[2]),
            ];
        });

        return $rows->pluck('symbol')->toArray();
    }
}
