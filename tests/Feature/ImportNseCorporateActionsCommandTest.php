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
 * ex-date = column 6 in Y-m-d, purpose = column 9).
 *
 * @param  array<int, array{0: string, 1: string, 2: string, 3: string}>  $rows  tuples of [series, symbol, exDate, purpose]
 */
function putCorporateActionsCsv(string $date, array $rows): void
{
    $lines = ['SERIES,SYMBOL,SECURITY,RECORD_DT,BC_STRT_DT,BC_END_DT,EX_DT,ND_STRT_DT,ND_END_DT,PURPOSE'];

    foreach ($rows as [$series, $symbol, $exDate, $purpose]) {
        $lines[] = "{$series},{$symbol},SECURITY,{$exDate},,,{$exDate},,,{$purpose}";
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
        ['EQ', 'TCS', '2020-01-28', 'BONUS 1:2'],
    ]);

    $this->artisan('backtest:import-corporate-actions', ['--date' => '2020-01-27', '--series' => 'EQ'])
        ->expectsOutputToContain('No corporate actions found')
        ->assertSuccessful();

    expect(BacktestNseCorporateAction::count())->toBe(0);
});

it('imports a corporate action with parsed type, ratio and dividend', function (string $purpose, CorporateActionTypeEnum $type, ?string $ratio, ?string $dividend) {
    putCorporateActionsCsv('2020-01-27', [
        ['EQ', 'TCS', '2020-01-27', $purpose],
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
        ['EQ', 'TCS', '2020-01-27', 'AGM/DIV-RS.3.50 PER SHARE'],
    ]);

    $this->artisan('backtest:import-corporate-actions', ['--date' => '2020-01-27', '--series' => 'EQ'])
        ->assertSuccessful();

    expect(BacktestNseCorporateAction::sole()->dividend)->toBe('3.50');
});

it('stores purposes that match the filter but no classification with a null type', function () {
    putCorporateActionsCsv('2020-01-27', [
        ['EQ', 'TCS', '2020-01-27', 'FV SPLIT RS.10 TO RE.1'],
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
        ['EQ', 'WIPRO', '2020-01-27', 'BONUS 1:2'],
        ['BE', 'INFY', '2020-01-27', 'BONUS 1:2'],
        ['EQ', 'TCS', '2020-01-28', 'BONUS 1:2'],
    ]);

    $this->artisan('backtest:import-corporate-actions', ['--date' => '2020-01-27', '--series' => 'EQ'])
        ->assertSuccessful();

    expect(BacktestNseCorporateAction::pluck('symbol')->all())->toBe(['WIPRO']);
});

it('keeps every action when a symbol has multiple actions on the same day', function () {
    putCorporateActionsCsv('2020-01-27', [
        ['EQ', 'TCS', '2020-01-27', 'BONUS 1:2'],
        ['EQ', 'TCS', '2020-01-27', 'DIV RS 5 PER SH'],
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
        ['EQ', 'WIPRO', '2020-01-27', 'BONUS 1:2'],
        ['EQ', 'TCS', '2020-01-27', 'BONUS 1:2'],
    ]);

    $this->artisan('backtest:import-corporate-actions', ['--date' => '2020-01-27', '--series' => 'EQ'])
        ->expectsOutputToContain('No price record for TCS on 2020-01-27')
        ->assertSuccessful();

    expect(BacktestNseCorporateAction::count())->toBe(2);
});

