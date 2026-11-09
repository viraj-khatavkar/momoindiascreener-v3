<?php

namespace App\Actions;

use App\Enums\ApplyFiltersOnOptionEnum;
use App\Enums\CountryEnum;
use App\Enums\CustomFilterComparatorOptionEnum;
use App\Enums\CustomFilterValueOptionEnum;
use App\Enums\NseIndexEnum;
use App\Enums\PlanEnum;
use App\Enums\ScreenResultColumnEnum;
use App\Enums\ScreenSortByOptionEnum;
use App\Enums\StateEnum;
use App\Models\BacktestNseInstrumentPrice;
use App\Models\Order;
use App\Models\Screen;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;

/**
 * @phpstan-type Issue array{kind: string, table: string, column: string, message: string, ids: list<int>, count: int}
 * @phpstan-type TableAudit array{file: string, sha256: string, source_columns: list<string>, destination_columns: list<string>, copied_columns: list<string>, omitted_columns: list<string>, defaulted_columns: array<string, string>, rows: int, eligible: int, destination_rows: int, min_id: ?int, max_id: ?int, latest_update: ?string, distributions: array<string, array<string, int>>}
 * @phpstan-type ImportAudit array{audited_at: string, database: string, destination_time_zone: string, source_time_zone?: ?string, market_data_range: array{first: ?string, last: ?string}, tables: array<string, TableAudit>, issues: array<string, Issue>, invalid_ids: array<string, array<int, true>>, unsupported_screen_ids: array<int, true>, public_screens: list<array{id: int, user_id: int}>}
 */
class PrepareLegacyProductionImportAction
{
    /** @var ImportAudit */
    private array $report;

    /** @var array<string, array<string, int>> */
    private array $seen = [];

    /** @var array<int, true> */
    private array $userIds = [];

    public function __construct(private ReadLegacySqlDumpAction $reader) {}

    /** @return array<string, string> */
    public static function tables(): array
    {
        return [
            'users' => (new User)->getTable(),
            'orders' => (new Order)->getTable(),
            'screens' => (new Screen)->getTable(),
        ];
    }

