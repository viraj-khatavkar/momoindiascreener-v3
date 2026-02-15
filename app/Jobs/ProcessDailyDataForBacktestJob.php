<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

class ProcessDailyDataForBacktestJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $symbol, public $date)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Artisan::call('backtest:calculate-variance', ['--date' => $this->date, '--symbol' => $this->symbol]);
        Artisan::call('backtest:calculate-covariance', ['--date' => $this->date, '--symbol' => $this->symbol]);
        Artisan::call('backtest:calculate-momentum', ['--date' => $this->date, '--symbol' => $this->symbol]);
        Artisan::call('backtest:calculate-rsi', ['--date' => $this->date, '--symbol' => $this->symbol]);
    }
}
