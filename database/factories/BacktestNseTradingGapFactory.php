<?php

namespace Database\Factories;

use App\Models\BacktestNseTradingGap;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BacktestNseTradingGap>
 */
class BacktestNseTradingGapFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'symbol' => strtoupper(fake()->unique()->lexify('??????')),
            'last_traded_date' => '2024-01-08',
            'confirmation_date' => null,
            'resumed_date' => null,
        ];
    }
}
