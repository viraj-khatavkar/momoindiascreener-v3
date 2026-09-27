<?php

namespace App\Console\Commands\Backtest;

use App\Actions\Backtest\StoreMarketCapAllocationAction;
use App\Enums\BacktestStatusEnum;
use App\Models\Backtest;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class BackfillMarketCapAllocationCommand extends Command
{
    protected $signature = 'backtest:backfill-market-cap-allocation
                            {--backtest= : Process one completed backtest by ID}
                            {--refresh : Recalculate previously saved allocation after historical data changes}';

    protected $description = 'Save daily market cap allocation for completed backtests without rerunning them';

    /**
     * Execute the console command.
     */
    public function handle(StoreMarketCapAllocationAction $storeAllocation): int
    {
        $backtestId = $this->option('backtest');
        $refresh = (bool) $this->option('refresh');
        $query = Backtest::query()->where('status', BacktestStatusEnum::Completed);

        if ($backtestId !== null) {
            if (filter_var($backtestId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                $this->error('The backtest ID must be a positive integer.');

                return self::FAILURE;
            }

            $query->whereKey($backtestId);

            if (! $query->exists()) {
                $this->error('The completed backtest was not found.');

                return self::FAILURE;
            }
        }

        $query->whereHas('dailySnapshots', fn (Builder $snapshots): Builder => $snapshots
            ->when(! $refresh, fn (Builder $pending): Builder => $pending->whereNull('market_cap_allocation_calculated_at')));
        $processed = 0;

        foreach ($query->lazyById(50) as $backtest) {
            $updated = $storeAllocation->execute($backtest, $refresh);
            $this->info("Backtest {$backtest->id}: saved allocation status for {$updated} daily records.");
            $processed++;
        }

        $this->info("Processed {$processed} completed backtests.");

        return self::SUCCESS;
    }
}
