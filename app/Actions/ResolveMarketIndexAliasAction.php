<?php

namespace App\Actions;

use App\Enums\MarketIndexAliasStatusEnum;
use App\Models\MarketIndex;
use App\Models\MarketIndexAlias;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ResolveMarketIndexAliasAction
{
    /** @var Collection<string, MarketIndexAlias>|null */
    private ?Collection $aliases = null;

    /** @var Collection<string, Collection<int, MarketIndex>>|null */
    private ?Collection $matchingIndexes = null;

    public function __construct(private NormalizeMarketIndexAction $normalizeIndex) {}

    public function execute(string $sourceLabel, string $symbol, CarbonInterface|string $date, ?string $previousSlug = null): MarketIndexAlias
    {
        $normalizedLabel = $this->normalizeLabel($sourceLabel);
        $symbol = Str::upper(trim($symbol));
        $generic = $this->normalizeIndex->isGeneric($sourceLabel);
        $scopedAlias = $this->aliases()->get($this->key($normalizedLabel, $symbol));
        $alias = $scopedAlias ?? ($generic ? null : $this->aliases()->get($this->key($normalizedLabel, '')));
        $candidate = $generic ? null : $this->findMarketIndex($sourceLabel);

        if (! $scopedAlias && $alias?->status !== MarketIndexAliasStatusEnum::Ignored
            && $previousSlug && $candidate && $candidate->slug !== $previousSlug && $this->findMarketIndex($previousSlug)) {
            $alias = $this->createAlias($sourceLabel, $normalizedLabel, $symbol, $date, $symbol, null, $candidate);
        } elseif (! $alias) {
            $alias = $this->createAlias($sourceLabel, $normalizedLabel, $symbol, $date, $generic ? $symbol : '', $candidate, $candidate);
        } elseif ($alias->status === MarketIndexAliasStatusEnum::Pending && $alias->symbol === '' && $candidate) {
            $values = [
                'market_index_id' => $candidate->id,
                'suggested_market_index_id' => $candidate->id,
                'suggested_slug' => $candidate->slug,
                'status' => MarketIndexAliasStatusEnum::Approved,
            ];
            $updated = MarketIndexAlias::query()->whereKey($alias->id)
                ->where('status', MarketIndexAliasStatusEnum::Pending)->update($values);

            if ($updated) {
                $alias->fill($values);
                $alias->setRelation('marketIndex', $candidate);
                $alias->setRelation('suggestedMarketIndex', $candidate);
            } else {
                $alias->refresh();
            }
        }

        $this->aliases()->put($this->key($normalizedLabel, $alias->symbol), $alias);
        $this->recordObservation($alias, $symbol, $date);

        return $alias;
    }

    public function normalizeLabel(string $sourceLabel): string
    {
        return Str::of($sourceLabel)->squish()->upper()->toString();
    }

    private function key(string $label, string $symbol): string
    {
        return $label.'|'.$symbol;
    }

    /** @return Collection<string, MarketIndexAlias> */
    private function aliases(): Collection
    {
        return $this->aliases ??= MarketIndexAlias::query()
            ->with(['marketIndex:id,name,slug,is_active', 'suggestedMarketIndex:id,name,slug,is_active'])
            ->get()
            ->keyBy(fn (MarketIndexAlias $alias): string => $this->key($alias->normalized_label, $alias->symbol));
    }

    public function findMarketIndex(string $sourceLabel): ?MarketIndex
    {
        if ($this->matchingIndexes === null) {
            $matches = collect();

            foreach (MarketIndex::query()->where('is_active', true)->get() as $index) {
                foreach ([$index->slug, $index->name] as $label) {
                    $key = $this->normalizeIndex->execute($label);
                    $matches->put($key, $matches->get($key, collect())->put($index->id, $index));
                }
            }

            foreach ($this->aliases() as $alias) {
                if ($alias->symbol !== '' || $alias->status !== MarketIndexAliasStatusEnum::Approved
                    || ! $alias->marketIndex?->is_active || $this->normalizeIndex->isGeneric($alias->source_label)) {
                    continue;
                }

                $key = $this->normalizeIndex->execute($alias->source_label);
                $matches->put($key, $matches->get($key, collect())->put($alias->market_index_id, $alias->marketIndex));
            }

            $this->matchingIndexes = $matches;
        }

        $candidates = $this->matchingIndexes->get($this->normalizeIndex->execute($sourceLabel), collect());

        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    private function createAlias(
        string $sourceLabel,
        string $normalizedLabel,
        string $sampleSymbol,
        CarbonInterface|string $date,
        string $symbol,
        ?MarketIndex $marketIndex,
        ?MarketIndex $suggestedIndex,
    ): MarketIndexAlias {
        $seenOn = CarbonImmutable::parse($date)->toDateString();
        $slug = $this->normalizeIndex->execute($sourceLabel);

        return MarketIndexAlias::query()->createOrFirst(
            ['normalized_label' => $normalizedLabel, 'symbol' => $symbol],
            [
                'source_label' => Str::squish($sourceLabel),
                'market_index_id' => $marketIndex?->id,
                'suggested_market_index_id' => $suggestedIndex?->id,
                'suggested_slug' => $suggestedIndex?->slug ?? ($this->normalizeIndex->isGeneric($sourceLabel) ? null : $slug),
                'status' => $marketIndex ? MarketIndexAliasStatusEnum::Approved : MarketIndexAliasStatusEnum::Pending,
                'sample_symbol' => $sampleSymbol,
                'first_seen_on' => $seenOn,
                'last_seen_on' => $seenOn,
            ],
        )->loadMissing(['marketIndex:id,name,slug,is_active', 'suggestedMarketIndex:id,name,slug,is_active']);
    }

    private function recordObservation(MarketIndexAlias $alias, string $symbol, CarbonInterface|string $date): void
    {
        $seenOn = CarbonImmutable::parse($date)->startOfDay();
        $updates = [];

        if (! $alias->first_seen_on || $seenOn->isBefore($alias->first_seen_on)) {
            $updates['first_seen_on'] = $seenOn->toDateString();
        }

        if (! $alias->last_seen_on || $seenOn->isAfter($alias->last_seen_on)) {
            $updates['last_seen_on'] = $seenOn->toDateString();
        }

        if (blank($alias->sample_symbol)) {
            $updates['sample_symbol'] = $symbol;
        }

        if ($updates !== []) {
            $alias->update($updates);
        }
    }
}
