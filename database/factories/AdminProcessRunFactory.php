<?php

namespace Database\Factories;

use App\Enums\AdminProcessRunStatusEnum;
use App\Models\AdminProcessRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminProcessRun>
 */
class AdminProcessRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'process_date' => fake()->unique()->date(),
            'status' => AdminProcessRunStatusEnum::Pending,
        ];
    }
}
