<?php

namespace Database\Factories;

use App\Models\Portfolio;
use App\Models\PortfolioHolding;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PortfolioHolding> */
class PortfolioHoldingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'portfolio_id' => Portfolio::factory(),
            'symbol' => fake()->randomElement(['RELIANCE', 'TCS', 'INFY']),
            'quantity' => fake()->numberBetween(1, 100),
            'price' => fake()->randomFloat(2, 10, 5000),
        ];
    }
}
