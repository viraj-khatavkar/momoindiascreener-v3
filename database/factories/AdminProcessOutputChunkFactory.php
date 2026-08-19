<?php

namespace Database\Factories;

use App\Models\AdminProcessOutputChunk;
use App\Models\AdminProcessStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminProcessOutputChunk>
 */
class AdminProcessOutputChunkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admin_process_step_id' => AdminProcessStep::factory(),
            'stream' => 'stdout',
            'output' => fake()->sentence()."\n",
        ];
    }
}
