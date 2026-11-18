<?php

use App\Actions\GenerateScreenExportAction;
use App\Actions\QueueScreenExportAction;
use App\Enums\ScreenExportStatusEnum;
use App\Jobs\GenerateScreenExportJob;
use App\Models\AdminProcessRun;
use App\Models\Backtest;
use App\Models\Screen;
use App\Models\ScreenExport;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
});

/** @return list<array<string, string|null>> */
function readScreenExportCsv(ZipArchive $zip, string $filename): array
{
    $contents = $zip->getFromName($filename);
    expect($contents)->not->toBeFalse();
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, ltrim($contents, "\xEF\xBB\xBF"));
    rewind($stream);
    $header = fgetcsv($stream, escape: '');
    $rows = [];
    while (($row = fgetcsv($stream, escape: '')) !== false) {
        $rows[] = array_combine($header, $row);
    }
    fclose($stream);

    return $rows;
}

it('queues saved settings quickly without reading price history or accepting submitted settings', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $screen = Screen::factory()->for($user)->create(['columns' => ['close_raw', 'beta']]);
    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $this->actingAs($user)->post(route('screen-exports.store'), [
        'screen_id' => $screen->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-30',
        'settings' => ['sort_by' => 'beta'], 'user_id' => 999999, 'path' => 'public/export.zip',
    ])->assertSessionHasNoErrors()->assertRedirect(route('screen-exports.index', ['screen' => $screen->id]));

    $export = ScreenExport::sole();
    expect($export->user_id)->toBe($user->id)
        ->and($export->status)->toBe(ScreenExportStatusEnum::Queued)
        ->and($export->total_dates)->toBe(30)
        ->and($export->settings['sort_by'])->toBe($screen->sort_by)
        ->and($export->settings['columns'])->toBe(['close_raw', 'beta'])
        ->and($export->path)->toBeNull()
        ->and(collect($queries)->filter(fn (string $sql): bool => str_contains($sql, 'backtest_nse_instrument_prices') || str_contains($sql, 'nse_indices')))->toBeEmpty();
    Queue::assertPushed(GenerateScreenExportJob::class, fn (GenerateScreenExportJob $job): bool => $job->screenExportId === $export->id && $job->queue === 'screen-exports');
});

it('enforces thirty inclusive calendar days on the server', function (string $start, string $end, bool $valid) {
    $screen = Screen::factory()->for(User::factory()->create(['is_paid' => true]))->create();
    $response = $this->actingAs($screen->user)->post(route('screen-exports.store'), [
        'screen_id' => $screen->id, 'start_date' => $start, 'end_date' => $end,
    ]);

    if ($valid) {
        $response->assertSessionHasNoErrors();
        Queue::assertPushed(GenerateScreenExportJob::class);
    } else {
        $response->assertSessionHasErrors();
        Queue::assertNothingPushed();
        expect(ScreenExport::count())->toBe(0);
    }
})->with([
    ['2026-07-01', '2026-07-30', true], ['2026-07-01', '2026-07-31', false],
    ['2026-07-01', '2026-07-01', true], ['2024-02-01', '2024-03-01', true],
    ['2024-02-01', '2024-03-02', false], ['2026-07-02', '2026-07-01', false],
    ['not-a-date', '2026-07-01', false], ['2999-01-01', '2999-01-02', false],
]);

it('requires March 2024 data for enabled valuation ranking factors only', function (bool $enabled) {
    $screen = Screen::factory()->for(User::factory()->create(['is_paid' => true]))->create([
        'apply_factor_two' => $enabled, 'factor_two_sort_by' => 'marketcap',
    ]);
    $response = $this->actingAs($screen->user)->post(route('screen-exports.store'), [
        'screen_id' => $screen->id, 'start_date' => '2024-02-15', 'end_date' => '2024-03-15',
    ]);

    if ($enabled) {
        $response->assertSessionHasErrors('start_date');
        Queue::assertNothingPushed();
    } else {
        $response->assertSessionHasNoErrors();
        Queue::assertPushed(GenerateScreenExportJob::class);
    }
})->with([false, true]);

