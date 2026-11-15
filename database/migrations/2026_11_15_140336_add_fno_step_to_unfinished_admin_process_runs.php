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
        DB::table('admin_process_runs')->where('status', '!=', 'completed')->eachById(function (object $run): void {
            DB::transaction(function () use ($run): void {
                $steps = DB::table('admin_process_steps')->where('admin_process_run_id', $run->id)->orderBy('position')->get();
                if ($steps->contains('key', 'import-fno-constituents')) {
                    return;
                }
                $anchor = $steps->firstWhere('key', 'calculate-t-percent');
                if ($anchor === null) {
                    return;
                }
                foreach ($steps->sortByDesc('position') as $step) {
                    if ($step->position >= $anchor->position) {
                        DB::table('admin_process_steps')->where('id', $step->id)->update(['position' => $step->position + 1]);
                    }
                }
                $date = substr($run->process_date, 0, 10);
                $command = 'backtest:import-fno-constituents';
                $arguments = ["--date={$date}"];
                DB::table('admin_process_steps')->insert([
                    'admin_process_run_id' => $run->id, 'position' => $anchor->position, 'key' => 'import-fno-constituents',
                    'name' => 'Import F&O membership',
                    'description' => 'Set F&O stock membership from the NSE file for this date.',
                    'command' => $command, 'arguments' => json_encode($arguments, JSON_THROW_ON_ERROR),
                    'command_line' => implode(' ', ['php artisan', $command, ...$arguments]),
                    'is_preview' => false, 'status' => 'pending', 'attempts' => 0,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            });
        });
    }

    /** Execution history is retained. A forward migration must change saved steps. */
    public function down(): void {}
};
