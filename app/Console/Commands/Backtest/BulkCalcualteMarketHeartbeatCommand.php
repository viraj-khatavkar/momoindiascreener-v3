<?php

namespace App\Console\Commands\Backtest;

use App\Actions\Backtest\BacktestTradingCalendarAction;
use App\Models\MarketHeartbeat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class BulkCalcualteMarketHeartbeatCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backtest:bulk-calculate-market-heartbeat';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear and rebuild all market heartbeats over the available price dates';

    /**
     * Execute the console command.
     */
    public function handle(BacktestTradingCalendarAction $calendar): int
    {
        $dates = $calendar->priceDates()->orderBy('date')->pluck('date');
        MarketHeartbeat::truncate();
        if ($dates->isEmpty()) {
            $this->info('No price dates are available. The heartbeat table is empty.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($dates->count());
        $bar->start();

        foreach ($dates as $date) {
            try {
                DB::transaction(function () use ($date): void {
                    if ($this->call('backtest:calculate-market-heartbeat', ['--date' => $date]) !== self::SUCCESS) {
                        throw new RuntimeException('The daily heartbeat command failed.');
                    }
                });
            } catch (Throwable $exception) {
                $this->newLine();
                $this->error("Heartbeat refresh failed on {$date}: ".$exception->getMessage());

                return self::FAILURE;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Rebuilt '.$dates->count().' price dates from '.$dates->first().' through '.$dates->last().'.');

        return self::SUCCESS;
    }
}
