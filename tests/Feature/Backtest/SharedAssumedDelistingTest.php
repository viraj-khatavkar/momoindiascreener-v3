<?php

use App\Actions\Backtest\FindAssumedDelistingExitsAction;
use App\Actions\Backtest\InvalidateAssumedDelistingsAction;
use App\Actions\Backtest\StartBacktestRunAction;
use App\Actions\Backtest\UpdateAssumedDelistingsAction;
use App\Models\Backtest;
use App\Models\BacktestNseTradingGap;
use App\Models\BacktestNseTradingGapState;
use Carbon\Carbon;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

/** @return Collection<int, Carbon> */
function sharedGapCalendar(int $days): Collection
{
    return collect(range(0, $days - 1))->map(function (int $day): Carbon {
        $date = Carbon::parse('2024-01-08')->addWeekdays($day);
        createScreenResultRow('MARKET', 'Market', $date->toDateString());

        return $date;
    });
}

/** @return list<array<string, mixed>> */
function savedSharedGaps(): array
{
    return BacktestNseTradingGap::query()->orderBy('symbol')->orderBy('last_traded_date')
        ->toBase()->get(['symbol', 'last_traded_date', 'confirmation_date', 'resumed_date'])
        ->map(fn (object $gap): array => (array) $gap)->all();
}

it('stores shared events once and loads them without reading any price history', function () {
    $dates = sharedGapCalendar(102);
    createScreenResultRow('A', 'A', $dates[1]->toDateString());
    $this->artisan('backtest:update-assumed-delistings')->assertSuccessful();
    $before = savedSharedGaps();
    $first = Backtest::factory()->create();
    $second = Backtest::factory()->create(['weightage' => 'price_weighted']);

    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        $one = app(FindAssumedDelistingExitsAction::class)->execute($first, $dates);
        $two = app(FindAssumedDelistingExitsAction::class)->execute($second, $dates);
        $queries = collect(DB::getQueryLog())->pluck('query')->all();
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    expect($one)->toBe([$dates[1]->toDateString() => ['A' => $dates[101]->toDateString()]])
        ->and($two)->toBe($one)
        ->and(implode(' ', $queries))->not->toContain('backtest_nse_instrument_prices')
        ->and(savedSharedGaps())->toBe($before);

    $this->artisan('backtest:update-assumed-delistings')->assertSuccessful();
    expect(savedSharedGaps())->toBe($before);
});

it('updates only appended price rows and matches a full rebuild', function (int $resumesAt) {
    $dates = sharedGapCalendar(205);
    foreach ([0, 1, $resumesAt] as $index) {
        createScreenResultRow('A', 'A', $dates[$index]->toDateString());
    }
    $update = app(UpdateAssumedDelistingsAction::class);
    $update->execute($dates[100]->toDateString());
    expect(BacktestNseTradingGap::query()->where('symbol', 'A')->sole()->confirmation_date)->toBeNull();

    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        $result = $update->execute();
        $priceReads = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_starts_with($query['query'], 'select `symbol`, `date` from `backtest_nse_instrument_prices`'));
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    expect($result['rebuilt'])->toBeFalse()->and($priceReads)->not->toBeEmpty();
    foreach ($priceReads as $query) {
        expect($query['query'])->toContain('force index (bnip_date_symbol_unique)', '`date` between ? and ?')
            ->and($query['bindings'][0])->toBeGreaterThan($dates[100]->toDateString());
    }
    $incremental = savedSharedGaps();
    $update->execute(rebuild: true);
    expect(savedSharedGaps())->toBe($incremental)
        ->and(BacktestNseTradingGap::query()->where('symbol', 'A')->count())->toBe($resumesAt === 102 ? 2 : 1);
})->with([101, 102]);

