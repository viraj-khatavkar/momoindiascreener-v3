<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const STEP_ORDER = [
        'check-instruments',
        'import-instruments',
        'import-corporate-actions-be',
        'import-corporate-actions-eq',
        'import-corporate-actions-sm',
        'import-corporate-actions-st',
        'import-corporate-actions-bz',
        'calculate-dividend-adjustment-factor',
        'apply-dividend-adjustment-factor',
        'adjust-dividends',
        'apply-dividends',
        'adjust-corporate-action',
        'apply-corporate-action',
        'mark-etfs',
        'import-constituents',
        'calculate-t-percent',
        'process-daily-data',
        'calculate-market-heartbeat',
        'copy-instruments',
    ];

    private const CORPORATE_ACTION_SERIES = [
        'import-corporate-actions-st' => 'ST',
        'import-corporate-actions-bz' => 'BZ',
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('admin_process_runs')
                ->where('status', '!=', 'completed')
                ->orderBy('id')
                ->eachById(function (object $run): void {
                    $stepsByKey = DB::table('admin_process_steps')
                        ->where('admin_process_run_id', $run->id)
                        ->get()
                        ->keyBy('key');

                    DB::table('admin_process_steps')
                        ->where('admin_process_run_id', $run->id)
                        ->increment('position', 100);

                    foreach (self::STEP_ORDER as $index => $key) {
                        $position = $index + 1;
                        $existingStep = $stepsByKey->get($key);

                        if ($existingStep) {
                            DB::table('admin_process_steps')
                                ->where('id', $existingStep->id)
                                ->update(['position' => $position]);

                            continue;
                        }

                        $series = self::CORPORATE_ACTION_SERIES[$key] ?? null;

                        if (! $series) {
                            continue;
                        }

                        $date = str($run->process_date)->before(' ')->toString();
                        $command = 'backtest:import-corporate-actions';
                        $arguments = ["--series={$series}", "--date={$date}"];

                        DB::table('admin_process_steps')->insert([
                            'admin_process_run_id' => $run->id,
                            'position' => $position,
                            'key' => $key,
                            'name' => "Import {$series} corporate actions",
                            'description' => "Import corporate actions for the {$series} series.",
                            'command' => $command,
                            'arguments' => json_encode($arguments, JSON_THROW_ON_ERROR),
                            'command_line' => implode(' ', ['php artisan', $command, ...$arguments]),
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
     * This data migration is intentionally irreversible because removing a
     * step can remove its execution history and terminal output.
     */
    public function down(): void {}
};
