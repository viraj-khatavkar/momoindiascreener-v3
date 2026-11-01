<?php

namespace App\Actions;

use App\Enums\MarketIndexAliasStatusEnum;
use App\Models\BacktestNseInstrument;
use App\Models\MarketIndex;
use App\Models\MarketIndexAlias;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ReviewMarketIndexAliasAction
{
    public function __construct(private NormalizeMarketIndexAction $normalizer) {}

    /**
     * @param  array{action: string, market_index_id?: int|null, name?: string|null, slug?: string|null, provider?: string|null}  $data
     */
    public function execute(MarketIndexAlias $marketIndexAlias, array $data, User $reviewer): MarketIndexAlias
    {
        return DB::transaction(function () use ($marketIndexAlias, $data, $reviewer): MarketIndexAlias {
            $lockedAlias = MarketIndexAlias::query()
                ->lockForUpdate()
                ->findOrFail($marketIndexAlias->id);

            if ($data['action'] === 'ignore') {
                return $this->ignore($lockedAlias, $reviewer);
            }

            $marketIndex = match ($data['action']) {
                'link' => MarketIndex::query()
                    ->where('is_active', true)
                    ->findOrFail($data['market_index_id']),
                'create' => MarketIndex::query()->create([
                    'name' => $this->normalizer->name($this->normalizer->execute($data['name'])),
                    'slug' => $this->normalizer->execute($data['slug']),
                    'provider' => filled($data['provider'] ?? null)
                        ? Str::squish($data['provider'])
                        : null,
                    'is_active' => true,
                ]),
                default => throw new InvalidArgumentException('The review action is not valid.'),
            };

            $lockedAlias->update([
                'market_index_id' => $marketIndex->id,
                'suggested_market_index_id' => $marketIndex->id,
                'suggested_slug' => $marketIndex->slug,
                'status' => MarketIndexAliasStatusEnum::Approved,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            BacktestNseInstrument::query()
                ->whereBelongsTo($lockedAlias, 'marketIndexAlias')
                ->update(['etf_index' => $marketIndex->slug]);

            if ($lockedAlias->symbol === '' && ! $this->normalizer->isGeneric($lockedAlias->source_label)) {
                $this->resolveEquivalentLabels($lockedAlias);
            }

            return $lockedAlias->load(['marketIndex', 'suggestedMarketIndex', 'reviewer']);
        });
    }

    private function resolveEquivalentLabels(MarketIndexAlias $reviewedAlias): void
    {
        $key = $this->normalizer->execute($reviewedAlias->source_label);
        $resolver = new ResolveMarketIndexAliasAction($this->normalizer);

        foreach (MarketIndexAlias::query()->where('symbol', '')->where('status', MarketIndexAliasStatusEnum::Pending)->get() as $alias) {
            if ($this->normalizer->execute($alias->source_label) !== $key) {
                continue;
            }

            $resolved = $resolver->execute($alias->source_label, '', $alias->last_seen_on ?? today());

            if ($resolved->status === MarketIndexAliasStatusEnum::Approved) {
                BacktestNseInstrument::query()->whereBelongsTo($resolved, 'marketIndexAlias')
                    ->update(['etf_index' => $resolved->marketIndex->slug]);
            }
        }
    }

    private function ignore(MarketIndexAlias $marketIndexAlias, User $reviewer): MarketIndexAlias
    {
        $marketIndexAlias->update([
            'market_index_id' => null,
            'status' => MarketIndexAliasStatusEnum::Ignored,
            'reviewed_by_user_id' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        BacktestNseInstrument::query()
            ->whereBelongsTo($marketIndexAlias, 'marketIndexAlias')
            ->update(['etf_index' => null]);

        return $marketIndexAlias->load(['marketIndex', 'suggestedMarketIndex', 'reviewer']);
    }
}
