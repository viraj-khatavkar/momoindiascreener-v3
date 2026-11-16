<?php

namespace App\Actions;

use App\Models\Backtest;
use App\Models\BacktestNseInstrumentPrice;
use App\Models\Screen;
use Illuminate\Support\Collection;

class RankInstrumentsAction
{
    /**
     * Rank equal factor values together. Stable score sorting preserves symbol
     * order when combined totals are equal.
     *
     * @param  Collection<int, BacktestNseInstrumentPrice>  $instruments
     * @return Collection<int, BacktestNseInstrumentPrice>
     */
    public function execute(Collection $instruments, Screen|Backtest $settings): Collection
    {
        $factors = [
            'factor_one_rank' => ['column' => $settings->sort_by, 'direction' => $settings->sort_direction],
        ];

        if ($settings->apply_factor_two) {
            $factors['factor_two_rank'] = ['column' => $settings->factor_two_sort_by, 'direction' => $settings->factor_two_sort_direction];
        }

        if ($settings->apply_factor_three) {
            $factors['factor_three_rank'] = ['column' => $settings->factor_three_sort_by, 'direction' => $settings->factor_three_sort_direction];
        }

        foreach ($factors as $rankAttribute => $factor) {
            $this->assignAverageRanks($instruments, $factor['column'], $factor['direction'], $rankAttribute);
        }

        return $instruments
            ->sortBy('symbol', SORT_STRING)
            ->sortBy(function (BacktestNseInstrumentPrice $instrument) use ($factors): int|float {
                $total = 0;

                foreach (array_keys($factors) as $rankAttribute) {
                    $total += $instrument->getAttribute($rankAttribute);
                }

                return $total;
            })
            ->values();
    }

    /**
     * @param  Collection<int, BacktestNseInstrumentPrice>  $instruments
     */
    protected function assignAverageRanks(Collection $instruments, string $column, string $direction, string $rankAttribute): void
    {
        $ordered = $instruments->sortBy($column, SORT_REGULAR, $direction === 'desc')->values()->all();
        $count = count($ordered);
        $start = 0;

        while ($start < $count) {
            $end = $start + 1;
            $value = $ordered[$start]->getAttribute($column);

            while ($end < $count && $ordered[$end]->getAttribute($column) == $value) {
                $end++;
            }

            $averageRank = ($start + 1 + $end) / 2;

            for ($position = $start; $position < $end; $position++) {
                $ordered[$position]->setAttribute($rankAttribute, $averageRank);
            }

            $start = $end;
        }
    }
}
