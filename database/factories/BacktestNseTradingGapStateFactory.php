<?php

namespace Database\Factories;

use App\Models\BacktestNseTradingGapState;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BacktestNseTradingGapState>
 */
class BacktestNseTradingGapStateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => 1,
            'processed_through' => '2024-01-08',
            'requires_rebuild' => false,
        ];
    }
}
