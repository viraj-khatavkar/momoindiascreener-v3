<?php

use App\Actions\Backtest\ApplyBacktestFiltersAction;
use App\Enums\ScreenSortByOptionEnum;
use App\Models\Backtest;
use App\Models\BacktestNseInstrumentPrice;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->previousMissingAttributeCheck = Model::preventsAccessingMissingAttributes();
    Model::preventAccessingMissingAttributes();
});

afterEach(function () {
    Model::preventAccessingMissingAttributes($this->previousMissingAttributeCheck);
});

it('loads every supported ranking field in each enabled factor position', function (ScreenSortByOptionEnum $factor, string $position) {
    $date = '2026-07-31';
    createScreenResultRow('LOW', 'Low', $date, [$factor->value => 10]);
    createScreenResultRow('HIGH', 'High', $date, [$factor->value => 20]);

    $settings = [
        'sort_by' => 'close_raw',
        'sort_direction' => 'desc',
        'apply_factor_two' => $position !== 'primary',
        'factor_two_sort_by' => 'volatility_one_year',
        'factor_two_sort_direction' => 'desc',
        'apply_factor_three' => $position === 'third',
        'factor_three_sort_direction' => 'desc',
    ];
    $settings[match ($position) {
        'primary' => 'sort_by',
        'second' => 'factor_two_sort_by',
        'third' => 'factor_three_sort_by',
    }] = $factor->value;

    $results = app(ApplyBacktestFiltersAction::class)->execute(Backtest::factory()->make($settings), $date);

    expect($results->pluck('symbol')->all())->toBe(['HIGH', 'LOW'])
        ->and((float) $results->first()->getAttribute($factor->value))->toBe(20.0)
        ->and($results->pluck('rank')->all())->toBe([1, 2]);
})->with(ScreenSortByOptionEnum::cases())->with(['primary', 'second', 'third']);

it('ranks a large candidate pool within a bounded memory budget without truncating candidates', function () {
    $date = '2026-07-31';
    $template = createScreenResultRow('STOCK0000', 'Stock', $date)->getAttributes();
    unset($template['id']);
    $batch = [];

    for ($index = 1; $index < 1000; $index++) {
        $batch[] = array_replace($template, ['symbol' => sprintf('STOCK%04d', $index)]);

        if (count($batch) === 100) {
            BacktestNseInstrumentPrice::insert($batch);
            $batch = [];
        }
    }

    BacktestNseInstrumentPrice::insert($batch);
    unset($batch, $template);

    $backtest = Backtest::factory()->make(['sort_by' => 'close_raw', 'max_stocks_to_hold' => 10]);
    $action = app(ApplyBacktestFiltersAction::class);
    $memoryBefore = memory_get_usage();

    $results = $action->execute($backtest, $date);
    $memoryUsed = memory_get_usage() - $memoryBefore;

    expect($results)->toHaveCount(1000)
        ->and($results->first()->symbol)->toBe('STOCK0000')
        ->and($results->last()->symbol)->toBe('STOCK0999')
        ->and($results->last()->rank)->toBe(1000)
        ->and($memoryUsed)->toBeLessThan(40 * 1024 * 1024);
});

it('applies filters to columns that are not needed in the execution result', function () {
    $date = '2026-07-31';
    $defaults = ['ma_200' => 90, 'ma_50' => 95];
    createScreenResultRow('PASS', 'Pass', $date, $defaults);
    createScreenResultRow('BELOW_DMA', 'Below DMA', $date, array_replace($defaults, ['ma_200' => 110]));
    createScreenResultRow('LOW_VOLUME', 'Low volume', $date, array_replace($defaults, ['median_volume_one_year' => 0]));
    createScreenResultRow('CUSTOM_FAIL', 'Custom fail', $date, array_replace($defaults, ['ma_50' => 80]));

    $backtest = Backtest::factory()->make([
        'sort_by' => 'close_raw',
        'apply_ma' => true,
        'above_ma_200' => true,
        'apply_custom_filter_one' => true,
        'custom_filter_one_value_one' => 'ma_50',
        'custom_filter_one_operator' => '>=',
        'custom_filter_one_value_two' => 'ma_200',
    ]);

    $results = app(ApplyBacktestFiltersAction::class)->execute($backtest, $date);

    expect($results->pluck('symbol')->all())->toBe(['PASS'])
        ->and($results->sole()->getAttributes())->not->toHaveKey('ma_200');
});
