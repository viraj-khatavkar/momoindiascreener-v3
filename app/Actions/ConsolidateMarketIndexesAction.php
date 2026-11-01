<?php

namespace App\Actions;

use App\Enums\MarketIndexAliasStatusEnum;
use App\Models\BacktestNseInstrument;
use App\Models\MarketIndex;
use App\Models\MarketIndexAlias;
use Illuminate\Support\Facades\DB;

class ConsolidateMarketIndexesAction
{
    public function __construct(private NormalizeMarketIndexAction $normalizer) {}

    /** @return array{changes: array<int, array{string, string}>, merged: int, approved: int, pending: array<int, array{string, string, string}>} */
    public function execute(): array
    {
        return DB::transaction(function (): array {
            $indexes = MarketIndex::query()->orderBy('id')->lockForUpdate()->get();
            $changes = [];
            $merged = 0;

            foreach ($indexes->groupBy(fn (MarketIndex $index): string => $this->normalizer->execute($index->name)) as $slug => $group) {
                if ($this->normalizer->isGeneric($slug)) {
                    continue;
                }

                $active = $group->where('is_active', true);
                $target = $active->firstWhere('slug', $slug) ?? $active->first() ?? $group->first();

                foreach ($group as $index) {
                    if ($index->slug !== $slug || $index->name !== $this->normalizer->name($slug)) {
                        $changes[] = [$index->slug, $slug];
                    }

                    BacktestNseInstrument::query()->where('etf_index', $index->slug)->update(['etf_index' => $slug]);

                    if ($index->id === $target->id) {
                        continue;
                    }

                    MarketIndexAlias::query()->where('market_index_id', $index->id)->update(['market_index_id' => $target->id]);
                    MarketIndexAlias::query()->where('suggested_market_index_id', $index->id)->update(['suggested_market_index_id' => $target->id]);
                    $index->delete();
                    $merged++;
                }

                $target->update(['slug' => $slug, 'name' => $this->normalizer->name($slug)]);
            }

            $this->scopeGenericAliases();
            $this->correctDistinctIndexes();
            $this->restoreSymbolMappings();

            $resolver = new ResolveMarketIndexAliasAction($this->normalizer);
            $approved = 0;

            foreach (MarketIndexAlias::query()->where('status', MarketIndexAliasStatusEnum::Pending)->get() as $alias) {
                $resolved = $resolver->execute($alias->source_label, $alias->symbol, $alias->last_seen_on ?? today());

                if ($resolved->status === MarketIndexAliasStatusEnum::Approved) {
                    $approved++;
                }
            }

            foreach (MarketIndexAlias::query()->with(['marketIndex', 'suggestedMarketIndex'])->get() as $alias) {
                $index = $alias->status === MarketIndexAliasStatusEnum::Approved ? $alias->marketIndex : $alias->suggestedMarketIndex;
                $slug = $index?->slug ?? ($this->normalizer->isGeneric($alias->source_label) ? null : $this->normalizer->execute($alias->source_label));
                $alias->update(['suggested_slug' => $slug]);

                if ($alias->status === MarketIndexAliasStatusEnum::Approved && $index?->is_active) {
                    BacktestNseInstrument::query()->whereBelongsTo($alias, 'marketIndexAlias')->update(['etf_index' => $index->slug]);
                }
            }

            return [
                'changes' => $changes,
                'merged' => $merged,
                'approved' => $approved,
                'pending' => MarketIndexAlias::query()->where('status', MarketIndexAliasStatusEnum::Pending)->get()
                    ->map(fn (MarketIndexAlias $alias): array => [$alias->source_label, $alias->symbol ?: ($alias->sample_symbol ?? ''), $alias->suggested_slug ?? ''])->all(),
            ];
        });
    }

    private function scopeGenericAliases(): void
    {
        foreach (MarketIndexAlias::query()->where('symbol', '')->with('instruments')->get() as $alias) {
            if (! $this->normalizer->isGeneric($alias->source_label)) {
                continue;
            }

            $symbols = $alias->instruments->pluck('symbol')->push($alias->sample_symbol)->filter()->unique()->values();

            foreach ($symbols as $symbol) {
                $scoped = MarketIndexAlias::query()->where('normalized_label', $alias->normalized_label)->where('symbol', $symbol)->first();

                if (! $scoped) {
                    $scoped = $alias->symbol === '' ? $alias : $alias->replicate();
                    $scoped->fill(['symbol' => $symbol, 'sample_symbol' => $symbol])->save();
                }

                BacktestNseInstrument::query()->where('symbol', $symbol)->where('market_index_alias_id', $alias->id)
                    ->update(['market_index_alias_id' => $scoped->id]);
            }

            if ($symbols->isNotEmpty() && $alias->symbol === '') {
                $alias->delete();
            }
        }
    }

