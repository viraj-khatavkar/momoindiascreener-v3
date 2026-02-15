<?php

namespace App\Console\Commands\Backtest;

use App\Actions\CheckIfBacktestPriceRecordExitsForDateAction;
use App\Jobs\ProcessDailyDataForBacktestJob;
use App\Models\BacktestNseInstrumentPrice;
use Illuminate\Console\Command;

class ProcessDailyDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backtest:process-daily-data {--date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process daily data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Processing daily data for backtest tables...');
        $date = $this->option('date');

        if (is_null($date)) {
            $this->error('Please provide a date');

            return Command::FAILURE;
        }

        if (! (new CheckIfBacktestPriceRecordExitsForDateAction)->execute($date)) {
            $this->error('Price record doesnt exists for '.$date);

            return Command::FAILURE;
        }

        $backtestNseInstrumentPrices = BacktestNseInstrumentPrice::query()
            ->where('date', $date)
            ->get();

        foreach ($backtestNseInstrumentPrices as $backtestNseInstrumentPrice) {
            ProcessDailyDataForBacktestJob::dispatch($backtestNseInstrumentPrice->symbol, $date);
        }
    }
}
