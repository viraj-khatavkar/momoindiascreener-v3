<?php

namespace App\Console\Commands\Backtest;

use App\Actions\CheckIfBacktestPriceRecordExitsForDateAction;
use App\Jobs\ProcessDailyDataForBacktestJob;
use App\Models\BacktestNseInstrumentPrice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

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
    public function handle(): int
    {
        $this->info('Processing daily data for backtest tables...');
        $date = (string) $this->option('date');

        if ($date === '') {
            $this->error('Please provide a date');

            return Command::FAILURE;
        }

        if (! (new CheckIfBacktestPriceRecordExitsForDateAction)->execute($date)) {
            $this->error('Price record doesnt exists for '.$date);

            return Command::FAILURE;
        }

        $symbols = BacktestNseInstrumentPrice::query()
            ->where('date', $date)
            ->distinct()
            ->orderBy('symbol')
            ->pluck('symbol');

        $batch = Bus::batch(
            $symbols
                ->map(fn (string $symbol): ProcessDailyDataForBacktestJob => new ProcessDailyDataForBacktestJob(
                    $symbol,
                    $date,
                ))
                ->all(),
        )
            ->name("Backtest daily data {$date}")
            ->dispatch();

        $this->info("Queued {$batch->totalJobs} instrument jobs.");
        $lastReportedProgress = -1;

        while (! $batch->finished() && ! $batch->cancelled()) {
            $batch = $batch->fresh();

            if (! $batch) {
                $this->error('The daily data batch could not be found.');

                return Command::FAILURE;
            }

            $progress = $batch->progress();

            if ($progress !== $lastReportedProgress) {
                $this->line("Daily data progress: {$progress}% ({$batch->processedJobs()}/{$batch->totalJobs})");
                $lastReportedProgress = $progress;
            }

            if (! $batch->finished() && ! $batch->cancelled()) {
                sleep(1);
            }
        }

        $batch = $batch->fresh();

        if (! $batch || $batch->cancelled() || $batch->failedJobs > 0) {
            $this->error('One or more daily data jobs failed.');

            return Command::FAILURE;
        }

        $this->info('Daily data processing completed.');

        return Command::SUCCESS;
    }
}
