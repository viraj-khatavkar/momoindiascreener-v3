<?php

namespace App\Console\Commands;

use App\Actions\PrepareLegacyProductionImportAction;
use App\Actions\ReadLegacySqlDumpAction;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/** @phpstan-import-type ImportAudit from PrepareLegacyProductionImportAction */
class ImportLegacyProductionCommand extends Command
{
    protected $signature = 'legacy:import-production
        {directory : Directory with users.gz, orders.gz and screens.gz (or .sql files)}
        {--apply : Import into empty destination tables after the audit passes}
        {--skip-unsupported-screens : Omit screens with unsupported settings; list their IDs in the report}
        {--source-timezone= : MySQL export session offset, such as +00:00 or +05:30; required with --apply}
        {--report= : New JSON report path relative to the private local storage disk}';

    protected $description = 'Audit and import production users, orders and screens from TablePlus SQL dumps';

    public function handle(PrepareLegacyProductionImportAction $prepare, ReadLegacySqlDumpAction $reader): int
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
            $reportPath = $this->saveReport($report);
            $this->showReport($report);
            $this->info('Audit report: '.$reportPath);

            $hasErrors = collect($report['issues'])->contains(fn (array $issue): bool => $issue['kind'] === 'error');
            $hasUnsupported = $report['unsupported_screen_ids'] !== [] && ! $this->option('skip-unsupported-screens');
            $hasDestinationRows = collect($report['tables'])->contains(fn (array $table): bool => $table['destination_rows'] > 0);

            if ($hasDestinationRows) {
                $this->warn('The users, orders and screens destination tables must be empty before import.');
            }

            if ($hasErrors || $hasUnsupported || $hasDestinationRows) {
                $this->error('Import blocked. No database rows were changed. Review the audit report.');

                return self::FAILURE;
            }

            if (! $this->option('apply')) {
                $this->info('Preview only. No database rows were changed. Use --apply to import.');

                return self::SUCCESS;
            }

            if ($sourceTimezone === null) {
                throw new InvalidArgumentException('Confirm the export session time zone and provide --source-timezone before using --apply.');
            }

            $this->import($paths, $report, $prepare, $reader);
            $this->info('Import complete. Source IDs, passwords, timestamps and ownership links were preserved.');

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
        $disk = Storage::disk('local');

        if ($disk->exists($path)) {
            throw new InvalidArgumentException('The report path already exists. Choose a new report path.');
        }

        if (! $disk->put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), ['visibility' => 'private'])) {
            throw new RuntimeException('Cannot save the audit report. No data was imported.');
        }

        return $disk->path($path);
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

    /**
     * @param  array<string, string>  $paths
     * @param  ImportAudit  $report
     */
    private function import(array $paths, array $report, PrepareLegacyProductionImportAction $prepare, ReadLegacySqlDumpAction $reader): void
    {
        $connection = DB::connection();
        $previousTimezone = $connection->selectOne('SELECT @@session.time_zone AS zone')->zone;
        $lock = 'legacy-import:'.substr(hash('sha256', $connection->getDatabaseName()), 0, 40);

        if ((int) $connection->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lock])->acquired !== 1) {
            throw new RuntimeException('Another production import holds the database lock.');
        }

        try {
            $connection->statement('SET time_zone = ?', [$this->option('source-timezone')]);

            foreach (PrepareLegacyProductionImportAction::tables() as $table) {
                $engine = $connection->selectOne(
                    'SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                    [$connection->getTablePrefix().$table],
                );

                if ($engine?->engine !== 'InnoDB') {
                    throw new RuntimeException('All three destination tables must use InnoDB for a transaction-safe import.');
                }
            }

            $connection->transaction(function () use ($paths, $report, $prepare, $reader): void {
                foreach (PrepareLegacyProductionImportAction::tables() as $table) {
                    if (DB::table($table)->exists()) {
                        throw new RuntimeException('Destination tables are no longer empty. No data was imported.');
                    }
                }

                foreach (PrepareLegacyProductionImportAction::tables() as $table => $destination) {
                    if (hash_file('sha256', $paths[$table]) !== $report['tables'][$table]['sha256']) {
                        throw new RuntimeException('A source dump changed after the audit. No data was imported.');
                    }

                    $batch = [];

                    foreach ($reader->execute($paths[$table], $table) as $row) {
                        if ($table === 'screens' && isset($report['unsupported_screen_ids'][(int) $row['id']])) {
                            continue;
                        }

                        $batch[] = $prepare->normalize($table, $row);

                        if (count($batch) === 100) {
                            DB::table($destination)->insert($batch);
                            $batch = [];
                        }
                    }

                    if ($batch !== []) {
                        DB::table($destination)->insert($batch);
                    }

                    if (hash_file('sha256', $paths[$table]) !== $report['tables'][$table]['sha256'] || DB::table($destination)->count() !== $report['tables'][$table]['eligible']) {
                        throw new RuntimeException('The source or destination changed during import. The transaction was rolled back.');
                    }
                }
            });
        } finally {
            try {
                $connection->statement('SET time_zone = ?', [$previousTimezone]);
                $connection->selectOne('SELECT RELEASE_LOCK(?)', [$lock]);
            } catch (Throwable) {
                $connection->disconnect();
            }
        }
    }
}
