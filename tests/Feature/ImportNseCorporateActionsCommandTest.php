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

it('imports matching actions from the current file before the previous trading day file', function (string $previousDate) {
    createBacktestPriceRow('INFY', $previousDate);

    putCorporateActionsCsv('2020-01-28', [
        ['EQ', 'TCS', '28/01/2020', 'DIV RS 5 PER SH'],
    ]);
    putCorporateActionsCsv($previousDate, [
        ['EQ', 'WIPRO', '28/01/2020', 'BONUS 1:2'],
        ['BE', 'INFY', '28/01/2020', 'BONUS 1:2'],
        ['EQ', 'HDFC', '27/01/2020', 'BONUS 1:2'],
        ['EQ', 'ITC', '29/01/2020', 'DIV RS 2 PER SH'],
    ]);

    $this->artisan('backtest:import-corporate-actions', ['--date' => '2020-01-28', '--series' => 'EQ'])
        ->assertSuccessful();

    $actions = BacktestNseCorporateAction::orderBy('id')->get();

    expect($actions->pluck('symbol')->all())->toBe(['TCS', 'WIPRO'])
        ->and($actions->pluck('date')->map->format('Y-m-d')->all())->toBe(['2020-01-28', '2020-01-28']);
})->with([
    'previous calendar day' => ['2020-01-27'],
    'weekend and holiday gap' => ['2020-01-24'],
    'longer holiday gap' => ['2020-01-23'],
]);

it('checks the previous file when the current file has no matching actions', function () {
    createBacktestPriceRow('INFY', '2020-01-24');

    putCorporateActionsCsv('2020-01-27', []);
    putCorporateActionsCsv('2020-01-24', [
        ['EQ', 'WIPRO', '27/01/2020', 'BONUS 1:2'],
    ]);

    $this->artisan('backtest:import-corporate-actions', ['--date' => '2020-01-27', '--series' => 'EQ'])
        ->doesntExpectOutputToContain('No corporate actions found')
        ->assertSuccessful();

    expect(BacktestNseCorporateAction::sole()->symbol)->toBe('WIPRO');
});

it('selects the latest earlier price date across symbols and series', function () {
    createBacktestPriceRow('TCS', '2020-01-23', ['series' => 'EQ']);
    createBacktestPriceRow('INFY', '2020-01-24', ['series' => 'BE']);
    createBacktestPriceRow('TCS', '2020-01-27', ['series' => 'EQ']);
    createBacktestPriceRow('TCS', '2020-01-28', ['series' => 'EQ']);

    putCorporateActionsCsv('2020-01-27', []);
    putCorporateActionsCsv('2020-01-24', [
        ['EQ', 'WIPRO', '27/01/2020', 'BONUS 1:2'],
    ]);
    putCorporateActionsCsv('2020-01-23', [
        ['EQ', 'TCS', '27/01/2020', 'DIV RS 5 PER SH'],
    ]);
    putCorporateActionsCsv('2020-01-28', [
        ['EQ', 'INFY', '27/01/2020', 'BONUS 1:2'],
    ]);
    putCorporateActionsCsv('2020-01-26', [
        ['EQ', 'HDFC', '27/01/2020', 'BONUS 1:2'],
    ]);

    $this->artisan('backtest:import-corporate-actions', ['--date' => '2020-01-27', '--series' => 'EQ'])
        ->assertSuccessful();

    expect(BacktestNseCorporateAction::sole()->symbol)->toBe('WIPRO');
});

it('keeps the current file details for duplicate actions across files and repeated imports', function () {
    createBacktestPriceRow('INFY', '2020-01-24');

    putCorporateActionsCsv('2020-01-27', [
        ['EQ', 'TCS', '27/01/2020', 'INT DIV RS 5 PER SH'],
    ]);
    putCorporateActionsCsv('2020-01-24', [
        ['EQ', 'TCS', '27/01/2020', 'DIV RS 5 PER SH'],
        ['EQ', 'TCS', '27/01/2020', 'DIV RS 5 PER SH'],
    ]);

    $this->artisan('backtest:import-corporate-actions', ['--date' => '2020-01-27', '--series' => 'EQ'])
        ->assertSuccessful();

    $action = BacktestNseCorporateAction::sole();
    $action->update([
        'dividend_adjustment_factor' => '0.98',
        'dividend_adjustment_applied_at' => now(),
    ]);

    $this->artisan('backtest:import-corporate-actions', ['--date' => '2020-01-27', '--series' => 'EQ'])
        ->assertSuccessful();

    $importedAction = BacktestNseCorporateAction::sole();

    expect($importedAction->id)->toBe($action->id)
        ->and($importedAction->description)->toBe('Corporate Action: EQ TCS INT DIV RS 5 PER SH')
        ->and($importedAction->dividend)->toBe('5')
        ->and($importedAction->dividend_adjustment_factor)->toBe('0.98')
        ->and($importedAction->dividend_adjustment_applied_at->equalTo($action->dividend_adjustment_applied_at))->toBeTrue();
});

