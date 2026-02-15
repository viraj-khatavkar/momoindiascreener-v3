<?php

namespace App\Actions;

use Illuminate\Support\Collection;

class CalculateRsiAction
{
    public function execute(Collection $prices, int $period = 14, $field = 'close'): ?float
    {
        // Need at least period+1 closes to form period changes
        if ($prices->count() < $period + 1) {
            return null;
        }

        $changes = $this->calculatePriceChanges($prices, $field);

        if ($changes->count() < $period) {
            return null;
        }

        // Seed averages over the first `period` changes
        $seed = $changes->take($period);
        $sumGain = 0.0;
        $sumLoss = 0.0;
        foreach ($seed as $ch) {
            if ($ch > 0) {
                $sumGain += $ch;
            } elseif ($ch < 0) {
                $sumLoss += -$ch;
            }
        }
        $avgGain = $sumGain / $period;
        $avgLoss = $sumLoss / $period;

        // Wilder smoothing for the rest
        for ($i = $period; $i < $changes->count(); $i++) {
            $ch = (float) $changes[$i];
            $gain = $ch > 0 ? $ch : 0.0;
            $loss = $ch < 0 ? -$ch : 0.0;

            $avgGain = (($avgGain * ($period - 1)) + $gain) / $period;
            $avgLoss = (($avgLoss * ($period - 1)) + $loss) / $period;
        }

        // Handle edge cases
        $eps = 1e-12;
        if ($avgLoss < $eps && $avgGain < $eps) {
            return 50.0; // perfectly flat -> neutral RSI
        }
        if ($avgLoss < $eps) {
            return 100.0; // gains with ~no losses
        }
        if ($avgGain < $eps) {
            return 0.0; // losses with ~no gains
        }

        $rs = $avgGain / $avgLoss;
        $rsi = 100.0 - (100.0 / (1.0 + $rs));

        return round($rsi, 4);
    }

    protected function calculatePriceChanges(Collection $prices, $field): Collection
    {
        $changes = collect();
        for ($i = 1; $i < $prices->count(); $i++) {
            $curr = (float) $prices[$i]->{$field};
            $prev = (float) $prices[$i - 1]->{$field};
            $changes->push($curr - $prev);
        }

        return $changes;
    }
}