it('allows only one active export per user without blocking backtests or other users', function (string $status) {
    $user = User::factory()->create(['is_paid' => true]);
    $screen = Screen::factory()->for($user)->create();
    ScreenExport::factory()->for($user)->create(['status' => $status]);
    $data = ['screen_id' => $screen->id, 'start_date' => '2026-07-01', 'end_date' => '2026-07-02'];

    $this->actingAs($user)->post(route('screen-exports.store'), $data)->assertSessionHasErrors('export');
    Queue::assertNothingPushed();

    $other = User::factory()->create(['is_paid' => true]);
    $otherScreen = Screen::factory()->for($other)->create();
    Backtest::factory()->for($other)->create(['status' => 'running']);
    $this->actingAs($other)->post(route('screen-exports.store'), [...$data, 'screen_id' => $otherScreen->id])->assertSessionHasNoErrors();
    expect(ScreenExport::where('user_id', $other->id)->count())->toBe(1);
})->with(['queued', 'running']);

it('allows example screens but blocks another users private screen', function () {
    $example = Screen::factory()->create(['id' => max(Screen::PUBLIC_SCREENS)]);
    $private = Screen::factory()->create();
    $user = User::factory()->create(['is_paid' => true]);
    $data = ['start_date' => '2026-07-01', 'end_date' => '2026-07-02'];

    $this->actingAs($user)->post(route('screen-exports.store'), [...$data, 'screen_id' => $private->id])->assertNotFound();
    $this->post(route('screen-exports.store'), [...$data, 'screen_id' => $example->id])->assertSessionHasNoErrors();
    expect(ScreenExport::sole()->user_id)->toBe($user->id);
});

it('requires a paid verified account for export pages and submissions', function (string $account, string $redirect) {
    if ($account !== 'guest') {
        $this->actingAs(User::factory()->create(['is_paid' => $account !== 'free', 'email_verified_at' => $account === 'unverified' ? null : now()]));
    }
    $this->get(route('screen-exports.index'))->assertRedirect($redirect);
    $this->post(route('screen-exports.store'))->assertRedirect($redirect);
    Queue::assertNothingPushed();
})->with([['guest', '/login'], ['free', '/pricing'], ['unverified', '/email/verify']]);

it('writes distinct dated CSVs with saved ranking settings and records empty and non-trading days', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $screen = Screen::factory()->for($user)->create([
        'name' => 'Momentum', 'sort_by' => 'close_raw', 'apply_factor_two' => true,
        'apply_historical_date' => true, 'historical_date' => '2026-06-01',
    ]);
    createScreenResultRow('WRONGDATE', 'Wrong date', '2026-06-01', ['close_raw' => 999]);
    foreach (['2026-07-01' => [100, 110], '2026-07-02' => [120, 90]] as $date => [$a, $b]) {
        createScreenResultRow('ALPHA', 'Alpha', $date, ['close_raw' => $a]);
        createScreenResultRow('BRAVO', 'Bravo', $date, ['close_raw' => $b]);
    }
    createScreenResultRow('LOWVOLUME', 'Low volume', '2026-07-03', ['median_volume_one_year' => 0]);
    $export = app(QueueScreenExportAction::class)->execute($user, $screen, '2026-07-01', '2026-07-05');
    $screen->update(['sort_direction' => 'asc']);

    (new GenerateScreenExportJob($export->id))->handle(app(GenerateScreenExportAction::class));
    $export->refresh();

    expect($export->status)->toBe(ScreenExportStatusEnum::Completed)
        ->and($export->processed_dates)->toBe(5)->and($export->file_count)->toBe(3)->and($export->row_count)->toBe(4)
        ->and($export->isDownloadable())->toBeTrue()
        ->and($screen->refresh()->historical_date->toDateString())->toBe('2026-06-01');
    $zip = new ZipArchive;
    expect($zip->open(Storage::disk('local')->path($export->path)))->toBeTrue();
    expect($zip->numFiles)->toBe(5);
    $first = readScreenExportCsv($zip, '2026-07-01.csv');
    $second = readScreenExportCsv($zip, '2026-07-02.csv');
    expect(array_column($first, 'symbol'))->toBe(['BRAVO', 'ALPHA'])
        ->and(array_column($second, 'symbol'))->toBe(['ALPHA', 'BRAVO'])
        ->and(array_column($first, 'rank'))->toBe(['1', '2'])
        ->and((float) $first[0]['factor_two_rank'])->toBe(1.5)
        ->and(readScreenExportCsv($zip, '2026-07-03.csv'))->toBeEmpty();
    $summary = readScreenExportCsv($zip, 'summary.csv');
    expect(array_column($summary, 'status'))->toBe(['exported', 'exported', 'no_matches', 'no_market_data', 'no_market_data'])
        ->and(json_decode($zip->getFromName('settings.json'), true)['settings']['sort_direction'])->toBe('desc');
    $zip->close();
    Storage::disk('local')->assertDirectoryEmpty($export->directory().'/working');
});

