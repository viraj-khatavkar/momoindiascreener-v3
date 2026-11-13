<?php

use App\Http\Controllers\BacktestCsvController;
use App\Models\Backtest;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

it('exports one consistent result set when another connection replaces results between batches', function (string $type) {
    $configuration = DB::connection()->getConfig();
    unset($configuration['name']);
    config([
        'database.connections.csv_export_test' => [...$configuration, 'isolation_level' => 'READ COMMITTED'],
        'database.connections.csv_writer_test' => $configuration,
    ]);
    $writer = DB::connection('csv_writer_test');
    $user = User::factory()->connection('csv_writer_test')->create(['is_paid' => true]);
    $backtest = null;

    try {
        $backtest = Backtest::factory()->connection('csv_writer_test')->create(['user_id' => $user->id]);
        $table = $type === 'trades' ? 'backtest_trades' : 'backtest_daily_snapshots';
        $originalRows = [];
        $replacementRows = [];
        for ($index = 0; $index < 1001; $index++) {
            if ($type === 'trades') {
                $row = [
                    'backtest_id' => $backtest->id, 'symbol' => 'ORIGINAL', 'name' => 'Original',
                    'trade_type' => 'buy', 'reason' => 'New entry', 'date' => '2024-01-01',
                    'quantity' => 1, 'price' => 100, 'raw_price' => 100, 'gross_amount' => 100,
                    'brokerage' => 0, 'stt' => 0, 'transaction_charges' => 0, 'sebi_charges' => 0,
                    'gst' => 0, 'stamp_charges' => 0, 'total_charges' => 0, 'net_amount' => 100,
                ];
                $replacementRows[] = [...$row, 'symbol' => 'REPLACEMENT'];
            } else {
                $row = [
                    'backtest_id' => $backtest->id, 'date' => Carbon::parse('2020-01-01')->addDays($index)->toDateString(),
                    'nav' => 100, 'portfolio_value' => 100, 'cash' => 0, 'total_value' => 100, 'holdings_count' => 1,
                ];
                $replacementRows[] = [...$row, 'nav' => 900];
            }
            $originalRows[] = $row;
        }
        foreach (array_chunk($originalRows, 500) as $chunk) {
            $writer->table($table)->insert($chunk);
        }

        $replaced = false;
        $readBatches = 0;
        DB::listen(function (QueryExecuted $query) use ($writer, $table, $backtest, $replacementRows, &$replaced, &$readBatches): void {
            if ($query->connectionName !== 'csv_export_test'
                || ! str_starts_with($query->sql, 'select') || ! str_contains($query->sql, $table)) {
                return;
            }
            $readBatches++;
            if ($replaced) {
                return;
            }
            $replaced = true;
            $writer->transaction(function () use ($writer, $table, $backtest, $replacementRows): void {
                $writer->table($table)->where('backtest_id', $backtest->id)->delete();
                foreach (array_chunk($replacementRows, 500) as $chunk) {
                    $writer->table($table)->insert($chunk);
                }
            });
        });

        $request = Request::create('/backtests/'.$backtest->id.'/csv/'.$type);
        $request->setUserResolver(fn (): User => $user);
        $export = (clone $backtest)->setConnection('csv_export_test');
        $response = app(BacktestCsvController::class)($request, $export, $type);
        $content = TestResponse::fromBaseResponse($response)->streamedContent();
        $lines = explode("\n", trim($content));
        $lastRow = str_getcsv($lines[array_key_last($lines)], escape: '');

        expect($replaced)->toBeTrue()
            ->and($readBatches)->toBe(2)
            ->and($lines)->toHaveCount(1002)
            ->and($writer->table($table)->where('backtest_id', $backtest->id)->count())->toBe(1001);
        if ($type === 'trades') {
            expect($lastRow[1])->toBe('ORIGINAL')
                ->and($content)->not->toContain('REPLACEMENT');
        } else {
            expect((float) $lastRow[1])->toBe(100.0);
        }
    } finally {
        $backtest?->delete();
        $user->delete();
        DB::purge('csv_export_test');
        DB::purge('csv_writer_test');
    }
})->with(['trades', 'nav']);
