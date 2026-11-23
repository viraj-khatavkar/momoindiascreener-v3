<?php

use App\Models\Order;
use App\Models\Portfolio;
use App\Models\Screen;
use App\Models\ScreenExport;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo(now()->setDate(2026, 11, 8)->startOfDay());
    $this->legacyDirectory = Storage::disk('local')->path('source');
    Storage::disk('local')->makeDirectory('source');

    $user = User::factory()->make([
        'id' => 20,
        'email' => 'legacy@example.test',
        'address_line_one' => null, 'address_line_two' => null, 'country' => null, 'state' => null,
        'city' => null, 'phone' => null, 'postal_code' => null,
        'is_paid' => true, 'is_newsletter_paid' => true, 'is_admin' => false,
        'plan_ends_at' => '2027-01-01',
        'created_at' => '2024-02-03 04:05:06', 'updated_at' => '2026-11-01 07:08:09',
    ])->getAttributes();
    $order = Order::factory()->make([
        'id' => 30, 'user_id' => 20, 'order_id' => 'order_legacy',
        'created_at' => '2025-02-03 04:05:06', 'updated_at' => '2026-11-01 07:08:09',
    ])->getAttributes();
    $screen = Screen::factory()->make([
        'id' => 40, 'user_id' => 20, 'ignore_top_beta' => false, 'ignore_top_volatility' => false,
        'created_at' => '2025-02-03 04:05:06', 'updated_at' => '2026-11-01 07:08:09',
    ])->getAttributes();
    $screen = Arr::except($screen, [
        'unique_etf_per_index', 'apply_ema', 'above_ema_200', 'above_ema_100', 'above_ema_50', 'above_ema_20',
        'below_ema_200', 'below_ema_100', 'below_ema_50', 'below_ema_20',
    ]);

    $this->legacyRows = ['users' => [$user], 'orders' => [$order], 'screens' => [$screen]];
    $this->importOptions = ['directory' => $this->legacyDirectory, '--report' => 'audit.json', '--source-timezone' => '+00:00'];
});

/** @param array<string, list<array<string, mixed>>> $tables */
function writeLegacyTestDumps(string $directory, array $tables): void
{
    foreach ($tables as $table => $rows) {
        $columns = array_keys($rows[0]);
        $values = [];

        foreach ($rows as $row) {
            $literals = [];

            foreach ($columns as $column) {
                $value = $row[$column];

                if ($value instanceof BackedEnum) {
                    $value = $value->value;
                } elseif ($value instanceof DateTimeInterface) {
                    $value = $value->format('Y-m-d H:i:s');
                }

                $literals[] = match (true) {
                    $value === null => 'NULL',
                    is_bool($value) => $value ? '1' : '0',
                    is_int($value), is_float($value) => (string) $value,
                    default => "'".strtr($value, ['\\' => '\\\\', "'" => "\\'", "\n" => '\\n', "\r" => '\\r', "\0" => '\\0'])."'",
                };
            }

            $values[] = '('.implode(', ', $literals).')';
        }

        $sql = "-- TablePlus fixture\n/*!40101 SET NAMES utf8mb4 */;\nDROP TABLE IF EXISTS `{$table}`;\nCREATE TABLE `{$table}` (`id` bigint);\n"
            ."INSERT INTO `{$table}` (`".implode('`, `', $columns).'`) VALUES '.implode(",\n", $values).";\n";
        file_put_contents($directory.'/'.$table.'.gz', gzencode($sql));
    }
}

it('previews without writes and saves an audit without personal data', function () {
    writeLegacyTestDumps($this->legacyDirectory, $this->legacyRows);

    $this->artisan('legacy:import-production', $this->importOptions)
        ->expectsOutputToContain('Preview only.')
        ->assertSuccessful();

    expect(User::count())->toBe(0)->and(Order::count())->toBe(0)->and(Screen::count())->toBe(0);
    $json = Storage::disk('local')->get('audit.json');
    $report = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    expect($report['tables']['users']['rows'])->toBe(1)
        ->and($report['tables']['users']['defaulted_columns'])->toBe(['convertkit_subscriber_id' => null])
        ->and($report['tables']['screens']['eligible'])->toBe(1)
        ->and($report['tables']['screens']['defaulted_columns'])->toHaveCount(10)
        ->and($report['tables']['screens']['defaulted_columns']['apply_ema'])->toBe('0')
        ->and($json)->not->toContain('legacy@example.test', $this->legacyRows['users'][0]['password']);
});

