<?php

namespace App\Actions;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\LazyCollection;
use RuntimeException;
use Throwable;

/** @phpstan-import-type ImportAudit from PrepareLegacyProductionImportAction */
class LoadLegacyProductionImportAction
{
    public function __construct(
        private ReadLegacySqlDumpAction $reader,
        private PrepareLegacyProductionImportAction $prepare,
    ) {}

    /**
     * @param  array<string, string>  $paths
     * @param  ImportAudit  $report
     * @param  Closure(array, array): void  $savePreflight
     */
    public function execute(array $paths, array $report, bool $fresh, bool $apply, Closure $savePreflight): void
    {
        $connection = DB::connection();
        $tables = PrepareLegacyProductionImportAction::tables();
        $previous = $connection->selectOne('SELECT @@session.time_zone AS zone, @@session.foreign_key_checks AS fk, @@session.lock_wait_timeout AS lock_timeout', [], false);
        $lock = 'legacy-import:'.substr(hash('sha256', $connection->getDatabaseName()), 0, 40);

        if (! $previous->fk) {
            throw new RuntimeException('Enable foreign key checks before running the import.');
        }

        if ((int) $connection->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lock], false)->acquired !== 1) {
            throw new RuntimeException('Another production import holds the database lock.');
        }

        $stages = [];
        $verified = [];
        $truncationStarted = false;
        $session = $connection->getPdo();
        $grammar = $connection->getQueryGrammar();

        try {
            $connection->statement('SET time_zone = ?', [$report['source_time_zone'] ?? $previous->zone]);

            foreach ($tables as $table => $destination) {
                $physical = $connection->getTablePrefix().$destination;
                $metadata = $connection->selectOne('SELECT ENGINE AS engine,
                    (SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND EVENT_OBJECT_TABLE = ?) AS triggers_count
                    FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$physical, $physical], false);

                if ($metadata?->engine !== 'InnoDB') {
                    throw new RuntimeException("The {$destination} table must use InnoDB for a verified import.");
                }
                if ($fresh && $metadata->triggers_count > 0) {
                    throw new RuntimeException("Review the write triggers on {$destination} before a fresh import.");
                }

                $stage = 'legacy_import_'.$destination;
                $connection->statement('CREATE TEMPORARY TABLE '.$grammar->wrapTable($stage).' LIKE '.$grammar->wrapTable($destination));
                $stages[$table] = $stage;
                $this->stage($paths[$table], $table, $stage, $report);
                $verified[$table] = ['rows' => DB::table($stage)->useWritePdo()->count(), 'sha256' => $this->fingerprint($stage)];

                if ($verified[$table]['rows'] !== $report['tables'][$table]['eligible']) {
                    throw new RuntimeException("The staged {$table} count differs from the audit.");
                }
            }

            $references = $this->unresolvedReferences($stages);
            $savePreflight($verified, $references);

            if (array_filter($references) !== []) {
                throw new RuntimeException('The exports would leave unresolved ownership links. See the reference counts in the audit.');
            }

            $this->checkFiles($paths, $report);

            if (! $apply) {
                return;
            }

            if ($fresh) {
                if ($connection->transactionLevel() > 0) {
                    throw new RuntimeException('MySQL truncation must run outside an existing transaction.');
                }

                // Never replay a TRUNCATE on a reconnected session that lost our staging tables and lock.
                if ($session !== $connection->getPdo()) {
                    throw new RuntimeException('The MySQL session changed after staging. Repeat the audit.');
                }

                $session->exec('SET SESSION lock_wait_timeout = '.min(15, (int) $previous->lock_timeout));
                $session->exec('SET SESSION FOREIGN_KEY_CHECKS = 0');

                try {
                    foreach (array_reverse($tables) as $destination) {
                        $truncationStarted = true;
                        $session->exec('TRUNCATE TABLE '.$grammar->wrapTable($destination));
                    }
                } finally {
                    $session->exec('SET SESSION FOREIGN_KEY_CHECKS = 1');
                }
            }

            $connection->transaction(function () use ($tables, $stages, $verified, $report, $paths, $fresh): void {
                foreach ($tables as $table => $destination) {
                    if (DB::table($destination)->lockForUpdate()->first(['id']) !== null) {
                        throw new RuntimeException('Destination tables are no longer empty. No rows were inserted.');
                    }

                    $columns = $report['tables'][$table]['destination_columns'];
                    $inserted = DB::table($destination)->insertUsing($columns, DB::table($stages[$table])->select($columns)->orderBy('id'));

                    if ($inserted !== $verified[$table]['rows'] || $this->fingerprint($destination) !== $verified[$table]['sha256']) {
                        throw new RuntimeException("Stored {$table} values differ from the audited export.");
                    }
                }

                if (array_filter($this->unresolvedReferences($tables)) !== []) {
                    throw new RuntimeException('A retained ownership link changed during the import.');
                }

                if (! $fresh) {
                    $this->checkFiles($paths, $report);
                }
            }, attempts: 3);

            if ($fresh) {
                foreach ($tables as $table => $destination) {
                    $next = BigInteger::max($report['tables'][$table]['source_auto_increment'] ?? 1, BigInteger::of($report['tables'][$table]['max_id'])->plus(1));
                    $target = $grammar->wrapTable($destination);
                    $session->exec("ALTER TABLE {$target} AUTO_INCREMENT = {$next}");
                    $definition = $connection->selectOne('SHOW CREATE TABLE '.$target, [], false)->{'Create Table'};

                    if (! preg_match('/\bAUTO_INCREMENT=(\d+)\b/', $definition, $option) || $option[1] !== (string) $next) {
                        throw new RuntimeException("The next ID for {$destination} differs from the audited sequence.");
                    }
                }
            }
        } catch (Throwable $exception) {
            if ($truncationStarted) {
                throw new RuntimeException('The fresh import was interrupted after truncation began. Rerun --fresh --apply with the same audited dumps.', previous: $exception);
            }

            throw $exception;
        } finally {
            try {
                if ($fresh && $apply) {
                    $connection->statement('SET SESSION FOREIGN_KEY_CHECKS = 1');
                    $connection->statement('SET SESSION lock_wait_timeout = ?', [(int) $previous->lock_timeout]);
                }
                foreach ($stages as $stage) {
                    $connection->statement('DROP TEMPORARY TABLE '.$connection->getQueryGrammar()->wrapTable($stage));
                }
                $connection->statement('SET time_zone = ?', [$previous->zone]);
                $connection->selectOne('SELECT RELEASE_LOCK(?)', [$lock], false);
            } catch (Throwable) {
                $connection->disconnect();
            }
        }
    }

    /** @param ImportAudit $report */
    private function stage(string $path, string $table, string $stage, array $report): void
    {
        $types = array_column(Schema::getColumns(PrepareLegacyProductionImportAction::tables()[$table]), 'type_name', 'name');
        $batches = LazyCollection::make(fn () => $this->reader->execute($path, $table))
            ->reject(fn (array $row): bool => $table === 'screens' && isset($report['unsupported_screen_ids'][(int) $row['id']]))
            ->map(fn (array $row): array => $this->prepare->normalize($table, $row))
            ->chunk(100);

        foreach ($batches as $batch) {
            $rows = $batch->all();
            DB::table($stage)->insert($rows);
            $stored = DB::table($stage)->useWritePdo()->whereIntegerInRaw('id', array_column($rows, 'id'))->get()->keyBy('id');

            foreach ($rows as $row) {
                $record = $stored->get($row['id']);

                foreach ($row as $column => $value) {
                    $actual = $record?->{$column};
                    if ($record === null || ! $this->sameValue($types[$column], $value, $actual)) {
                        throw new RuntimeException("The staged {$table}.{$column} differs from the export at ID {$row['id']}.");
                    }
                }
            }
        }

        if ($table === 'orders') {
            foreach (PrepareLegacyProductionImportAction::ORDER_IDENTIFIERS as $column) {
                if (DB::table($stage)->useWritePdo()->select($column)->whereNotNull($column)->groupBy($column)->havingRaw('COUNT(*) > 1')->exists()) {
                    throw new RuntimeException("Duplicate orders.{$column} under the destination collation.");
                }
            }
        }
    }

    private function sameValue(string $type, ?string $expected, mixed $actual): bool
    {
        if ($expected === null || $actual === null) {
            return $expected === $actual;
        }

        return match ($type) {
            'decimal', 'bigint', 'int', 'tinyint' => BigDecimal::of($expected)->isEqualTo((string) $actual),
            'json' => json_decode($expected, true, flags: JSON_THROW_ON_ERROR) === json_decode($actual, true, flags: JSON_THROW_ON_ERROR),
            default => $expected === (string) $actual,
        };
    }

    private function fingerprint(string $table): string
    {
        $hash = hash_init('sha256');
        foreach (DB::table($table)->useWritePdo()->orderBy('id')->cursor() as $row) {
            hash_update($hash, serialize((array) $row));
        }

        return hash_final($hash);
    }

    /**
     * @param  array<string, string>  $incoming  Physical data comes from staged tables in preflight and live tables after insertion.
     * @return array<string, int>
     */
    private function unresolvedReferences(array $incoming): array
    {
        $connection = DB::connection();
        $grammar = $connection->getQueryGrammar();
        $database = $connection->getDatabaseName();
        $prefix = $connection->getTablePrefix();
        $destinations = PrepareLegacyProductionImportAction::tables();
        $parents = [];
        foreach ($destinations as $table => $destination) {
            $parents[$prefix.$destination] = $grammar->wrapTable($incoming[$table]);
        }

        $keys = $connection->selectFromWriteConnection('SELECT TABLE_SCHEMA AS child_schema, TABLE_NAME AS child_table, COLUMN_NAME AS child_column,
            REFERENCED_TABLE_NAME AS parent_table, REFERENCED_COLUMN_NAME AS parent_column, ORDINAL_POSITION AS position
            FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
            AND REFERENCED_TABLE_NAME IN (?, ?, ?) ORDER BY TABLE_SCHEMA, TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION', array_keys($parents));

        $sessions = config('session.table', 'sessions');
        if (Schema::hasColumn($sessions, 'user_id')) {
            $keys[] = (object) ['child_schema' => $database, 'child_table' => $prefix.$sessions, 'child_column' => 'user_id',
                'parent_table' => $prefix.$destinations['users'], 'parent_column' => 'id', 'position' => 1];
        }

        $missing = [];
        foreach ($keys as $key) {
            if ((int) $key->position !== 1) {
                throw new RuntimeException("Review the composite foreign key on {$key->child_schema}.{$key->child_table} before importing.");
            }

            $importedChild = $key->child_schema === $database && isset($parents[$key->child_table]);
            $child = $importedChild ? $parents[$key->child_table] : $grammar->wrap($key->child_schema).'.'.$grammar->wrap($key->child_table);
            $parent = $parents[$key->parent_table];
            $fk = $grammar->wrap($key->child_column);
            $pk = $grammar->wrap($key->parent_column);
            $original = '';
            $unresolved = "p.{$pk} IS NULL";

            // A retained portfolio, session or export must still belong to the same account when IDs overlap.
            if ($incoming !== $destinations && ! $importedChild && $key->parent_column === 'id') {
                $live = $grammar->wrap($database).'.'.$grammar->wrap($key->parent_table);
                $original = "LEFT JOIN {$live} original ON original.id = c.{$fk}";
                $changedIdentity = match ($key->parent_table) {
                    $prefix.$destinations['users'] => 'original.email <> p.email AND (original.created_at IS NULL OR NOT (original.created_at <=> p.created_at))',
                    $prefix.$destinations['orders'] => 'original.order_id <> p.order_id OR original.user_id <> p.user_id',
                    $prefix.$destinations['screens'] => 'original.user_id <> p.user_id',
                };
                $unresolved .= " OR (original.id IS NOT NULL AND ({$changedIdentity}))";
            }

            $label = "{$key->child_schema}.{$key->child_table}.{$key->child_column} -> {$key->parent_table}.{$key->parent_column}";
            $missing[$label] = (int) $connection->selectOne("SELECT COUNT(*) AS missing FROM {$child} c LEFT JOIN {$parent} p
                ON c.{$fk} = p.{$pk} {$original} WHERE c.{$fk} IS NOT NULL AND ({$unresolved})", [], false)->missing;
        }

        return $missing;
    }

    /** @param ImportAudit $report */
    private function checkFiles(array $paths, array $report): void
    {
        foreach ($paths as $table => $path) {
            if (hash_file('sha256', $path) !== $report['tables'][$table]['sha256']) {
                throw new RuntimeException('A source dump changed after the audit.');
            }
        }
    }
}
