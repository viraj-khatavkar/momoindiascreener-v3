<?php

namespace App\Actions\Backtest;

use App\Models\Backtest;
use App\Models\BacktestSummaryMetric;
use App\Models\BacktestTrade;

class CalculateBacktestMetricsAction
{
    public function __construct(private CalculateBacktestPositionPerformanceAction $positionPerformance) {}

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

        // Rolling Returns (by calendar years, not fixed trading days)
        $rollingReturnsOneYear = $this->calculateRollingReturns($snapshots, 1);
        $rollingReturnsThreeYear = $this->calculateRollingReturns($snapshots, 3);
        $rollingReturnsFiveYear = $this->calculateRollingReturns($snapshots, 5);

        // Trade stats
        $totalTrades = BacktestTrade::where('backtest_id', $backtest->id)->count();
        $totalCharges = BacktestTrade::where('backtest_id', $backtest->id)->sum('total_charges');

        // Per-stock performance
        $stockPerformance = $this->positionPerformance->execute($backtest, $lastSnapshot->date);

        // Advanced metrics
        $sharpeRatio = $this->calculateSharpeRatio($snapshots, (float) $backtest->cash_return_rate);
        $winnersPercentage = $stockPerformance['closed']['winners_percentage'];
        $ulcerIndex = $this->calculateUlcerIndex($snapshots);
        $kRatio = $this->calculateKRatio($snapshots);
        $profitFactor = $stockPerformance['closed']['profit_factor'];