it('imports hashes IDs timestamps and relationships and translates only equivalent screen fields', function () {
    $this->legacyRows['screens'][0] = array_replace($this->legacyRows['screens'][0], [
        'index' => 'nifty_large_mid_250', 'sort_by' => 'close',
        'custom_filter_one_value_one' => 'close', 'columns' => '["close", "beta"]',
        'name' => "O'Brien; screen 😀",
    ]);
    writeLegacyTestDumps($this->legacyDirectory, $this->legacyRows);
    Mail::fake();
    Notification::fake();
    Event::fake(['eloquent.created: '.User::class, 'eloquent.created: '.Order::class, 'eloquent.created: '.Screen::class]);

    $this->artisan('legacy:import-production', $this->importOptions + ['--apply' => true])->assertSuccessful();

    $user = User::findOrFail(20);
    $screen = Screen::findOrFail(40);
    expect($user->getRawOriginal('password'))->toBe($this->legacyRows['users'][0]['password'])
        ->and(Hash::check('password', $user->password))->toBeTrue()
        ->and((int) DB::table('users')->where('id', 20)->selectRaw('UNIX_TIMESTAMP(created_at) AS instant')->value('instant'))
        ->toBe(CarbonImmutable::parse('2024-02-03 04:05:06', 'UTC')->timestamp)
        ->and($user->is_paid)->toBeTrue()
        ->and($user->convertkit_subscriber_id)->toBeNull()
        ->and($user->orders->sole()->id)->toBe(30)
        ->and($user->screens->sole()->id)->toBe(40)
        ->and($screen->index->value)->toBe('nifty_largemidcap_250')
        ->and($screen->sort_by)->toBe('close_adjusted')
        ->and($screen->custom_filter_one_value_one)->toBe('close_adjusted')
        ->and($screen->columns)->toBe(['close_adjusted', 'beta'])
        ->and($screen->unique_etf_per_index)->toBeFalse()
        ->and($screen->name)->toBe("O'Brien; screen 😀");
    Mail::assertNothingSent();
    Notification::assertNothingSent();
    Event::assertNotDispatched('eloquent.created: '.User::class);
    expect(User::factory()->create()->id)->toBeGreaterThan(20);
});

it('blocks unsupported screens by default and skips them only with the explicit option', function (array $settings) {
    $this->legacyRows['screens'][] = array_replace($this->legacyRows['screens'][0], [
        'id' => 41, 'ignore_top_beta' => true, 'ignore_top_volatility' => true,
    ], $settings);
    writeLegacyTestDumps($this->legacyDirectory, $this->legacyRows);

    $this->artisan('legacy:import-production', $this->importOptions + ['--apply' => true])
        ->expectsOutputToContain('1 distinct screens have unsupported settings.')
        ->assertFailed();
    expect(User::count())->toBe(0);

    $options = array_replace($this->importOptions, ['--report' => 'skip-audit.json', '--apply' => true, '--skip-unsupported-screens' => true]);
    $this->artisan('legacy:import-production', $options)->assertSuccessful();

    expect(User::count())->toBe(1)->and(Order::count())->toBe(1)->and(Screen::pluck('id')->all())->toBe([40]);
    $report = json_decode(Storage::disk('local')->get('skip-audit.json'), true, flags: JSON_THROW_ON_ERROR);
    expect($report['unsupported_screen_ids'])->toBe([41 => true]);
})->with([
    'unknown index' => [['index' => 'unknown_index']],
    'missing rank' => [['sort_by' => 'unknown_ranking_field']],
    'inactive but selected rank' => [['apply_factor_two' => false, 'factor_two_sort_by' => 'unknown_ranking_field']],
]);

