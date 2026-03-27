<?php

namespace App\Actions\Backtest;

use App\Enums\BacktestCashCallEnum;
use App\Enums\BacktestWeightageEnum;
use App\Models\Backtest;
use App\Models\BacktestDailySnapshot;
use App\Models\BacktestNseInstrumentPrice;
use App\Models\BacktestTrade;
use App\Models\NseIndex;
use Carbon\Carbon;

class RunBacktestAction
{
    private const START_DATE = '2011-01-05';

    private const BUY_COST_RATE = 0.001187;

    private array $holdings = [];

    private float $cash;

    private float $navBase = 100.0;

    private float $initialCapital;

    private array $indexData = [];

    private array $dmaData = [];

    private array $rebalanceDates = [];

    private array $snapshotBatch = [];

    private array $tradeBatch = [];

    private float $dailyCashReturnRate = 0;

    private int $dmaPeriod = 50;

    public function __construct(
        private ApplyBacktestFiltersAction $filtersAction,
        private CalculateTransactionCostsAction $costsAction,
    ) {}

    public function execute(Backtest $backtest): void
    {
        $this->initialCapital = (float) $backtest->initial_capital;
        $this->cash = $this->initialCapital;
        $this->holdings = [];
        $this->indexData = [];
        $this->dmaData = [];
        $this->rebalanceDates = [];
        $this->snapshotBatch = [];
        $this->tradeBatch = [];

        $annualRate = (float) $backtest->cash_return_rate;
        $this->dailyCashReturnRate = pow(1 + $annualRate / 100, 1.0 / 252) - 1;

        $tradingDates = $this->loadTradingDates($backtest);

        if ($tradingDates->isEmpty()) {
            return;
        }

        $this->dmaPeriod = (int) $backtest->cash_call_dma_period;
        $this->loadIndexData($backtest->cash_call_index);
        $this->computeDma($this->dmaPeriod);
        $this->computeRebalanceDates($backtest, $tradingDates);

        // When execute_next_trading_day is enabled, shift execution to next trading day
        // rebalanceDates stores: executionDate => decisionDate (filterDate)
        if ($backtest->execute_next_trading_day) {
            $this->rebalanceDates = $this->shiftRebalanceDatesToNextDay($tradingDates);
        }

        $totalDays = $tradingDates->count();
        $dayIndex = 0;

        foreach ($tradingDates as $date) {
            $dateStr = $date->format('Y-m-d');
            $dayIndex++;

            // Apply daily interest on cash balance
            if ($this->cash > 0 && $this->dailyCashReturnRate > 0) {
                $this->cash += $this->cash * $this->dailyCashReturnRate;
            }

            // Step A: Mark-to-market FIRST (loads today's prices for held stocks)
            $this->markToMarket($date);

            // Step B: Rebalance (if applicable)
            // rebalanceDates[executionDate] = decisionDate (filter date)
            if (isset($this->rebalanceDates[$dateStr])) {
                $filterDate = $this->rebalanceDates[$dateStr];
                $this->rebalance($backtest, $date, $filterDate);
            }

            $portfolioValue = $this->calculatePortfolioValue();
            $totalValue = $portfolioValue + $this->cash;
            $nav = $this->navBase * ($totalValue / $this->initialCapital);

            // Step C: Save snapshot
            $this->snapshotBatch[] = [
                'backtest_id' => $backtest->id,
                'date' => $dateStr,
                'nav' => $nav,
                'portfolio_value' => round($portfolioValue, 2),
                'cash' => round($this->cash, 2),
                'total_value' => round($totalValue, 2),
                'holdings_count' => count($this->holdings),
            ];

            if (count($this->snapshotBatch) >= 100) {
                $this->flushSnapshots();
            }

            // Step D: Update progress
            if ($dayIndex % 50 === 0) {
                $backtest->update(['progress' => (int) (($dayIndex / $totalDays) * 95)]);
            }
        }

        $this->flushSnapshots();
        $this->flushTrades();
    }

