<?php

namespace App\Console\Commands\Backtest;

use App\Actions\ReadCsvAction;
use App\Actions\ResolveMarketIndexAliasAction;
use App\Enums\MarketIndexAliasStatusEnum;
use App\Models\BacktestNseInstrument;
use App\Models\BacktestNseInstrumentPrice;
use App\Models\MarketIndexAlias;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CopyInstrumentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backtest:copy-instruments {--date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Copies the symbols traded on a given date into backtest_nse_instruments and populates etf_index (normalized) from that date ETF file';

    /**
     * Execute the console command.
     */
    public function handle(
        ResolveMarketIndexAliasAction $resolveMarketIndexAlias,
        ReadCsvAction $readCsvAction,
    ): int {
        $date = $this->option('date');

        if (is_null($date)) {
            $this->error('Please provide a date');

            return Command::FAILURE;
        }

        $this->info('Copying instruments for '.$date.'...');

        $sourceSymbols = BacktestNseInstrumentPrice::query()
            ->where('date', $date)
            ->select('symbol')
            ->distinct()
            ->pluck('symbol');

        $existingSymbols = BacktestNseInstrument::query()->pluck('symbol');

        $newSymbols = $sourceSymbols->diff($existingSymbols)->values();

        if ($newSymbols->isNotEmpty()) {
            BacktestNseInstrument::query()->insert(
                $newSymbols->map(fn (string $symbol): array => ['symbol' => $symbol])->all()
            );
        }

        $this->info($newSymbols->count().' instruments copied. '.($sourceSymbols->count() - $newSymbols->count()).' already present.');

        $this->populateEtfIndexes($date, $resolveMarketIndexAlias, $readCsvAction);

        return Command::SUCCESS;
    }

    /**
     * Populate the etf_index column from the ETF file for the given date.
     * Symbols not listed in that file keep their existing value (null for non-ETFs).
     */
    protected function populateEtfIndexes(
        string $date,
        ResolveMarketIndexAliasAction $resolveMarketIndexAlias,
        ReadCsvAction $readCsvAction,
    ): void {
        $relativePath = 'uploads/'.(new Carbon($date))->format('Y-m-d').'/etf.csv';

        if (! Storage::exists($relativePath)) {
            $this->info('No ETF file found for '.$date.', skipping etf_index population.');

            return;
        }

        $rows = $readCsvAction->execute(Storage::path($relativePath))->toCollection();

        $seen = 0;
        $populated = 0;
        $pendingAliases = [];

        foreach ($rows as $row) {
            if (! isset($row[2], $row[14])) {
                continue;
            }

            $symbol = trim($row[2]);

            if ($symbol === '') {
                continue;
            }

            $seen++;

            $marketIndexAlias = $resolveMarketIndexAlias->execute($row[14], $symbol, $date);
            $slug = $marketIndexAlias->status === MarketIndexAliasStatusEnum::Approved
                ? $marketIndexAlias->marketIndex?->slug
                : null;

            if ($marketIndexAlias->status === MarketIndexAliasStatusEnum::Pending) {
                $pendingAliases[$marketIndexAlias->id] = $marketIndexAlias;
            }

            $updated = BacktestNseInstrument::query()
                ->where('symbol', $symbol)
                ->where(function ($query) use ($date) {
                    $query->whereNull('etf_index_source_date')
                        ->orWhere('etf_index_source_date', '<=', $date);
                })
                ->update([
                    'etf_index' => $slug,
                    'market_index_alias_id' => $marketIndexAlias->id,
                    'etf_index_source_date' => $date,
                ]);

            if ($slug !== null) {
                $populated += $updated;
            }
        }

        $this->info($populated.' etf_index values populated from '.$seen.' ETFs in the file for '.$date.'.');

        $this->warnAboutPendingAliases($pendingAliases);
    }

    /**
     * @param  array<int, MarketIndexAlias>  $pendingAliases
     */
    protected function warnAboutPendingAliases(array $pendingAliases): void
    {
        if ($pendingAliases === []) {
            return;
        }

        $this->warn(count($pendingAliases).' ETF index label(s) need review:');

        foreach ($pendingAliases as $marketIndexAlias) {
            $suggestion = $marketIndexAlias->suggestedMarketIndex?->slug
                ?? $marketIndexAlias->suggested_slug;

            $this->warn('  "'.$marketIndexAlias->source_label.'" ('.$marketIndexAlias->sample_symbol.') => '.$suggestion);
        }

        $this->warn('Review these labels in Admin > ETF Index Mappings.');
    }
}