it('imports saved F&O screens now that dated membership is supported', function () {
    $this->legacyRows['screens'][0]['index'] = 'nifty_fno';
    writeLegacyTestDumps($this->legacyDirectory, $this->legacyRows);
    $this->artisan('legacy:import-production', $this->importOptions + ['--apply' => true])->assertSuccessful();
    expect(Screen::sole()->index->value)->toBe('nifty_fno');
});

it('imports screens with both series flags off and preserves the saved values', function () {
    $this->legacyRows['screens'][0]['series_eq'] = false;
    $this->legacyRows['screens'][0]['series_be'] = false;
    writeLegacyTestDumps($this->legacyDirectory, $this->legacyRows);

    $this->artisan('legacy:import-production', $this->importOptions + ['--apply' => true])->assertSuccessful();

    $screen = Screen::sole();
    expect($screen->series_eq)->toBeFalse()->and($screen->series_be)->toBeFalse();

    $report = json_decode(Storage::disk('local')->get('audit.json'), true, flags: JSON_THROW_ON_ERROR);
    expect($report['unsupported_screen_ids'])->toBe([])
        ->and($report['tables']['screens']['eligible'])->toBe(1)
        ->and($report['issues']['screens.series_eq/series_be.changed_empty_series']['kind'])->toBe('info');
});

it('imports the six and three month Sharpe option for all saved ranking factors', function () {
    $fields = ['sort_by', 'factor_two_sort_by', 'factor_three_sort_by'];
    $this->legacyRows['screens'][0] = array_replace(
        $this->legacyRows['screens'][0],
        array_fill_keys($fields, 'average_sharpe_return_six_three_months'),
    );
    writeLegacyTestDumps($this->legacyDirectory, $this->legacyRows);

    $this->artisan('legacy:import-production', $this->importOptions + ['--apply' => true])->assertSuccessful();

    $screen = Screen::sole();
    foreach ($fields as $field) {
        expect($screen->{$field})->toBe('average_sharpe_return_six_three_months');
    }

    $report = json_decode(Storage::disk('local')->get('audit.json'), true, flags: JSON_THROW_ON_ERROR);
    expect($report['unsupported_screen_ids'])->toBe([])
        ->and($report['tables']['screens']['eligible'])->toBe(1);
});

it('imports screens without the excluded legacy filters and keeps other settings', function (array $settings) {
    $this->legacyRows['screens'][0] = array_replace($this->legacyRows['screens'][0], [
        'ignore_above_beta' => '1.25',
    ], $settings);
    writeLegacyTestDumps($this->legacyDirectory, $this->legacyRows);

    $this->artisan('legacy:import-production', $this->importOptions + ['--apply' => true])->assertSuccessful();

    $screen = Screen::sole();
    expect($screen->id)->toBe(40)
        ->and($screen->user_id)->toBe(20)
        ->and($screen->ignore_above_beta)->toBe('1.25')
        ->and($screen->getAttributes())->not->toHaveKeys(['ignore_top_beta', 'ignore_top_volatility']);

    $report = json_decode(Storage::disk('local')->get('audit.json'), true, flags: JSON_THROW_ON_ERROR);
    expect($report['unsupported_screen_ids'])->toBe([])
        ->and($report['tables']['screens']['eligible'])->toBe(1)
        ->and($report['tables']['screens']['omitted_columns'])->toBe(['ignore_top_beta', 'ignore_top_volatility']);

    foreach ($settings as $column => $value) {
        if ($value === true) {
            $issue = $report['issues']['screens.'.$column.'.omitted_filter'];
            expect($issue['kind'])->toBe('info')->and($issue['ids'])->toBe([40]);
        }
    }
})->with([
    'beta filter' => [['ignore_top_beta' => true]],
    'volatility filter' => [['ignore_top_volatility' => true]],
    'both filters' => [['ignore_top_beta' => true, 'ignore_top_volatility' => true]],
    'unused invalid value' => [['ignore_top_beta' => 2]],
]);

