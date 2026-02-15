<?php

namespace App\Console\Commands\Backtest;

use App\Models\BacktestNseInstrumentPrice;
use Illuminate\Console\Command;

use function array_diff;
use function dd;

class DiffNseIndexForInstrumentsCommand extends Command
{
    protected $signature = 'backtest:diff-nse-index-instruments {--from-date=} {--to-date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description ';

    public function handle()
    {
        $fromDate = $this->option('from-date');
        $toDate = $this->option('to-date');

        if (is_null($fromDate)) {
            $this->error('Please provide a from date');

            return Command::FAILURE;
        }

        if (is_null($toDate)) {
            $this->error('Please provide a to date');

            return Command::FAILURE;
        }

        $fromInstruments = BacktestNseInstrumentPrice::query()
            ->where('date', $fromDate)
            ->where('is_nifty_500', true)
            ->select('symbol')
            ->get()
            ->pluck('symbol')
            ->toArray();

        $toInstruments = BacktestNseInstrumentPrice::query()
            ->where('date', $toDate)
            ->where('is_nifty_500', true)
            ->select('symbol')
            ->get()
            ->pluck('symbol')
            ->toArray();

        dd(array_diff($fromInstruments, $toInstruments));
    }
}
