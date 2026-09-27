<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->moveValuationSteps('mark-etfs');
    }

    public function down(): void
    {
        $this->moveValuationSteps('import-instruments', insertAfter: true);
    }

    private function moveValuationSteps(string $anchorKey, bool $insertAfter = false): void
    {
        DB::transaction(function () use ($anchorKey, $insertAfter): void {
            DB::table('admin_process_runs')
                ->where('process_date', '>=', '2024-03-01')
                ->where('status', '!=', 'completed')
                ->lockForUpdate()
                ->eachById(function (object $run) use ($anchorKey, $insertAfter): void {
                    $stepsByKey = DB::table('admin_process_steps')
                        ->where('admin_process_run_id', $run->id)
                        ->orderBy('position')
                        ->get(['id', 'key', 'position'])
                        ->keyBy('key');
                    $valuationKeys = ['import-marketcap', 'import-price-to-earnings'];

                    if (! $stepsByKey->has([$anchorKey, ...$valuationKeys])) {
                        return;
                    }

                    $orderedKeys = $stepsByKey->keys()
                        ->reject(fn (string $key): bool => in_array($key, $valuationKeys, true))
                        ->values()->all();
                    $anchorPosition = array_search($anchorKey, $orderedKeys, true);
                    array_splice($orderedKeys, $anchorPosition + (int) $insertAfter, 0, $valuationKeys);

                    if ($orderedKeys === $stepsByKey->keys()->all()) {
                        return;
                    }

                    DB::table('admin_process_steps')
                        ->where('admin_process_run_id', $run->id)
                        ->increment('position', $stepsByKey->max('position') + 1);

                    foreach ($orderedKeys as $index => $key) {
                        DB::table('admin_process_steps')
                            ->where('id', $stepsByKey->get($key)->id)
                            ->update(['position' => $index + 1]);
                    }
                }, column: 'id');
        });
    }
};
