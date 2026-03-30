<?php

namespace App\Actions\Backtest;

use App\Models\Backtest;
use App\Models\BacktestNseInstrumentPrice;
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

        // Per-stock performance
        $stockPerformance = $this->calculateStockPerformance($backtest, $lastSnapshot->date);

        // Advanced metrics
        $sharpeRatio = $this->calculateSharpeRatio($snapshots, (float) $backtest->cash_return_rate);
        $winnersPercentage = $this->calculateWinnersPercentage($stockPerformance);
        $ulcerIndex = $this->calculateUlcerIndex($snapshots);
        $kRatio = $this->calculateKRatio($snapshots);
        $profitFactor = $this->calculateProfitFactor($stockPerformance);

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

    private function calculateStockPerformance(Backtest $backtest, $lastDate): array
    {
        $trades = BacktestTrade::where('backtest_id', $backtest->id)
            ->orderBy('date')
            ->get();

        $stocks = [];

        foreach ($trades as $trade) {
            $symbol = $trade->symbol;

            if (! isset($stocks[$symbol])) {
                $stocks[$symbol] = [
                    'symbol' => $symbol,
                    'name' => $trade->name ?? $symbol,
                    'buy_qty' => 0,
                    'sell_qty' => 0,
                    'buy_value' => 0,
                    'sell_value' => 0,
                    'charges' => 0,
                ];
            }

            $stocks[$symbol]['charges'] += (float) $trade->total_charges;

            if ($trade->trade_type === 'buy') {
                $stocks[$symbol]['buy_qty'] += $trade->quantity;
                $stocks[$symbol]['buy_value'] += (float) $trade->gross_amount;
            } else {
                $stocks[$symbol]['sell_qty'] += $trade->quantity;
                $stocks[$symbol]['sell_value'] += (float) $trade->gross_amount;
            }
        }

        // For stocks still held at end, get their last closing price for unrealized P&L
        $heldSymbols = [];
        foreach ($stocks as $symbol => $data) {
            $remaining = $data['buy_qty'] - $data['sell_qty'];
            if ($remaining > 0) {
                $heldSymbols[] = $symbol;
            }
        }

        $lastPrices = [];
        if (! empty($heldSymbols)) {
            $lastPrices = BacktestNseInstrumentPrice::query()
                ->where('date', $lastDate->format('Y-m-d'))
                ->whereIn('symbol', $heldSymbols)
                ->pluck('close_adjusted', 'symbol')
                ->map(fn ($p) => (float) $p)
                ->toArray();
        }

        // Calculate P&L for each stock
        $performance = [];
        foreach ($stocks as $symbol => $data) {
            $remainingQty = $data['buy_qty'] - $data['sell_qty'];
            $unrealizedValue = $remainingQty > 0 ? $remainingQty * ($lastPrices[$symbol] ?? 0) : 0;

            $totalPnl = $data['sell_value'] + $unrealizedValue - $data['buy_value'];
            $netPnl = $totalPnl - $data['charges'];
            $pnlPct = $data['buy_value'] > 0 ? ($netPnl / $data['buy_value']) * 100 : 0;

            $performance[] = [
                'symbol' => $symbol,
                'name' => $data['name'],
                'buy_value' => round($data['buy_value'], 2),
                'sell_value' => round($data['sell_value'], 2),
                'unrealized_value' => round($unrealizedValue, 2),
                'charges' => round($data['charges'], 2),
                'net_pnl' => round($netPnl, 2),
                'pnl_pct' => round($pnlPct, 2),
                'still_held' => $remainingQty > 0,
            ];
        }

        // Sort by net_pnl descending
        usort($performance, fn ($a, $b) => $b['net_pnl'] <=> $a['net_pnl']);

        return $performance;
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
     * Winners % = stocks with positive P&L / total stocks traded
     */
    private function calculateWinnersPercentage(array $stockPerformance): ?float
    {
        if (empty($stockPerformance)) {
            return null;
        }

        $winners = count(array_filter($stockPerformance, fn ($s) => $s['net_pnl'] > 0));

        return round(($winners / count($stockPerformance)) * 100, 2);
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

    /**
     * Profit Factor = gross profits / gross losses
     * Sum of winning stocks' P&L / abs(sum of losing stocks' P&L)
     */
    private function calculateProfitFactor(array $stockPerformance): ?float
    {
        if (empty($stockPerformance)) {
            return null;
        }

        $grossProfits = 0;
        $grossLosses = 0;

        foreach ($stockPerformance as $stock) {
            if ($stock['net_pnl'] > 0) {
                $grossProfits += $stock['net_pnl'];
            } else {
                $grossLosses += abs($stock['net_pnl']);
            }
        }

        if ($grossLosses == 0) {
            return $grossProfits > 0 ? 999.99 : null;
        }

        return round($grossProfits / $grossLosses, 4);
    }
}
