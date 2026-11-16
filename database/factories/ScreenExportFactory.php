<?php

namespace Database\Factories;

use App\Models\Screen;
use App\Models\ScreenExport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

/**
 * @extends Factory<ScreenExport>
 */
class ScreenExportFactory extends Factory
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
            'screen_id' => null,
            'screen_name' => 'Historical screen',
            'settings' => fn (): array => Arr::except(Screen::factory()->make()->toArray(), ['id', 'user_id', 'created_at', 'updated_at']),
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-30',
            'total_dates' => 30,
            'status' => 'queued',
        ];
    }
}
