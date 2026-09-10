<?php

it('calculates returns only for the selected symbol and date using its previous trading price', function () {
    $previous = createBacktestPriceRow('TCS', '2022-01-07', [
        'close_adjusted' => 100,
        't_percent' => 7,
        't_percent_raw' => 0.07,
    ]);
    $selected = createBacktestPriceRow('TCS', '2022-01-10', ['close_adjusted' => 110]);
    $other = createBacktestPriceRow('WIPRO', '2022-01-10', [
        'close_adjusted' => 80,
        't_percent' => 3,
        't_percent_raw' => 0.03,
    ]);
    $later = createBacktestPriceRow('TCS', '2022-01-11', ['close_adjusted' => 120]);

    $this->artisan('backtest:calculate-t-percent', ['--date' => '2022-01-10', '--symbol' => 'TCS'])
        ->assertSuccessful();

    expect((float) $selected->fresh()->t_percent)->toBe(10.0)
        ->and((float) $selected->fresh()->t_percent_raw)->toBe(0.1)
        ->and((float) $previous->fresh()->t_percent)->toBe(7.0)
        ->and((float) $previous->fresh()->t_percent_raw)->toBe(0.07)
        ->and((float) $other->fresh()->t_percent)->toBe(3.0)
        ->and((float) $other->fresh()->t_percent_raw)->toBe(0.03)
        ->and((float) $later->fresh()->t_percent)->toBe(0.0);
});

it('still calculates all symbols when the symbol option is omitted', function () {
    createBacktestPriceRow('TCS', '2022-01-07', ['close_adjusted' => 100]);
    createBacktestPriceRow('WIPRO', '2022-01-07', ['close_adjusted' => 200]);
    $tcs = createBacktestPriceRow('TCS', '2022-01-10', ['close_adjusted' => 110]);
    $wipro = createBacktestPriceRow('WIPRO', '2022-01-10', ['close_adjusted' => 180]);
    $first = createBacktestPriceRow('NEW', '2022-01-10', ['close_adjusted' => 50, 't_percent' => 99]);

    $this->artisan('backtest:calculate-t-percent', ['--date' => '2022-01-10'])->assertSuccessful();

    expect((float) $tcs->fresh()->t_percent)->toBe(10.0)
        ->and((float) $wipro->fresh()->t_percent)->toBe(-10.0)
        ->and((float) $first->fresh()->t_percent)->toBe(0.0)
        ->and((float) $first->fresh()->t_percent_raw)->toBe(0.0);
});
