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

    /** @var Collection<string, MarketIndex>|null */
    private ?Collection $marketIndexes = null;

    public function execute(string $sourceLabel, string $symbol, CarbonInterface|string $date): MarketIndexAlias
    {
        $normalizedLabel = $this->normalizeLabel($sourceLabel);
        $marketIndexAlias = $this->aliases()->get($normalizedLabel);

        if (! $marketIndexAlias) {
            $marketIndexAlias = $this->createAlias($sourceLabel, $normalizedLabel, $symbol, $date);
            $this->aliases?->put($normalizedLabel, $marketIndexAlias);
        }

        $this->recordObservation($marketIndexAlias, $symbol, $date);

        return $marketIndexAlias;
    }

    public function normalizeLabel(string $sourceLabel): string
    {
        return Str::of($sourceLabel)->squish()->upper()->toString();
    }

    /**
     * @return Collection<string, MarketIndexAlias>
     */
    private function aliases(): Collection
    {
        return $this->aliases ??= MarketIndexAlias::query()
            ->with([
                'marketIndex:id,slug',
                'suggestedMarketIndex:id,slug',
            ])
            ->get([
                'id',
                'source_label',
                'normalized_label',
                'market_index_id',
                'suggested_market_index_id',
                'suggested_slug',
                'status',
                'sample_symbol',
                'first_seen_on',
                'last_seen_on',
            ])
            ->keyBy('normalized_label');
    }

    /**
     * @return Collection<string, MarketIndex>
     */
    private function marketIndexes(): Collection
    {
        return $this->marketIndexes ??= MarketIndex::query()
            ->where('is_active', true)
            ->get(['id', 'name', 'slug'])
            ->keyBy('slug');
    }

    private function createAlias(
        string $sourceLabel,
        string $normalizedLabel,
        string $symbol,
        CarbonInterface|string $date,
    ): MarketIndexAlias {
        $seenOn = CarbonImmutable::parse($date)->toDateString();
        $rawSlug = Str::slug($normalizedLabel);
        $exactMarketIndex = $this->marketIndexes()->get($rawSlug);
        $suggestedMarketIndex = $exactMarketIndex ?? $this->findSuggestedMarketIndex($rawSlug);

        $marketIndexAlias = MarketIndexAlias::query()->createOrFirst(
            ['normalized_label' => $normalizedLabel],
            [
                'source_label' => Str::squish($sourceLabel),
                'market_index_id' => $exactMarketIndex?->id,
                'suggested_market_index_id' => $suggestedMarketIndex?->id,
                'suggested_slug' => $suggestedMarketIndex?->slug ?? $rawSlug,
                'status' => $exactMarketIndex
                    ? MarketIndexAliasStatusEnum::Approved
                    : MarketIndexAliasStatusEnum::Pending,
                'sample_symbol' => $symbol,
                'first_seen_on' => $seenOn,
                'last_seen_on' => $seenOn,
            ],
        );

        return $marketIndexAlias->loadMissing([
            'marketIndex:id,slug',
            'suggestedMarketIndex:id,slug',
        ]);
    }

    private function findSuggestedMarketIndex(string $rawSlug): ?MarketIndex
    {
        $suffixes = [
            '-total-return-index',
            '-tri-index',
            '-tri',
            '-index',
            '-etf',
        ];

        foreach ($suffixes as $suffix) {
            if (! Str::endsWith($rawSlug, $suffix)) {
                continue;
            }

            $candidateSlug = Str::beforeLast($rawSlug, $suffix);

            if ($candidateSlug !== '' && $this->marketIndexes()->has($candidateSlug)) {
                return $this->marketIndexes()->get($candidateSlug);
            }
        }

        return null;
    }

    private function recordObservation(
        MarketIndexAlias $marketIndexAlias,
        string $symbol,
        CarbonInterface|string $date,
    ): void {
        $seenOn = CarbonImmutable::parse($date)->startOfDay();
        $updates = [];

        if (! $marketIndexAlias->first_seen_on || $seenOn->isBefore($marketIndexAlias->first_seen_on)) {
            $updates['first_seen_on'] = $seenOn->toDateString();
        }

        if (! $marketIndexAlias->last_seen_on || $seenOn->isAfter($marketIndexAlias->last_seen_on)) {
            $updates['last_seen_on'] = $seenOn->toDateString();
        }

        if (blank($marketIndexAlias->sample_symbol)) {
            $updates['sample_symbol'] = $symbol;
        }

        if ($updates !== []) {
            $marketIndexAlias->update($updates);
        }
    }
}