    /**
     * These provider indexes are distinct from the older registry assignments.
     */
    private function correctDistinctIndexes(): void
    {
        $corrections = [
            ['HANG SENG TECH TOTAL RETURN INDEX', 'hang-seng', 'hang-seng-tech', 'Hang Seng Indexes'],
            ['CRISIL 10 YEAR GILT INDEX', 'gsec-10', 'crisil-10-yr-gilt', 'CRISIL'],
        ];

        foreach ($corrections as [$label, $oldSlug, $slug, $provider]) {
            $alias = MarketIndexAlias::query()->where('normalized_label', $label)->where('symbol', '')
                ->where('status', MarketIndexAliasStatusEnum::Approved)->whereHas('marketIndex', fn ($query) => $query->where('slug', $oldSlug))->first();

            if (! $alias) {
                continue;
            }

            $index = MarketIndex::query()->firstOrCreate(['slug' => $slug], ['name' => $this->normalizer->name($slug), 'provider' => $provider]);
            $alias->update(['market_index_id' => $index->id, 'suggested_market_index_id' => $index->id]);
        }
    }

    /**
     * Reuse approved pre-change labels for the ambiguous October 2025 labels.
     * The MOLOWVOL exception follows the provider's BSE Low Volatility mandate.
     * These aliases apply only to the named ETF, never to another symbol.
     */
    private function restoreSymbolMappings(): void
    {
        $mappings = [
            ['GROWWGOLD', 'COMMODITY', 'DOMESTIC PRICE OF PHYSICAL GOLD'],
            ['GROWWNET', 'INDEX', 'NIFTY INDIA INTERNET INDEX - TRI'],
            ['GROWWNXT50', 'INDEX', 'NIFTY NEXT 50 INDEX - TRI'],
            ['GROWWRLTY', 'INDEX', 'NIFTY REALTY INDEX - TRI'],
            ['INTERNET', 'EQUITY INDEX', 'NIFTY INDIA INTERNET TOTAL RETURN INDEX'],
            ['LIQUIDBEES', 'GOVERNMENT SECURITIES', 'CALL MONEY SHORT TERM G-SECS & MONEY MARKET INSTR'],
            ['MOLOWVOL', 'NIFTY MIDCAP 100 TRI', 'S&P BSE LOW VOLATILITY TOTAL RETURN INDEX'],
        ];

        foreach ($mappings as [$symbol, $label, $previousLabel]) {
            $previousAlias = MarketIndexAlias::query()->with('marketIndex')->where('normalized_label', $previousLabel)
                ->where('status', MarketIndexAliasStatusEnum::Approved)->where('symbol', '')->first();
            $observed = MarketIndexAlias::query()->where('normalized_label', $label)->whereIn('symbol', ['', $symbol])->first();

            if (! $previousAlias?->marketIndex?->is_active || ! $observed) {
                continue;
            }

            $scoped = MarketIndexAlias::query()->firstOrNew(['normalized_label' => $label, 'symbol' => $symbol]);

            if ($scoped->exists && $scoped->status !== MarketIndexAliasStatusEnum::Pending) {
                continue;
            }

            $scoped->fill([
                'source_label' => $label,
                'market_index_id' => $previousAlias->market_index_id,
                'suggested_market_index_id' => $previousAlias->market_index_id,
                'suggested_slug' => $previousAlias->marketIndex->slug,
                'status' => MarketIndexAliasStatusEnum::Approved,
                'sample_symbol' => $symbol,
                'first_seen_on' => $observed->first_seen_on,
                'last_seen_on' => $observed->last_seen_on,
            ])->save();

            BacktestNseInstrument::query()->where('symbol', $symbol)
                ->whereHas('marketIndexAlias', fn ($query) => $query->where('normalized_label', $label))
                ->update(['market_index_alias_id' => $scoped->id]);
        }
    }
}
