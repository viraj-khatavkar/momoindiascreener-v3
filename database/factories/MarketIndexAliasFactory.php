<?php

namespace Database\Factories;

use App\Enums\MarketIndexAliasStatusEnum;
use App\Models\MarketIndexAlias;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketIndexAlias>
 */
class MarketIndexAliasFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sourceLabel = str(fake()->unique()->words(4, true))->upper()->toString();

        return [
            'source_label' => $sourceLabel,
            'normalized_label' => str($sourceLabel)->squish()->upper()->toString(),
            'suggested_slug' => str($sourceLabel)->slug()->toString(),
            'status' => MarketIndexAliasStatusEnum::Pending,
            'sample_symbol' => str(fake()->unique()->lexify('ETF????'))->upper()->toString(),
            'first_seen_on' => today(),
            'last_seen_on' => today(),
        ];
    }
}
