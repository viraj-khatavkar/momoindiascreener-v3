<?php

namespace App\Actions;

use App\Models\BacktestNseInstrument;
use App\Models\BacktestNseInstrumentPrice;
use Illuminate\Support\Collection;

class SelectUniqueEtfsAction
{
    /**
     * Keep the most liquid eligible ETF per mapped index, preserving ranking order.
     *
     * @param  Collection<int, BacktestNseInstrumentPrice>  $instruments
     * @return Collection<int, BacktestNseInstrumentPrice>
     */
    public function execute(Collection $instruments): Collection
    {
        if ($instruments->isEmpty()) {
            return $instruments;
        }

        $indices = BacktestNseInstrument::query()
            ->whereIn('symbol', $instruments->pluck('symbol'))->pluck('etf_index', 'symbol');

        $selectedIds = $instruments->sortBy([
            ['median_volume_one_year', 'desc'], ['symbol', 'asc'],
        ])->unique(function (BacktestNseInstrumentPrice $instrument) use ($indices): string {
            $index = $indices->get($instrument->symbol);

            return filled($index) ? 'index:'.$index : 'symbol:'.$instrument->symbol;
        })->pluck('id');

        return $instruments->whereIn('id', $selectedIds)->values();
    }

    /**
     * A protected or untradable holding still occupies its underlying index.
     *
     * @param  Collection<int, BacktestNseInstrumentPrice>  $candidates
     * @param  list<string>  $heldSymbols
     * @return Collection<int, BacktestNseInstrumentPrice>
     */
    public function excludeHeldIndices(Collection $candidates, array $heldSymbols): Collection
    {
        if ($candidates->isEmpty() || $heldSymbols === []) {
            return $candidates;
        }

        $indices = BacktestNseInstrument::query()
            ->whereIn('symbol', $candidates->pluck('symbol')->merge($heldSymbols)->unique())
            ->pluck('etf_index', 'symbol');
        $heldIndices = $indices->only($heldSymbols)->filter(fn (?string $index): bool => filled($index))->flip();

        return $candidates->reject(fn (BacktestNseInstrumentPrice $candidate): bool => $heldIndices->has($indices->get($candidate->symbol) ?? '')
        )->values();
    }
}