it('advances confirmation for a stock with no new quote', function () {
    $dates = sharedGapCalendar(102);
    createScreenResultRow('A', 'A', $dates[1]->toDateString());
    $update = app(UpdateAssumedDelistingsAction::class);
    $update->execute($dates[100]->toDateString());
    expect(BacktestNseTradingGap::query()->where('symbol', 'A')->sole()->confirmation_date)->toBeNull();
    $update->execute();
    expect(BacktestNseTradingGap::query()->where('symbol', 'A')->sole()->confirmation_date->toDateString())->toBe($dates[101]->toDateString());
});

it('does not use confirmation dates outside the simulated period', function () {
    $dates = sharedGapCalendar(103);
    createScreenResultRow('A', 'A', $dates[1]->toDateString());
    app(UpdateAssumedDelistingsAction::class)->execute();
    expect(app(FindAssumedDelistingExitsAction::class)->execute(Backtest::factory()->create(), $dates->take(101)))->toBe([]);
});

it('counts the selected simulation calendar before scheduling an exit', function () {
    $dates = sharedGapCalendar(110);
    createScreenResultRow('A', 'A', $dates[1]->toDateString());
    createScreenResultRow('A', 'A', $dates[102]->toDateString());
    app(UpdateAssumedDelistingsAction::class)->execute();
    $shorterCalendar = $dates->reject(fn (Carbon $date, int $index): bool => $index >= 50 && $index < 55)->values();
    expect(app(FindAssumedDelistingExitsAction::class)->execute(Backtest::factory()->create(), $shorterCalendar))->toBe([]);
});

it('rejects missing or stale shared data before deleting saved results', function (string $state) {
    $dates = sharedGapCalendar(102);
    if ($state !== 'missing') {
        BacktestNseTradingGapState::factory()->create([
            'processed_through' => $dates[$state === 'outdated' ? 100 : 101]->toDateString(),
            'requires_rebuild' => $state === 'dirty',
        ]);
    }
    $backtest = Backtest::factory()->create(['start_date' => $dates->first()->toDateString()]);
    $snapshot = $backtest->dailySnapshots()->create([
        'date' => $dates->first()->toDateString(), 'nav' => 100, 'portfolio_value' => 0,
        'cash' => 10000, 'total_value' => 10000, 'holdings_count' => 0,
    ]);
    Queue::fake();
    expect(fn () => app(StartBacktestRunAction::class)->execute($backtest))->toThrow(ValidationException::class);
    $this->assertModelExists($snapshot);
    Queue::assertNothingPushed();
})->with(['missing', 'outdated', 'dirty']);

it('removes an assumed exit after corrected historical quotes fill the gap', function () {
    $dates = sharedGapCalendar(103);
    createScreenResultRow('A', 'A', $dates[1]->toDateString());
    app(UpdateAssumedDelistingsAction::class)->execute();
    createScreenResultRow('A', 'A', $dates[50]->toDateString());
    app(InvalidateAssumedDelistingsAction::class)->execute($dates[50]->toDateString());
    $result = app(UpdateAssumedDelistingsAction::class)->execute();
    expect($result['rebuilt'])->toBeTrue()
        ->and(BacktestNseTradingGap::query()->whereNotNull('confirmation_date')->count())->toBe(0);
});

it('rebuilds after a symbol change without keeping the old symbol', function () {
    $dates = sharedGapCalendar(102);
    createScreenResultRow('OLD', 'Old', $dates[1]->toDateString());
    app(UpdateAssumedDelistingsAction::class)->execute();
    $this->artisan('backtest:change-symbol', ['--old-symbol' => 'OLD', '--new-symbol' => 'NEW'])->assertSuccessful();
    expect(BacktestNseTradingGapState::findOrFail(1)->requires_rebuild)->toBeTrue();
    $this->artisan('backtest:update-assumed-delistings')->assertSuccessful();
    expect(BacktestNseTradingGap::query()->where('symbol', 'OLD')->exists())->toBeFalse()
        ->and(BacktestNseTradingGap::query()->where('symbol', 'NEW')->sole()->confirmation_date->toDateString())->toBe($dates[101]->toDateString());
});

