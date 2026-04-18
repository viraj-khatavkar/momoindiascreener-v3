<?php

namespace App\Actions\Backtest;

use App\Models\Backtest;
use App\Models\NseIndex;
use Illuminate\Support\Collection;

class LoadBenchmarkSeriesAction
{
    /**
     * @return Collection<int, array{date: string, nav: float}>
     */
    public function execute(Backtest $backtest, string $slug): Collection
    {
        $firstSnapshot = $backtest->dailySnapshots()->orderBy('date')->first();
        $lastSnapshot = $backtest->dailySnapshots()->orderBy('date', 'desc')->first();

        if (! $firstSnapshot || ! $lastSnapshot) {
            return collect();
        }

        $indexData = NseIndex::query()
            ->where('slug', $slug)
            ->whereBetween('date', [
                $firstSnapshot->date->format('Y-m-d'),
                $lastSnapshot->date->format('Y-m-d'),
            ])
            ->orderBy('date')
            ->select('date', 'close')
            ->distinct()
            ->get();

        if ($indexData->isEmpty()) {
            return collect();
        }

        $startClose = (float) $indexData->first()->close;

        if ($startClose <= 0) {
            return collect();
        }

        return $indexData->map(fn ($row) => [
            'date' => $row->date->format('Y-m-d'),
            'nav' => round(100.0 * ((float) $row->close / $startClose), 6),
        ]);
    }
}
