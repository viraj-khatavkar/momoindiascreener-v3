<?php

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

class ProcessDailyDataForBacktestJob implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $symbol, public string $date) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        foreach ([
            'backtest:calculate-variance',
            'backtest:calculate-covariance',
            'backtest:calculate-momentum',
            'backtest:calculate-rsi',
        ] as $command) {
            $exitCode = Artisan::call($command, [
                '--date' => $this->date,
                '--symbol' => $this->symbol,
            ]);

            if ($exitCode !== Command::SUCCESS) {
                throw new RuntimeException(
                    "{$command} failed for {$this->symbol} with exit code {$exitCode}.",
                );
            }
        }
    }
}