it('keeps gaps current when a price adjustment preserves valid quote dates', function (string $command, string $factorField, bool $dryRun) {
    sharedGapCalendar(2);
    app(UpdateAssumedDelistingsAction::class)->execute();
    createCorporateAction('MARKET', '2024-01-09', [$factorField => 2]);
    $this->artisan($command, ['--date' => '2024-01-09', '--dry-run' => $dryRun])->assertSuccessful();
    expect(BacktestNseTradingGapState::findOrFail(1)->requires_rebuild)->toBeFalse();
})->with([
    ['backtest:adjust-corporate-action', 'price_adjustment_factor'],
    ['backtest:adjust-dividends', 'dividend_adjustment_factor'],
])->with([false, true]);

it('rebuilds when an applied adjustment rounds a stored valid quote down to zero', function (string $command, string $factorField, float $factor, bool $dryRun) {
    $dates = sharedGapCalendar(102);
    createScreenResultRow('A', 'A', $dates[0]->toDateString(), ['close_adjusted' => 100]);
    $smallPrice = createScreenResultRow('A', 'A', $dates[50]->toDateString(), ['close_adjusted' => 0.01]);
    app(UpdateAssumedDelistingsAction::class)->execute();
    createCorporateAction('A', $dates[101]->toDateString(), [$factorField => $factor]);

    $this->artisan($command, ['--date' => $dates[101]->toDateString(), '--dry-run' => $dryRun])->assertSuccessful();

    expect(BacktestNseTradingGapState::findOrFail(1)->requires_rebuild)->toBe(! $dryRun)
        ->and((float) $smallPrice->fresh()->close_adjusted)->toBe($dryRun ? 0.01 : 0.0);
    $result = app(UpdateAssumedDelistingsAction::class)->execute();
    expect($result['rebuilt'])->toBe(! $dryRun)
        ->and($result['confirmed_gaps'])->toBe($dryRun ? 0 : 1);
})->with([
    ['backtest:adjust-corporate-action', 'price_adjustment_factor', 10.0],
    ['backtest:adjust-dividends', 'dividend_adjustment_factor', 0.1],
])->with([false, true]);

it('does not invalidate saved gaps when only unprocessed quotes become zero', function () {
    $dates = sharedGapCalendar(4);
    app(UpdateAssumedDelistingsAction::class)->execute($dates[1]->toDateString());
    createScreenResultRow('NEW', 'New', $dates[2]->toDateString(), ['close_adjusted' => 0.01]);
    createCorporateAction('NEW', $dates[3]->toDateString(), ['price_adjustment_factor' => 10]);
    $this->artisan('backtest:adjust-corporate-action', ['--date' => $dates[3]->toDateString()])->assertSuccessful();

    expect(BacktestNseTradingGapState::findOrFail(1)->requires_rebuild)->toBeFalse();
    $result = app(UpdateAssumedDelistingsAction::class)->execute();
    expect($result['rebuilt'])->toBeFalse()
        ->and(BacktestNseTradingGap::query()->where('symbol', 'NEW')->exists())->toBeFalse();
});

it('reads a new trading date once after a normal dividend adjustment', function () {
    $dates = sharedGapCalendar(102);
    foreach (range(1, 120) as $index) {
        createScreenResultRow('A'.$index, 'Stock', $dates[0]->toDateString());
        createScreenResultRow('A'.$index, 'Stock', $dates[101]->toDateString());
    }
    $update = app(UpdateAssumedDelistingsAction::class);
    $update->execute($dates[100]->toDateString());
    createCorporateAction('A1', $dates[101]->toDateString(), ['dividend_adjustment_factor' => 0.99]);
    $this->artisan('backtest:adjust-dividends', ['--date' => $dates[101]->toDateString()])->assertSuccessful();

    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        $result = $update->execute();
        $priceReads = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_starts_with($query['query'], 'select `symbol`, `date` from `backtest_nse_instrument_prices`'));
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    expect($result['rebuilt'])->toBeFalse()->and($priceReads)->toHaveCount(1)
        ->and(array_slice($priceReads->first()['bindings'], 0, 2))->toBe(array_fill(0, 2, $dates[101]->toDateString()))
        ->and(BacktestNseTradingGap::query()->whereNotNull('resumed_date')->count())->toBe(120);
    $incremental = savedSharedGaps();
    $update->execute(rebuild: true);
    expect(savedSharedGaps())->toBe($incremental);
});