it('prints but does not persist actions with omit-create', function () {
    putCorporateActionsCsv('2020-01-27', [
        ['EQ', 'TCS', '2020-01-27', 'BONUS 1:2'],
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
        ['BE', 'IIFLWAM', '2020-01-29', 'INT DIV-RS 10 PER SH'],
        ['EQ', 'IIFLWAM', '2020-01-29', 'INT DIV-RS 10 PER SH'],
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
        ['BE', 'IIFLWAM', '2020-01-29', 'INT DIV-RS 10 PER SH'],
        ['EQ', 'IIFLWAM', '2020-01-29', 'INT DIV-RS 10 PER SH'],
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
        ['EQ', 'TCS', '2020-01-27', 'BONUS 1:2'],
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
        ['EQ', 'TCS', '2020-01-28', 'DIV RS 5 PER SH'],
    ]);
    putCorporateActionsCsv($previousDate, [
        ['EQ', 'WIPRO', '2020-01-28', 'BONUS 1:2'],
        ['BE', 'INFY', '2020-01-28', 'BONUS 1:2'],
        ['EQ', 'HDFC', '2020-01-27', 'BONUS 1:2'],
        ['EQ', 'ITC', '2020-01-29', 'DIV RS 2 PER SH'],
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
        ['EQ', 'WIPRO', '2020-01-27', 'BONUS 1:2'],
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
        ['EQ', 'WIPRO', '2020-01-27', 'BONUS 1:2'],
    ]);
    putCorporateActionsCsv('2020-01-23', [
        ['EQ', 'TCS', '2020-01-27', 'DIV RS 5 PER SH'],
    ]);
    putCorporateActionsCsv('2020-01-28', [
        ['EQ', 'INFY', '2020-01-27', 'BONUS 1:2'],
    ]);
    putCorporateActionsCsv('2020-01-26', [
        ['EQ', 'HDFC', '2020-01-27', 'BONUS 1:2'],
    ]);

    $this->artisan('backtest:import-corporate-actions', ['--date' => '2020-01-27', '--series' => 'EQ'])
        ->assertSuccessful();

    expect(BacktestNseCorporateAction::sole()->symbol)->toBe('WIPRO');
});

it('keeps the current file details for duplicate actions across files and repeated imports', function () {
    createBacktestPriceRow('INFY', '2020-01-24');

    putCorporateActionsCsv('2020-01-27', [
        ['EQ', 'TCS', '2020-01-27', 'INT DIV RS 5 PER SH'],
    ]);
    putCorporateActionsCsv('2020-01-24', [
        ['EQ', 'TCS', '2020-01-27', 'DIV RS 5 PER SH'],
        ['EQ', 'TCS', '2020-01-27', 'DIV RS 5 PER SH'],
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
        ['EQ', 'TCS', '2020-01-27', 'BONUS 1:2'],
        ['EQ', 'TCS', '2020-01-27', 'DIV RS 5 PER SH'],
    ]);
    putCorporateActionsCsv('2020-01-24', [
        ['EQ', 'TCS', '2020-01-27', 'BONUS 1:2'],
        ['EQ', 'TCS', '2020-01-27', 'BONUS 1:3'],
        ['EQ', 'TCS', '2020-01-27', 'DIV RS 4 PER SH'],
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
        ['EQ', 'TCS', '2020-01-27', 'INT DIV RS 5 PER SH'],
    ]);
    putCorporateActionsCsv('2020-01-24', [
        ['EQ', 'TCS', '2020-01-27', 'DIV RS 5 PER SH'],
        ['EQ', 'WIPRO', '2020-01-27', 'BONUS 1:2'],
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
        ['EQ', 'TCS', '2020-01-27', 'DIV RS 5 PER SH'],
    ]);
    putCorporateActionsCsv('2020-01-24', [
        ['EQ', 'WIPRO', '2020-01-27', 'BONUS 1:2'],
    ]);

    $this->artisan('backtest:import-corporate-actions', ['--date' => '2020-01-27', '--series' => 'EQ'])
        ->assertSuccessful();

    expect(BacktestNseCorporateAction::sole()->symbol)->toBe('TCS');
});