it('refuses invalid data even when unsupported screens can be skipped', function (string $table, string $column, mixed $value) {
    $this->legacyRows[$table][0][$column] = $value;
    writeLegacyTestDumps($this->legacyDirectory, $this->legacyRows);

    $this->artisan('legacy:import-production', $this->importOptions + ['--apply' => true, '--skip-unsupported-screens' => true])->assertFailed();

    expect(User::count())->toBe(0)->and(Order::count())->toBe(0)->and(Screen::count())->toBe(0);
})->with([
    'missing owner' => ['orders', 'user_id', 999],
    'bad hash' => ['users', 'password', 'not-a-hash'],
    'bad country enum' => ['users', 'country', 'unknown-country'],
    'missing email' => ['users', 'email', null],
    'bad boolean' => ['users', 'is_paid', 2],
    'bad date' => ['users', 'plan_ends_at', '2026-02-30'],
    'negative id' => ['users', 'id', -1],
    'long string' => ['users', 'name', str_repeat('a', 256)],
    'text byte overflow' => ['orders', 'address_line_one', str_repeat('😀', 16384)],
    'amount overflow' => ['orders', 'amount', '123456789.00'],
    'amount precision' => ['orders', 'amount', '12.345'],
    'missing plan' => ['orders', 'plan', 'old-plan'],
    'invalid JSON' => ['screens', 'columns', '{broken'],
    'object JSON' => ['screens', 'columns', '{"0":"close"}'],
    'invalid direction' => ['screens', 'sort_direction', 'sideways'],
]);

it('rejects duplicate identities without a partial import', function (string $table, string $column) {
    $duplicate = $this->legacyRows[$table][0];
    $duplicate['id'] = 99;

    if ($column === 'id') {
        $duplicate['id'] = $this->legacyRows[$table][0]['id'];
    }

    $this->legacyRows[$table][] = $duplicate;
    writeLegacyTestDumps($this->legacyDirectory, $this->legacyRows);
    $this->artisan('legacy:import-production', $this->importOptions + ['--apply' => true])->assertFailed();
    expect(User::count())->toBe(0);
})->with([['users', 'email'], ['users', 'id'], ['orders', 'order_id'], ['screens', 'id']]);

it('does not replace existing records or rerun an import', function () {
    $existing = User::factory()->create();
    writeLegacyTestDumps($this->legacyDirectory, $this->legacyRows);

    $this->artisan('legacy:import-production', $this->importOptions + ['--apply' => true])
        ->expectsOutputToContain('destination tables must be empty')
        ->assertFailed();
    expect(User::sole()->id)->toBe($existing->id)->and(Order::count())->toBe(0);
});

it('copies optional fields that exist in the export and defaults only missing fields', function () {
    $this->legacyRows['screens'][0]['above_ema_200'] = true;
    writeLegacyTestDumps($this->legacyDirectory, $this->legacyRows);

    $this->artisan('legacy:import-production', $this->importOptions + ['--apply' => true])->assertSuccessful();
    $report = Storage::disk('local')->json('audit.json');
    expect(Screen::sole()->above_ema_200)->toBeTrue()
        ->and(Screen::sole()->below_ema_200)->toBeFalse()
        ->and($report['tables']['screens']['copied_columns'])->toContain('above_ema_200')
        ->and($report['tables']['screens']['defaulted_columns'])->not->toHaveKey('above_ema_200');
});

it('blocks a fresh import when a retained record would lose its owner', function () {
    $user = User::factory()->create(['id' => 21]);
    $portfolio = Portfolio::factory()->for($user)->create();
    DB::table('sessions')->insert(['id' => 'legacy-session', 'user_id' => 21, 'payload' => '', 'last_activity' => 1]);
    writeLegacyTestDumps($this->legacyDirectory, $this->legacyRows);

    $this->artisan('legacy:import-production', $this->importOptions + ['--fresh' => true, '--apply' => true])
        ->expectsOutputToContain('unresolved ownership links')->assertFailed();
    $report = Storage::disk('local')->json('audit.json');
    expect($report['status'])->toBe('blocked')
        ->and(array_sum($report['unresolved_references']))->toBe(2)
        ->and(User::sole()->is($user))->toBeTrue()
        ->and(Portfolio::sole()->is($portfolio))->toBeTrue();
});

