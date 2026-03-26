<?php

namespace App\Actions\Backtest;

use App\Models\Backtest;
use App\Models\BacktestSummaryMetric;
use App\Models\BacktestTrade;

class CalculateBacktestMetricsAction
{
    public function execute(Backtest $backtest): void
    {
        $snapshots = $backtest->dailySnapshots()
            ->orderBy('date')
            ->get(['date', 'nav', 'total_value']);

        if ($snapshots->count() < 2) {
            return;
        }

        $firstSnapshot = $snapshots->first();
        $lastSnapshot = $snapshots->last();

        $years = $firstSnapshot->date->diffInDays($lastSnapshot->date) / 365.25;

        if ($years <= 0) {
            return;
        }

        // CAGR
        $finalNav = (float) $lastSnapshot->nav;
        $cagr = pow($finalNav / 100.0, 1.0 / $years) - 1;

        // Max Drawdown
        [$maxDrawdown, $ddStartDate, $ddEndDate] = $this->calculateMaxDrawdown($snapshots);

        // Rolling Returns
        $rollingReturnsOneYear = $this->calculateRollingReturns($snapshots, 252);
        $rollingReturnsThreeYear = $this->calculateRollingReturns($snapshots, 756);
        $rollingReturnsFiveYear = $this->calculateRollingReturns($snapshots, 1260);

        // Trade stats
        $totalTrades = BacktestTrade::where('backtest_id', $backtest->id)->count();
        $totalCharges = BacktestTrade::where('backtest_id', $backtest->id)->sum('total_charges');

        BacktestSummaryMetric::create([
            'backtest_id' => $backtest->id,
            'cagr' => round($cagr, 4),
            'max_drawdown' => round($maxDrawdown, 4),
            'max_drawdown_start_date' => $ddStartDate,
            'max_drawdown_end_date' => $ddEndDate,
            'total_trades' => $totalTrades,
            'total_charges_paid' => round($totalCharges, 2),
            'final_value' => round((float) $lastSnapshot->total_value, 2),
            'rolling_returns_one_year' => $rollingReturnsOneYear,
            'rolling_returns_three_year' => $rollingReturnsThreeYear,
            'rolling_returns_five_year' => $rollingReturnsFiveYear,
        ]);
    }

    /**
     * @return array{0: float, 1: string|null, 2: string|null}
     */
    private function calculateMaxDrawdown($snapshots): array
    {
        $peak = 0;
        $maxDd = 0;
        $ddStart = null;
        $ddEnd = null;
        $currentPeakDate = null;

        foreach ($snapshots as $snapshot) {
            $nav = (float) $snapshot->nav;

            if ($nav > $peak) {
                $peak = $nav;
                $currentPeakDate = $snapshot->date->format('Y-m-d');
            }

            if ($peak > 0) {
                $drawdown = ($nav - $peak) / $peak;
                if ($drawdown < $maxDd) {
                    $maxDd = $drawdown;
                    $ddStart = $currentPeakDate;
                    $ddEnd = $snapshot->date->format('Y-m-d');
                }
            }
        }

        return [$maxDd, $ddStart, $ddEnd];
    }

    private function calculateRollingReturns($snapshots, int $tradingDaysLookback): array
    {
        $returns = [];
        $count = $snapshots->count();

        if ($count <= $tradingDaysLookback) {
            return $returns;
        }

        $years = $tradingDaysLookback / 252.0;

        for ($i = $tradingDaysLookback; $i < $count; $i++) {
            $currentNav = (float) $snapshots[$i]->nav;
            $pastNav = (float) $snapshots[$i - $tradingDaysLookback]->nav;

            if ($pastNav > 0) {
                $annualizedReturn = pow($currentNav / $pastNav, 1.0 / $years) - 1;
                $returns[] = [
                    'date' => $snapshots[$i]->date->format('Y-m-d'),
                    'return' => round($annualizedReturn, 4),
                ];
            }
        }

        return $returns;
    }
}
