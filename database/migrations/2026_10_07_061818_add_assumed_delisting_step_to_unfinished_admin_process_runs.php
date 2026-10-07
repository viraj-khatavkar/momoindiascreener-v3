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
            DB::table('admin_process_runs')->where('status', '!=', 'completed')
                ->orderBy('id')->eachById(function (object $run): void {
                    $steps = DB::table('admin_process_steps')->where('admin_process_run_id', $run->id);
                    if ((clone $steps)->where('key', 'update-assumed-delistings')->exists()) {
                        return;
                    }

                    $arguments = ['--date='.$run->process_date];
                    DB::table('admin_process_steps')->insert([
                        'admin_process_run_id' => $run->id,
                        'position' => ((clone $steps)->max('position') ?? 0) + 1,
                        'key' => 'update-assumed-delistings',
                        'name' => 'Update assumed delistings',
                        'description' => 'Update shared 100-trading-day gaps after all price data is complete. Backtests reuse these records.',
                        'command' => 'backtest:update-assumed-delistings',
                        'arguments' => json_encode($arguments, JSON_THROW_ON_ERROR),
                        'command_line' => 'php artisan backtest:update-assumed-delistings '.implode(' ', $arguments),
                        'is_preview' => false,
                        'status' => 'pending',
                        'attempts' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }, column: 'id');
        });
    }

    /** Preserve saved step results and output on rollback, like other checklist migrations. */
    public function down(): void {}
};
