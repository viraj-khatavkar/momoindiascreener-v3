<?php

use App\Enums\CorporateActionTypeEnum;
use App\Models\BacktestNseCorporateAction;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Fake the disk so the command never touches the real uploads/ files.
    Storage::fake('local');
});

/**
 * Write a fake NSE corporate_actions.csv (series = column 0, symbol = column 1,
 * ex-date = column 6 in d/m/Y, purpose = column 9).
 *
 * @param  array<int, array{0: string, 1: string, 2: string, 3: string}>  $rows  tuples of [series, symbol, exDate, purpose]
 */
function putCorporateActionsCsv(string $date, array $rows): void
{
    $lines = ['SERIES,SYMBOL,SECURITY,FACE VALUE,QTY,RECORD DATE,EX-DATE,BC START,BC END,PURPOSE'];

    foreach ($rows as [$series, $symbol, $exDate, $purpose]) {
        $lines[] = "{$series},{$symbol},SECURITY,1,100,{$exDate},{$exDate},-,-,{$purpose}";
    }

    Storage::put("uploads/{$date}/corporate_actions.csv", implode("\n", $lines));
}

it('fails when no date is provided', function () {
    $this->artisan('backtest:import-corporate-actions')
        ->expectsOutputToContain('Please provide a date')
        ->assertFailed();
});

it('fails when no series is provided', function () {
    $this->artisan('backtest:import-corporate-actions', ['--date' => '2020-01-27'])
        ->expectsOutputToContain('Please provide a series')
        ->assertFailed();
});

it('reports when no corporate actions match the date', function () {
    putCorporateActionsCsv('2020-01-27', [
        ['EQ', 'TCS', '28/01/2020', 'BONUS 1:2'],
    ]);

    $this->artisan('backtest:import-corporate-actions', ['--date' => '2020-01-27', '--series' => 'EQ'])
        ->expectsOutputToContain('No corporate actions found')
        ->assertSuccessful();

    expect(BacktestNseCorporateAction::count())->toBe(0);
});

it('imports a corporate action with parsed type, ratio and dividend', function (string $purpose, CorporateActionTypeEnum $type, ?string $ratio, ?string $dividend) {
    putCorporateActionsCsv('2020-01-27', [
        ['EQ', 'TCS', '27/01/2020', $purpose],
    ]);

    $this->artisan('backtest:import-corporate-actions', ['--date' => '2020-01-27', '--series' => 'EQ'])
        ->assertSuccessful();

    $action = BacktestNseCorporateAction::sole();

    expect($action->symbol)->toBe('TCS')
        ->and($action->series)->toBe('EQ')
        ->and($action->date->format('Y-m-d'))->toBe('2020-01-27')
        ->and($action->type)->toBe($type)
        ->and($action->ratio)->toBe($ratio)
        ->and($action->dividend)->toBe($dividend)
        ->and($action->description)->toBe('Corporate Action: EQ TCS '.$purpose);
})->with([
    'bonus' => ['BONUS 1:2', CorporateActionTypeEnum::BONUS, '1:2', null],
    'split' => ['FV SPLT FRM RS.10 TO RS.2', CorporateActionTypeEnum::SPLIT, '10:2', null],
    'dividend' => ['DIV RS 5 PER SH', CorporateActionTypeEnum::DIVIDEND, '5', '5'],
    'rights' => ['RIGHTS 1:1', CorporateActionTypeEnum::RIGHTS, 'RIGHTS', null],
    'demerger' => ['DEMERGER', CorporateActionTypeEnum::DEMERGER, 'DEMERGER', null],
]);

it('extracts the dividend amount from decorated purposes', function () {
    putCorporateActionsCsv('2020-01-27', [
        ['EQ', 'TCS', '27/01/2020', 'AGM/DIV-RS.3.50 PER SHARE'],
    ]);

    $this->artisan('backtest:import-corporate-actions', ['--date' => '2020-01-27', '--series' => 'EQ'])
        ->assertSuccessful();

    expect(BacktestNseCorporateAction::sole()->dividend)->toBe('3.50');
});

it('stores purposes that match the filter but no classification with a null type', function () {
    putCorporateActionsCsv('2020-01-27', [
        ['EQ', 'TCS', '27/01/2020', 'FV SPLIT RS.10 TO RE.1'],
    ]);

    $this->artisan('backtest:import-corporate-actions', ['--date' => '2020-01-27', '--series' => 'EQ'])
        ->assertSuccessful();

    $action = BacktestNseCorporateAction::sole();

    expect($action->type)->toBeNull()
        ->and($action->ratio)->toBeNull()
        ->and($action->dividend)->toBeNull();
});

it('only imports rows for the given series and ex-date', function () {
    putCorporateActionsCsv('2020-01-27', [
        ['EQ', 'WIPRO', '27/01/2020', 'BONUS 1:2'],
        ['BE', 'INFY', '27/01/2020', 'BONUS 1:2'],
        ['EQ', 'TCS', '28/01/2020', 'BONUS 1:2'],
    ]);

    $this->artisan('backtest:import-corporate-actions', ['--date' => '2020-01-27', '--series' => 'EQ'])
        ->assertSuccessful();

    expect(BacktestNseCorporateAction::pluck('symbol')->all())->toBe(['WIPRO']);
});