it('detects a reused account ID before replacing its retained portfolio owner', function () {
    $old = User::factory()->create(['id' => 20, 'email' => 'different@example.test']);
    $portfolio = Portfolio::factory()->for($old)->create();
    writeLegacyTestDumps($this->legacyDirectory, $this->legacyRows);

    $this->artisan('legacy:import-production', $this->importOptions + ['--fresh' => true, '--apply' => true])
        ->expectsOutputToContain('unresolved ownership links')->assertFailed();
    expect(array_sum(Storage::disk('local')->json('audit.json')['unresolved_references']))->toBe(1)
        ->and($portfolio->fresh()->user->email)->toBe('different@example.test');
});

it('detects a changed stored field even when all row counts match', function () {
    writeLegacyTestDumps($this->legacyDirectory, $this->legacyRows);
    DB::listen(function (QueryExecuted $query): void {
        if (str_starts_with($query->sql, 'insert into `orders`')) {
            DB::table('orders')->where('id', 30)->update(['amount' => '1.00']);
        }
    });

    $this->artisan('legacy:import-production', $this->importOptions + ['--apply' => true])
        ->expectsOutputToContain('Stored orders values differ')->assertFailed();
    expect(User::count())->toBe(0)->and(Order::count())->toBe(0);
});

it('previews then truncates only the three import tables and verifies a repeat run', function () {
    writeLegacyTestDumps($this->legacyDirectory, $this->legacyRows);
    $path = $this->legacyDirectory.'/screens.gz';
    $sql = str_replace('(`id` bigint);', '(`id` bigint) ENGINE=InnoDB AUTO_INCREMENT=83;', gzdecode(file_get_contents($path)));
    file_put_contents($path, gzencode($sql));

    // MySQL TRUNCATE commits implicitly. Exercise it on a separate connection to the same isolated test database.
    config(['database.connections.legacy_cutover' => Arr::except(DB::connection()->getConfig(), 'name')]);
    DB::usingConnection('legacy_cutover', function () {
        expect(User::count())->toBe(0)->and(Order::count())->toBe(0)->and(Screen::count())->toBe(0);
        try {
            $user = User::factory()->create(['id' => 20, 'email' => 'legacy@example.test']);
            $screen = Screen::factory()->for($user)->create(['id' => 40, 'name' => 'Before import', 'apply_ema' => true]);
            $portfolio = Portfolio::factory()->for($user)->create();
            $export = ScreenExport::factory()->for($user)->create(['screen_id' => $screen->id, 'settings' => []]);
            $extra = User::factory()->create(['id' => 200]);
            Order::factory()->for($extra)->create(['id' => 300]);
            Screen::factory()->for($extra)->create(['id' => 400]);

            $options = $this->importOptions + ['--fresh' => true];
            $this->artisan('legacy:import-production', $options)->expectsOutputToContain('Preview only.')->assertSuccessful();
            expect($screen->fresh()->name)->toBe('Before import')->and(User::count())->toBe(2);

            $interrupt = true;
            DB::listen(function (QueryExecuted $query) use (&$interrupt): void {
                if ($interrupt && str_starts_with($query->sql, 'insert into `orders`')) {
                    $interrupt = false;
                    throw new RuntimeException('Simulated interruption after truncation.');
                }
            });
            $this->artisan('legacy:import-production', array_replace($options, ['--apply' => true, '--report' => 'interrupted.json']))
                ->expectsOutputToContain('Rerun --fresh --apply')->assertFailed();
            expect($interrupt)->toBeFalse()->and(User::count())->toBe(0)
                ->and((int) DB::selectOne('SELECT @@session.foreign_key_checks AS enabled')->enabled)->toBe(1);

            foreach (['first.json', 'repeat.json'] as $report) {
                $this->artisan('legacy:import-production', array_replace($options, ['--apply' => true, '--report' => $report]))->assertSuccessful();
                $verified = Storage::disk('local')->json($report);
                expect($verified['status'])->toBe('import_verified')->and($verified['staged_tables']['screens']['rows'])->toBe(1)
                    ->and(User::sole()->id)->toBe(20)->and(Order::sole()->id)->toBe(30)->and(Screen::sole()->id)->toBe(40)
                    ->and(Screen::sole()->apply_ema)->toBeFalse()
                    ->and($user->fresh()->getRawOriginal('password'))->toBe($this->legacyRows['users'][0]['password'])
                    ->and($portfolio->fresh()->user_id)->toBe(20)->and($export->fresh()->screen_id)->toBe(40)
                    ->and((int) DB::selectOne('SELECT @@session.foreign_key_checks AS enabled')->enabled)->toBe(1);
            }
            expect(Screen::factory()->for($user)->create()->id)->toBe(83);
        } finally {
            DB::table('screen_exports')->where('user_id', 20)->delete();
            DB::table('portfolios')->where('user_id', 20)->delete();
            foreach (['orders', 'screens'] as $table) {
                DB::table($table)->whereIn('user_id', [20, 200])->delete();
            }
            DB::table('users')->whereIn('id', [20, 200])->delete();
        }
    });
});