    private function loadTradingDates(Backtest $backtest)
    {
        return BacktestNseInstrumentPrice::query()
            ->where($backtest->index->isIndexFieldName(), true)
            ->where('date', '>=', self::START_DATE)
            ->select('date')
            ->distinct()
            ->orderBy('date')
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d));
    }

    private function loadIndexData(string $slug): void
    {
        // Load enough history before START_DATE for the DMA period to be computed
        // 200 DMA needs ~280 calendar days of prior data; use generous buffer
        $loadFrom = Carbon::parse(self::START_DATE)->subDays($this->dmaPeriod * 2)->format('Y-m-d');

        NseIndex::query()
            ->where('slug', $slug)
            ->where('date', '>=', $loadFrom)
            ->orderBy('date')
            ->get(['date', 'close'])
            ->each(function ($row) {
                $this->indexData[$row->date->format('Y-m-d')] = (float) $row->close;
            });
    }

    private function computeDma(int $period): void
    {
        $closes = [];

        foreach ($this->indexData as $date => $close) {
            $closes[] = $close;
            $count = count($closes);

            if ($count >= $period) {
                $this->dmaData[$date] = array_sum(array_slice($closes, -$period)) / $period;
            }
        }
    }

    private function computeRebalanceDates(Backtest $backtest, $tradingDates): void
    {
        $this->rebalanceDates = [];

        // First trading day is always initial rebalance
        $firstDate = $tradingDates->first()->format('Y-m-d');
        $this->rebalanceDates[$firstDate] = $firstDate;

        if ($backtest->rebalance_frequency->value === 'weekly') {
            $grouped = $tradingDates->groupBy(fn (Carbon $d) => $d->isoWeekYear().'-W'.$d->isoWeek());

            foreach ($grouped as $dates) {
                $matched = null;
                foreach ($dates as $date) {
                    if ($date->dayOfWeekIso >= $backtest->rebalance_day) {
                        $matched = $date->format('Y-m-d');
                        break;
                    }
                }

                // Fallback: if chosen day not available this week, use last trading day of week
                if ($matched === null && $dates->isNotEmpty()) {
                    $matched = $dates->last()->format('Y-m-d');
                }

                if ($matched !== null && $matched !== $firstDate) {
                    $this->rebalanceDates[$matched] = $matched;
                }
            }
        } else {
            $grouped = $tradingDates->groupBy(fn (Carbon $d) => $d->format('Y-m'));

            foreach ($grouped as $dates) {
                $matched = null;
                foreach ($dates as $date) {
                    if ($date->day >= $backtest->rebalance_day) {
                        $matched = $date->format('Y-m-d');
                        break;
                    }
                }

                // Fallback: use last trading day of month
                if ($matched === null && $dates->isNotEmpty()) {
                    $matched = $dates->last()->format('Y-m-d');
                }

                if ($matched !== null && $matched !== $firstDate) {
                    $this->rebalanceDates[$matched] = $matched;
                }
            }
        }
    }

    private function shiftRebalanceDatesToNextDay($tradingDates): array
    {
        $tradingDatesList = $tradingDates->map(fn ($d) => $d->format('Y-m-d'))->values()->toArray();
        $dateIndex = array_flip($tradingDatesList);

        $shifted = [];
        foreach ($this->rebalanceDates as $decisionDate => $value) {
            $idx = $dateIndex[$decisionDate] ?? null;

            if ($idx === null || $idx + 1 >= count($tradingDatesList)) {
                continue;
            }

            $executionDate = $tradingDatesList[$idx + 1];
            $shifted[$executionDate] = $decisionDate;
        }

        return $shifted;
    }

    private function rebalance(Backtest $backtest, Carbon $date, ?string $filterDate = null): void
    {
        $filterDate = $filterDate ?? $date->format('Y-m-d');
        $executionDateStr = $date->format('Y-m-d');

        $rankedStocks = $this->filtersAction->execute($backtest, $filterDate);

        // When executing next day, reload prices from execution date for buy candidates
        if ($filterDate !== $executionDateStr) {
            $symbols = $rankedStocks->pluck('symbol')->toArray();
            $executionPrices = BacktestNseInstrumentPrice::query()
                ->where('date', $executionDateStr)
                ->whereIn('symbol', $symbols)
                ->get(['symbol', 'close_adjusted', 'close_raw'])
                ->keyBy('symbol');

            $rankedStocks = $rankedStocks->map(function ($stock) use ($executionPrices) {
                $execData = $executionPrices->get($stock->symbol);
                if ($execData) {
                    $stock->close_adjusted = $execData->close_adjusted;
                    $stock->close_raw = $execData->close_raw;
                }

                return $stock;
            });
        }
        $rankedBySymbol = $rankedStocks->keyBy('symbol');

        // Cash call DMA check uses decision date (not execution date)
        $indexClose = $this->indexData[$filterDate] ?? 0;
        $indexDma = $this->dmaData[$filterDate] ?? null;
        $indexBelowDma = $indexDma !== null && $indexClose < $indexDma;

        // Determine cash call behavior
        $cashCall = $backtest->cash_call;

        if ($cashCall === BacktestCashCallEnum::FullCashBelowIndexDma && $indexBelowDma) {
            $this->sellEverything($backtest, $date, 'Cash call - index below '.$this->dmaPeriod.' DMA');

            return;
        }

        if ($cashCall === BacktestCashCallEnum::AllocateToGoldBelowIndexDma && $indexBelowDma) {
            $this->allocateToGold($backtest, $date);

            return;
        }

        if ($cashCall === BacktestCashCallEnum::OnlyExitsAllocateToGoldBelowIndexDma && $indexBelowDma) {
            $this->onlyExitsAndAllocateToGold($backtest, $date, $rankedBySymbol, $filterDate);

            return;
        }

        // If gold allocation was active but index recovered, sell GOLDBEES first
        $isGoldCashCall = in_array($cashCall, [
            BacktestCashCallEnum::AllocateToGoldBelowIndexDma,
            BacktestCashCallEnum::OnlyExitsAllocateToGoldBelowIndexDma,
        ]);
        if ($isGoldCashCall && isset($this->holdings['GOLDBEES'])) {
            $this->executeSell($backtest, $date, 'GOLDBEES', $this->holdings['GOLDBEES']['quantity'], 'Index recovered above '.$this->dmaPeriod.' DMA - exiting gold');
        }

        $onlyExits = $cashCall === BacktestCashCallEnum::OnlyExitsBelowIndexDma && $indexBelowDma;

        // Determine sells (exclude GOLDBEES from normal rank checks)
        $symbolsToSell = [];
        $excludedSymbols = [];
        foreach ($this->holdings as $symbol => $holding) {
            if ($symbol === 'GOLDBEES') {
                continue;
            }
            if (! $rankedBySymbol->has($symbol)) {
                $excludedSymbols[] = $symbol;
            } elseif ($rankedBySymbol->get($symbol)->rank > $backtest->worst_rank_held) {
                $symbolsToSell[$symbol] = 'Rank exceeded threshold ('.$rankedBySymbol->get($symbol)->rank.' > '.$backtest->worst_rank_held.')';
            }
        }

        // Diagnose why excluded stocks failed filters (use decision date, not execution date)
        if (! empty($excludedSymbols)) {
            $diagnosisDate = Carbon::parse($filterDate);
            $excludedReasons = $this->diagnoseExclusions($backtest, $diagnosisDate, $excludedSymbols);
            foreach ($excludedReasons as $symbol => $reason) {
                $symbolsToSell[$symbol] = $reason;
            }
        }

        // Hold Above DMA override: protect stocks that are still above their own DMA
        if ($backtest->apply_hold_above_dma && ! empty($symbolsToSell)) {
            $symbolsToSell = $this->applyHoldAboveDmaOverride($backtest, $date, $symbolsToSell);
        }

        // For equal_weight_rebalanced and inverse_volatility, determine trims
        $weightage = $backtest->weightage;
        $needsRebalancing = in_array($weightage, [
            BacktestWeightageEnum::EqualWeightRebalanced,
            BacktestWeightageEnum::InverseVolatility,
        ]);

        // Execute full sells first
        foreach ($symbolsToSell as $symbol => $reason) {
            $this->executeSell($backtest, $date, $symbol, $this->holdings[$symbol]['quantity'], $reason);
        }

        if ($onlyExits) {
            return;
        }

        // Determine buy candidates
        $remainingSlots = $backtest->max_stocks_to_hold - count($this->holdings);
        $buyCandidates = $rankedStocks
            ->filter(fn ($stock) => ! isset($this->holdings[$stock->symbol]))
            ->take(max($remainingSlots, 0));

        if ($needsRebalancing) {
            $this->rebalanceWeights($backtest, $date, $rankedBySymbol, $buyCandidates);
        } else {
            $this->equalWeightBuy($backtest, $date, $buyCandidates);
        }
    }

    private function equalWeightBuy(Backtest $backtest, Carbon $date, $buyCandidates): void
    {
        if ($buyCandidates->isEmpty() || $this->cash <= 0) {
            return;
        }

        $cashCall = $backtest->cash_call;
        $remainingSlots = $backtest->max_stocks_to_hold - count($this->holdings);

        if ($cashCall === BacktestCashCallEnum::CashCallIfNotEnoughStocks) {
            $perStockBudget = $this->cash / max($remainingSlots, 1);
        } else {
            $perStockBudget = $this->cash / max($buyCandidates->count(), 1);
        }

        foreach ($buyCandidates as $stock) {
            $this->executeBuy($backtest, $date, $stock, $perStockBudget, 'New entry');
        }
    }

    private function rebalanceWeights(Backtest $backtest, Carbon $date, $rankedBySymbol, $buyCandidates): void
    {
        $portfolioValue = $this->calculatePortfolioValue();
        $totalValue = $portfolioValue + $this->cash;

        $allTargetStocks = collect();

        // Add kept holdings
        foreach ($this->holdings as $symbol => $holding) {
            $stockData = $rankedBySymbol->get($symbol);
            if ($stockData) {
                $allTargetStocks->put($symbol, $stockData);
            }
        }

        // Add new buy candidates
        foreach ($buyCandidates as $stock) {
            $allTargetStocks->put($stock->symbol, $stock);
        }

        if ($allTargetStocks->isEmpty()) {
            return;
        }

        // Calculate target per stock
        $targets = $this->calculateTargetAllocations($backtest, $totalValue, $allTargetStocks);

        // Phase 0: For inverse volatility, sell held stocks with no volatility data
        if ($backtest->weightage === BacktestWeightageEnum::InverseVolatility) {
            foreach ($this->holdings as $symbol => $holding) {
                if ($symbol === 'GOLDBEES') {
                    continue;
                }
                if (! isset($targets[$symbol])) {
                    $this->executeSell($backtest, $date, $symbol, $holding['quantity'], 'No volatility data for inverse volatility weighting');
                }
            }

            // Recalculate total value after selling null-vol stocks
            $portfolioValue = $this->calculatePortfolioValue();
            $totalValue = $portfolioValue + $this->cash;
            $targets = $this->calculateTargetAllocations($backtest, $totalValue, $allTargetStocks);
        }

        // Phase 1: Execute trims (sells of overweight positions)
        foreach ($this->holdings as $symbol => $holding) {
            if (! isset($targets[$symbol])) {
                continue;
            }

            $currentValue = $holding['quantity'] * $holding['last_known_price'];
            $targetValue = $targets[$symbol];

            if ($currentValue > $targetValue && $holding['last_known_price'] > 0) {
                $excessQty = (int) floor(($currentValue - $targetValue) / $holding['last_known_price']);
                if ($excessQty > 0) {
                    $this->executeSell($backtest, $date, $symbol, $excessQty, 'Weight rebalance adjustment');
                }
            }
        }

        // Phase 2: Calculate total buy budget and scale down if needed
        $buyOrders = [];
        $totalBuyBudget = 0;

        foreach ($allTargetStocks as $symbol => $stock) {
            if (! isset($targets[$symbol])) {
                continue;
            }

            if (isset($this->holdings[$symbol])) {
                $currentValue = $this->holdings[$symbol]['quantity'] * $this->holdings[$symbol]['last_known_price'];
                $targetValue = $targets[$symbol];
                if ($currentValue < $targetValue) {
                    $budget = $targetValue - $currentValue;
                    $buyOrders[$symbol] = ['stock' => $stock, 'budget' => $budget, 'reason' => 'Weight rebalance adjustment'];
                    $totalBuyBudget += $budget;
                }
            } else {
                $budget = $targets[$symbol];
                $buyOrders[$symbol] = ['stock' => $stock, 'budget' => $budget, 'reason' => 'New entry'];
                $totalBuyBudget += $budget;
            }
        }

        // Scale down if not enough cash
        $scaleFactor = 1.0;
        $estimatedCosts = $totalBuyBudget * self::BUY_COST_RATE;
        if (($totalBuyBudget + $estimatedCosts) > $this->cash && $totalBuyBudget > 0) {
            $scaleFactor = $this->cash / ($totalBuyBudget + $estimatedCosts);
        }

        // Execute buys (in rank order)
        $sortedOrders = collect($buyOrders)->sortBy(fn ($order) => $order['stock']->rank);
        foreach ($sortedOrders as $symbol => $order) {
            $budget = $order['budget'] * $scaleFactor;
            $this->executeBuy($backtest, $date, $order['stock'], $budget, $order['reason']);
        }
    }

    private function calculateTargetAllocations(Backtest $backtest, float $totalValue, $allTargetStocks): array
    {
        $targets = [];
        $cashCall = $backtest->cash_call;
        $n = $allTargetStocks->count();

        if ($backtest->weightage === BacktestWeightageEnum::InverseVolatility) {
            $validStocks = $allTargetStocks->filter(fn ($stock) => $stock->volatility_one_year > 0);

            if ($validStocks->isEmpty()) {
                return $targets;
            }

            $invVolSum = $validStocks->sum(fn ($stock) => 1.0 / (float) $stock->volatility_one_year);

            foreach ($validStocks as $symbol => $stock) {
                $weight = (1.0 / (float) $stock->volatility_one_year) / $invVolSum;

                if ($cashCall === BacktestCashCallEnum::CashCallIfNotEnoughStocks) {
                    $scale = $validStocks->count() / $backtest->max_stocks_to_hold;
                    $targets[$symbol] = $totalValue * $weight * $scale;
                } else {
                    $targets[$symbol] = $totalValue * $weight;
                }
            }
        } else {
            // Equal weight rebalanced
            if ($cashCall === BacktestCashCallEnum::CashCallIfNotEnoughStocks) {
                $targetPerStock = $totalValue / $backtest->max_stocks_to_hold;
            } else {
                $targetPerStock = $totalValue / max($n, 1);
            }

            foreach ($allTargetStocks as $symbol => $stock) {
                $targets[$symbol] = $targetPerStock;
            }
        }

        return $targets;
    }

    private function diagnoseExclusions(Backtest $backtest, Carbon $date, array $symbols): array
    {
        $dateStr = $date->format('Y-m-d');
        $indexField = $backtest->index->isIndexFieldName();

        $stocks = BacktestNseInstrumentPrice::query()
            ->where('date', $dateStr)
            ->whereIn('symbol', $symbols)
            ->get()
            ->keyBy('symbol');

        $reasons = [];

        foreach ($symbols as $symbol) {
            $stock = $stocks->get($symbol);

            if (! $stock) {
                $reasons[$symbol] = 'No price data available';

                continue;
            }

            if (! $stock->$indexField) {
                $reasons[$symbol] = 'Removed from selected index';

                continue;
            }

            $sortBy = $backtest->sort_by;
            if ($stock->$sortBy === null) {
                $reasons[$symbol] = 'Missing ranking metric data';

                continue;
            }

            $failures = [];

            if ($stock->close_raw < $backtest->price_from || $stock->close_raw > $backtest->price_to) {
                $failures[] = 'Price out of range (₹'.number_format((float) $stock->close_raw, 2).')';
            }

            if ($stock->median_volume_one_year < $backtest->median_volume_one_year) {
                $failures[] = 'Volume below threshold';
            }

            if ($backtest->minimum_return_one_year > -100 && $stock->absolute_return_one_year <= $backtest->minimum_return_one_year) {
                $failures[] = 'Return below minimum ('.$stock->absolute_return_one_year.'%)';
            }

            if ($backtest->apply_ma) {
                if ($backtest->above_ma_200 && $stock->close_raw <= $stock->ma_200) {
                    $failures[] = 'Below 200-day MA';
                }
                if ($backtest->above_ma_100 && $stock->close_raw <= $stock->ma_100) {
                    $failures[] = 'Below 100-day MA';
                }
                if ($backtest->above_ma_50 && $stock->close_raw <= $stock->ma_50) {
                    $failures[] = 'Below 50-day MA';
                }
                if ($backtest->above_ma_20 && $stock->close_raw <= $stock->ma_20) {
                    $failures[] = 'Below 20-day MA';
                }
            }

            if ($backtest->apply_ema) {
                if ($backtest->above_ema_200 && $stock->close_raw <= $stock->ema_200) {
                    $failures[] = 'Below 200-day EMA';
                }
                if ($backtest->above_ema_100 && $stock->close_raw <= $stock->ema_100) {
                    $failures[] = 'Below 100-day EMA';
                }
                if ($backtest->above_ema_50 && $stock->close_raw <= $stock->ema_50) {
                    $failures[] = 'Below 50-day EMA';
                }
                if ($backtest->above_ema_20 && $stock->close_raw <= $stock->ema_20) {
                    $failures[] = 'Below 20-day EMA';
                }
            }

            if ($stock->away_from_high_all_time <= -$backtest->away_from_high_all_time) {
                $failures[] = 'Too far from ATH ('.$stock->away_from_high_all_time.'%)';
            }

            if ($stock->away_from_high_one_year <= -$backtest->away_from_high_one_year) {
                $failures[] = 'Too far from 1Y high ('.$stock->away_from_high_one_year.'%)';
            }

            if ($stock->circuits_one_year > $backtest->circuits_one_year) {
                $failures[] = 'Too many circuits ('.$stock->circuits_one_year.')';
            }

            $series = [];
            if ($backtest->series_eq) {
                $series[] = 'EQ';
            }
            if ($backtest->series_be) {
                $series[] = 'BE';
            }
            if (! empty($series) && ! in_array($stock->series, $series)) {
                $failures[] = 'Series mismatch ('.$stock->series.')';
            }

            if ($backtest->ignore_above_beta < 100 && $stock->beta > $backtest->ignore_above_beta) {
                $failures[] = 'Beta too high ('.$stock->beta.' > '.$backtest->ignore_above_beta.')';
            }

            $reasons[$symbol] = ! empty($failures)
                ? implode('; ', $failures)
                : 'Failed screening criteria';
        }

        return $reasons;
    }

    private function applyHoldAboveDmaOverride(Backtest $backtest, Carbon $date, array $symbolsToSell): array
    {
        $dateStr = $date->format('Y-m-d');
        $dmaColumn = match ($backtest->hold_above_dma_period) {
            20 => 'ma_20',
            50 => 'ma_50',
            100 => 'ma_100',
            default => 'ma_200',
        };

        $symbols = array_keys($symbolsToSell);

        $stockData = BacktestNseInstrumentPrice::query()
            ->where('date', $dateStr)
            ->whereIn('symbol', $symbols)
            ->get(['symbol', 'close_raw', $dmaColumn])
            ->keyBy('symbol');

        foreach ($symbols as $symbol) {
            $data = $stockData->get($symbol);

            if (! $data) {
                continue;
            }

            $closeRaw = (float) $data->close_raw;
            $dmaValue = (float) $data->$dmaColumn;

            if ($dmaValue > 0 && $closeRaw > $dmaValue) {
                unset($symbolsToSell[$symbol]);
            }
        }

        return $symbolsToSell;
    }

    private function sellEverything(Backtest $backtest, Carbon $date, string $reason): void
    {
        $symbols = array_keys($this->holdings);

        foreach ($symbols as $symbol) {
            $this->executeSell($backtest, $date, $symbol, $this->holdings[$symbol]['quantity'], $reason);
        }
    }

    private function allocateToGold(Backtest $backtest, Carbon $date): void
    {
        $dateStr = $date->format('Y-m-d');

        // Sell all non-GOLDBEES holdings
        $symbols = array_keys($this->holdings);
        foreach ($symbols as $symbol) {
            if ($symbol !== 'GOLDBEES') {
                $this->executeSell($backtest, $date, $symbol, $this->holdings[$symbol]['quantity'], 'Index below '.$this->dmaPeriod.' DMA - rotating to gold');
            }
        }

        // Buy GOLDBEES with all available cash (buy more if already holding)
        if ($this->cash <= 0) {
            return;
        }

        $goldData = BacktestNseInstrumentPrice::query()
            ->where('date', $dateStr)
            ->where('symbol', 'GOLDBEES')
            ->first();

        if (! $goldData || (float) $goldData->close_adjusted <= 0) {
            return;
        }

        $this->executeBuy($backtest, $date, $goldData, $this->cash, 'Index below '.$this->dmaPeriod.' DMA - allocating to gold');
    }

    private function onlyExitsAndAllocateToGold(Backtest $backtest, Carbon $date, $rankedBySymbol, string $filterDate): void
    {
        $dateStr = $date->format('Y-m-d');

        // Only sell stocks that exceed worst rank or dropped from universe (not all stocks)
        $symbolsToSell = [];
        $excludedSymbols = [];
        foreach ($this->holdings as $symbol => $holding) {
            if ($symbol === 'GOLDBEES') {
                continue;
            }
            if (! $rankedBySymbol->has($symbol)) {
                $excludedSymbols[] = $symbol;
            } elseif ($rankedBySymbol->get($symbol)->rank > $backtest->worst_rank_held) {
                $symbolsToSell[$symbol] = 'Rank exceeded threshold - rotating to gold';
            }
        }

        if (! empty($excludedSymbols)) {
            $diagnosisDate = Carbon::parse($filterDate);
            $excludedReasons = $this->diagnoseExclusions($backtest, $diagnosisDate, $excludedSymbols);
            foreach ($excludedReasons as $symbol => $reason) {
                $symbolsToSell[$symbol] = $reason.' - rotating to gold';
            }
        }

        // Hold Above DMA override
        if ($backtest->apply_hold_above_dma && ! empty($symbolsToSell)) {
            $symbolsToSell = $this->applyHoldAboveDmaOverride($backtest, $date, $symbolsToSell);
        }

        foreach ($symbolsToSell as $symbol => $reason) {
            $this->executeSell($backtest, $date, $symbol, $this->holdings[$symbol]['quantity'], $reason);
        }

        // Allocate freed-up cash to GOLDBEES (buy more if already holding)
        if ($this->cash <= 0) {
            return;
        }

        $goldData = BacktestNseInstrumentPrice::query()
            ->where('date', $dateStr)
            ->where('symbol', 'GOLDBEES')
            ->first();

        if (! $goldData || (float) $goldData->close_adjusted <= 0) {
            return;
        }

        $this->executeBuy($backtest, $date, $goldData, $this->cash, 'Index below '.$this->dmaPeriod.' DMA - allocating exits to gold');
    }

    private function executeSell(Backtest $backtest, Carbon $date, string $symbol, int $quantity, string $reason): void
    {
        if (! isset($this->holdings[$symbol]) || $quantity <= 0) {
            return;
        }

        $holding = $this->holdings[$symbol];
        $sellPrice = $holding['last_known_price'];
        $grossAmount = $quantity * $sellPrice;
        $costs = $this->costsAction->execute($grossAmount, 'sell');
        $netAmount = $grossAmount - $costs['total_charges'];

        $this->cash += $netAmount;

        $this->tradeBatch[] = [
            'backtest_id' => $backtest->id,
            'symbol' => $symbol,
            'name' => $holding['name'] ?? null,
            'trade_type' => 'sell',
            'reason' => $reason,
            'date' => $date->format('Y-m-d'),
            'quantity' => $quantity,
            'price' => round($sellPrice, 2),
            'raw_price' => round($holding['last_known_raw_price'] ?? 0, 2),
            'gross_amount' => round($grossAmount, 2),
            'stt' => $costs['stt'],
            'transaction_charges' => $costs['transaction_charges'],
            'sebi_charges' => $costs['sebi_charges'],
            'gst' => $costs['gst'],
            'stamp_charges' => $costs['stamp_charges'],
            'total_charges' => $costs['total_charges'],
            'net_amount' => round($netAmount, 2),
        ];

        if ($quantity >= $holding['quantity']) {
            unset($this->holdings[$symbol]);
        } else {
            $this->holdings[$symbol]['quantity'] -= $quantity;
        }

        if (count($this->tradeBatch) >= 100) {
            $this->flushTrades();
        }
    }

    private function executeBuy(Backtest $backtest, Carbon $date, $stock, float $budget, string $reason): void
    {
        if ($budget <= 0 || $this->cash <= 0) {
            return;
        }

        $buyPrice = (float) $stock->close_adjusted;

        if ($buyPrice <= 0) {
            return;
        }

        $maxGross = $budget / (1 + self::BUY_COST_RATE);
        $quantity = (int) floor($maxGross / $buyPrice);

        if ($quantity <= 0) {
            return;
        }

        $grossAmount = $quantity * $buyPrice;
        $costs = $this->costsAction->execute($grossAmount, 'buy');
        $netCost = $grossAmount + $costs['total_charges'];

        // Safety: reduce quantity if we can't afford
        if ($netCost > $this->cash) {
            $quantity = (int) floor($this->cash / ($buyPrice * (1 + self::BUY_COST_RATE)));
            if ($quantity <= 0) {
                return;
            }
            $grossAmount = $quantity * $buyPrice;
            $costs = $this->costsAction->execute($grossAmount, 'buy');
            $netCost = $grossAmount + $costs['total_charges'];
        }

        $this->cash -= $netCost;

        $rawPrice = (float) $stock->close_raw;

        $this->tradeBatch[] = [
            'backtest_id' => $backtest->id,
            'symbol' => $stock->symbol,
            'name' => $stock->name ?? null,
            'trade_type' => 'buy',
            'reason' => $reason,
            'date' => $date->format('Y-m-d'),
            'quantity' => $quantity,
            'price' => round($buyPrice, 2),
            'raw_price' => round($rawPrice, 2),
            'gross_amount' => round($grossAmount, 2),
            'stt' => $costs['stt'],
            'transaction_charges' => $costs['transaction_charges'],
            'sebi_charges' => $costs['sebi_charges'],
            'gst' => $costs['gst'],
            'stamp_charges' => $costs['stamp_charges'],
            'total_charges' => $costs['total_charges'],
            'net_amount' => round($netCost, 2),
        ];

        $symbol = $stock->symbol;

        if (isset($this->holdings[$symbol])) {
            $oldQty = $this->holdings[$symbol]['quantity'];
            $oldCost = $this->holdings[$symbol]['cost_basis'];
            $newQty = $oldQty + $quantity;
            $this->holdings[$symbol]['quantity'] = $newQty;
            $this->holdings[$symbol]['cost_basis'] = (($oldCost * $oldQty) + ($buyPrice * $quantity)) / $newQty;
            $this->holdings[$symbol]['last_known_price'] = $buyPrice;
            $this->holdings[$symbol]['last_known_raw_price'] = $rawPrice;
        } else {
            $this->holdings[$symbol] = [
                'quantity' => $quantity,
                'cost_basis' => $buyPrice,
                'last_known_price' => $buyPrice,
                'last_known_raw_price' => $rawPrice,
                'name' => $stock->name ?? null,
            ];
        }

        if (count($this->tradeBatch) >= 100) {
            $this->flushTrades();
        }
    }

    private function markToMarket(Carbon $date): void
    {
        if (empty($this->holdings)) {
            return;
        }

        $symbols = array_keys($this->holdings);
        $dateStr = $date->format('Y-m-d');

        $prices = BacktestNseInstrumentPrice::query()
            ->where('date', $dateStr)
            ->whereIn('symbol', $symbols)
            ->get(['symbol', 'close_adjusted', 'close_raw'])
            ->keyBy('symbol');

        foreach ($this->holdings as $symbol => &$holding) {
            if ($prices->has($symbol)) {
                $holding['last_known_price'] = (float) $prices->get($symbol)->close_adjusted;
                $holding['last_known_raw_price'] = (float) $prices->get($symbol)->close_raw;
            }
        }
        unset($holding);
    }

    private function calculatePortfolioValue(): float
    {
        $value = 0;

        foreach ($this->holdings as $holding) {
            $value += $holding['quantity'] * $holding['last_known_price'];
        }

        return $value;
    }

    private function flushSnapshots(): void
    {
        if (! empty($this->snapshotBatch)) {
            BacktestDailySnapshot::insert($this->snapshotBatch);
            $this->snapshotBatch = [];
        }
    }

    private function flushTrades(): void
    {
        if (! empty($this->tradeBatch)) {
            BacktestTrade::insert($this->tradeBatch);
            $this->tradeBatch = [];
        }
    }
}