it('marks missing membership, missing factor data and unfinished daily data separately from no matches', function () {
    $screen = Screen::factory()->create(['sort_by' => 'beta']);
    createScreenResultRow('GOOD', 'Good', '2026-07-01');
    createScreenResultRow('OTHER', 'Other', '2026-07-02', ['is_nifty_allcap' => false]);
    createScreenResultRow('NOFACTOR', 'No factor', '2026-07-03', ['beta' => null]);
    createScreenResultRow('UNFINISHED', 'Unfinished', '2026-07-04');
    AdminProcessRun::factory()->create(['process_date' => '2026-07-04', 'status' => 'in_progress']);
    $export = app(QueueScreenExportAction::class)->execute($screen->user, $screen, '2026-07-01', '2026-07-04');

    (new GenerateScreenExportJob($export->id))->handle(app(GenerateScreenExportAction::class));
    $export->refresh();
    $zip = new ZipArchive;
    $zip->open(Storage::disk('local')->path($export->path));
    expect($export->file_count)->toBe(1)->and($export->warning_count)->toBe(3)
        ->and(array_column(readScreenExportCsv($zip, 'summary.csv'), 'status'))->toBe(['exported', 'unavailable', 'unavailable', 'unavailable'])
        ->and($zip->getFromName('2026-07-02.csv'))->toBeFalse();
    $zip->close();
});

it('retains historical valuation warnings in the ZIP and can run after the source is deleted', function () {
    $screen = Screen::factory()->create(['apply_pe' => true]);
    createScreenResultRow('OLD', 'Old', '2024-02-01', ['marketcap' => null, 'price_to_earnings' => null]);
    $export = app(QueueScreenExportAction::class)->execute($screen->user, $screen, '2024-02-01', '2024-02-01');
    $screen->delete();

    (new GenerateScreenExportJob($export->id))->handle(app(GenerateScreenExportAction::class));
    $export->refresh();
    $zip = new ZipArchive;
    $zip->open(Storage::disk('local')->path($export->path));
    expect($export->screen_id)->toBeNull()->and($export->row_count)->toBe(1)->and($export->warning_count)->toBe(2)
        ->and(readScreenExportCsv($zip, 'summary.csv')[0]['warnings'])->toContain('Market cap', 'P/E');
    $zip->close();
});

it('fails clearly for a range without trading data and does not rerun a failed job', function () {
    $export = ScreenExport::factory()->create();
    $job = new GenerateScreenExportJob($export->id);
    expect(fn () => $job->handle(app(GenerateScreenExportAction::class)))->toThrow(ValidationException::class);
    expect($export->refresh()->status)->toBe(ScreenExportStatusEnum::Failed)
        ->and($export->error_message)->toContain('No trading data')
        ->and($export->path)->toBeNull();

    $generator = $this->mock(GenerateScreenExportAction::class);
    $generator->shouldNotReceive('execute');
    $job->handle($generator);
});

it('marks dispatch and worker failures as failed and removes incomplete files', function (bool $dispatchFailure) {
    if ($dispatchFailure) {
        Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Queue unavailable'));
        $screen = Screen::factory()->create();
        $export = app(QueueScreenExportAction::class)->execute($screen->user, $screen, '2026-07-01', '2026-07-02');
    } else {
        $export = ScreenExport::factory()->create(['status' => 'running']);
        Storage::disk('local')->put($export->directory().'/working/partial.csv', 'partial');
        (new GenerateScreenExportJob($export->id))->failed(new RuntimeException('Internal path /private/secret'));
    }

    expect($export->refresh()->status)->toBe(ScreenExportStatusEnum::Failed)
        ->and($export->error_message)->not->toContain('/private/secret')
        ->and(Storage::disk('local')->allFiles($export->directory()))->toBeEmpty();
})->with([false, true]);

it('restricts ZIP downloads to the owner and rejects expired or unfinished exports', function () {
    $owner = User::factory()->create(['is_paid' => true]);
    $export = ScreenExport::factory()->for($owner)->create(['status' => 'completed', 'expires_at' => now()->addDay()]);
    $export->update(['path' => $export->directory().'/export.zip']);
    Storage::disk('local')->put($export->path, 'private zip bytes');
    $url = route('screen-exports.download', $export);

    $response = $this->actingAs($owner)->get($url)->assertSuccessful()->assertDownload();
    expect($response->streamedContent())->toBe('private zip bytes');
    $this->actingAs(User::factory()->create(['is_paid' => true]))->get($url)->assertNotFound();
    $this->actingAs(User::factory()->create(['is_paid' => false]))->get($url)->assertRedirect('/pricing');
    $export->update(['status' => 'running']);
    $this->actingAs($owner)->get($url)->assertNotFound();
    $export->update(['status' => 'completed', 'expires_at' => now()->subSecond()]);
    $this->get($url)->assertGone();
});