it('keeps every action when a symbol has multiple actions on the same day', function () {
    putCorporateActionsCsv('2020-01-27', [
        ['EQ', 'TCS', '27/01/2020', 'BONUS 1:2'],
        ['EQ', 'TCS', '27/01/2020', 'DIV RS 5 PER SH'],
    ]);

    $this->artisan('backtest:import-corporate-actions', ['--date' => '2020-01-27', '--series' => 'EQ'])
        ->assertSuccessful();

    expect(BacktestNseCorporateAction::count())->toBe(2)
        ->and(BacktestNseCorporateAction::pluck('type')->all())
        ->toBe([CorporateActionTypeEnum::BONUS, CorporateActionTypeEnum::DIVIDEND]);
});

it('warns when the symbol has no price record for the date', function () {
    createBacktestPriceRow('WIPRO', '2020-01-27');

    putCorporateActionsCsv('2020-01-27', [
        ['EQ', 'WIPRO', '27/01/2020', 'BONUS 1:2'],
        ['EQ', 'TCS', '27/01/2020', 'BONUS 1:2'],
    ]);

    $this->artisan('backtest:import-corporate-actions', ['--date' => '2020-01-27', '--series' => 'EQ'])
        ->expectsOutputToContain('No price record for TCS on 2020-01-27')
        ->assertSuccessful();

    expect(BacktestNseCorporateAction::count())->toBe(2);
});

it('prints but does not persist actions with omit-create', function () {
    putCorporateActionsCsv('2020-01-27', [
        ['EQ', 'TCS', '27/01/2020', 'BONUS 1:2'],
    ]);

    $this->artisan('backtest:import-corporate-actions', [
        '--date' => '2020-01-27',
        '--series' => 'EQ',
        '--omit-create' => true,
    ])
        ->expectsOutputToContain('Corporate Action: EQ TCS BONUS 1:2')
        ->assertSuccessful();

    expect(BacktestNseCorporateAction::count())->toBe(0);
});

it('does not duplicate the same action listed under multiple series', function () {
    putCorporateActionsCsv('2020-01-29', [
        ['BE', 'IIFLWAM', '29/01/2020', 'INT DIV-RS 10 PER SH'],
        ['EQ', 'IIFLWAM', '29/01/2020', 'INT DIV-RS 10 PER SH'],
    ]);

    $this->artisan('backtest:import-corporate-actions', ['--date' => '2020-01-29', '--series' => 'BE'])
        ->assertSuccessful();
    $this->artisan('backtest:import-corporate-actions', ['--date' => '2020-01-29', '--series' => 'EQ'])
        ->assertSuccessful();

    $action = BacktestNseCorporateAction::sole();

    expect($action->symbol)->toBe('IIFLWAM')
        ->and($action->series)->toBe('EQ')
        ->and($action->description)->toBe('Corporate Action: EQ IIFLWAM INT DIV-RS 10 PER SH')
        ->and($action->dividend)->toBe('10');
});

it('preserves factors across series re-imports of the same action', function () {
    putCorporateActionsCsv('2020-01-29', [
        ['BE', 'IIFLWAM', '29/01/2020', 'INT DIV-RS 10 PER SH'],
        ['EQ', 'IIFLWAM', '29/01/2020', 'INT DIV-RS 10 PER SH'],
    ]);

    $this->artisan('backtest:import-corporate-actions', ['--date' => '2020-01-29', '--series' => 'BE'])
        ->assertSuccessful();

    BacktestNseCorporateAction::sole()->update([
        'dividend_adjustment_factor' => '0.98',
        'dividend_adjustment_applied_at' => now(),
    ]);

    $this->artisan('backtest:import-corporate-actions', ['--date' => '2020-01-29', '--series' => 'EQ'])
        ->assertSuccessful();

    $action = BacktestNseCorporateAction::sole();

    expect($action->dividend_adjustment_factor)->toBe('0.98')
        ->and($action->dividend_adjustment_applied_at)->not->toBeNull();
});

it('preserves adjustment factors and applied stamps on re-import', function () {
    putCorporateActionsCsv('2020-01-27', [
        ['EQ', 'TCS', '27/01/2020', 'BONUS 1:2'],
    ]);

    $this->artisan('backtest:import-corporate-actions', ['--date' => '2020-01-27', '--series' => 'EQ'])
        ->assertSuccessful();

    BacktestNseCorporateAction::sole()->update([
        'price_adjustment_factor' => '0.5',
        'price_adjustment_applied_at' => now(),
    ]);

    $this->artisan('backtest:import-corporate-actions', ['--date' => '2020-01-27', '--series' => 'EQ'])
        ->assertSuccessful();

    $action = BacktestNseCorporateAction::sole();

    expect($action->price_adjustment_factor)->toBe('0.5')
        ->and($action->price_adjustment_applied_at)->not->toBeNull()
        ->and($action->type)->toBe(CorporateActionTypeEnum::BONUS);
});
