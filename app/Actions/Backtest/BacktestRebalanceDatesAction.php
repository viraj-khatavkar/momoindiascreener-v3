<?php

namespace App\Actions\Backtest;

use App\Enums\BacktestRebalanceFrequencyEnum;
use App\Models\Backtest;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BacktestRebalanceDatesAction
{
    /**
     * @param  Collection<int, Carbon>  $tradingDates  Trading sessions in chronological order.
     * @return array<string, string> Decision dates before applying next-session execution.
     */
    public function execute(Backtest $backtest, Collection $tradingDates): array
    {
        if ($tradingDates->isEmpty()) {
            return [];
        }

        $this->validateMonth($backtest);
        $frequency = $backtest->rebalance_frequency;
        $months = $frequency->monthInterval();
        $periodFormat = $months === null ? 'o-W' : 'Y-m';
        $sessions = $tradingDates->groupBy(fn (Carbon $date) => $date->format($periodFormat));
        $last = $tradingDates->last();
        $lastPeriod = $last->format($periodFormat);
        $opening = $tradingDates->first()->toDateString();
        $decisions = [$opening => $opening];

        $periodStart = $this->firstPeriod($backtest);
        while ($periodStart->lte($last)) {
            $period = $periodStart->format($periodFormat);
            $dates = $sessions->get($period);
            if ($dates !== null) {
                $decision = $dates->first(fn (Carbon $date) => ($months === null ? $date->dayOfWeekIso : $date->day) >= $backtest->rebalance_day);
                // A later period must confirm a closure before we can use an earlier session.
                if ($decision === null && $period !== $lastPeriod) {
                    $decision = $dates->last();
                }
                if ($decision !== null) {
                    $date = $decision->toDateString();
                    $decisions[$date] = $date;
                }
            }

            // Advance from the calendar target so holidays and missing sessions never shift the cycle.
            if ($months === null) {
                $periodStart->addWeeks($frequency === BacktestRebalanceFrequencyEnum::Fortnightly ? 2 : 1);
            } else {
                $periodStart->addMonths($months);
            }
        }

        return $decisions;
    }

    public function validateMonth(Backtest $backtest): void
    {
        if ($backtest->rebalance_frequency->monthInterval() > 1 && ($backtest->rebalance_month < 1 || $backtest->rebalance_month > 12)) {
            throw ValidationException::withMessages(['rebalance_month' => 'Select a first rebalance month.']);
        }
    }

    private function firstPeriod(Backtest $backtest): Carbon
    {
        $start = $backtest->start_date ?? Carbon::parse(BacktestStartDateAction::DEFAULT_START_DATE);
        $target = Carbon::instance($start);
        $frequency = $backtest->rebalance_frequency;
        if ($frequency->monthInterval() === null) {
            $target->startOfWeek(Carbon::MONDAY);
            if ($frequency === BacktestRebalanceFrequencyEnum::Fortnightly && $start->dayOfWeekIso > $backtest->rebalance_day) {
                $target->addWeek();
            }

            return $target;
        }

        if ($frequency === BacktestRebalanceFrequencyEnum::Monthly) {
            return $target->startOfMonth();
        }

        $target->setDate($start->year, $backtest->rebalance_month, $backtest->rebalance_day);

        if ($target->lt($start)) {
            $target->addYear();
        }

        return $target->startOfMonth();
    }
}
