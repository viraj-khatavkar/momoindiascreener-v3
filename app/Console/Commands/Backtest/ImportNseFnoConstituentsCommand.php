<?php

namespace App\Console\Commands\Backtest;

use App\Actions\Backtest\ImportNseFnoMembershipAction;
use App\Actions\ReadNseFnoMembershipAction;
use App\Enums\NseIndexEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Throwable;

class ImportNseFnoConstituentsCommand extends Command
{
    protected $signature = 'backtest:import-fno-constituents
        {--date= : Trading date to import}
        {--file= : NSE F&O UDiFF CSV; defaults to the dated NSE Files upload}
        {--symbol-changes= : NSE symbolchange.csv path}
        {--dry-run : Check the file and symbol matches without changing membership}';

    protected $description = 'Import daily NSE F&O stock membership into price flags';

    public function handle(ReadNseFnoMembershipAction $reader, ImportNseFnoMembershipAction $importer): int
    {
        $date = $this->option('date');
        if (Validator::make(['date' => $date], ['date' => ['required', 'date_format:Y-m-d']])->fails()) {
            $this->error('Use --date=YYYY-MM-DD.');

            return self::FAILURE;
        }
        if (in_array($date, NseIndexEnum::FNO_IGNORED_DATES, true)) {
            $this->info("{$date}: skipped the excluded special session.");

            return self::SUCCESS;
        }
        $lock = Cache::lock('nse-fno-membership:'.$date, 180);
        try {
            if (! $lock->get()) {
                throw new RuntimeException('Another F&O import is running for this date.');
            }
            $path = $this->option('file') ?? Storage::path("uploads/{$date}/fno.csv");
            if (! is_file($path)) {
                throw new RuntimeException('Upload the F&O CSV in NSE Files, or provide --file.');
            }
            $changesPath = $this->option('symbol-changes') ?? (is_file(storage_path('app/symbolchange.csv')) ? storage_path('app/symbolchange.csv') : null);
            $symbols = $reader->execute($path, $date);
            $dryRun = (bool) $this->option('dry-run');
            $result = $importer->execute($date, $symbols, $changesPath, $dryRun);
            $this->info("{$date}: ".count($result['mappings']).' F&O symbols matched.');
            $this->info($dryRun ? 'Dry run: no membership was changed.' : 'F&O membership saved in the price flags.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
