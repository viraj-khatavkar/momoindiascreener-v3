<?php

use App\Enums\ScreenExportStatusEnum;
use App\Jobs\GenerateScreenExportJob;
use App\Models\Screen;
use App\Models\ScreenExport;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('opens the export page from a saved screen and queues an inclusive thirty day range', function (int $width, int $height) {
    Queue::fake();
    $user = User::factory()->create(['is_paid' => true]);
    $screen = Screen::factory()->for($user)->create([
        'id' => max(Screen::PUBLIC_SCREENS) + 1,
        'name' => 'Monthly Momentum',
    ]);
    createScreenResultRow('ALPHA', 'Alpha', '2026-07-31');
    loginAs($user->email);

    $page = visit('/screens/'.$screen->id.'/edit')->resize($width, $height)
        ->click('Historical ZIP')
        ->assertPathIs('/screen-exports')
        ->assertValue('screen_id', (string) $screen->id)
        ->fill('start_date', '2026-07-01')
        ->fill('end_date', '2026-07-31')
        ->assertSee('Select no more than 30 calendar days, including both dates.')
        ->assertDisabled('Generate ZIP')
        ->fill('end_date', '2026-07-30')
        ->assertEnabled('Generate ZIP')
        ->press('Generate ZIP')
        ->assertSee('Your screen export is queued.')
        ->assertSee('Your current export is in progress.')
        ->assertSee('Queued')
        ->assertDisabled('Generate ZIP')
        ->assertNoJavaScriptErrors();

    $export = ScreenExport::sole();
    expect($export->user_id)->toBe($user->id)
        ->and($export->total_dates)->toBe(30)
        ->and($export->status)->toBe(ScreenExportStatusEnum::Queued)
        ->and($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
    Queue::assertPushed(GenerateScreenExportJob::class, fn (GenerateScreenExportJob $job): bool => $job->screenExportId === $export->id);
})->with([[1440, 1000], [390, 844]]);

it('shows progress and updates the download link when an export completes', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $export = ScreenExport::factory()->for($user)->create([
        'screen_name' => 'Daily Momentum',
        'status' => ScreenExportStatusEnum::Running,
        'processed_dates' => 12,
    ]);
    loginAs($user->email);

    $page = visit('/screen-exports')
        ->assertSee('Preparing')
        ->assertSee('12 of 30 dates checked')
        ->assertDontSee('Download ZIP');

    $export->update([
        'status' => ScreenExportStatusEnum::Completed,
        'processed_dates' => 30,
        'file_count' => 21,
        'row_count' => 420,
        'warning_count' => 1,
        'path' => $export->directory().'/export.zip',
        'expires_at' => now()->addDays(7),
    ]);

    $page->assertSee('Ready')
        ->assertSee('21 daily CSV files')
        ->assertSee('420 matching rows')
        ->assertSee('Read summary.csv in the ZIP.')
        ->assertSee('Download ZIP')
        ->assertDontSee('Your current export is in progress.')
        ->assertNoJavaScriptErrors();
});

it('shows failed and expired exports without download links', function () {
    $user = User::factory()->create(['is_paid' => true]);
    ScreenExport::factory()->for($user)->create([
        'status' => ScreenExportStatusEnum::Failed,
        'error_message' => 'No trading data is available in the selected date range.',
    ]);
    ScreenExport::factory()->for($user)->create([
        'status' => ScreenExportStatusEnum::Completed,
        'path' => 'screen-exports/expired/export.zip',
        'expires_at' => now()->subMinute(),
    ]);
    loginAs($user->email);

    visit('/screen-exports')
        ->assertSee('No trading data is available in the selected date range.')
        ->assertSee('This download has expired.')
        ->assertDontSee('Download ZIP')
        ->assertNoJavaScriptErrors();
});
