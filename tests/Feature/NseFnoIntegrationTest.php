<?php

use App\Actions\AdminProcess\BuildDailyProcessStepsAction;
use App\Actions\AdminProcess\CreateAdminProcessRunAction;
use App\Actions\ApplyScreenFiltersAction;
use App\Actions\Backtest\ApplyBacktestFiltersAction;
use App\Actions\Backtest\BacktestTradingCalendarAction;
use App\Actions\Backtest\RunBacktestAction;
use App\Enums\AdminProcessRunStatusEnum;
use App\Enums\AdminProcessStepStatusEnum;
use App\Enums\ApplyFiltersOnOptionEnum;
use App\Enums\NseIndexEnum;
use App\Models\AdminProcessOutputChunk;
use App\Models\Backtest;
use App\Models\BacktestNseIndexConstituent;
use App\Models\MarketHeartbeat;
use App\Models\Screen;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

it('offers F&O uploads on the existing NSE Files page and stores a dated CSV', function () {
    Storage::fake('local');
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->get('/admin/nse-files/create')->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/NseFiles/Create')
            ->where('fileNames', fn ($options) => collect($options)->contains(fn ($option) => $option['id'] === 'fno' && $option['name'] === 'F&O membership')));

    $csv = "TradDt,FinInstrmTp,TckrSymb,XpryDt\n".str_repeat("2026-10-30,STF,MEMBER,2026-11-24\n", 210000);
    $this->post('/admin/nse-files', [
        'filename' => 'fno', 'date' => '2026-10-30',
        'file' => UploadedFile::fake()->createWithContent('BhavCopy_NSE_FO.csv', $csv),
    ])->assertSessionHasNoErrors()->assertRedirect('/admin/nse-files?date=2026-10-30');
    Storage::assertExists('uploads/2026-10-30/fno.csv');

    $saved = Storage::get('uploads/2026-10-30/fno.csv');
    expect($saved)->toBe($csv);
    $this->post('/admin/nse-files', [
        'filename' => 'fno', 'date' => '2026-10-30',
        'file' => UploadedFile::fake()->createWithContent('wrong-date.csv', "TradDt,FinInstrmTp,TckrSymb,XpryDt\n2026-10-29,STF,MEMBER,2026-11-24\n"),
    ])->assertSessionHasErrors('file');
    expect(Storage::get('uploads/2026-10-30/fno.csv'))->toBe($saved);
});

it('rejects ZIP uploads and ZIP contents renamed as CSV without replacing a saved file', function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $csv = "TradDt,FinInstrmTp,TckrSymb,XpryDt\n2026-10-30,STF,MEMBER,2026-11-24\n";
    $path = Storage::path('bhavcopy.zip');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('reports/BhavCopy_NSE_FO.csv', $csv);
    $zip->close();
    $bytes = file_get_contents($path);
    Storage::put('uploads/2026-10-30/fno.csv', $csv);

    foreach (['BhavCopy_NSE_FO.csv.zip', 'renamed.csv'] as $filename) {
        $this->post('/admin/nse-files', [
            'filename' => 'fno', 'date' => '2026-10-30',
            'file' => UploadedFile::fake()->createWithContent($filename, $bytes),
        ])->assertSessionHasErrors('file');
    }
    expect(Storage::get('uploads/2026-10-30/fno.csv'))->toBe($csv);
    createBacktestPriceRow('MEMBER', '2026-10-30');
    $this->artisan('backtest:import-fno-constituents', ['--date' => '2026-10-30'])->assertSuccessful();
    expect(isIndexFlagged('MEMBER', '2026-10-30', 'is_nifty_fno'))->toBeTrue();

    $this->post('/admin/nse-files', [
        'filename' => 'fno', 'date' => '2026-10-29',
        'file' => UploadedFile::fake()->createWithContent('wrong-date.zip', $bytes),
    ])->assertSessionHasErrors('file');
    Storage::assertMissing('uploads/2026-10-29/fno.csv');

    $this->post('/admin/nse-files', [
        'filename' => 'bhavcopy', 'date' => '2026-10-30',
        'file' => UploadedFile::fake()->createWithContent('fo.zip', $bytes),
    ])->assertSessionHasErrors('file');
});

it('rejects legacy and settlement formats on the F&O upload page', function (string $csv) {
    Storage::fake('local');
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $this->post('/admin/nse-files', [
        'filename' => 'fno', 'date' => '2026-10-30',
        'file' => UploadedFile::fake()->createWithContent('fno.csv', $csv),
    ])->assertSessionHasErrors(['file' => 'Only NSE F&O UDiFF files are supported. Required columns: FININSTRMTP, TCKRSYMB, TRADDT, XPRYDT']);
    Storage::assertMissing('uploads/2026-10-30/fno.csv');
})->with([
    'legacy' => ["INSTRUMENT,SYMBOL,TIMESTAMP,EXPIRY_DT\nFUTSTK,MEMBER,30-OCT-2026,24-NOV-2026\n"],
    'settlement' => ["DATE,INSTRUMENT,UNDERLYING,EXPIRY DATE\n30-OCT-2026,FUTSTK,MEMBER,24-NOV-2026\n"],
]);

