<?php

namespace App\Actions\Backtest;

use App\Models\Backtest;
use App\Models\BacktestNseTradingGap;
use App\Models\BacktestNseTradingGapState;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FindAssumedDelistingExitsAction
{
    public const MISSING_TRADING_DAYS = 100;

    /**
     * This user-selected assumption deliberately uses future prices to schedule a sale at the last quoted close.
     *
     * @param  Collection<int, Carbon>  $tradingDates
     * @return array<string, array<string, string>> Confirmation dates keyed by exit date and symbol.
     */
    public function execute(Backtest $backtest, Collection $tradingDates): array
    {
        if ($tradingDates->count() <= self::MISSING_TRADING_DAYS) {
            return [];
        }

        $dates = $tradingDates->map(fn (Carbon $date): string => $date->toDateString())->values();
        $this->assertReadyThrough($dates->last());
        $dateIndices = $dates->flip();
        $lastPossibleExit = $dates[$dates->count() - self::MISSING_TRADING_DAYS - 1];
        $exits = [];

        $gaps = BacktestNseTradingGap::query()
            ->whereBetween('last_traded_date', [$dates->first(), $lastPossibleExit])
            ->where('confirmation_date', '<=', $dates->last())
            ->toBase()->get(['symbol', 'last_traded_date', 'resumed_date']);

        foreach ($gaps as $gap) {
            $exitIndex = $dateIndices->get($gap->last_traded_date);
            if ($exitIndex === null) {
                continue;
            }

            $confirmationDate = $dates[$exitIndex + self::MISSING_TRADING_DAYS];
            if ($gap->resumed_date !== null && $gap->resumed_date <= $confirmationDate) {
                continue;
            }

            $exits[$gap->last_traded_date][$gap->symbol] = $confirmationDate;
        }

        return $exits;
    }

    public function assertReadyThrough(string $lastDate): void
    {
        $state = BacktestNseTradingGapState::query()->find(1);
        if ($state === null || $state->requires_rebuild || $state->processed_through?->toDateString() < $lastDate) {
            throw ValidationException::withMessages([
                'start_date' => 'Market data processing is incomplete for this period. Please try again after the daily data process is complete.',
            ]);
        }
    }
}
