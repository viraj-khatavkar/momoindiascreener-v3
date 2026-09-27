<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const VALUATION_STEPS = [
        'import-marketcap' => [
            'name' => 'Import market cap',
            'description' => 'Import market cap for the selected date and save values in crores.',
            'command' => 'backtest:import-marketcap',
        ],
        'import-price-to-earnings' => [
            'name' => 'Import price to earnings',
            'description' => 'Import ADJUSTED P/E values for the selected date.',
            'command' => 'backtest:import-price-to-earnings',
        ],
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('admin_process_runs')
                ->where('process_date', '>=', '2024-03-01')
                ->where('status', '!=', 'completed')
                ->eachById(function (object $run): void {
                    $stepsByKey = DB::table('admin_process_steps')
                        ->where('admin_process_run_id', $run->id)
                        ->orderBy('position')
                        ->get()
                        ->keyBy('key');

                    if ($stepsByKey->has(array_keys(self::VALUATION_STEPS))) {
                        return;
                    }

                    $keys = $stepsByKey->keys()
                        ->reject(fn (string $key): bool => isset(self::VALUATION_STEPS[$key]))
                        ->values()->all();
                    $instrumentPosition = array_search('import-instruments', $keys, true);

                    if ($instrumentPosition === false) {
                        return;
                    }

                    array_splice($keys, $instrumentPosition + 1, 0, array_keys(self::VALUATION_STEPS));

                    DB::table('admin_process_steps')
                        ->where('admin_process_run_id', $run->id)
                        ->increment('position', $stepsByKey->max('position') + 1);

                    foreach ($keys as $index => $key) {
                        if ($existingStep = $stepsByKey->get($key)) {
                            DB::table('admin_process_steps')
                                ->where('id', $existingStep->id)
                                ->update(['position' => $index + 1]);

                            continue;
                        }

                        $definition = self::VALUATION_STEPS[$key];
                        $date = str($run->process_date)->before(' ')->toString();
                        $arguments = ["--date={$date}"];

                        DB::table('admin_process_steps')->insert([
                            ...$definition,
                            'admin_process_run_id' => $run->id,
                            'position' => $index + 1,
                            'key' => $key,
                            'arguments' => json_encode($arguments, JSON_THROW_ON_ERROR),
                            'command_line' => implode(' ', ['php artisan', $definition['command'], ...$arguments]),
                            'is_preview' => false,
                            'status' => 'pending',
                            'attempts' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }, column: 'id');
        });
    }

    /**
     * Removing a step can remove its execution history and terminal output.
     * Keep these records if this data migration is rolled back.
     */
    public function down(): void {}
};