it('rolls back all three tables if a write fails after users were inserted', function () {
    writeLegacyTestDumps($this->legacyDirectory, $this->legacyRows);
    $reachedOrders = false;
    DB::listen(function (QueryExecuted $query) use (&$reachedOrders): void {
        if (str_starts_with($query->sql, 'insert into `orders`')) {
            $reachedOrders = true;
            throw new RuntimeException('Simulated write failure.');
        }
    });

    $this->artisan('legacy:import-production', $this->importOptions + ['--apply' => true])->assertFailed();

    expect($reachedOrders)->toBeTrue()->and(User::count())->toBe(0)->and(Order::count())->toBe(0)->and(Screen::count())->toBe(0);
});

it('rolls back if a dump changes after its audit', function () {
    writeLegacyTestDumps($this->legacyDirectory, $this->legacyRows);
    $directory = $this->legacyDirectory;
    DB::listen(function (QueryExecuted $query) use ($directory): void {
        if (str_starts_with($query->sql, 'insert into `users`')) {
            file_put_contents($directory.'/screens.gz', gzencode('changed'));
        }
    });

    $this->artisan('legacy:import-production', $this->importOptions + ['--apply' => true])->assertFailed();
    expect(User::count())->toBe(0)->and(Order::count())->toBe(0);
});

it('rejects unknown columns and malformed trailing SQL', function (string $case) {
    if ($case === 'column') {
        $this->legacyRows['users'][0]['unknown_field'] = 'do not drop this';
    }

    writeLegacyTestDumps($this->legacyDirectory, $this->legacyRows);

    if ($case === 'SQL') {
        $path = $this->legacyDirectory.'/screens.gz';
        file_put_contents($path, gzencode(gzdecode(file_get_contents($path)).'UPDATE users SET is_admin = 1;'));
    }

    $this->artisan('legacy:import-production', $this->importOptions + ['--apply' => true])->assertFailed();
    expect(User::count())->toBe(0)->and(Order::count())->toBe(0)->and(Screen::count())->toBe(0);
})->with(['column', 'SQL']);

it('preserves paid order history and reports expired access without changing it', function () {
    $this->legacyRows['users'][0]['plan_ends_at'] = '2026-01-01';
    $this->legacyRows['orders'][0] = array_replace($this->legacyRows['orders'][0], [
        'status' => 'paid', 'invoice_number' => 768, 'amount' => '799.00',
        'razorpay_payment_id' => 'pay_legacy', 'razorpay_signature' => 'private-signature',
        'plan_starts_at' => '2025-01-01', 'plan_ends_at' => '2026-01-01',
    ]);
    writeLegacyTestDumps($this->legacyDirectory, $this->legacyRows);
    $this->artisan('legacy:import-production', $this->importOptions + ['--apply' => true])->assertSuccessful();

    expect(User::sole()->is_paid)->toBeTrue()
        ->and(Order::sole()->amount)->toBe('799.00')
        ->and(Order::sole()->invoice_number)->toBe(768)
        ->and(Order::sole()->razorpay_signature)->toBe('private-signature');
    $json = Storage::disk('local')->get('audit.json');
    expect($json)->toContain('paid_without_current_term')->not->toContain('private-signature');
});

