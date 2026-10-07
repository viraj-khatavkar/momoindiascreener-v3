<?php

namespace App\Actions\Backtest;

use App\Models\BacktestNseInstrumentPrice;
use App\Models\BacktestNseTradingGap;
use App\Models\BacktestNseTradingGapState;
use Closure;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateAssumedDelistingsAction
{
    /**
     * Keep one open gap per symbol plus confirmed historical gaps. Open gaps carry
     * the last quote into the next update, so appended dates need no history scan.
     *
     * @param  ?Closure(string): void  $progress
     * @return array{processed_through: string, rebuilt: bool, confirmed_gaps: int}
     */
    public function execute(?string $throughDate = null, bool $rebuild = false, ?Closure $progress = null): array
    {
        $latestDate = BacktestNseInstrumentPrice::query()->max('date');
        if ($latestDate === null) {
            throw new InvalidArgumentException('No price data is available.');
        }

        if ($throughDate !== null && ! BacktestNseInstrumentPrice::query()->where('date', $throughDate)->exists()) {
            throw new InvalidArgumentException('No price data is available for '.$throughDate.'.');
        }

        BacktestNseTradingGapState::query()->firstOrCreate(['id' => 1]);

        return DB::transaction(function () use ($throughDate, $latestDate, $rebuild, $progress): array {
            $state = BacktestNseTradingGapState::query()->lockForUpdate()->findOrFail(1);
            $previousDate = $state->processed_through?->toDateString();
            $rebuild = $rebuild || $state->requires_rebuild || $previousDate === null || $latestDate < $previousDate;
            $throughDate = min($latestDate, max($throughDate ?? $latestDate, $previousDate ?? ''));

            if ($rebuild || $throughDate !== $previousDate) {
                $dates = BacktestNseInstrumentPrice::query()->where('date', '<=', $throughDate)
                    ->distinct()->orderBy('date')->toBase()->pluck('date')->all();
                $dateIndices = array_flip($dates);
                $lastQuoteDates = $rebuild ? [] : BacktestNseTradingGap::query()->whereNull('resumed_date')
                    ->toBase()->pluck('last_traded_date', 'symbol')->all();
                $datesToProcess = $rebuild ? $dates : array_slice($dates, $dateIndices[$previousDate] + 1);
                $totalDates = count($datesToProcess);
                $processedDates = 0;
                $progress?->__invoke(($rebuild ? 'Rebuilding history' : 'Reading new quotes').' for '.$totalDates.' market dates through '.$throughDate.'.');

                if ($rebuild) {
                    BacktestNseTradingGap::query()->delete();
                }

                foreach (array_chunk($datesToProcess, 10) as $window) {
                    $records = [];
                    $quotes = BacktestNseInstrumentPrice::query()->forceIndex('bnip_date_symbol_unique')
                        ->whereBetween('date', [min($window), max($window)])->where('close_adjusted', '>', 0)
                        ->orderBy('date')->toBase()->get(['symbol', 'date']);

                    foreach ($quotes as $quote) {
                        $lastDate = $lastQuoteDates[$quote->symbol] ?? null;
                        $confirmationDate = $lastDate === null ? null : ($dates[$dateIndices[$lastDate] + FindAssumedDelistingExitsAction::MISSING_TRADING_DAYS] ?? null);

                        if ($confirmationDate !== null && $quote->date > $confirmationDate) {
                            $records[] = [
                                'symbol' => $quote->symbol,
                                'last_traded_date' => $lastDate,
                                'confirmation_date' => $confirmationDate,
                                'resumed_date' => $quote->date,
                            ];
                        }

                        $lastQuoteDates[$quote->symbol] = $quote->date;
                    }

                    unset($quotes);
                    $this->saveGaps($records);
                    $processedDates += count($window);
                    if ($processedDates % 250 === 0 || $processedDates === $totalDates) {
                        $progress?->__invoke('Processed '.$processedDates.' of '.$totalDates.' market dates.');
                    }
                }

                BacktestNseTradingGap::query()->whereNull('resumed_date')->delete();
                foreach (array_chunk($lastQuoteDates, 500, preserve_keys: true) as $chunk) {
                    $records = [];
                    foreach ($chunk as $symbol => $lastDate) {
                        $records[] = [
                            'symbol' => $symbol,
                            'last_traded_date' => $lastDate,
                            'confirmation_date' => $dates[$dateIndices[$lastDate] + FindAssumedDelistingExitsAction::MISSING_TRADING_DAYS] ?? null,
                            'resumed_date' => null,
                        ];
                    }
                    $this->saveGaps($records);
                }

                $state->update(['processed_through' => $throughDate, 'requires_rebuild' => false]);
            } else {
                $progress?->__invoke('Saved gaps are already current through '.$throughDate.'.');
            }

            return [
                'processed_through' => $throughDate,
                'rebuilt' => $rebuild,
                'confirmed_gaps' => BacktestNseTradingGap::query()->whereNotNull('confirmation_date')->count(),
            ];
        });
    }

    /** @param list<array{symbol: string, last_traded_date: string, confirmation_date: ?string, resumed_date: ?string}> $records */
    private function saveGaps(array $records): void
    {
        foreach (array_chunk($records, 500) as $batch) {
            BacktestNseTradingGap::query()->upsert($batch, ['symbol', 'last_traded_date'], ['confirmation_date', 'resumed_date']);
        }
    }
}
