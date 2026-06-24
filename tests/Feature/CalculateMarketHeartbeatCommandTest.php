<?php

use App\Models\MarketHeartbeat;

it('fails when no date is provided', function () {
    $this->artisan('backtest:calculate-market-heartbeat')
        ->expectsOutputToContain('Please provide a date')
        ->assertFailed();
});

it('computes market heartbeat for the new indices', function () {
    $date = '2019-12-20';

    // Smallcap 250: one stock above all MAs with a positive day, one below with a negative day.
    createBacktestPriceRow('SCUP', $date, [
        'is_nifty_smallcap_250' => true,
        'close_adjusted' => 100, 'ma_200' => 50, 'ma_100' => 50, 'ma_50' => 50, 'ma_20' => 50,
        'absolute_return_one_year' => 25, 'away_from_high_all_time' => -5, 't_percent' => 2,
    ]);
    createBacktestPriceRow('SCDOWN', $date, [
        'is_nifty_smallcap_250' => true,
        'close_adjusted' => 10, 'ma_200' => 50, 'ma_100' => 50, 'ma_50' => 50, 'ma_20' => 50,
        'absolute_return_one_year' => -25, 'away_from_high_all_time' => -50, 't_percent' => -2,
    ]);
    // Midcap 150: a single stock above its MAs.
    createBacktestPriceRow('MIDUP', $date, [
        'is_nifty_midcap_150' => true,
        'close_adjusted' => 100, 'ma_200' => 50, 'ma_100' => 50, 'ma_50' => 50, 'ma_20' => 50,
        'absolute_return_one_year' => 25, 'away_from_high_all_time' => -5, 't_percent' => 1,
    ]);
    createBacktestPriceRow('N200UP', $date, [
        'is_nifty_200' => true,
        'close_adjusted' => 100, 'ma_200' => 50, 'ma_100' => 50, 'ma_50' => 50, 'ma_20' => 50,
        'absolute_return_one_year' => 25, 'away_from_high_all_time' => -5, 't_percent' => 1,
    ]);
    createBacktestPriceRow('MID100UP', $date, [
        'is_nifty_midcap_100' => true,
        'close_adjusted' => 100, 'ma_200' => 50, 'ma_100' => 50, 'ma_50' => 50, 'ma_20' => 50,
        'absolute_return_one_year' => 25, 'away_from_high_all_time' => -5, 't_percent' => 1,
    ]);

    $this->artisan('backtest:calculate-market-heartbeat', ['--date' => $date])->assertSuccessful();

    $smallcap = MarketHeartbeat::where('index', 'nifty-smallcap-250')->where('date', $date)->first();
    $midcap = MarketHeartbeat::where('index', 'nifty-midcap-150')->where('date', $date)->first();
    $nifty200 = MarketHeartbeat::where('index', 'nifty-200')->where('date', $date)->first();
    $midcap100 = MarketHeartbeat::where('index', 'nifty-midcap-100')->where('date', $date)->first();

    expect($smallcap)->not->toBeNull()
        ->and((float) $smallcap->percentage_above_ma_200)->toBe(50.0)
        ->and((int) $smallcap->advances)->toBe(1)
        ->and((int) $smallcap->declines)->toBe(1)
        ->and($midcap)->not->toBeNull()
        ->and((float) $midcap->percentage_above_ma_200)->toBe(100.0)
        ->and((int) $midcap->advances)->toBe(1)
        ->and($nifty200)->not->toBeNull()
        ->and((float) $nifty200->percentage_above_ma_200)->toBe(100.0)
        ->and($midcap100)->not->toBeNull()
        ->and((float) $midcap100->percentage_above_ma_200)->toBe(100.0);
});