    /**
     * @param  array<string, string>  $paths
     * @return ImportAudit
     */
    public function execute(array $paths): array
    {
        $this->seen = [];
        $this->userIds = [];
        $this->report = [
            'audited_at' => now()->toIso8601String(),
            'database' => DB::connection()->getDatabaseName(),
            'destination_time_zone' => DB::selectOne('SELECT @@session.time_zone AS zone')->zone,
            'market_data_range' => [
                'first' => BacktestNseInstrumentPrice::query()->where('is_nifty_allcap', true)->orderBy('date')->toBase()->value('date'),
                'last' => BacktestNseInstrumentPrice::query()->where('is_nifty_allcap', true)->orderByDesc('date')->toBase()->value('date'),
            ],
            'tables' => [],
            'issues' => [],
            'invalid_ids' => ['users' => [], 'orders' => [], 'screens' => []],
            'unsupported_screen_ids' => [],
            'public_screens' => [],
        ];

        foreach (self::tables() as $table => $destination) {
            if (! is_file($paths[$table]) || ! is_readable($paths[$table])) {
                throw new InvalidArgumentException("Provide a readable {$table} dump.");
            }

            $schema = Schema::getColumns($destination);

            if ($schema === []) {
                throw new InvalidArgumentException("The {$table} destination table is missing. Run the application migrations first.");
            }

            $destinationColumns = array_column($schema, 'name');
            $rules = $this->schemaRules($schema);
            $this->report['tables'][$table] = [
                'file' => basename($paths[$table]),
                'sha256' => hash_file('sha256', $paths[$table]),
                'source_columns' => [],
                'destination_columns' => $destinationColumns,
                'copied_columns' => [],
                'omitted_columns' => $table === 'screens' ? ['ignore_top_beta', 'ignore_top_volatility'] : [],
                'defaulted_columns' => $table === 'screens' ? ['unique_etf_per_index' => '0'] : [],
                'rows' => 0,
                'eligible' => 0,
                'destination_rows' => DB::table($destination)->count(),
                'min_id' => null,
                'max_id' => null,
                'latest_update' => null,
                'distributions' => [],
            ];

            foreach ($this->reader->execute($paths[$table], $table) as $row) {
                $summary = &$this->report['tables'][$table];
                $summary['rows']++;

                if ($summary['source_columns'] === []) {
                    $summary['source_columns'] = array_keys($row);
                    $summary['copied_columns'] = array_values(array_intersect(array_keys($row), $destinationColumns));
                    $expected = $destinationColumns;

                    if ($table === 'screens') {
                        $expected = array_merge(array_diff($expected, ['unique_etf_per_index']), ['ignore_top_beta', 'ignore_top_volatility']);
                    }

                    if (array_diff($expected, array_keys($row)) !== [] || array_diff(array_keys($row), $expected) !== []) {
                        throw new InvalidArgumentException("The {$table} dump columns do not match the supported production schema.");
                    }
                }

                $id = (int) $row['id'];
                $summary['min_id'] = min($summary['min_id'] ?? $id, $id);
                $summary['max_id'] = max($summary['max_id'] ?? $id, $id);
                $summary['latest_update'] = max($summary['latest_update'], $row['updated_at']);
                unset($summary);

                $this->unique($table, 'id', $row['id'], $id);

                try {
                    $normalized = $this->normalize($table, $row);
                } catch (JsonException|InvalidArgumentException) {
                    $this->issue('error', $table, 'columns', 'invalid_json', $id, 'Screen columns must be a JSON list of field names.');

                    continue;
                }

                $validator = Validator::make($normalized, $rules);
                $validator->passes();

                foreach (array_keys($validator->failed()) as $column) {
                    $this->issue('error', $table, $column, 'invalid_value', $id, "Invalid value for {$table}.{$column}.");
                }

                foreach ($row as $column => $value) {
                    if (array_key_exists($column, $normalized) && $normalized[$column] !== $value) {
                        $this->issue('info', $table, $column, 'renamed', $id, "Convert legacy names in {$table}.{$column}.");
                    }
                }

                if ($table === 'users') {
                    $this->auditUser($normalized, $id);
                } else {
                    if (! isset($this->userIds[(int) $row['user_id']])) {
                        $this->issue('error', $table, 'user_id', 'missing_user', $id, 'The owner is absent from the users dump.');
                    }

                    if ($table === 'orders') {
                        $this->auditOrder($normalized, $id);
                    } else {
                        $this->auditScreen($normalized, $row, $id);
                    }
                }

                if (! isset($this->report['invalid_ids'][$table][$id]) && ! ($table === 'screens' && isset($this->report['unsupported_screen_ids'][$id]))) {
                    $this->report['tables'][$table]['eligible']++;
                }
            }

            if ($this->report['tables'][$table]['rows'] === 0) {
                throw new InvalidArgumentException("The {$table} dump has no INSERT rows.");
            }

            if (hash_file('sha256', $paths[$table]) !== $this->report['tables'][$table]['sha256']) {
                throw new InvalidArgumentException("The {$table} dump changed during the audit.");
            }
        }

        return $this->report;
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array<string, string|null>
     */
    public function normalize(string $table, array $row): array
    {
        if ($table !== 'screens') {
            return $row;
        }

        unset($row['ignore_top_beta'], $row['ignore_top_volatility']);
        $row['unique_etf_per_index'] = '0';
        $row['index'] = match ($row['index']) {
            'nifty_large_mid_250' => 'nifty_largemidcap_250',
            'nifty_mid_small_400' => 'nifty_midsmallcap_400',
            default => $row['index'],
        };

        foreach (array_merge(['sort_by', 'factor_two_sort_by', 'factor_three_sort_by'], $this->customFilterFields()) as $field) {
            if ($row[$field] === 'close') {
                $row[$field] = 'close_adjusted';
            }
        }

        $columns = json_decode($row['columns'] ?? '', true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($columns) || ! array_is_list($columns) || ! str_starts_with(ltrim($row['columns']), '[')) {
            throw new InvalidArgumentException('Screen columns must be a JSON list.');
        }

        foreach ($columns as &$column) {
            if (! is_string($column)) {
                throw new InvalidArgumentException('Screen columns must contain only strings.');
            }

            if ($column === 'close') {
                $column = 'close_adjusted';
            }
        }
        unset($column);

        if ($columns !== json_decode($row['columns'], true, flags: JSON_THROW_ON_ERROR)) {
            $row['columns'] = json_encode($columns, JSON_THROW_ON_ERROR);
        }

        return $row;
    }

    /**
     * @param  list<array{name: string, type: string, nullable: bool}>  $schema
     * @return array<string, list<string|Closure>>
     */
    private function schemaRules(array $schema): array
    {
        $rules = [];

        foreach ($schema as $column) {
            $type = $column['type'];
            $fieldRules = ['present', $column['nullable'] ? 'nullable' : 'required'];

            if (str_starts_with($type, 'varchar')) {
                preg_match('/\((\d+)\)/', $type, $matches);
                $fieldRules = array_merge($fieldRules, ['string', 'max:'.$matches[1]]);
                $fieldRules[1] = $column['nullable'] ? 'nullable' : 'string';
            } elseif ($type === 'tinyint(1)') {
                $fieldRules[] = 'boolean';
            } elseif (preg_match('/^(?:bigint|int)\b/', $type)) {
                $fieldRules[] = 'integer';
                $fieldRules[] = str_contains($type, 'unsigned') ? 'min:0' : 'min:-2147483648';
                $fieldRules[] = str_starts_with($type, 'bigint') ? 'max:'.PHP_INT_MAX : 'max:2147483647';
            } elseif (preg_match('/^decimal\((\d+),(\d+)\)/', $type, $matches)) {
                $digits = (int) $matches[1] - (int) $matches[2];
                $scale = (int) $matches[2];
                $fieldRules[] = "regex:/^-?\d{1,{$digits}}(?:\.\d{1,{$scale}})?$/D";
            } elseif ($type === 'date') {
                $fieldRules[] = 'date_format:Y-m-d';
                $fieldRules[] = 'after_or_equal:1000-01-01';
            } elseif (in_array($type, ['timestamp', 'datetime'], true)) {
                $fieldRules[] = 'date_format:Y-m-d H:i:s';
            } elseif ($type === 'json') {
                $fieldRules[] = 'json';
            } elseif ($type === 'text') {
                $fieldRules[] = 'string';
                $fieldRules[] = static function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && strlen($value) > 65535) {
                        $fail('The text exceeds the database byte limit.');
                    }
                };
            } else {
                throw new InvalidArgumentException('Unsupported destination column type: '.$column['name'].'.');
            }

            if (in_array($column['name'], ['id', 'user_id'], true)) {
                $fieldRules[] = 'min:1';
            }

            $rules[$column['name']] = $fieldRules;
        }

