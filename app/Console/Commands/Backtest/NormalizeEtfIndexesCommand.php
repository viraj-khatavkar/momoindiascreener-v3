<?php

namespace App\Console\Commands\Backtest;

use App\Actions\ConsolidateMarketIndexesAction;
use App\Models\BacktestNseInstrument;
use App\Models\MarketIndex;
use App\Models\MarketIndexAlias;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class NormalizeEtfIndexesCommand extends Command
{
    protected $signature = 'backtest:normalize-etf-indexes {--apply : Save the changes after backing up the mapping tables}';

    protected $description = 'Standardize ETF index names and slugs, merge equivalent indexes, and resolve known source labels';

    public function handle(ConsolidateMarketIndexesAction $consolidate): int
    {
        if ($this->option('apply')) {
            $path = 'backups/etf-index-mappings-'.now()->format('Ymd-His').'-'.Str::random(6).'.json';
            $saved = Storage::disk('local')->put($path, json_encode([
                'market_indices' => MarketIndex::query()->get()->toArray(),
                'market_index_aliases' => MarketIndexAlias::query()->get()->toArray(),
                'backtest_nse_instruments' => BacktestNseInstrument::query()->get()->toArray(),
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            if (! $saved) {
                throw new RuntimeException('The ETF mapping backup could not be saved. No changes were made.');
            }

            $this->info('Backup: '.Storage::disk('local')->path($path));
        }

        DB::beginTransaction();

        try {
            $report = $consolidate->execute();

            if ($this->option('apply')) {
                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        $this->table(['Previous slug', 'Canonical slug'], $report['changes']);
        $this->info($report['merged'].' duplicate index records merged.');
        $this->info($report['approved'].' pending labels matched to existing indexes.');
        $this->info(count($report['pending']).' labels still need review.');

        if ($report['pending'] !== []) {
            $this->table(['Source label', 'ETF', 'Suggested slug'], $report['pending']);
        }
        $this->info($this->option('apply') ? 'ETF index mappings saved.' : 'Preview only. No changes saved. Use --apply to save.');

        return self::SUCCESS;
    }
}
