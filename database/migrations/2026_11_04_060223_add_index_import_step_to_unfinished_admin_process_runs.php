<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('admin_process_runs')
                ->where('process_date', '>=', '2026-02-17')
                ->where('status', '!=', 'completed')
                ->lockForUpdate()
                ->eachById(function (object $run): void {
                    $steps = DB::table('admin_process_steps')
                        ->where('admin_process_run_id', $run->id)
                        ->orderBy('position')
                        ->get(['id', 'key', 'position']);

                    if ($steps->contains('key', 'import-indices')) {
                        return;
                    }

                    $marketcapPosition = $steps->search(fn (object $step): bool => $step->key === 'import-marketcap');

                    if ($marketcapPosition === false) {
                        return;
                    }

                    DB::table('admin_process_steps')
                        ->where('admin_process_run_id', $run->id)
                        ->increment('position', $steps->max('position') + 1);

                    foreach ($steps as $index => $step) {
                        DB::table('admin_process_steps')->where('id', $step->id)
                            ->update(['position' => $index + 1 + (int) ($index >= $marketcapPosition)]);
                    }

                    $arguments = ['--date='.$run->process_date];
                    DB::table('admin_process_steps')->insert([
                        'admin_process_run_id' => $run->id,
                        'position' => $marketcapPosition + 1,
                        'key' => 'import-indices',
                        'name' => 'Import indices',
                        'description' => 'Import index prices and valuations for the selected date.',
                        'command' => 'nse:import-indices',
                        'arguments' => json_encode($arguments, JSON_THROW_ON_ERROR),
                        'command_line' => 'php artisan nse:import-indices '.implode(' ', $arguments),
                        'is_preview' => false,
                        'status' => 'pending',
                        'attempts' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }, column: 'id');
        });
    }

    /**
     * Preserve saved step results and output when this data migration is rolled back.
     */
    public function down(): void {}
};