it('requires an export time zone for writes and restores the destination time zone', function () {
    writeLegacyTestDumps($this->legacyDirectory, $this->legacyRows);
    $options = $this->importOptions + ['--apply' => true];
    unset($options['--source-timezone']);
    $this->artisan('legacy:import-production', $options)
        ->expectsOutputToContain('Confirm the export session time zone')
        ->assertFailed();
    expect(User::count())->toBe(0);

    $previousTimezone = DB::selectOne('SELECT @@session.time_zone AS zone')->zone;

    try {
        DB::statement('SET time_zone = ?', ['+05:30']);
        $options = array_replace($this->importOptions, ['--apply' => true, '--report' => 'with-zone.json']);
        $this->artisan('legacy:import-production', $options)->assertSuccessful();
        expect(DB::selectOne('SELECT @@session.time_zone AS zone')->zone)->toBe('+05:30')
            ->and(User::sole()->getRawOriginal('created_at'))->toBe('2024-02-03 09:35:06')
            ->and(User::sole()->plan_ends_at)->toBe('2027-01-01');
    } finally {
        DB::statement('SET time_zone = ?', [$previousTimezone]);
    }
});

it('imports multiple batches and retains gaps in IDs', function () {
    for ($offset = 0; $offset < 205; $offset++) {
        $this->legacyRows['users'][] = array_replace($this->legacyRows['users'][0], [
            'id' => 1000 + $offset, 'email' => "legacy-{$offset}@example.test",
        ]);
        $this->legacyRows['orders'][] = array_replace($this->legacyRows['orders'][0], [
            'id' => 2000 + $offset, 'user_id' => 1000 + $offset, 'order_id' => 'legacy_order_'.$offset,
        ]);
        $this->legacyRows['screens'][] = array_replace($this->legacyRows['screens'][0], [
            'id' => 3000 + $offset, 'user_id' => 1000 + $offset,
        ]);
    }

    writeLegacyTestDumps($this->legacyDirectory, $this->legacyRows);
    $this->artisan('legacy:import-production', $this->importOptions + ['--apply' => true])->assertSuccessful();

    expect(User::count())->toBe(206)->and(Order::count())->toBe(206)->and(Screen::count())->toBe(206)
        ->and(Screen::findOrFail(3204)->user_id)->toBe(1204);
});

it('reads uncompressed exports and preserves the public screen IDs', function () {
    $this->legacyRows['screens'][0]['id'] = 1;
    writeLegacyTestDumps($this->legacyDirectory, $this->legacyRows);

    foreach (['users', 'orders', 'screens'] as $table) {
        $path = $this->legacyDirectory.'/'.$table;
        file_put_contents($path.'.sql', gzdecode(file_get_contents($path.'.gz')));
        unlink($path.'.gz');
    }

    $this->artisan('legacy:import-production', $this->importOptions + ['--apply' => true])->assertSuccessful();
    $report = json_decode(Storage::disk('local')->get('audit.json'), true, flags: JSON_THROW_ON_ERROR);
    expect(Screen::sole()->id)->toBe(1)->and($report['public_screens'])->toBe([['id' => 1, 'user_id' => 20]]);
});

it('does not overwrite an existing report', function () {
    writeLegacyTestDumps($this->legacyDirectory, $this->legacyRows);
    Storage::disk('local')->put('audit.json', 'keep this report');

    $this->artisan('legacy:import-production', $this->importOptions + ['--apply' => true])->assertFailed();
    expect(User::count())->toBe(0)->and(Storage::disk('local')->get('audit.json'))->toBe('keep this report');
});

it('preserves text fields that fit the byte limit even above 16383 characters', function () {
    $this->legacyRows['orders'][0]['address_line_one'] = str_repeat('a', 17000);
    writeLegacyTestDumps($this->legacyDirectory, $this->legacyRows);

    $this->artisan('legacy:import-production', $this->importOptions + ['--apply' => true])->assertSuccessful();
    expect(Order::sole()->address_line_one)->toBe(str_repeat('a', 17000));
});