it('requires the F&O file and imports membership before daily calculations', function () {
    $builder = app(BuildDailyProcessStepsAction::class);
    $steps = collect($builder->execute('2026-10-30'))->keyBy('key');
    expect(collect($builder->requiredFiles('2026-10-30'))->pluck('key'))->toContain('fno')
        ->and($steps['import-fno-constituents']['command_line'])->toBe('php artisan backtest:import-fno-constituents --date=2026-10-30');
    $keys = $steps->keys()->all();
    expect(array_search('import-fno-constituents', $keys))->toBeGreaterThan(array_search('import-constituents', $keys))
        ->toBeLessThan(array_search('process-daily-data', $keys));
});

it('adds the F&O step only to unfinished runs without changing saved results', function (AdminProcessRunStatusEnum $status) {
    $run = app(CreateAdminProcessRunAction::class)->execute(User::factory()->create(['is_admin' => true]), '2026-10-30');
    $run->update(['status' => $status]);
    $run->steps()->where('key', 'import-fno-constituents')->delete();
    foreach ($run->steps()->get() as $position => $step) {
        $step->update(['position' => $position + 1]);
    }
    $first = $run->steps()->first();
    $first->update(['status' => AdminProcessStepStatusEnum::Completed, 'attempts' => 2]);
    $chunk = AdminProcessOutputChunk::factory()->create(['admin_process_step_id' => $first->id]);
    $original = $run->steps()->get()->keyBy('key');
    $migration = require database_path('migrations/2026_11_15_140336_add_fno_step_to_unfinished_admin_process_runs.php');
    $migration->up();
    $migration->up();
    $run->refresh()->load('steps');
    expect($run->steps->where('key', 'import-fno-constituents'))->toHaveCount($status === AdminProcessRunStatusEnum::Completed ? 0 : 1)
        ->and($first->outputChunks()->sole()->id)->toBe($chunk->id);
    foreach ($original as $key => $step) {
        expect(collect($run->steps->firstWhere('key', $key)->getAttributes())->except('position')->all())
            ->toBe(collect($step->getAttributes())->except('position')->all());
    }
})->with(AdminProcessRunStatusEnum::cases());

it('uses historical F&O members in screens and backtest filters', function () {
    $date = '2011-01-05';
    $screen = Screen::factory()->create(['index' => NseIndexEnum::NIFTY_FNO]);
    $backtest = Backtest::factory()->create(['index' => NseIndexEnum::NIFTY_FNO]);
    createScreenResultRow('OLD', 'Old member', $date, ['is_nifty_fno' => true]);
    createScreenResultRow('FUTURE', 'Future member', $date, ['is_nifty_fno' => false, 'sharpe_return_one_year' => 50]);
    createScreenResultRow('FUTURE', 'Future member', '2026-10-30', ['is_nifty_fno' => true]);

    expect(Schema::hasTable('backtest_nse_fno_imports'))->toBeFalse()
        ->and(app(ApplyScreenFiltersAction::class)->execute($screen, $date)->pluck('symbol')->all())->toBe(['OLD'])
        ->and(app(ApplyBacktestFiltersAction::class)->execute($backtest, $date)->pluck('symbol')->all())->toBe(['OLD'])
        ->and(collect(NseIndexEnum::getOptionsForFilters())->pluck('id'))->toContain('nifty_fno');
});

it('counts dated F&O price flags before metric filters for decile sizes', function (bool $missingMetrics) {
    $date = '2026-10-30';
    $settings = ['index' => NseIndexEnum::NIFTY_FNO, 'apply_filters_on' => ApplyFiltersOnOptionEnum::TOP_DECILE];
    $screen = Screen::factory()->create($settings);
    $backtest = Backtest::factory()->create($settings);
    foreach (range(1, 20) as $number) {
        createScreenResultRow('STOCK'.$number, 'Stock', $date, [
            'is_nifty_fno' => true, 'sharpe_return_one_year' => $missingMetrics && $number <= 10 ? null : $number,
        ]);
    }
    createScreenResultRow('OUTSIDE', 'Outside F&O', $date, ['sharpe_return_one_year' => 100]);
    createScreenResultRow('FUTURE', 'Future member', '2026-11-02', ['is_nifty_fno' => true]);
    expect(app(ApplyScreenFiltersAction::class)->execute($screen, $date)->pluck('symbol')->all())->toBe(['STOCK20', 'STOCK19'])
        ->and(app(ApplyBacktestFiltersAction::class)->execute($backtest, $date)->pluck('symbol')->all())->toBe(['STOCK20', 'STOCK19'])
        ->and(NseIndexEnum::NIFTY_500->numberOfConstituents())->toBe(500);
})->with([false, true]);

