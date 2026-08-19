<?php

namespace Database\Factories;

use App\Enums\AdminProcessStepStatusEnum;
use App\Models\AdminProcessRun;
use App\Models\AdminProcessStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminProcessStep>
 */
class AdminProcessStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admin_process_run_id' => AdminProcessRun::factory(),
            'position' => fake()->numberBetween(1, 1000),
            'key' => fake()->unique()->slug(3),
            'name' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'command' => 'backtest:example',
            'arguments' => ['--date=2022-02-04'],
            'command_line' => 'php artisan backtest:example --date=2022-02-04',
            'is_preview' => false,
            'status' => AdminProcessStepStatusEnum::Pending,
        ];
    }
}