it('warns and imports the current file when the previous trading day file is missing', function () {
    createBacktestPriceRow('INFY', '2020-01-23');
    createBacktestPriceRow('INFY', '2020-01-24');
    putCorporateActionsCsv('2020-01-27', [
        ['EQ', 'TCS', '2020-01-27', 'DIV RS 5 PER SH'],
    ]);
    putCorporateActionsCsv('2020-01-23', [
        ['EQ', 'WIPRO', '2020-01-27', 'BONUS 1:2'],
    ]);

    $this->artisan('backtest:import-corporate-actions', ['--date' => '2020-01-27', '--series' => 'EQ'])
        ->expectsOutputToContain('No corporate actions file for 2020-01-24, skipping previous trading day')
        ->assertSuccessful();

    expect(BacktestNseCorporateAction::sole()->symbol)->toBe('TCS');
});

it('imports the October 14 actions using ISO ex-dates', function () {
    putCorporateActionsCsv('2025-10-14', [
        ['EQ', 'DELPHIFX', '2025-10-14', 'RGHTS 23:49@PRM RS 181/-'],
        ['EQ', 'GOKULAGRO', '2025-10-14', 'FVSPLT FRM RS 2 TO RE 1'],
        ['EQ', 'TATAINVEST', '2025-10-14', 'FVSPLT FRM RS 10 TO RE 1'],
        ['EQ', 'TATAMOTORS', '2025-10-14', 'DEMERGER'],
        ['EQ', 'UTKARSHBNK', '2025-10-14', 'RIGHTS 8:13 @ PRM RS 4/-'],
        ['SM', 'MOS', '2025-10-14', 'BONUS 1:1'],
    ]);

    $this->artisan('backtest:import-corporate-actions', ['--date' => '2025-10-14', '--series' => 'EQ'])->assertSuccessful();
    $this->artisan('backtest:import-corporate-actions', ['--date' => '2025-10-14', '--series' => 'SM'])->assertSuccessful();

    $actions = BacktestNseCorporateAction::query()->get()->keyBy('symbol');

    expect($actions)->toHaveCount(6)
        ->and($actions->get('DELPHIFX')->type)->toBe(CorporateActionTypeEnum::RIGHTS)
        ->and($actions->get('GOKULAGRO')->ratio)->toBe('2:1')
        ->and($actions->get('TATAINVEST')->ratio)->toBe('10:1')
        ->and($actions->get('TATAMOTORS')->type)->toBe(CorporateActionTypeEnum::DEMERGER)
        ->and($actions->get('UTKARSHBNK')->type)->toBe(CorporateActionTypeEnum::RIGHTS)
        ->and($actions->get('MOS')->ratio)->toBe('1:1')
        ->and($actions->pluck('date')->map->toDateString()->unique()->values()->all())->toBe(['2025-10-14']);
});

it('ignores legacy ex-dates in both the current and previous files', function () {
    createBacktestPriceRow('TCS', '2025-10-10');
    putCorporateActionsCsv('2025-10-10', [
        ['EQ', 'LEGACYOLD', '14/10/2025', 'BONUS 1:1'],
    ]);
    putCorporateActionsCsv('2025-10-14', [
        ['EQ', 'LEGACYNEW', '14/10/2025', 'BONUS 1:1'],
        ['EQ', 'TATAMOTORS', '2025-10-14', 'DEMERGER'],
    ]);

    $this->artisan('backtest:import-corporate-actions', ['--date' => '2025-10-14', '--series' => 'EQ'])->assertSuccessful();

    expect(BacktestNseCorporateAction::query()->sole()->symbol)->toBe('TATAMOTORS');
});

it('imports a trust distribution once and retains its applied state on repeat', function () {
    putCorporateActionsCsv('2025-08-05', [
        ['RR', 'EMBASSY', '2025-08-05', 'Distribution Rs 5.80 Per Unit Interest Rs 0.18/Dividend Rs 2.01/Repayment Rs 3.61'],
        ['IV', 'INDIGRID', '2025-08-05', 'Interest Rs 2 Per Unit'],
    ]);
    $arguments = ['--date' => '2025-08-05', '--series' => 'RR'];
    $this->artisan('backtest:import-corporate-actions', $arguments)->assertSuccessful();
    $action = BacktestNseCorporateAction::sole();
    expect($action->type)->toBe(CorporateActionTypeEnum::DISTRIBUTION)->and($action->dividend)->toBe('5.8');
    $action->update(['dividend_adjustment_factor' => '0.98', 'dividend_adjustment_applied_at' => now()]);
    $this->artisan('backtest:import-corporate-actions', $arguments)->assertSuccessful();
    expect(BacktestNseCorporateAction::count())->toBe(1)->and($action->fresh()->dividend_adjustment_factor)->toBe('0.98');
});