it('reports missing F&O coverage in screens and blocks a backtest with an interior gap', function () {
    $screen = Screen::factory()->create(['index' => NseIndexEnum::NIFTY_FNO]);
    $action = app(ApplyScreenFiltersAction::class);
    $price = createScreenResultRow('MEMBER', 'Member', '2011-01-05');
    expect($action->execute($screen, '2011-01-05'))->toBeEmpty()
        ->and($action->getValuationFilterWarnings()[0])->toContain('F&O membership is not available');

    $backtest = Backtest::factory()->create(['index' => NseIndexEnum::NIFTY_FNO]);
    $price->update(['is_nifty_fno' => true]);
    createScreenResultRow('MEMBER', 'Member', '2011-01-06');
    createScreenResultRow('MEMBER', 'Member', '2011-01-07', ['is_nifty_fno' => true]);
    expect(fn () => app(RunBacktestAction::class)->validateDataAvailability($backtest))
        ->toThrow(ValidationException::class, '2011-01-06');
});

it('allows F&O backtests across the excluded special session but still rejects other missing dates', function () {
    $backtest = Backtest::factory()->create(['index' => NseIndexEnum::NIFTY_FNO, 'start_date' => '2024-03-01']);
    foreach (['2024-03-01', '2024-03-04'] as $date) {
        createBacktestPriceRow('MEMBER', $date, ['is_nifty_fno' => true]);
    }
    createBacktestPriceRow('EMBASSY', '2024-03-02', ['series' => 'RR']);
    expect(collect(app(BuildDailyProcessStepsAction::class)->requiredFiles('2024-03-02'))->pluck('key'))->not->toContain('fno');

    $action = app(RunBacktestAction::class);
    $action->validateDataAvailability($backtest);
    expect(app(BacktestTradingCalendarAction::class)->query(NseIndexEnum::NIFTY_FNO)->pluck('date')->all())
        ->toBe(['2024-03-01', '2024-03-04']);

    createBacktestPriceRow('MEMBER', '2024-03-05');
    expect(fn () => $action->validateDataAvailability($backtest))->toThrow(ValidationException::class, '2024-03-05');
});

it('includes F&O in market heartbeat and allows a focused historical refresh', function () {
    createBacktestPriceRow('UP', '2026-10-30', ['is_nifty_fno' => true, 'is_nifty_50' => true, 'ma_200' => 90, 'close_adjusted' => 100, 't_percent' => 1]);
    createBacktestPriceRow('DOWN', '2026-10-30', ['is_nifty_fno' => true, 'ma_200' => 110, 'close_adjusted' => 100, 't_percent' => -1]);
    createBacktestPriceRow('OUTSIDE', '2026-10-30', ['ma_200' => 90, 'close_adjusted' => 100, 't_percent' => 1]);
    $this->artisan('backtest:calculate-market-heartbeat', ['--date' => '2026-10-30', '--index' => 'nifty-fno'])->assertSuccessful();
    expect(MarketHeartbeat::sole()->index)->toBe('nifty-fno')
        ->and((float) MarketHeartbeat::sole()->percentage_above_ma_200)->toBe(50.0)
        ->and(MarketHeartbeat::sole()->advances)->toBe(1)
        ->and(MarketHeartbeat::sole()->declines)->toBe(1);
    $this->get('/market-health/nifty-fno')->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->where('indexName', 'NSE F&O Stocks')->has('heartbeats', 1));
});

it('preserves dated F&O membership through daily symbol changes', function () {
    Storage::fake('local');
    $price = createBacktestPriceRow('OLD', '2011-01-05', ['is_nifty_fno' => true]);
    $outside = createBacktestPriceRow('OLD', '2011-01-06');
    $this->artisan('backtest:change-symbol', ['--old-symbol' => 'OLD', '--new-symbol' => 'NEW'])->assertSuccessful();
    expect($price->fresh()->symbol)->toBe('NEW')->and($price->fresh()->is_nifty_fno)->toBeTrue()
        ->and($outside->fresh()->symbol)->toBe('NEW')->and($outside->fresh()->is_nifty_fno)->toBeFalse();
});

it('does not copy the current F&O list over historical membership in general constituent imports', function () {
    createBacktestPriceRow('OLD', '2011-01-05', ['is_nifty_fno' => true]);
    createBacktestPriceRow('CURRENT', '2011-01-05');
    BacktestNseIndexConstituent::insert(['index' => 'nifty_fno', 'symbol' => 'CURRENT']);
    $this->artisan('backtest:import-constituents', ['--date' => '2011-01-05'])->assertSuccessful();
    expect(isIndexFlagged('OLD', '2011-01-05', 'is_nifty_fno'))->toBeTrue()
        ->and(isIndexFlagged('CURRENT', '2011-01-05', 'is_nifty_fno'))->toBeFalse();
});
