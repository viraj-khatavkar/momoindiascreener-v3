<?php

namespace App\Console\Commands\Backtest;

use App\Actions\ReadCsvAction;
use App\Models\BacktestNseInstrument;
use App\Models\BacktestNseInstrumentPrice;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
     * Maps the raw NSE "UNDERLYING" label (squished + upper-cased) to a canonical
     * index slug. Slugs align with nse_indices.slug where an NSE index exists;
     * non-NSE underlyings (gold, BSE, international, money market) use sensible slugs.
     *
     * @var array<string, string>
     */
    private const INDEX_NORMALIZATION_MAP = [
        'NIFTY' => 'nifty-50',
        'NIFTY 50' => 'nifty-50',
        'NIFTY50' => 'nifty-50',
        'NIFTY 100' => 'nifty-100',
        'LIC MF EXCHANGE TRADED FUND- NIFTY 100' => 'nifty-100',
        'NIFTY BANK' => 'nifty-bank',
        'NIFTY NEXT 50' => 'nifty-next-50',
        'NIFTY NEXT 50 ETF' => 'nifty-next-50',
        'NIFTY MIDCAP 100' => 'nifty-midcap-100',
        'NIFTY MIDCAP 150' => 'nifty-midcap-150',
        'NIFTY CPSE' => 'nifty-cpse',
        'NIFTY PRIVATE BANK INDEX' => 'nifty-private-bank',
        'NIFTY PSU BANK INDEX' => 'nifty-psu-bank',
        'NIFTY1D RATE INDEX' => 'nifty-1d-rate-index',
        'NIFTY 1D RATE INDEX' => 'nifty-1d-rate-index',
        'NIFTY50 VALUE 20' => 'nifty50-value-20',
        'NIFTY 100 LOW VOLATILITY 30 INDEX' => 'nifty100-low-volatility-30',
        'NIFTY 200 QUALITY 30 INDEX' => 'nifty200-quality-30',
        'NIFTY QUALITY 30' => 'nifty100-quality-30',
        'NIFTY CONSUMPTION INDEX' => 'nifty-india-consumption',
        'NIFTY INFRA' => 'nifty-infrastructure',
        'NIFTY DIV OPPS 50' => 'nifty-dividend-opportunities-50',
        'NIFTY 10 YR BENCHMARK G-SEC INDEX' => 'nifty-10-yr-benchmark-g-sec',
        'NIFTY GS 8 13YR' => 'nifty-8-13-yr-g-sec',
        'NIFTY BHARAT BOND' => 'nifty-bharat-bond',
        'GSEC 10 NSE INDEX' => 'gsec-10',
        'SENSEX' => 'sensex',
        'BSE SENSEX NEXT 50' => 'bse-sensex-next-50',
        'NASDAQ100' => 'nasdaq-100',
        'HANG SENG INDEX' => 'hang-seng',
        'S&P BSE 500 INDEX' => 'bse-500',
        'S&P BSE BHARAT 22 INDEX' => 'bse-bharat-22',
        'S&P BSE LIQUID RATE INDEX' => 'bse-liquid-rate',
        'S&P BSE MIDCAP SELECT INDEX' => 'bse-midcap-select',
        'GOLD' => 'gold',
        'CALL MONEY SHORT TERM G-SECS & MONEY MARKET INSTR' => 'liquid',
        'SHARIAH INDEX' => 'nifty50-shariah',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
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

        $this->populateEtfIndexes($date);

        return Command::SUCCESS;
    }

    /**
     * Populate the etf_index column from the ETF file for the given date.
     * Symbols not listed in that file keep their existing value (null for non-ETFs).
     */
    protected function populateEtfIndexes(string $date): void
    {
        $relativePath = 'uploads/'.(new Carbon($date))->format('Y-m-d').'/etf.csv';

        if (! Storage::exists($relativePath)) {
            $this->info('No ETF file found for '.$date.', skipping etf_index population.');

            return;
        }

        /** @var ReadCsvAction $readCsvAction */
        $readCsvAction = app(ReadCsvAction::class);

        $rows = $readCsvAction->execute(Storage::path($relativePath))->toCollection();

        $seen = 0;
        $updated = 0;
        $unmapped = [];

        foreach ($rows as $row) {
            if (! isset($row[2], $row[14])) {
                continue;
            }

            $symbol = trim($row[2]);

            if ($symbol === '') {
                continue;
            }

            $seen++;

            $rawIndex = $row[14];
            $slug = $this->normalizeIndex($rawIndex);

            if (! $this->isKnownIndex($rawIndex)) {
                $unmapped[Str::squish($rawIndex)] = $slug;
            }

            $updated += BacktestNseInstrument::query()
                ->where('symbol', $symbol)
                ->update(['etf_index' => $slug]);
        }

        $this->info($updated.' etf_index values populated from '.$seen.' ETFs in the file for '.$date.'.');

        $this->warnAboutUnmappedIndexes($unmapped);
    }

    /**
     * Warn about underlying labels that had no curated mapping and were slugged
     * via the fallback, so a canonical entry can be added to the map.
     *
     * @param  array<string, string>  $unmapped  raw label => fallback slug
     */
    protected function warnAboutUnmappedIndexes(array $unmapped): void
    {
        if ($unmapped === []) {
            return;
        }

        $this->warn(count($unmapped).' unmapped ETF index(es) slugged via fallback — add to INDEX_NORMALIZATION_MAP for a canonical slug:');

        foreach ($unmapped as $rawIndex => $slug) {
            $this->warn('  "'.$rawIndex.'" => '.$slug);
        }
    }

    /**
     * Normalize a raw NSE underlying label into a canonical index slug.
     * Known labels use the curated map; anything else falls back to a slug of
     * the raw label so new/unmapped indexes still get a consistent value.
     */
    public function normalizeIndex(string $rawIndex): string
    {
        $key = $this->normalizeKey($rawIndex);

        return self::INDEX_NORMALIZATION_MAP[$key] ?? Str::slug($key);
    }

    /**
     * Whether the raw underlying label has an explicit (curated) mapping.
     */
    public function isKnownIndex(string $rawIndex): bool
    {
        return isset(self::INDEX_NORMALIZATION_MAP[$this->normalizeKey($rawIndex)]);
    }

    /**
     * Normalize a raw underlying label into a stable lookup key.
     */
    private function normalizeKey(string $rawIndex): string
    {
        return Str::of($rawIndex)->squish()->upper()->toString();
    }
}