        BacktestSummaryMetric::create([
            'backtest_id' => $backtest->id,
            'cagr' => round($cagr, 4),
            'max_drawdown' => round($maxDrawdown, 4),
            'max_drawdown_start_date' => $ddStartDate,
            'max_drawdown_end_date' => $ddEndDate,
            'sharpe_ratio' => $sharpeRatio,
            'winners_percentage' => $winnersPercentage,
            'ulcer_index' => $ulcerIndex,
            'k_ratio' => $kRatio,
            'profit_factor' => $profitFactor,
            'total_trades' => $totalTrades,
            'total_charges_paid' => round($totalCharges, 2),
            'final_value' => round((float) $lastSnapshot->total_value, 2),
            'start_date' => $firstSnapshot->date->format('Y-m-d'),
            'end_date' => $lastSnapshot->date->format('Y-m-d'),
            'rolling_returns_one_year' => $rollingReturnsOneYear,
            'rolling_returns_three_year' => $rollingReturnsThreeYear,
            'rolling_returns_five_year' => $rollingReturnsFiveYear,
            'stock_performance' => $stockPerformance,
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

    private function calculateRollingReturns($snapshots, int $calendarYears): array
    {
        $returns = [];
        $count = $snapshots->count();

        if ($count < 10) {
            return $returns;
        }

        // Build a date-indexed lookup for finding the snapshot closest to N years ago
        $dateIndex = [];
        foreach ($snapshots as $i => $snapshot) {
            $dateIndex[$snapshot->date->format('Y-m-d')] = $i;
        }

        for ($i = 0; $i < $count; $i++) {
            $currentDate = $snapshots[$i]->date;
            $pastDate = $currentDate->copy()->subYears($calendarYears);

            // Find the closest trading day on or after the target past date
            $pastIdx = null;
            for ($attempt = 0; $attempt < 10; $attempt++) {
                $checkDate = $pastDate->copy()->addDays($attempt)->format('Y-m-d');
                if (isset($dateIndex[$checkDate])) {
                    $pastIdx = $dateIndex[$checkDate];
                    break;
                }
            }

            if ($pastIdx === null) {
                continue;
            }

            $currentNav = (float) $snapshots[$i]->nav;
            $pastNav = (float) $snapshots[$pastIdx]->nav;

            if ($pastNav > 0) {
                $actualYears = $snapshots[$pastIdx]->date->diffInDays($currentDate) / 365.25;
                if ($actualYears > 0) {
                    $annualizedReturn = pow($currentNav / $pastNav, 1.0 / $actualYears) - 1;
                    $returns[] = [
                        'date' => $currentDate->format('Y-m-d'),
                        'return' => round($annualizedReturn, 4),
                    ];
                }
            }
        }

        return $returns;
    }

    /**
     * Sharpe Ratio = (annualized return - risk free rate) / annualized volatility of daily returns
     */
    private function calculateSharpeRatio($snapshots, float $riskFreeRate): ?float
    {
        if ($snapshots->count() < 3) {
            return null;
        }

        $dailyReturns = [];
        for ($i = 1; $i < $snapshots->count(); $i++) {
            $prev = (float) $snapshots[$i - 1]->nav;
            $curr = (float) $snapshots[$i]->nav;
            if ($prev > 0) {
                $dailyReturns[] = ($curr - $prev) / $prev;
            }
        }

        if (count($dailyReturns) < 2) {
            return null;
        }

        $meanReturn = array_sum($dailyReturns) / count($dailyReturns);
        $variance = array_sum(array_map(fn ($r) => ($r - $meanReturn) ** 2, $dailyReturns)) / (count($dailyReturns) - 1);
        $dailyStdDev = sqrt($variance);

        $annualizedReturn = $meanReturn * 252;
        $annualizedVol = $dailyStdDev * sqrt(252);

        if ($annualizedVol == 0) {
            return null;
        }

        return round(($annualizedReturn - $riskFreeRate / 100) / $annualizedVol, 4);
    }

    /**
     * Ulcer Index = sqrt(mean(drawdown^2))
     * Measures downside risk — lower is better
     */
    private function calculateUlcerIndex($snapshots): ?float
    {
        if ($snapshots->count() < 2) {
            return null;
        }

        $peak = 0;
        $squaredDrawdowns = [];

        foreach ($snapshots as $snapshot) {
            $nav = (float) $snapshot->nav;
            if ($nav > $peak) {
                $peak = $nav;
            }
            if ($peak > 0) {
                $drawdownPct = (($nav - $peak) / $peak) * 100;
                $squaredDrawdowns[] = $drawdownPct ** 2;
            }
        }

        if (empty($squaredDrawdowns)) {
            return null;
        }

        return round(sqrt(array_sum($squaredDrawdowns) / count($squaredDrawdowns)), 4);
    }

    /**
     * K-Ratio = slope of log(NAV) regression / standard error of slope
     * Measures return consistency — higher is better
     */
    private function calculateKRatio($snapshots): ?float
    {
        $n = $snapshots->count();
        if ($n < 3) {
            return null;
        }

        $logNavs = [];
        foreach ($snapshots as $snapshot) {
            $nav = (float) $snapshot->nav;
            if ($nav > 0) {
                $logNavs[] = log($nav);
            }
        }

        $n = count($logNavs);
        if ($n < 3) {
            return null;
        }

        // Simple linear regression: y = a + b*x where x=0,1,2,...,n-1 and y=log(nav)
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumX += $i;
            $sumY += $logNavs[$i];
            $sumXY += $i * $logNavs[$i];
            $sumX2 += $i * $i;
        }

        $denominator = $n * $sumX2 - $sumX * $sumX;
        if ($denominator == 0) {
            return null;
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / $denominator;
        $intercept = ($sumY - $slope * $sumX) / $n;

        // Standard error of slope
        $ssResidual = 0;
        for ($i = 0; $i < $n; $i++) {
            $predicted = $intercept + $slope * $i;
            $ssResidual += ($logNavs[$i] - $predicted) ** 2;
        }

        $mse = $ssResidual / ($n - 2);
        $sxDeviation = $sumX2 - ($sumX * $sumX) / $n;

        if ($sxDeviation <= 0 || $mse < 0) {
            return null;
        }

        $stdErrorSlope = sqrt($mse / $sxDeviation);

        if ($stdErrorSlope == 0) {
            return null;
        }

        return round($slope / $stdErrorSlope, 4);
    }
}