it('lists only the users export metadata and polls without reading market data', function () {
    $user = User::factory()->create(['is_paid' => true]);
    ScreenExport::factory()->for($user)->create(['screen_name' => 'Mine']);
    ScreenExport::factory()->create(['screen_name' => 'Other']);
    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $this->actingAs($user)->get(route('screen-exports.index'))->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->component('Screens/Exports')->has('exports.data', 1)->where('exports.data.0.screen_name', 'Mine')
        ->missing('exports.data.0.settings')->missing('exports.data.0.path')->where('hasActiveExport', true)->where('maxDays', 30));
    expect(collect($queries)->filter(fn (string $sql): bool => str_contains($sql, 'backtest_nse_instrument_prices')))->toBeEmpty();
});

it('removes expired files while preserving current and active exports', function () {
    $expired = ScreenExport::factory()->create(['status' => 'completed', 'expires_at' => now()->subDay()]);
    $expired->update(['path' => $expired->directory().'/export.zip']);
    $current = ScreenExport::factory()->create(['status' => 'completed', 'expires_at' => now()->addDay()]);
    $current->update(['path' => $current->directory().'/export.zip']);
    $active = ScreenExport::factory()->create(['status' => 'running']);
    Storage::disk('local')->put($expired->path, 'expired');
    Storage::disk('local')->put($current->path, 'current');
    Storage::disk('local')->put($active->directory().'/working/part.csv', 'active');

    $this->artisan('screens:prune-exports')->assertSuccessful();
    expect($expired->refresh()->status)->toBe(ScreenExportStatusEnum::Expired)->and($expired->path)->toBeNull()
        ->and($expired->settings)->toBeEmpty()->and($current->refresh()->status)->toBe(ScreenExportStatusEnum::Completed);
    Storage::disk('local')->assertExists($current->path);
    Storage::disk('local')->assertExists($active->directory().'/working/part.csv');
});

it('configures a separate bounded export worker with one job attempt', function () {
    $job = new GenerateScreenExportJob(1);
    $supervisor = config('horizon.defaults.screen-exports');
    expect($job->tries)->toBe(1)->and($job->failOnTimeout)->toBeTrue()
        ->and($supervisor['queue'])->toBe(['screen-exports'])->and($supervisor['maxProcesses'])->toBe(1)
        ->and($supervisor['timeout'])->toBeGreaterThan($job->timeout)
        ->and(config('queue.connections.redis.retry_after'))->toBeGreaterThan($supervisor['timeout']);
});

it('removes private export files after the owner account is deleted', function () {
    $export = ScreenExport::factory()->create(['status' => 'completed', 'expires_at' => now()->addDays(7)]);
    $directory = $export->directory();
    Storage::disk('local')->put($directory.'/export.zip', 'private data');
    $export->user->delete();

    expect(ScreenExport::find($export->id))->toBeNull();
    $this->artisan('screens:prune-exports')->assertSuccessful();
    expect(Storage::disk('local')->directoryExists($directory))->toBeFalse();
});

it('uses saved EMA rules in every dated CSV even after the screen is edited', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $screen = Screen::factory()->for($user)->create(['apply_ema' => true, 'above_ema_20' => true]);
    foreach (['2026-07-01', '2026-07-02'] as $date) {
        createScreenResultRow('PASS', 'Pass', $date, ['ema_20' => 90]);
        createScreenResultRow('FAIL', 'Fail', $date, ['ema_20' => 110]);
    }
    $export = app(QueueScreenExportAction::class)->execute($user, $screen, '2026-07-01', '2026-07-02');
    $screen->update(['apply_ema' => false]);
    (new GenerateScreenExportJob($export->id))->handle(app(GenerateScreenExportAction::class));
    $export->refresh();
    expect($export->status)->toBe(ScreenExportStatusEnum::Completed)->and($export->row_count)->toBe(2);
    $zip = new ZipArchive;
    expect($zip->open(Storage::disk('local')->path($export->path)))->toBeTrue();
    foreach (['2026-07-01', '2026-07-02'] as $date) {
        expect(array_column(readScreenExportCsv($zip, $date.'.csv'), 'symbol'))->toBe(['PASS']);
    }
    $zip->close();
});