        return $rules;
    }

    /** @param array<string, string|null> $row */
    private function auditUser(array $row, int $id): void
    {
        $this->userIds[$id] = true;
        $this->unique('users', 'email', Str::lower(trim($row['email'] ?? '')), $id);

        if (Validator::make($row, ['email' => 'required|email:rfc', 'name' => 'required|string'])->fails()) {
            $this->issue('error', 'users', 'email/name', 'invalid_identity', $id, 'A user needs a valid email and name.');
        }

        if (password_get_info($row['password'] ?? '')['algoName'] !== config('hashing.driver', 'bcrypt')) {
            $this->issue('error', 'users', 'password', 'unsupported_hash', $id, 'The password hash is not compatible with the configured hasher.');
        }

        foreach (['country' => CountryEnum::class, 'state' => StateEnum::class] as $column => $enum) {
            if ($row[$column] !== null && $enum::tryFrom($row[$column]) === null) {
                $this->issue('error', 'users', $column, 'unsupported_enum', $id, "The user {$column} is not supported by the current enum.");
            }
        }

        if ($row['is_paid'] === '1' && ($row['plan_ends_at'] === null || $row['plan_ends_at'] < now('Asia/Kolkata')->toDateString())) {
            $this->issue('warning', 'users', 'plan_ends_at', 'paid_without_current_term', $id, 'Paid access has an expired or missing end date. Preserve the source flag for review.');
        }

        foreach (['is_paid', 'is_newsletter_paid', 'is_admin'] as $column) {
            $this->distribution('users', $column, in_array($row[$column], ['0', '1'], true) ? $row[$column] : 'invalid');
        }

        $this->distribution('users', 'email_verified', $row['email_verified_at'] === null ? 'no' : 'yes');
        $this->distribution('users', 'password_algorithm', password_get_info($row['password'] ?? '')['algoName']);
    }

    /** @param array<string, string|null> $row */
    private function auditOrder(array $row, int $id): void
    {
        foreach (['order_id', 'invoice_number', 'razorpay_payment_id'] as $column) {
            $this->unique('orders', $column, $row[$column], $id);
        }

        if (PlanEnum::tryFrom($row['plan'] ?? '') === null || ! in_array($row['status'], ['pending', 'paid'], true)) {
            $this->issue('error', 'orders', 'plan/status', 'unsupported_value', $id, 'The order plan or status is not supported.');
        }

        if ($row['status'] === 'paid') {
            foreach (['invoice_number', 'razorpay_payment_id', 'razorpay_order_id', 'razorpay_signature', 'plan_starts_at', 'plan_ends_at', 'description'] as $column) {
                if ($row[$column] === null || $row[$column] === '') {
                    $this->issue('warning', 'orders', $column, 'missing_paid_detail', $id, "A paid order has no {$column}. Preserve the source value.");
                }
            }
        }

        if ($row['plan_starts_at'] !== null && $row['plan_ends_at'] !== null && $row['plan_starts_at'] > $row['plan_ends_at']) {
            $this->issue('warning', 'orders', 'plan_ends_at', 'reversed_term', $id, 'The order end date is before its start date.');
        }

        foreach (['plan', 'status'] as $column) {
            if ($column === 'plan' ? PlanEnum::tryFrom($row[$column] ?? '') !== null : in_array($row[$column], ['pending', 'paid'], true)) {
                $this->distribution('orders', $column, $row[$column]);
            }
        }
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  array<string, string|null>  $source
     */
    private function auditScreen(array $row, array $source, int $id): void
    {
        foreach (['ignore_top_beta', 'ignore_top_volatility'] as $column) {
            if ($source[$column] === '1') {
                $this->issue('info', 'screens', $column, 'omitted_filter', $id, "{$column} is enabled in the dump and will be omitted.");
            }
        }

        $enums = [
            'index' => NseIndexEnum::class,
            'apply_filters_on' => ApplyFiltersOnOptionEnum::class,
            'sort_by' => ScreenSortByOptionEnum::class,
            'factor_two_sort_by' => ScreenSortByOptionEnum::class,
            'factor_three_sort_by' => ScreenSortByOptionEnum::class,
        ];

        foreach ($this->customFilterFields() as $field) {
            $enums[$field] = CustomFilterValueOptionEnum::class;
        }

        foreach (['one', 'two', 'three', 'four', 'five'] as $number) {
            $enums['custom_filter_'.$number.'_operator'] = CustomFilterComparatorOptionEnum::class;
        }

        foreach ($enums as $column => $enum) {
            if ($enum::tryFrom($row[$column] ?? '') === null) {
                $this->issue('unsupported', 'screens', $column, 'unsupported_enum', $id, "The saved {$column} is not supported by the new application.");
            }
        }

        foreach (json_decode($row['columns'], true, flags: JSON_THROW_ON_ERROR) as $column) {
            if (ScreenResultColumnEnum::tryFrom($column) === null) {
                $this->issue('unsupported', 'screens', 'columns', 'unsupported_column', $id, 'A saved result column is not supported.');
            }
        }

        foreach (['sort_direction', 'factor_two_sort_direction', 'factor_three_sort_direction'] as $column) {
            if (! in_array($row[$column], ['asc', 'desc'], true)) {
                $this->issue('error', 'screens', $column, 'invalid_direction', $id, 'The sort direction must be asc or desc.');
            }
        }

        if ($row['series_eq'] === '0' && $row['series_be'] === '0') {
            $this->issue('info', 'screens', 'series_eq/series_be', 'changed_empty_series', $id, 'Both series flags are off and will be preserved. Under the current results behavior, this screen returns no rows.');
        }

        if ($row['apply_historical_date'] === '1' && $row['historical_date'] < '2010-01-04') {
            $this->issue('unsupported', 'screens', 'historical_date', 'before_supported_history', $id, 'The historical date is before the supported start date.');
        }

        if ($row['apply_historical_date'] === '1') {
            $range = $this->report['market_data_range'];

            if ($range['first'] === null || $row['historical_date'] < $range['first']) {
                $this->issue('warning', 'screens', 'historical_date', 'missing_market_history', $id, 'No local market data exists on or before this saved date. Load market data before opening this screen.');
            } elseif ($row['historical_date'] > $range['last']) {
                $this->issue('warning', 'screens', 'historical_date', 'after_local_market_history', $id, 'The saved date is after the latest local market data. Screen results will use an earlier date.');
            }
        }

        if (in_array($id, Screen::PUBLIC_SCREENS, true)) {
            $this->report['public_screens'][] = ['id' => $id, 'user_id' => (int) $row['user_id']];
        }

        if (NseIndexEnum::tryFrom($row['index'] ?? '') !== null || $row['index'] === 'nifty_fno') {
            $this->distribution('screens', 'index', $row['index']);
        }

        $this->distribution('screens', 'apply_historical_date', in_array($row['apply_historical_date'], ['0', '1'], true) ? $row['apply_historical_date'] : 'invalid');
    }

    /** @return list<string> */
    private function customFilterFields(): array
    {
        $fields = [];

        foreach (['one', 'two', 'three', 'four', 'five'] as $number) {
            $fields[] = 'custom_filter_'.$number.'_value_one';
            $fields[] = 'custom_filter_'.$number.'_value_two';
        }

        return $fields;
    }

    private function unique(string $table, string $column, ?string $value, int $id): void
    {
        if ($value === null) {
            return;
        }

        $key = $table.'.'.$column;

        if (isset($this->seen[$key][$value])) {
            $this->issue('error', $table, $column, 'duplicate', $id, "Duplicate {$table}.{$column} in the dump.");
        }

        $this->seen[$key][$value] = $id;
    }

    private function issue(string $kind, string $table, string $column, string $code, int $id, string $message): void
    {
        $key = $table.'.'.$column.'.'.$code;
        $this->report['issues'][$key] ??= compact('kind', 'table', 'column', 'message') + ['ids' => [], 'count' => 0];

        if (! in_array($id, $this->report['issues'][$key]['ids'], true)) {
            $this->report['issues'][$key]['ids'][] = $id;
            $this->report['issues'][$key]['count']++;
        }

        if ($kind === 'error') {
            $this->report['invalid_ids'][$table][$id] = true;
        } elseif ($kind === 'unsupported') {
            $this->report['unsupported_screen_ids'][$id] = true;
        }
    }

    private function distribution(string $table, string $column, ?string $value): void
    {
        $counts = &$this->report['tables'][$table]['distributions'][$column];
        $counts[$value ?? 'null'] = ($counts[$value ?? 'null'] ?? 0) + 1;
    }
}