it('preserves multiple completed gaps across update windows and ignores nonpositive quotes', function () {
    $dates = sharedGapCalendar(310);
    foreach ([0, 101, 202] as $index) {
        createScreenResultRow('A', 'A', $dates[$index]->toDateString());
    }
    createScreenResultRow('A', 'A', $dates[75]->toDateString(), ['close_adjusted' => 0]);
    createScreenResultRow('A', 'A', $dates[180]->toDateString(), ['close_adjusted' => -1]);
    $update = app(UpdateAssumedDelistingsAction::class);
    foreach ([99, 110, 201, 209, 309] as $index) {
        $update->execute($dates[$index]->toDateString());
    }

    $gaps = BacktestNseTradingGap::query()->where('symbol', 'A')->orderBy('last_traded_date')->get();
    expect($gaps)->toHaveCount(3)
        ->and($gaps->map(fn (BacktestNseTradingGap $gap): string => $gap->confirmation_date->toDateString())->all())
        ->toBe([$dates[100]->toDateString(), $dates[201]->toDateString(), $dates[302]->toDateString()]);
    $incremental = savedSharedGaps();
    $update->execute(rebuild: true);
    expect(savedSharedGaps())->toBe($incremental);
});

it('rejects invalid or unavailable command dates', function (array $options) {
    $this->artisan('backtest:update-assumed-delistings', $options)->assertFailed();
})->with([[[]], [['--date' => 'invalid']], [['--date' => '2024-01-08']]]);

it('updates every symbol including stocks with no new quotes', function () {
    $dates = sharedGapCalendar(101);
    foreach (range(1, 105) as $index) {
        createScreenResultRow('A'.$index, 'Stock', $dates[0]->toDateString());
    }
    $this->artisan('backtest:update-assumed-delistings')->assertSuccessful();
    expect(BacktestNseTradingGap::query()->whereNotNull('confirmation_date')->count())->toBe(105);
    $nextDate = $dates->last()->copy()->addWeekday()->toDateString();
    createScreenResultRow('MARKET', 'Market', $nextDate);
    $this->artisan('backtest:update-assumed-delistings')->assertSuccessful();
    expect(BacktestNseTradingGap::query()->whereNotNull('confirmation_date')->count())->toBe(105)
        ->and(BacktestNseTradingGap::query()->whereNull('resumed_date')->count())->toBe(106);
});

it('rolls back a failed update without publishing partial gaps or advancing the processed date', function () {
    $dates = sharedGapCalendar(102);
    createScreenResultRow('A', 'A', $dates[1]->toDateString());
    app(UpdateAssumedDelistingsAction::class)->execute($dates[100]->toDateString());
    $before = savedSharedGaps();
    $failInsert = true;
    DB::listen(function (QueryExecuted $query) use (&$failInsert): void {
        if ($failInsert && str_starts_with($query->sql, 'insert into `backtest_nse_trading_gaps`')) {
            throw new RuntimeException('Test update failure');
        }
    });
    try {
        expect(fn () => app(UpdateAssumedDelistingsAction::class)->execute())->toThrow(RuntimeException::class, 'Test update failure');
    } finally {
        $failInsert = false;
    }
    expect(savedSharedGaps())->toBe($before)
        ->and(BacktestNseTradingGapState::findOrFail(1)->processed_through->toDateString())->toBe($dates[100]->toDateString());
});