it('imports all three October distributions including NSE abbreviations without duplicates', function () {
    $date = '2026-10-30';
    $rows = [
        ['IV', 'CUBEINVIT', $date, 'DISTRIBUTION - RS 3.79'],
        ['RR', 'EMBASSY', $date, 'DISTRIBUTN- RS 7.07'],
        ['RT', 'EMBASSY', $date, 'DISTRIBUTN- RS 7.07'],
        ['IV', 'SEITINVIT', $date, 'DISTRIBUTN - RS 3.13984'],
    ];
    createBacktestPriceRow('OTHER', '2026-10-29');
    putCorporateActionsCsv('2026-10-29', $rows);
    putCorporateActionsCsv($date, $rows);

    $this->artisan('backtest:import-corporate-actions', ['--date' => $date, '--series' => 'RR', '--omit-create' => true])
        ->expectsOutputToContain('EMBASSY 2026-10-30: cash 7.07')
        ->assertSuccessful();
    $this->artisan('backtest:import-corporate-actions', ['--date' => $date, '--series' => 'IV', '--omit-create' => true])
        ->expectsOutputToContain('CUBEINVIT 2026-10-30: cash 3.79')
        ->expectsOutputToContain('SEITINVIT 2026-10-30: cash 3.13984')
        ->assertSuccessful();
    expect(BacktestNseCorporateAction::count())->toBe(0);

    foreach (['RR', 'IV', 'RR', 'IV'] as $series) {
        $this->artisan('backtest:import-corporate-actions', ['--date' => $date, '--series' => $series])->assertSuccessful();
    }

    $actions = BacktestNseCorporateAction::query()->orderBy('symbol')->get();
    expect($actions->pluck('dividend', 'symbol')->all())->toBe([
        'CUBEINVIT' => '3.79', 'EMBASSY' => '7.07', 'SEITINVIT' => '3.13984',
    ])
        ->and($actions->pluck('series')->all())->toBe(['IV', 'RR', 'IV'])
        ->and($actions->pluck('type')->unique()->values()->all())->toBe([CorporateActionTypeEnum::DISTRIBUTION])
        ->and($actions->pluck('date')->map->toDateString()->unique()->values()->all())->toBe([$date]);
});

it('rejects unresolved trust cash before saving any actions', function () {
    putCorporateActionsCsv('2025-08-05', [
        ['RR', 'EMBASSY', '2025-08-05', 'Interest Rs 1 Per Unit'],
        ['RR', 'BIRET', '2025-08-05', 'Distribution'],
    ]);
    $this->artisan('backtest:import-corporate-actions', ['--date' => '2025-08-05', '--series' => 'RR'])->assertFailed();
    expect(BacktestNseCorporateAction::count())->toBe(0);
});

it('uses the current trust cash file over its previous day copy and ignores debt repayment', function () {
    createBacktestPriceRow('OTHER', '2025-08-04');
    putCorporateActionsCsv('2025-08-04', [['IV', 'INDIGRID', '2025-08-05', 'Distribution Rs 3 Per Unit']]);
    putCorporateActionsCsv('2025-08-05', [
        ['IV', 'INDIGRID', '2025-08-05', 'Distribution Rs 4 Per Unit'],
        ['IV', 'IRBINVIT', '2025-08-05', 'Repayment of debt Rs 3 Per Unit'],
    ]);
    $this->artisan('backtest:import-corporate-actions', ['--date' => '2025-08-05', '--series' => 'IV'])->assertSuccessful();
    expect(BacktestNseCorporateAction::sole()->dividend)->toBe('4');
});
