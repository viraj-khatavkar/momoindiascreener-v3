<?php

use App\Models\BacktestNseIndexConstituent;
use App\Models\MarketHeartbeat;

it('fails when no date is provided', function () {
    $this->artisan('backtest:calculate-market-heartbeat')
        ->expectsOutputToContain('Please provide a date')
        ->assertFailed();
});

it('uses only dated members for total market and microcap heartbeats', function () {
    BacktestNseIndexConstituent::insert([
        ['symbol' => 'MICRO', 'index' => 'nifty_microcap_250'],
        ['symbol' => 'MICRO', 'index' => 'nifty_total_market'],
    ]);
    createBacktestPriceRow('MICRO', '2024-10-31', ['series' => 'EQ', 't_percent' => 1]);
    createBacktestPriceRow('MICRO', '2024-11-01', [
        'is_nifty_microcap_250' => true, 'is_nifty_total_market' => true, 't_percent' => -1,
    ]);
    createBacktestPriceRow('REIT', '2024-11-01', [
        'series' => 'RR', 'is_nifty_total_market' => true, 't_percent' => 1,
    ]);

    $this->artisan('backtest:calculate-market-heartbeat', ['--date' => '2024-10-31'])->assertSuccessful();
    expect(MarketHeartbeat::count())->toBe(0);

    $this->artisan('backtest:calculate-market-heartbeat', ['--date' => '2024-11-01'])->assertSuccessful();
    $this->artisan('backtest:calculate-market-heartbeat', ['--date' => '2024-11-01'])->assertSuccessful();

    expect(MarketHeartbeat::count())->toBe(2)
        ->and(MarketHeartbeat::where('index', 'nifty-total-market')->sole()->advances)->toBe(1)
        ->and(MarketHeartbeat::where('index', 'nifty-total-market')->sole()->declines)->toBe(1)
        ->and(MarketHeartbeat::where('index', 'nifty-microcap-250')->sole()->advances)->toBe(0)
        ->and(MarketHeartbeat::where('index', 'nifty-microcap-250')->sole()->declines)->toBe(1);
});

it('excludes missing metrics from each percentage while retaining zero values', function () {
    $date = '2026-10-23';
    createBacktestPriceRow('ABOVE', $date, [
        'is_nifty_500' => true, 'close_adjusted' => 200, 'ma_200' => 100,
        'absolute_return_one_year' => 25, 'away_from_high_all_time' => -5, 't_percent' => 2,
    ]);
    createBacktestPriceRow('BELOW', $date, [
        'is_nifty_500' => true, 'close_adjusted' => 50, 'ma_200' => 100,
        'absolute_return_one_year' => 0, 'away_from_high_all_time' => -20, 't_percent' => -2,
    ]);
    createBacktestPriceRow('NEWREIT', $date, [
        'series' => 'RR', 'is_nifty_500' => true, 'close_adjusted' => 100,
        'ma_200' => null, 'absolute_return_one_year' => null, 'away_from_high_all_time' => null, 't_percent' => 1,
    ]);

    $this->artisan('backtest:calculate-market-heartbeat', ['--date' => $date])->assertSuccessful();
    $heartbeat = MarketHeartbeat::where('index', 'nifty-500')->sole();

    expect((float) $heartbeat->percentage_above_ma_200)->toBe(50.0)
        ->and($heartbeat->percentage_above_ma_100)->toBeNull()
        ->and((float) $heartbeat->percentage_of_stocks_with_returns_one_year_above_zero)->toBe(50.0)
        ->and((float) $heartbeat->percentage_of_stocks_with_returns_one_year_above_ten)->toBe(50.0)
        ->and((float) $heartbeat->percentage_of_stocks_with_returns_one_year_above_hundred)->toBe(0.0)
        ->and((float) $heartbeat->percentage_of_stocks_within_ten_percent_of_ath)->toBe(50.0)
        ->and((float) $heartbeat->percentage_of_stocks_within_twenty_percent_of_ath)->toBe(50.0)
        ->and((float) $heartbeat->percentage_of_stocks_within_thirty_percent_of_ath)->toBe(100.0)
        ->and($heartbeat->advances)->toBe(2)
        ->and($heartbeat->declines)->toBe(1);
});

it('stores missing percentages when members have no usable metrics', function () {
    createBacktestPriceRow('NEWREIT', '2026-10-23', [
        'series' => 'RR', 'is_nifty_allcap' => true, 'close_adjusted' => 100,
    ]);

    $this->artisan('backtest:calculate-market-heartbeat', ['--date' => '2026-10-23'])->assertSuccessful();
    $heartbeat = MarketHeartbeat::sole();
    foreach ($heartbeat->getAttributes() as $column => $value) {
        if (str_starts_with($column, 'percentage_')) {
            expect($value)->toBeNull();
        }
    }
    expect($heartbeat->advances)->toBe(0)->and($heartbeat->declines)->toBe(0);
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
