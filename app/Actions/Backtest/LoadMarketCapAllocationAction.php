<?php

namespace App\Actions\Backtest;

use App\Models\Backtest;

class LoadMarketCapAllocationAction
{
    /**
     * @return array{start_date: ?string, excluded_days: int, points: list<array{date: string, large_cap: float, mid_cap: float, small_cap: float, etf: float, unclassified: float, cash: float}>}
     */
    public function execute(Backtest $backtest): array
    {
        $result = ['start_date' => null, 'excluded_days' => 0, 'points' => []];
        $snapshots = $backtest->dailySnapshots()->orderBy('date')
            ->toBase()->get(['date', 'market_cap_allocation']);

        foreach ($snapshots as $snapshot) {
            if ($snapshot->market_cap_allocation === null) {
                $result['excluded_days']++;

                continue;
            }

            $allocation = json_decode($snapshot->market_cap_allocation, true, flags: JSON_THROW_ON_ERROR);
            $result['start_date'] ??= $snapshot->date;
            $result['points'][] = [
                'date' => $snapshot->date,
                'large_cap' => (float) $allocation['large_cap'],
                'mid_cap' => (float) $allocation['mid_cap'],
                'small_cap' => (float) $allocation['small_cap'],
                'etf' => (float) $allocation['etf'],
                'unclassified' => (float) ($allocation['unclassified'] ?? 0),
                'cash' => (float) $allocation['cash'],
            ];
        }

        return $result;
    }
}
