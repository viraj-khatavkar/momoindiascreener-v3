<?php

namespace App\Actions\Backtest;

use App\Enums\BacktestCashCallEnum;
use App\Enums\BacktestStopLossProceedsEnum;
use App\Enums\BacktestWeightageEnum;
use App\Enums\CorporateActionTypeEnum;
use App\Models\Backtest;
use App\Models\BacktestDailySnapshot;
use App\Models\BacktestNseCorporateAction;
use App\Models\BacktestNseInstrumentPrice;
use App\Models\BacktestTrade;
use App\Models\NseIndex;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RunBacktestAction
{
    private const DEFAULT_START_DATE = '2011-01-05';

    private string $startDate;

    /** Buy-side cost fraction derived from the backtest's configured rates. */
    private float $buyCostRate = 0;

    // Circuit hits land at one of these close-to-close return values (in %).
    private const CIRCUIT_PERCENTAGES = [
        4.99, 5.00, 9.99, 10.00, 19.99, 20.00,
        -4.99, -5.00, -9.99, -10.00, -19.99, -20.00,
    ];

    private array $holdings = [];

    private float $cash;

    private float $stopLossCash = 0;

    /** @var array<string, array{date: string, close: float}> */
    private array $stopLossSignals = [];

    private float $navBase = 100.0;

    private float $initialCapital;

    private array $indexData = [];

    private array $dmaData = [];

    private array $rebalanceDates = [];

    /** @var array<string, string|null> Decision dates for trades on each simulation day. */
    private array $decisionDates = [];

    private array $snapshotBatch = [];

    private array $tradeBatch = [];

    /** @var array<string, true> Symbols in circuit on the current execution day (set semantics). */
    private array $circuitSymbols = [];

    /** @var array<string, array<string, string>> Corporate-action ex-dates keyed by exit date and symbol. */
    private array $demergerExDatesByExitDate = [];

    private float $dailyCashReturnRate = 0;

    private int $dmaPeriod = 50;

    public function __construct(
        private ApplyBacktestFiltersAction $filtersAction,
        private CalculateTransactionCostsAction $costsAction,
    ) {}

    public function execute(Backtest $backtest): void
    {
        $this->startDate = $backtest->start_date
            ? Carbon::parse($backtest->start_date)->format('Y-m-d')
            : self::DEFAULT_START_DATE;
        $this->initialCapital = (float) $backtest->initial_capital;
        $this->cash = $this->initialCapital;
        $this->stopLossCash = 0;
        $this->stopLossSignals = [];
        $this->holdings = [];
        $this->indexData = [];
        $this->dmaData = [];
        $this->rebalanceDates = [];
        $this->decisionDates = [];
        $this->snapshotBatch = [];
        $this->tradeBatch = [];
        $this->circuitSymbols = [];
        $this->demergerExDatesByExitDate = [];

        $annualRate = (float) $backtest->cash_return_rate;
        $this->dailyCashReturnRate = pow(1 + $annualRate / 100, 1.0 / 252) - 1;
        $this->buyCostRate = $this->costsAction->buyCostRate($backtest);

        $tradingDates = $this->loadTradingDates($backtest);

        if ($tradingDates->isEmpty()) {
            return;
        }

        $backtest->update(['progress' => 2]);

        $this->dmaPeriod = (int) $backtest->cash_call_dma_period;
        if ($backtest->cash_call->usesIndexDma()) {
            $this->loadIndexData($backtest->cash_call_index);
            $this->computeDma($this->dmaPeriod);
        }
        $this->computeRebalanceDates($backtest, $tradingDates);

        $previousDate = null;
        foreach ($tradingDates as $date) {
            $dateStr = $date->format('Y-m-d');
            $this->decisionDates[$dateStr] = $backtest->execute_next_trading_day ? $previousDate : $dateStr;
            $previousDate = $dateStr;
        }

        if ($backtest->exit_before_demerger) {
            $this->loadDemergerExitDates($tradingDates);
        }

        // When execute_next_trading_day is enabled, shift execution to next trading day
        // rebalanceDates stores: executionDate => decisionDate (filterDate)
        if ($backtest->execute_next_trading_day) {
            $this->rebalanceDates = $this->shiftRebalanceDatesToNextDay($tradingDates);
        }

        $totalDays = $tradingDates->count();
        $dayIndex = 0;
        $previousTradingDate = null;
        $lastReportedProgress = 5;

        $backtest->update(['progress' => $lastReportedProgress]);

        foreach ($tradingDates as $date) {
            $dateStr = $date->format('Y-m-d');
            $dayIndex++;

            // Step A: Mark-to-market FIRST (loads today's prices for held stocks)
            $dailyPrices = $this->markToMarket($date);

            $isRebalanceDate = isset($this->rebalanceDates[$dateStr]);
            $stopLossExits = $this->handleStopLossExits($backtest, $date, $previousTradingDate, $dailyPrices);
            $demergerExitSymbols = $this->handleDemergerExits($backtest, $date, $isRebalanceDate, $stopLossExits['symbols']);
            $beExitSymbols = $this->handleBeSeriesExits($backtest, $date, $isRebalanceDate, [...$stopLossExits['symbols'], ...$demergerExitSymbols]);
            $blockedBuySymbols = [...$stopLossExits['symbols'], ...$demergerExitSymbols, ...$beExitSymbols];

            // Step B: Rebalance (if applicable)
            // rebalanceDates[executionDate] = decisionDate (filter date)
            if ($isRebalanceDate) {
                $this->cash += $this->stopLossCash;
                $this->stopLossCash = 0;
                $filterDate = $this->rebalanceDates[$dateStr];
                $this->rebalance($backtest, $date, $filterDate, $blockedBuySymbols);
            } elseif ($backtest->stop_loss_proceeds === BacktestStopLossProceedsEnum::ReplaceImmediately && $stopLossExits['symbols'] !== []) {
                $this->buyReplacements($backtest, $date, $stopLossExits['symbols'], $stopLossExits['proceeds'], 'Replacement after stop-loss exit', [...$demergerExitSymbols, ...$beExitSymbols]);
            }

            $this->recordStopLossSignals($backtest, $dateStr, $dailyPrices);
            $previousTradingDate = $dateStr;

            // Accrue interest on end-of-day cash (overnight carry, post-rebalance).
            if ($this->cash > 0 && $this->dailyCashReturnRate > 0) {
                $this->cash += $this->cash * $this->dailyCashReturnRate;
            }
            if ($this->stopLossCash > 0 && $this->dailyCashReturnRate > 0) {
                $this->stopLossCash += $this->stopLossCash * $this->dailyCashReturnRate;
            }

            $portfolioValue = $this->calculatePortfolioValue();
            $totalCash = $this->cash + $this->stopLossCash;
            $totalValue = $portfolioValue + $totalCash;
            $nav = $this->navBase * ($totalValue / $this->initialCapital);

            // Step C: Save snapshot
            $this->snapshotBatch[] = [
                'backtest_id' => $backtest->id,
                'date' => $dateStr,
                'nav' => $nav,
                'portfolio_value' => round($portfolioValue, 2),
                'cash' => round($totalCash, 2),
                'total_value' => round($totalValue, 2),
                'holdings_count' => count($this->holdings),
            ];

            if (count($this->snapshotBatch) >= 100) {
                $this->flushSnapshots();
            }

            // Step D: Update progress
            $simulationProgress = 5 + (int) floor(($dayIndex / $totalDays) * 89);

            if ($simulationProgress > $lastReportedProgress) {
                $backtest->update(['progress' => $simulationProgress]);
                $lastReportedProgress = $simulationProgress;
            }
        }

        $this->flushSnapshots();
        $this->flushTrades();

        $backtest->update(['progress' => 95]);
    }

    public function validateDataAvailability(Backtest $backtest): void
    {
        $dates = BacktestNseInstrumentPrice::query()
            ->where($backtest->index->isIndexFieldName(), true)
            ->where('date', '>=', $backtest->start_date?->toDateString() ?? self::DEFAULT_START_DATE)
            ->select('date')->distinct()->limit(2)->pluck('date');

        if ($dates->count() < 2) {
            throw ValidationException::withMessages([
                'start_date' => 'Insufficient trading data. At least two trading dates are required from the selected start date.',
            ]);
        }
    }

    private function loadTradingDates(Backtest $backtest)
    {
        return BacktestNseInstrumentPrice::query()
            ->where($backtest->index->isIndexFieldName(), true)
            ->where('date', '>=', $this->startDate)
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
        $loadFrom = Carbon::parse($this->startDate)->subDays($this->dmaPeriod * 2)->format('Y-m-d');

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

                /** Later-period data must confirm the week is complete before using its last trading day. */
                if ($matched === null && $dates->last()->copy()->endOfWeek()->lt($tradingDates->last())) {
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

                /** Do not bring a rebalance forward because the latest imported month is incomplete. */
                if ($matched === null && $dates->last()->copy()->endOfMonth()->lt($tradingDates->last())) {
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

    private function loadDemergerExitDates(Collection $tradingDates): void
    {
        if ($tradingDates->count() < 2) {
            return;
        }

        $exitDateByExDate = [];
        $tradingDateStrings = $tradingDates
            ->map(fn (Carbon $date) => $date->format('Y-m-d'))
            ->values();

        foreach ($tradingDateStrings as $index => $tradingDate) {
            $nextTradingDate = $tradingDateStrings->get($index + 1);

            if ($nextTradingDate === null) {
                continue;
            }

            $exitDateByExDate[$nextTradingDate] = $tradingDate;
        }

        BacktestNseCorporateAction::query()
            ->whereBetween('date', [$tradingDateStrings->get(1), $tradingDateStrings->last()])
            ->where('type', CorporateActionTypeEnum::DEMERGER->value)
            ->get(['date', 'symbol'])
            ->each(function (BacktestNseCorporateAction $corporateAction) use ($exitDateByExDate): void {
                $exDate = $corporateAction->date->format('Y-m-d');
                $exitDate = $exitDateByExDate[$exDate] ?? null;

                if ($exitDate === null) {
                    return;
                }

                $this->demergerExDatesByExitDate[$exitDate][$corporateAction->symbol] = $exDate;
            });
    }

    private function rankedStocksForExecution(Backtest $backtest, string $executionDate, string $decisionDate): Collection
    {
        $rankedStocks = $this->filtersAction->execute($backtest, $decisionDate);

        return $this->withExecutionPrices($rankedStocks, $executionDate, $decisionDate);
    }

    private function withExecutionPrices(Collection $stocks, string $executionDate, string $decisionDate): Collection
    {
        $stocks->each(function (BacktestNseInstrumentPrice $stock): void {
            $stock->decision_close_raw = $stock->close_raw;
        });

        // When executing next day, reload prices from execution date for buy candidates
        if ($decisionDate !== $executionDate) {
            $symbols = $stocks->pluck('symbol')->toArray();
            $executionPrices = BacktestNseInstrumentPrice::query()
                ->where('date', $executionDate)
                ->whereIn('symbol', $symbols)
                ->get(['symbol', 'close_adjusted', 'close_raw', 'series'])
                ->keyBy('symbol');

            // Series is refreshed alongside prices so the BE entry block is
            // anchored to the execution day, matching the circuit check.
            $stocks = $stocks->map(function ($stock) use ($executionPrices) {
                $execData = $executionPrices->get($stock->symbol);
                if ($execData) {
                    $stock->close_adjusted = $execData->close_adjusted;
                    $stock->close_raw = $execData->close_raw;
                    $stock->series = $execData->series;
                } else {
                    $stock->close_adjusted = null;
                    $stock->close_raw = null;
                }

                return $stock;
            });
        }

        return $stocks;
    }

    private function indexIsBelowDma(string $decisionDate): bool
    {
        $close = $this->indexData[$decisionDate] ?? null;
        $dma = $this->dmaData[$decisionDate] ?? null;

        return $close !== null && $dma !== null && $close < $dma;
    }

    private function rebalance(Backtest $backtest, Carbon $date, ?string $filterDate = null, array $blockedBuySymbols = []): void
    {
        $filterDate = $filterDate ?? $date->format('Y-m-d');
        $executionDateStr = $date->format('Y-m-d');
        $rankedStocks = $this->rankedStocksForExecution($backtest, $executionDateStr, $filterDate);
        $rankedBySymbol = $rankedStocks->keyBy('symbol');

        // Circuit set is keyed on the execution date, so the rule still honors
        // execute_next_trading_day (filters use $filterDate, trades use $executionDateStr).
        $this->loadCircuitSymbols($backtest, $executionDateStr, $rankedStocks);

        // Cash call DMA check uses decision date (not execution date)
        $indexBelowDma = $this->indexIsBelowDma($filterDate);

        // Determine cash call behavior
        $cashCall = $backtest->cash_call;

        if ($cashCall === BacktestCashCallEnum::FullCashBelowIndexDma && $indexBelowDma) {
            $this->sellEverything($backtest, $date, 'Cash call - index below '.$this->dmaPeriod.' DMA');

            return;
        }

        if ($cashCall === BacktestCashCallEnum::AllocateToGoldBelowIndexDma && $indexBelowDma) {
            $this->allocateToGold($backtest, $date, $filterDate);

            return;
        }

        // If gold allocation was active but index recovered, sell GOLDBEES first
        if ($cashCall->allocatesToGold() && ! $indexBelowDma && isset($this->holdings['GOLDBEES'])) {
            $this->executeSell($backtest, $date, 'GOLDBEES', $this->holdings['GOLDBEES']['quantity'], 'Index recovered above '.$this->dmaPeriod.' DMA - exiting gold');
        }

        $onlyExits = $cashCall->usesIndexDma() && $indexBelowDma;

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
            $symbolsToSell = $this->applyHoldAboveDmaOverride($backtest, $filterDate, $symbolsToSell);
        }

        $eligibleStocks = $rankedStocks
            ->filter(fn (BacktestNseInstrumentPrice $stock): bool => (float) $stock->close_adjusted > 0)
            ->filter(fn (BacktestNseInstrumentPrice $stock): bool => ! in_array($stock->symbol, $blockedBuySymbols, true))
            ->filter(fn (BacktestNseInstrumentPrice $stock): bool => ! isset($this->circuitSymbols[$stock->symbol]))
            ->filter(fn (BacktestNseInstrumentPrice $stock): bool => ! $this->isBeSeriesEntryBlocked($backtest, $stock))
            ->filter(fn (BacktestNseInstrumentPrice $stock): bool => ! $this->isDemergerEntryBlocked($stock->symbol, $executionDateStr))
            ->filter(fn (BacktestNseInstrumentPrice $stock): bool => $this->hasWeightingData($backtest, $stock));

        if ($cashCall === BacktestCashCallEnum::NoCashCall && $eligibleStocks->isEmpty()) {
            if ($backtest->weightage->rebalancesHoldings()) {
                $this->rebalanceWeights($backtest, $date, $rankedBySymbol, collect(), $filterDate);
            } else {
                $this->topUpHeldStocks($backtest, $date, $this->cash);
            }

            return;
        }

        // Execute full sells first
        foreach ($symbolsToSell as $symbol => $reason) {
            $this->executeSell($backtest, $date, $symbol, $this->holdings[$symbol]['quantity'], $reason);
        }

        if ($onlyExits) {
            if ($cashCall->allocatesToGold()) {
                $this->buyGold($backtest, $date, $filterDate, $this->cash, 'Index below '.$this->dmaPeriod.' DMA - allocating exits to gold');
            }

            return;
        }

        // Determine buy candidates. Circuit-hit stocks are filtered out before the
        // slot cap so the next-ranked candidate slides in to take their place.
        $remainingSlots = $backtest->max_stocks_to_hold - count($this->holdings);
        $buyCandidates = $eligibleStocks
            ->filter(fn ($stock) => ! isset($this->holdings[$stock->symbol]))
            ->take(max($remainingSlots, 0));

        if ($backtest->weightage->rebalancesHoldings()) {
            $this->rebalanceWeights($backtest, $date, $rankedBySymbol, $buyCandidates, $filterDate);
        } else {
            $this->equalWeightBuy($backtest, $date, $buyCandidates);
        }

        if ($cashCall === BacktestCashCallEnum::NoCashCall && ! $backtest->weightage->usesRankOrPriceWeights()) {
            $this->topUpHeldStocks($backtest, $date, $this->cash);
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

    private function handleDemergerExits(Backtest $backtest, Carbon $date, bool $isRebalanceDate, array $sameDayExitedSymbols = []): array
    {
        if (empty($this->holdings)) {
            return [];
        }

        $dateStr = $date->format('Y-m-d');
        $demergerExDates = $this->demergerExDatesByExitDate[$dateStr] ?? [];
        $demergerSymbols = array_keys($demergerExDates);

        if (empty($demergerSymbols)) {
            return [];
        }

        $demergerSymbols = array_values(array_filter(
            $demergerSymbols,
            fn (string $symbol): bool => isset($this->holdings[$symbol]),
        ));

        if (empty($demergerSymbols)) {
            return [];
        }

        $cashBeforeExits = $this->cash;
        $exitedSymbols = [];

        foreach ($demergerSymbols as $symbol) {
            $quantity = $this->holdings[$symbol]['quantity'];
            $this->executeSell($backtest, $date, $symbol, $quantity, 'Demerger ex-date on '.$demergerExDates[$symbol].' - exiting one trading day before ex-date', force: true);

            if (! isset($this->holdings[$symbol])) {
                $exitedSymbols[] = $symbol;
            }
        }

        if (empty($exitedSymbols)) {
            return [];
        }

        if (! $isRebalanceDate) {
            $this->buyReplacements($backtest, $date, $exitedSymbols, $this->cash - $cashBeforeExits, 'Replacement after demerger exit', $sameDayExitedSymbols);
        }

        return $exitedSymbols;
    }

    /**
     * @param  Collection<string, BacktestNseInstrumentPrice>  $dailyPrices
     * @return array{symbols: list<string>, proceeds: float}
     */
    private function handleStopLossExits(Backtest $backtest, Carbon $date, ?string $previousTradingDate, Collection $dailyPrices): array
    {
        $exits = ['symbols' => [], 'proceeds' => 0.0];

        if (! $backtest->apply_stop_loss || $previousTradingDate === null) {
            return $exits;
        }

        $cashBeforeExits = $this->cash;

        foreach ($this->stopLossSignals as $symbol => $signal) {
            $price = $dailyPrices->get($symbol);

            if (! isset($this->holdings[$symbol]) || $signal['date'] !== $previousTradingDate || ! $price) {
                continue;
            }

            $close = (float) $price->close_adjusted;

            if ($close <= 0 || $close >= $signal['close']) {
                continue;
            }

            if ($backtest->skip_circuit_trades && in_array((float) $price->t_percent, self::CIRCUIT_PERCENTAGES, true)) {
                continue;
            }

            $reason = ($backtest->trail_stop_loss ? 'Trailing stop loss' : 'Stop loss')
                .' confirmed - close below '.$signal['close'].' on '.$signal['date'];

            /** Today's quote and circuit status have been checked; the cached circuit set may belong to an earlier rebalance. */
            $this->executeSell($backtest, $date, $symbol, $this->holdings[$symbol]['quantity'], $reason, force: true);

            if (! isset($this->holdings[$symbol])) {
                $exits['symbols'][] = $symbol;
            }
        }

        $exits['proceeds'] = $this->cash - $cashBeforeExits;

        if ($backtest->stop_loss_proceeds === BacktestStopLossProceedsEnum::WaitForRebalance) {
            $this->cash -= $exits['proceeds'];
            $this->stopLossCash += $exits['proceeds'];
        }

        return $exits;
    }

    /** @param Collection<string, BacktestNseInstrumentPrice> $dailyPrices */
    private function recordStopLossSignals(Backtest $backtest, string $date, Collection $dailyPrices): void
    {
        $this->stopLossSignals = [];

        if (! $backtest->apply_stop_loss) {
            return;
        }

        $stopFraction = 1 - (float) $backtest->stop_loss_percentage / 100;

        foreach ($this->holdings as $symbol => &$holding) {
            $price = $dailyPrices->get($symbol);

            if ($symbol === 'GOLDBEES' || ! $price || (float) $price->close_adjusted <= 0) {
                continue;
            }

            $close = (float) $price->close_adjusted;
            $holding['highest_close'] = max($holding['highest_close'], $close);
            $referencePrice = $backtest->trail_stop_loss ? $holding['highest_close'] : $holding['average_entry_price'];
            $stopPrice = round($referencePrice * $stopFraction, 10);

            if ($close < $stopPrice) {
                $this->stopLossSignals[$symbol] = ['date' => $date, 'close' => $close];
            }
        }
        unset($holding);
    }

    /**
     * Exit holdings whose series has moved to BE (trade-to-trade) when the
     * backtest opts in. The check runs every trading day; a circuit-hit symbol
     * is skipped and retried the next day since its series stays BE.
     *
     * @param  array<int, string>  $sameDayExitedSymbols  Symbols force-exited earlier today (demergers), excluded from replacements.
     * @return array<int, string> Exited symbols, blocked from same-day re-entry.
     */
    private function handleBeSeriesExits(Backtest $backtest, Carbon $date, bool $isRebalanceDate, array $sameDayExitedSymbols = []): array
    {
        if (! $backtest->exit_on_be_series || empty($this->holdings)) {
            return [];
        }

        $heldSymbols = array_values(array_filter(
            array_keys($this->holdings),
            fn (string $symbol): bool => $symbol !== 'GOLDBEES',
        ));

        if (empty($heldSymbols)) {
            return [];
        }

        $dateStr = $date->format('Y-m-d');

        $beSymbols = BacktestNseInstrumentPrice::query()
            ->where('date', $dateStr)
            ->whereIn('symbol', $heldSymbols)
            ->where('series', 'BE')
            ->pluck('symbol')
            ->all();

        if ($backtest->skip_circuit_trades && ! empty($beSymbols)) {
            $circuitHits = BacktestNseInstrumentPrice::query()
                ->where('date', $dateStr)
                ->whereIn('symbol', $beSymbols)
                ->whereIn('t_percent', self::CIRCUIT_PERCENTAGES)
                ->pluck('symbol')
                ->all();

            $beSymbols = array_values(array_diff($beSymbols, $circuitHits));
        }

        if (empty($beSymbols)) {
            return [];
        }

        $cashBeforeExits = $this->cash;
        $exitedSymbols = [];

        foreach ($beSymbols as $symbol) {
            // Today's circuit status was already checked above, so force past
            // the stale circuit set carried over from the last rebalance.
            $this->executeSell($backtest, $date, $symbol, $this->holdings[$symbol]['quantity'], 'Series changed to BE - exiting', force: true);

            if (! isset($this->holdings[$symbol])) {
                $exitedSymbols[] = $symbol;
            }
        }

        if (empty($exitedSymbols)) {
            return [];
        }

        if (! $isRebalanceDate) {
            $this->buyReplacements($backtest, $date, $exitedSymbols, $this->cash - $cashBeforeExits, 'Replacement after BE series exit', $sameDayExitedSymbols);
        }

        return $exitedSymbols;
    }

    /**
     * Buy next-ranked replacements for forced exits that happened outside a
     * scheduled rebalance, spending only the cash those exits freed up.
     *
     * @param  array<int, string>  $exitedSymbols
     * @param  array<int, string>  $additionalBlockedSymbols  Symbols exited by another flow today, also barred from re-entry.
     */
    private function buyReplacements(Backtest $backtest, Carbon $date, array $exitedSymbols, float $replacementBudget, string $reason, array $additionalBlockedSymbols = []): void
    {
        if ($replacementBudget <= 0) {
            return;
        }

        $dateStr = $date->format('Y-m-d');
        $decisionDate = $this->decisionDates[$dateStr] ?? null;
        if ($decisionDate === null) {
            return;
        }

        $rankedStocks = $this->rankedStocksForExecution($backtest, $dateStr, $decisionDate);
        $previousCircuitSymbols = $this->circuitSymbols;

        try {
            $this->loadCircuitSymbols($backtest, $dateStr, $rankedStocks);

            if ($backtest->cash_call->usesIndexDma() && $this->indexIsBelowDma($decisionDate)) {
                if ($backtest->cash_call->allocatesToGold()) {
                    $this->buyGold($backtest, $date, $decisionDate, $replacementBudget, $reason.' - allocating to gold');
                }

                return;
            }

            $remainingSlots = $backtest->max_stocks_to_hold - count($this->holdings);
            $replacementCount = min(count($exitedSymbols), max($remainingSlots, 0));

            if ($replacementCount <= 0) {
                return;
            }

            $blockedSymbols = [...$exitedSymbols, ...$additionalBlockedSymbols];

            $replacementCandidates = $rankedStocks
                ->filter(fn (BacktestNseInstrumentPrice $stock): bool => (float) $stock->close_adjusted > 0)
                ->filter(fn ($stock) => ! isset($this->holdings[$stock->symbol]))
                ->filter(fn ($stock) => ! in_array($stock->symbol, $blockedSymbols, true))
                ->filter(fn ($stock) => ! isset($this->circuitSymbols[$stock->symbol]))
                ->filter(fn ($stock) => ! $this->isBeSeriesEntryBlocked($backtest, $stock))
                ->filter(fn ($stock) => ! $this->isDemergerEntryBlocked($stock->symbol, $dateStr))
                ->filter(fn (BacktestNseInstrumentPrice $stock): bool => $this->hasWeightingData($backtest, $stock))
                ->take($replacementCount);

            if ($replacementCandidates->isEmpty()) {
                return;
            }

            $perStockBudget = $replacementBudget / $replacementCandidates->count();

            foreach ($replacementCandidates as $stock) {
                $this->executeBuy($backtest, $date, $stock, $perStockBudget, $reason);
            }

        } finally {
            $this->circuitSymbols = $previousCircuitSymbols;
        }
    }

    private function topUpHeldStocks(Backtest $backtest, Carbon $date, float $budget): void
    {
        if ($budget <= 0 || empty($this->holdings)) {
            return;
        }

        $stocks = BacktestNseInstrumentPrice::query()
            ->where('date', $date->format('Y-m-d'))
            ->whereIn('symbol', array_keys($this->holdings))
            ->orderBy('symbol')
            ->get(['symbol', 'name', 'series', 'close_adjusted', 'close_raw', 'volatility_one_year'])
            ->filter(fn (BacktestNseInstrumentPrice $stock): bool => (float) $stock->close_adjusted > 0
                && ! isset($this->circuitSymbols[$stock->symbol])
                && ! $this->isBeSeriesEntryBlocked($backtest, $stock)
                && (float) $stock->close_adjusted * (1 + $this->buyCostRate) <= min($budget, $this->cash));

        if ($stocks->isEmpty()) {
            return;
        }

        $perStockBudget = min($budget, $this->cash) / $stocks->count();
        foreach ($stocks as $stock) {
            $this->executeBuy($backtest, $date, $stock, $perStockBudget, 'No cash call - allocating available cash to stocks');
        }
    }

    /**
     * With exit_on_be_series enabled, buying a BE stock would just be exited
     * the next trading day, so BE stocks are blocked from entry entirely.
     */
    private function isBeSeriesEntryBlocked(Backtest $backtest, $stock): bool
    {
        return $backtest->exit_on_be_series && ($stock->series ?? null) === 'BE';
    }

    private function isDemergerEntryBlocked(string $symbol, string $executionDate): bool
    {
        return isset($this->demergerExDatesByExitDate[$executionDate][$symbol]);
    }

    private function hasWeightingData(Backtest $backtest, BacktestNseInstrumentPrice $stock): bool
    {
        return match ($backtest->weightage) {
            BacktestWeightageEnum::InverseVolatility => (float) $stock->volatility_one_year > 0,
            BacktestWeightageEnum::RankWeighted => (int) $stock->rank > 0,
            BacktestWeightageEnum::PriceWeighted => (float) $stock->decision_close_raw > 0,
            default => true,
        };
    }

    private function rebalanceWeights(Backtest $backtest, Carbon $date, Collection $rankedBySymbol, Collection $buyCandidates, string $decisionDate): void
    {
        $portfolioValue = $this->calculatePortfolioValue();
        $totalValue = $portfolioValue + $this->cash;

        $unrankedHeldStocks = collect();

        if (in_array($backtest->weightage, [BacktestWeightageEnum::EqualWeightRebalanced, BacktestWeightageEnum::InverseVolatility, BacktestWeightageEnum::PriceWeighted], true)) {
            $unrankedHeldSymbols = array_diff(array_keys($this->holdings), $rankedBySymbol->keys()->all(), ['GOLDBEES']);

            if ($unrankedHeldSymbols !== []) {
                $quoteDate = $backtest->weightage === BacktestWeightageEnum::EqualWeightRebalanced ? $date->format('Y-m-d') : $decisionDate;
                $decisionStocks = BacktestNseInstrumentPrice::query()
                    ->where('date', $quoteDate)
                    ->whereIn('symbol', $unrankedHeldSymbols)
                    ->get(['symbol', 'name', 'series', 'close_adjusted', 'close_raw', 'volatility_one_year']);

                $unrankedHeldStocks = $this->withExecutionPrices($decisionStocks, $date->format('Y-m-d'), $quoteDate)
                    ->keyBy('symbol');
            }
        }

        $allTargetStocks = collect();

        // Add kept holdings
        foreach ($this->holdings as $symbol => $holding) {
            $stockData = $rankedBySymbol->get($symbol) ?? $unrankedHeldStocks->get($symbol);
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
        $estimatedCosts = $totalBuyBudget * $this->buyCostRate;
        if (($totalBuyBudget + $estimatedCosts) > $this->cash && $totalBuyBudget > 0) {
            $scaleFactor = $this->cash / ($totalBuyBudget + $estimatedCosts);
        }

        // Execute buys (in rank order)
        $sortedOrders = collect($buyOrders)->sortBy(fn ($order) => $order['stock']->rank ?? PHP_INT_MAX);
        foreach ($sortedOrders as $symbol => $order) {
            $budget = $order['budget'] * (1 + $this->buyCostRate) * $scaleFactor;
            $this->executeBuy($backtest, $date, $order['stock'], $budget, $order['reason']);
        }
    }

    private function calculateTargetAllocations(Backtest $backtest, float $totalValue, Collection $allTargetStocks): array
    {
        if ($backtest->weightage->usesRankOrPriceWeights()) {
            return $this->calculateRankOrPriceTargets($backtest, $totalValue, $allTargetStocks);
        }

        $targets = [];
        $cashCall = $backtest->cash_call;
        $allTargetStocks = $allTargetStocks->filter(fn ($stock): bool => (float) $stock->close_adjusted > 0);
        $retainedValue = 0.0;
        $retainedCount = 0;
        foreach ($this->holdings as $symbol => $holding) {
            if (! $allTargetStocks->has($symbol)) {
                $retainedValue += $holding['quantity'] * $holding['last_known_price'];
                $retainedCount++;
            }
        }
        $totalValue = max($totalValue - $retainedValue, 0);
        $availableSlots = max($backtest->max_stocks_to_hold - $retainedCount, 1);
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
                    $scale = min($validStocks->count() / $availableSlots, 1);
                    $targets[$symbol] = $totalValue * $weight * $scale;
                } else {
                    $targets[$symbol] = $totalValue * $weight;
                }
            }
        } else {
            // Equal weight rebalanced
            if ($cashCall === BacktestCashCallEnum::CashCallIfNotEnoughStocks) {
                $targetPerStock = $totalValue / $availableSlots;
            } else {
                $targetPerStock = $totalValue / max($n, 1);
            }

            foreach ($allTargetStocks as $symbol => $stock) {
                $targets[$symbol] = $targetPerStock;
            }
        }

        return $targets;
    }

    /**
     * @param  Collection<string, BacktestNseInstrumentPrice>  $stocks
     * @return array<string, float>
     */
    private function calculateRankOrPriceTargets(Backtest $backtest, float $totalValue, Collection $stocks): array
    {
        $factors = $stocks
            ->filter(fn (BacktestNseInstrumentPrice $stock): bool => (float) $stock->close_adjusted > 0 && $this->hasWeightingData($backtest, $stock))
            ->map(fn (BacktestNseInstrumentPrice $stock): float => $backtest->weightage === BacktestWeightageEnum::RankWeighted
                ? 1.0 / (int) $stock->rank
                : (float) $stock->decision_close_raw);

        if ($factors->isEmpty()) {
            return [];
        }

        $retainedValue = 0.0;
        $retainedCount = 0;

        foreach ($this->holdings as $symbol => $holding) {
            if (! $factors->has($symbol)) {
                $retainedValue += $holding['quantity'] * $holding['last_known_price'];
                $retainedCount++;
            }
        }

        $availableValue = max($totalValue - $retainedValue, 0);

        if ($backtest->cash_call === BacktestCashCallEnum::CashCallIfNotEnoughStocks) {
            $availableSlots = max($backtest->max_stocks_to_hold - $retainedCount, 1);
            $availableValue *= min($factors->count() / $availableSlots, 1);
        }

        $factorSum = $factors->sum();

        return $factors->map(fn (float $factor): float => $availableValue * $factor / $factorSum)->all();
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
                if ($backtest->above_ma_200 && $stock->close_adjusted <= $stock->ma_200) {
                    $failures[] = 'Below 200-day MA';
                }
                if ($backtest->above_ma_100 && $stock->close_adjusted <= $stock->ma_100) {
                    $failures[] = 'Below 100-day MA';
                }
                if ($backtest->above_ma_50 && $stock->close_adjusted <= $stock->ma_50) {
                    $failures[] = 'Below 50-day MA';
                }
                if ($backtest->above_ma_20 && $stock->close_adjusted <= $stock->ma_20) {
                    $failures[] = 'Below 20-day MA';
                }
            }

            if ($backtest->apply_ema) {
                if ($backtest->above_ema_200 && $stock->close_adjusted <= $stock->ema_200) {
                    $failures[] = 'Below 200-day EMA';
                }
                if ($backtest->above_ema_100 && $stock->close_adjusted <= $stock->ema_100) {
                    $failures[] = 'Below 100-day EMA';
                }
                if ($backtest->above_ema_50 && $stock->close_adjusted <= $stock->ema_50) {
                    $failures[] = 'Below 50-day EMA';
                }
                if ($backtest->above_ema_20 && $stock->close_adjusted <= $stock->ema_20) {
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

    private function loadCircuitSymbols(Backtest $backtest, string $executionDateStr, $rankedStocks): void
    {
        $this->circuitSymbols = [];

        if (! $backtest->skip_circuit_trades) {
            return;
        }

        $symbols = array_values(array_unique(array_merge(
            array_keys($this->holdings),
            $rankedStocks->pluck('symbol')->all(),
            ['GOLDBEES'],
        )));

        if (empty($symbols)) {
            return;
        }

        $hits = BacktestNseInstrumentPrice::query()
            ->where('date', $executionDateStr)
            ->whereIn('symbol', $symbols)
            ->whereIn('t_percent', self::CIRCUIT_PERCENTAGES)
            ->pluck('symbol')
            ->all();

        $this->circuitSymbols = array_flip($hits);
    }

    private function applyHoldAboveDmaOverride(Backtest $backtest, string $decisionDate, array $symbolsToSell): array
    {
        $dmaColumn = match ($backtest->hold_above_dma_period) {
            20 => 'ma_20',
            50 => 'ma_50',
            100 => 'ma_100',
            default => 'ma_200',
        };

        $symbols = array_keys($symbolsToSell);

        $stockData = BacktestNseInstrumentPrice::query()
            ->where('date', $decisionDate)
            ->whereIn('symbol', $symbols)
            ->get(['symbol', 'close_adjusted', $dmaColumn])
            ->keyBy('symbol');

        foreach ($symbols as $symbol) {
            $data = $stockData->get($symbol);

            if (! $data) {
                continue;
            }

            $adjustedClose = (float) $data->close_adjusted;
            $dmaValue = (float) $data->$dmaColumn;

            if ($dmaValue > 0 && $adjustedClose > $dmaValue) {
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

    private function allocateToGold(Backtest $backtest, Carbon $date, string $decisionDate): void
    {
        // Sell all non-GOLDBEES holdings
        $symbols = array_keys($this->holdings);
        foreach ($symbols as $symbol) {
            if ($symbol !== 'GOLDBEES') {
                $this->executeSell($backtest, $date, $symbol, $this->holdings[$symbol]['quantity'], 'Index below '.$this->dmaPeriod.' DMA - rotating to gold');
            }
        }

        $this->buyGold($backtest, $date, $decisionDate, $this->cash, 'Index below '.$this->dmaPeriod.' DMA - allocating to gold');
    }

    private function buyGold(Backtest $backtest, Carbon $date, string $decisionDate, float $budget, string $reason): void
    {
        if ($budget <= 0 || $this->cash <= 0) {
            return;
        }

        if ($backtest->cash_call === BacktestCashCallEnum::OnlyExitsAllocateToGoldAboveDmaBelowIndexDma) {
            $dmaColumn = 'ma_'.$backtest->cash_call_gold_dma_period;
            $goldSignal = BacktestNseInstrumentPrice::query()
                ->where('date', $decisionDate)
                ->where('symbol', 'GOLDBEES')
                ->first(['close_adjusted', $dmaColumn]);

            if (! $goldSignal || (float) $goldSignal->$dmaColumn <= 0
                || (float) $goldSignal->close_adjusted <= (float) $goldSignal->$dmaColumn) {
                return;
            }

            $reason .= ' - GOLDBEES above '.$backtest->cash_call_gold_dma_period.' DMA';
        }

        $goldData = BacktestNseInstrumentPrice::query()
            ->where('date', $date->format('Y-m-d'))
            ->where('symbol', 'GOLDBEES')
            ->first(['symbol', 'name', 'close_adjusted', 'close_raw']);

        if (! $goldData || (float) $goldData->close_adjusted <= 0) {
            return;
        }

        $this->executeBuy($backtest, $date, $goldData, min($budget, $this->cash), $reason);
    }

    private function executeSell(Backtest $backtest, Carbon $date, string $symbol, int $quantity, string $reason, bool $force = false): void
    {
        if (! isset($this->holdings[$symbol]) || $quantity <= 0) {
            return;
        }

        if (! $force && isset($this->circuitSymbols[$symbol])) {
            return;
        }

        $holding = $this->holdings[$symbol];
        if ($holding['last_price_date'] !== $date->format('Y-m-d') || $holding['last_known_price'] <= 0) {
            return;
        }

        $sellPrice = $holding['last_known_price'];
        $grossAmount = $quantity * $sellPrice;
        $costs = $this->costsAction->execute($grossAmount, 'sell', $backtest);
        $netAmount = $grossAmount - $costs['total_charges'];

        $this->cash += $netAmount;

        // Realized P&L against the charge-inclusive average cost of the shares
        // sold, so a round trip nets out buy-side and sell-side charges alike.
        $investedValue = $holding['cost_basis'] * $quantity;
        $realizedPnl = $netAmount - $investedValue;
        $realizedPnlPct = $investedValue > 0 ? ($realizedPnl / $investedValue) * 100 : null;

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
            'brokerage' => $costs['brokerage'],
            'stt' => $costs['stt'],
            'transaction_charges' => $costs['transaction_charges'],
            'sebi_charges' => $costs['sebi_charges'],
            'gst' => $costs['gst'],
            'stamp_charges' => $costs['stamp_charges'],
            'total_charges' => $costs['total_charges'],
            'net_amount' => round($netAmount, 2),
            'realized_pnl' => round($realizedPnl, 2),
            'realized_pnl_pct' => $realizedPnlPct !== null ? round($realizedPnlPct, 2) : null,
        ];

        if ($quantity >= $holding['quantity']) {
            unset($this->holdings[$symbol]);
            unset($this->stopLossSignals[$symbol]);
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

        if (isset($this->circuitSymbols[$stock->symbol])) {
            return;
        }

        $buyPrice = (float) $stock->close_adjusted;

        if ($buyPrice <= 0) {
            return;
        }

        $maxGross = $budget / (1 + $this->buyCostRate);
        $quantity = (int) floor($maxGross / $buyPrice);

        if ($quantity <= 0) {
            return;
        }

        $grossAmount = $quantity * $buyPrice;
        $costs = $this->costsAction->execute($grossAmount, 'buy', $backtest);
        $netCost = $grossAmount + $costs['total_charges'];

        // Safety: reduce quantity if we can't afford
        if ($netCost > $this->cash) {
            $quantity = (int) floor($this->cash / ($buyPrice * (1 + $this->buyCostRate)));
            if ($quantity <= 0) {
                return;
            }
            $grossAmount = $quantity * $buyPrice;
            $costs = $this->costsAction->execute($grossAmount, 'buy', $backtest);
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
            'brokerage' => $costs['brokerage'],
            'stt' => $costs['stt'],
            'transaction_charges' => $costs['transaction_charges'],
            'sebi_charges' => $costs['sebi_charges'],
            'gst' => $costs['gst'],
            'stamp_charges' => $costs['stamp_charges'],
            'total_charges' => $costs['total_charges'],
            'net_amount' => round($netCost, 2),
            'realized_pnl' => null,
            'realized_pnl_pct' => null,
        ];

        $symbol = $stock->symbol;

        // Cost basis includes buy-side charges so realized P&L on the eventual
        // sell nets out the full round-trip cost, matching the position metrics.
        if (isset($this->holdings[$symbol])) {
            $oldQty = $this->holdings[$symbol]['quantity'];
            $oldCost = $this->holdings[$symbol]['cost_basis'];
            $newQty = $oldQty + $quantity;
            $this->holdings[$symbol]['average_entry_price'] = (($this->holdings[$symbol]['average_entry_price'] * $oldQty) + $grossAmount) / $newQty;
            $this->holdings[$symbol]['highest_close'] = max($this->holdings[$symbol]['highest_close'], $buyPrice);
            $this->holdings[$symbol]['quantity'] = $newQty;
            $this->holdings[$symbol]['cost_basis'] = (($oldCost * $oldQty) + $netCost) / $newQty;
            $this->holdings[$symbol]['last_known_price'] = $buyPrice;
            $this->holdings[$symbol]['last_known_raw_price'] = $rawPrice;
        } else {
            $this->holdings[$symbol] = [
                'quantity' => $quantity,
                'cost_basis' => $netCost / $quantity,
                'average_entry_price' => $buyPrice,
                'highest_close' => $buyPrice,
                'last_known_price' => $buyPrice,
                'last_known_raw_price' => $rawPrice,
                'name' => $stock->name ?? null,
            ];
        }

        $this->holdings[$symbol]['last_price_date'] = $date->format('Y-m-d');

        if (count($this->tradeBatch) >= 100) {
            $this->flushTrades();
        }
    }

    private function markToMarket(Carbon $date): Collection
    {
        if (empty($this->holdings)) {
            return collect();
        }

        $symbols = array_keys($this->holdings);
        $dateStr = $date->format('Y-m-d');

        $prices = BacktestNseInstrumentPrice::query()
            ->where('date', $dateStr)
            ->whereIn('symbol', $symbols)
            ->get(['symbol', 'close_adjusted', 'close_raw', 't_percent'])
            ->keyBy('symbol');

        foreach ($this->holdings as $symbol => &$holding) {
            if ($prices->has($symbol) && (float) $prices->get($symbol)->close_adjusted > 0) {
                $holding['last_known_price'] = (float) $prices->get($symbol)->close_adjusted;
                $holding['last_known_raw_price'] = (float) $prices->get($symbol)->close_raw;
                $holding['last_price_date'] = $dateStr;
            }
        }
        unset($holding);

        return $prices;
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
