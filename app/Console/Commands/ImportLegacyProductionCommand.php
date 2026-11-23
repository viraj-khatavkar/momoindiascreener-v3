<?php

namespace App\Console\Commands;

use App\Actions\LoadLegacyProductionImportAction;
use App\Actions\PrepareLegacyProductionImportAction;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use League\Flysystem\WhitespacePathNormalizer;
use RuntimeException;
use Throwable;

/** @phpstan-import-type ImportAudit from PrepareLegacyProductionImportAction */
class ImportLegacyProductionCommand extends Command
{
    protected $signature = 'legacy:import-production
        {directory : Directory with users.gz, orders.gz and screens.gz (or .sql files)}
        {--apply : Write the audited import to the destination}
        {--fresh : Verify a complete replacement. With --apply, truncate users, orders and screens without a backup}
        {--skip-unsupported-screens : Omit screens with unsupported settings; list their IDs in the report}
        {--source-timezone= : MySQL export session offset, such as +00:00 or +05:30; required with --apply}
        {--report= : New JSON report path relative to the private local storage disk}';

    protected $description = 'Audit and load users, orders and screens for the legacy production migration';

    public function handle(PrepareLegacyProductionImportAction $prepare, LoadLegacyProductionImportAction $load): int
    {
        try {
            $paths = $this->paths();
            $sourceTimezone = $this->option('source-timezone');

            if ($sourceTimezone !== null && ! preg_match('/^(?:[+-](?:0\d|1[0-3]):[0-5]\d|\+14:00)$/D', $sourceTimezone)) {
                throw new InvalidArgumentException('Use a numeric MySQL time zone offset, such as +00:00 or +05:30.');
            }

            if (DB::connection()->getDriverName() !== 'mysql') {
                throw new InvalidArgumentException('This import requires a MySQL destination.');
            }

            $report = $prepare->execute($paths);
            $report['source_time_zone'] = $sourceTimezone;
            $fresh = (bool) $this->option('fresh');
            $apply = (bool) $this->option('apply');
            $report['mode'] = $fresh ? 'fresh' : 'empty';
            $report['apply_requested'] = $apply;
            $report['status'] = 'source_audited';
            $reportPath = $this->saveReport($report);
            $this->showReport($report);
            $this->info('Audit report: '.$reportPath);

            $hasErrors = collect($report['issues'])->contains(fn (array $issue): bool => $issue['kind'] === 'error');
            $hasUnsupported = $report['unsupported_screen_ids'] !== [] && ! $this->option('skip-unsupported-screens');
            $hasDestinationRows = collect($report['tables'])->contains(fn (array $table): bool => $table['destination_rows'] > 0);

            if ($hasDestinationRows && ! $fresh) {
                $this->warn('The users, orders and screens destination tables must be empty before import.');
            }

            if ($hasErrors || $hasUnsupported || ($hasDestinationRows && ! $fresh)) {
                $this->error('Import blocked. No database rows were changed. Review the audit report.');

                return self::FAILURE;
            }

            if ($apply && $sourceTimezone === null) {
                throw new InvalidArgumentException('Confirm the export session time zone and provide --source-timezone before using --apply.');
            }

            if ($fresh || $apply) {
                $load->execute($paths, $report, $fresh, $apply, function (array $verified, array $references) use (&$report, $reportPath): void {
                    $report['staged_tables'] = $verified;
                    $report['unresolved_references'] = $references;
                    $report['status'] = array_filter($references) === [] ? 'staged' : 'blocked';
                    File::replace($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), 0600);
                });
            }

            if (! $apply) {
                $this->info($fresh
                    ? 'Preview only. All staged values and retained references passed. Add --apply to truncate and reload the three tables.'
                    : 'Preview only. No database rows were changed. Use --apply to import.');

                return self::SUCCESS;
            }

            $report['status'] = 'import_verified';
            $report['completed_at'] = now()->toIso8601String();
            try {
                File::replace($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), 0600);
            } catch (Throwable) {
                $this->error('The import committed and verified, but writing the completion status to the audit report failed.');

                return self::FAILURE;
            }
            $this->info('Import complete. Every stored row and column matched the audited data.');

            return self::SUCCESS;
        } catch (QueryException $exception) {
            $this->error('Database operation failed (SQLSTATE '.$exception->getCode().'). Check the destination before another attempt.');
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->error($exception->getMessage());
        } catch (Throwable) {
            $this->error('Import failed. Check the dump format, database access and destination row counts before another attempt.');
        }

        return self::FAILURE;
    }

    /** @return array<string, string> */
    private function paths(): array
    {
        $directory = realpath((string) $this->argument('directory'));

        if ($directory === false || ! is_dir($directory)) {
            throw new InvalidArgumentException('Provide a local directory that contains the three production dumps.');
        }

        $paths = [];

        foreach (array_keys(PrepareLegacyProductionImportAction::tables()) as $table) {
            $paths[$table] = $directory.'/'.$table.(is_file($directory.'/'.$table.'.gz') ? '.gz' : '.sql');
        }

        return $paths;
    }

    /** @param ImportAudit $report */
    private function saveReport(array $report): string
    {
        $path = $this->option('report') ?: 'migration-reports/production-'.now()->format('Ymd-His').'-'.Str::random(8).'.json';
        $path = (new WhitespacePathNormalizer)->normalizePath($path);
        $disk = Storage::disk('local');
        if (dirname($path) !== '.') {
            $disk->makeDirectory(dirname($path));
        }

        $absolute = $disk->path($path);
        $file = @fopen($absolute, 'xb');
        if ($file === false) {
            throw new RuntimeException($disk->exists($path)
                ? 'The report path already exists. Choose a new report path.'
                : 'Cannot create the private audit report.');
        }
        fclose($file);
        File::replace($absolute, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), 0600);

        return $absolute;
    }

    /** @param ImportAudit $report */
    private function showReport(array $report): void
    {
        $this->info('Destination database: '.$report['database']);
        $this->table(['Table', 'Source rows', 'Eligible rows', 'Destination rows'], collect($report['tables'])
            ->map(fn (array $table, string $name): array => [$name, $table['rows'], $table['eligible'], $table['destination_rows']])->all());
        $this->table(['Type', 'Field', 'Rows', 'Finding'], collect($report['issues'])
            ->map(fn (array $issue): array => [$issue['kind'], $issue['table'].'.'.$issue['column'], $issue['count'], $issue['message']])->values()->all());

        if ($report['unsupported_screen_ids'] !== []) {
            $this->warn(count($report['unsupported_screen_ids']).' distinct screens have unsupported settings.');

            if ($this->option('skip-unsupported-screens')) {
                $this->warn('These screens will be omitted. Their IDs are in the report and their data remains in the source dump.');
            }
        }

        $this->line('Export session time zone: '.($report['source_time_zone'] ?? 'unknown; required for --apply').'. Destination session time zone: '.$report['destination_time_zone'].'.');
        $this->line('Local market data: '.($report['market_data_range']['first'] ?? 'none').' to '.($report['market_data_range']['last'] ?? 'none').'.');
        $this->line('Sessions, password reset tokens, market data and other tables are outside this import.');
    }
}
