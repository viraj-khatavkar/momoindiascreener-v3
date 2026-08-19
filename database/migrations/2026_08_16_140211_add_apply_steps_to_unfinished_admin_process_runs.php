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

    private const APPLY_STEPS = [
        'apply-dividend-adjustment-factor' => [
            'name' => 'Apply dividend adjustment factors',
            'description' => 'Save the calculated dividend adjustment factors.',
            'command' => 'backtest:calculate-dividend-adjustment-factor',
        ],
        'apply-dividends' => [
            'name' => 'Apply dividend adjustments',
            'description' => 'Apply dividend adjustment factors to historic prices.',
            'command' => 'backtest:adjust-dividends',
        ],
        'apply-corporate-action' => [
            'name' => 'Apply corporate action adjustments',
            'description' => 'Apply corporate action price factors to historic prices.',
            'command' => 'backtest:adjust-corporate-action',
        ],
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

                        $applyStep = self::APPLY_STEPS[$key] ?? null;

                        if (! $applyStep) {
                            continue;
                        }

                        $date = str($run->process_date)->before(' ')->toString();
                        $arguments = ["--date={$date}"];

                        DB::table('admin_process_steps')->insert([
                            'admin_process_run_id' => $run->id,
                            'position' => $position,
                            'key' => $key,
                            'name' => $applyStep['name'],
                            'description' => $applyStep['description'],
                            'command' => $applyStep['command'],
                            'arguments' => json_encode($arguments, JSON_THROW_ON_ERROR),
                            'command_line' => implode(' ', ['php artisan', $applyStep['command'], ...$arguments]),
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
     * This data migration is intentionally irreversible because removing an
     * apply step can remove its execution history and terminal output.
     */
    public function down(): void {}
};
