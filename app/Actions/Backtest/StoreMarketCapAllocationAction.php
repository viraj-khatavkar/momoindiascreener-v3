<?php

namespace App\Actions\Backtest;

use App\Models\Backtest;
use App\Models\BacktestDailySnapshot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class StoreMarketCapAllocationAction
{
    public function __construct(private CalculateMarketCapAllocationAction $calculateAllocation) {}

    public function execute(Backtest $backtest, bool $refresh = false): int
    {
        $snapshots = $backtest->dailySnapshots()
            ->when(! $refresh, fn (Builder $query): Builder => $query->whereNull('market_cap_allocation_calculated_at'))
            ->orderBy('id')->toBase()->get(['id', 'date']);

        if ($snapshots->isEmpty()) {
            return 0;
        }

        $points = collect($this->calculateAllocation->execute($backtest)['points'])->keyBy('date');
        $calculatedAt = now();
        $table = DB::connection()->getQueryGrammar()->wrapTable((new BacktestDailySnapshot)->getTable());

        return DB::transaction(function () use ($backtest, $refresh, $snapshots, $points, $calculatedAt, $table): int {
            $updated = 0;

            foreach ($snapshots->chunk(500) as $chunk) {
                $cases = [];
                $bindings = [];

                foreach ($chunk as $snapshot) {
                    $point = $points->get($snapshot->date);
                    $cases[] = 'WHEN ? THEN ?';
                    $bindings[] = $snapshot->id;
                    $bindings[] = $point === null ? null : json_encode(Arr::except($point, 'date'), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
                }

                $ids = $chunk->pluck('id')->all();
                $placeholders = implode(', ', array_fill(0, count($ids), '?'));
                $pendingOnly = $refresh ? '' : ' AND market_cap_allocation_calculated_at IS NULL';

                /** Update captured IDs only, so a concurrent rerun cannot receive allocation from the previous run. */
                $updated += DB::update(
                    "UPDATE {$table} SET market_cap_allocation = CASE id ".implode(' ', $cases)." END, market_cap_allocation_calculated_at = ? WHERE backtest_id = ? AND id IN ({$placeholders}){$pendingOnly}",
                    [...$bindings, $calculatedAt, $backtest->getKey(), ...$ids],
                );
            }

            return $updated;
        });
    }
}
