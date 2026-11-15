<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('admin_process_runs')->where('status', '!=', 'completed')->eachById(function (object $run): void {
            DB::transaction(function () use ($run): void {
                foreach (['RR', 'IV'] as $series) {
                    $steps = DB::table('admin_process_steps')->where('admin_process_run_id', $run->id)->orderBy('position')->get();
                    $key = 'import-corporate-actions-'.strtolower($series);
                    if ($steps->contains('key', $key)) {
                        continue;
                    }
                    $anchor = $steps->firstWhere('key', 'calculate-dividend-adjustment-factor');
                    if ($anchor === null) {
                        continue;
                    }
                    foreach ($steps->sortByDesc('position') as $step) {
                        if ($step->position >= $anchor->position) {
                            DB::table('admin_process_steps')->where('id', $step->id)->update(['position' => $step->position + 1]);
                        }
                    }
                    $date = substr($run->process_date, 0, 10);
                    $command = 'backtest:import-corporate-actions';
                    $arguments = ["--series={$series}", "--date={$date}"];
                    DB::table('admin_process_steps')->insert([
                        'admin_process_run_id' => $run->id, 'position' => $anchor->position, 'key' => $key,
                        'name' => "Import {$series} corporate actions",
                        'description' => "Import distributions, dividends, and interest for the {$series} series.",
                        'command' => $command, 'arguments' => json_encode($arguments, JSON_THROW_ON_ERROR),
                        'command_line' => implode(' ', ['php artisan', $command, ...$arguments]),
                        'is_preview' => false, 'status' => 'pending', 'attempts' => 0,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            });
        });
    }

    /** Execution history is retained. A forward migration must change saved steps. */
    public function down(): void {}
};