it('keeps distinct action types and ratios for the same symbol across both files', function () {
    createBacktestPriceRow('INFY', '2020-01-24');

    putCorporateActionsCsv('2020-01-27', [
        ['EQ', 'TCS', '27/01/2020', 'BONUS 1:2'],
        ['EQ', 'TCS', '27/01/2020', 'DIV RS 5 PER SH'],
    ]);
    putCorporateActionsCsv('2020-01-24', [
        ['EQ', 'TCS', '27/01/2020', 'BONUS 1:2'],
        ['EQ', 'TCS', '27/01/2020', 'BONUS 1:3'],
        ['EQ', 'TCS', '27/01/2020', 'DIV RS 4 PER SH'],
    ]);

    $this->artisan('backtest:import-corporate-actions', ['--date' => '2020-01-27', '--series' => 'EQ'])
        ->assertSuccessful();

    $actions = BacktestNseCorporateAction::orderBy('id')->get();

    expect($actions->pluck('ratio')->all())->toBe(['1:2', '5', '1:3', '4'])
        ->and($actions->pluck('type')->all())->toBe([
            CorporateActionTypeEnum::BONUS,
            CorporateActionTypeEnum::DIVIDEND,
            CorporateActionTypeEnum::BONUS,
            CorporateActionTypeEnum::DIVIDEND,
        ]);
});

it('previews unique actions from both files without saving them', function () {
    createBacktestPriceRow('INFY', '2020-01-24');

    putCorporateActionsCsv('2020-01-27', [
        ['EQ', 'TCS', '27/01/2020', 'INT DIV RS 5 PER SH'],
    ]);
    putCorporateActionsCsv('2020-01-24', [
        ['EQ', 'TCS', '27/01/2020', 'DIV RS 5 PER SH'],
        ['EQ', 'WIPRO', '27/01/2020', 'BONUS 1:2'],
    ]);

    $this->artisan('backtest:import-corporate-actions', [
        '--date' => '2020-01-27',
        '--series' => 'EQ',
        '--omit-create' => true,
    ])
        ->expectsOutputToContain('Corporate Action: EQ TCS INT DIV RS 5 PER SH')
        ->expectsOutputToContain('Corporate Action: EQ WIPRO BONUS 1:2')
        ->doesntExpectOutputToContain('Corporate Action: EQ TCS DIV RS 5 PER SH')
        ->assertSuccessful();

    expect(BacktestNseCorporateAction::count())->toBe(0);
});

it('imports the current file when there are no earlier price dates', function () {
    createBacktestPriceRow('TCS', '2020-01-27');
    createBacktestPriceRow('TCS', '2020-01-28');
    putCorporateActionsCsv('2020-01-27', [
        ['EQ', 'TCS', '27/01/2020', 'DIV RS 5 PER SH'],
    ]);
    putCorporateActionsCsv('2020-01-24', [
        ['EQ', 'WIPRO', '27/01/2020', 'BONUS 1:2'],
    ]);

    $this->artisan('backtest:import-corporate-actions', ['--date' => '2020-01-27', '--series' => 'EQ'])
        ->assertSuccessful();

    expect(BacktestNseCorporateAction::sole()->symbol)->toBe('TCS');
});

it('warns and imports the current file when the previous trading day file is missing', function () {
    createBacktestPriceRow('INFY', '2020-01-23');
    createBacktestPriceRow('INFY', '2020-01-24');
    putCorporateActionsCsv('2020-01-27', [
        ['EQ', 'TCS', '27/01/2020', 'DIV RS 5 PER SH'],
    ]);
    putCorporateActionsCsv('2020-01-23', [
        ['EQ', 'WIPRO', '27/01/2020', 'BONUS 1:2'],
    ]);

    $this->artisan('backtest:import-corporate-actions', ['--date' => '2020-01-27', '--series' => 'EQ'])
        ->expectsOutputToContain('No corporate actions file for 2020-01-24, skipping previous trading day')
        ->assertSuccessful();

    expect(BacktestNseCorporateAction::sole()->symbol)->toBe('TCS');
});
