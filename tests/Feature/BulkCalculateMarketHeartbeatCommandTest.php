<?php

use App\Console\Commands\Backtest\BulkCalcualteMarketHeartbeatCommand;
use App\Models\MarketHeartbeat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** MySQL TRUNCATE commits implicitly. Let the command own its date transactions. */
beforeEach(function () {
    DB::rollBack();
});

it('refreshes only available index dates and includes history before 2011', function () {
    createBacktestPriceRow('EARLY', '2010-01-04', ['is_nifty_allcap' => true, 't_percent' => 1]);
    createBacktestPriceRow('STOCK', '2024-10-31', ['is_nifty_allcap' => true, 't_percent' => 1]);
    createBacktestPriceRow('MICRO', '2024-11-01', [
        'is_nifty_allcap' => true, 'is_nifty_total_market' => true, 'is_nifty_microcap_250' => true, 't_percent' => -1,
    ]);

    $this->artisan('backtest:bulk-calculate-market-heartbeat')->assertSuccessful();
    $this->artisan('backtest:bulk-calculate-market-heartbeat')->assertSuccessful();

    expect(MarketHeartbeat::count())->toBe(5)
        ->and(MarketHeartbeat::where('index', 'nifty-allcap')->min('date'))->toBe('2010-01-04')
        ->and(MarketHeartbeat::where('index', 'nifty-total-market')->sole()->date->toDateString())->toBe('2024-11-01')
        ->and(MarketHeartbeat::where('index', 'nifty-microcap-250')->sole()->date->toDateString())->toBe('2024-11-01');
});

it('clears saved heartbeats before rebuilding all source dates', function () {
    $before = createBacktestPriceRow('OLD', '2024-10-31', ['is_nifty_allcap' => true, 't_percent' => 1]);
    createBacktestPriceRow('NEW', '2024-11-01', ['is_nifty_allcap' => true, 't_percent' => -1]);
    $this->artisan('backtest:calculate-market-heartbeat', ['--date' => '2024-10-31'])->assertSuccessful();
    $before->update(['t_percent' => -1]);
    MarketHeartbeat::insert([
        'index' => 'nifty-total-market', 'date' => '2009-01-01',
        'advances' => 1, 'declines' => 0, 'advance_decline_ratio' => 1,
    ]);

    $this->artisan('backtest:bulk-calculate-market-heartbeat')->assertSuccessful();

    expect(MarketHeartbeat::count())->toBe(2)
        ->and(MarketHeartbeat::where('date', '2009-01-01')->exists())->toBeFalse()
        ->and(MarketHeartbeat::where('date', '2024-10-31')->sole()->advances)->toBe(0)
        ->and(MarketHeartbeat::where('date', '2024-10-31')->sole()->declines)->toBe(1);
});

it('fails without continuing when a daily heartbeat calculation fails', function () {
    createBacktestPriceRow('FIRST', '2024-11-01');
    createBacktestPriceRow('SECOND', '2024-11-04');
    $command = $this->partialMock(BulkCalcualteMarketHeartbeatCommand::class);
    $command->__construct();
    $command->shouldReceive('call')
        ->once()
        ->with('backtest:calculate-market-heartbeat', ['--date' => '2024-11-01'])
        ->andReturnUsing(function (): int {
            MarketHeartbeat::insert([
                'index' => 'nifty-total-market', 'date' => '2024-11-01',
                'advances' => 1, 'declines' => 0, 'advance_decline_ratio' => 1,
            ]);

            return Command::FAILURE;
        });

    $this->artisan('backtest:bulk-calculate-market-heartbeat')
        ->expectsOutputToContain('Heartbeat refresh failed on 2024-11-01')
        ->assertFailed();
    expect(MarketHeartbeat::count())->toBe(0);
});

it('clears saved heartbeats when no source price dates are available', function () {
    MarketHeartbeat::insert([
        'index' => 'nifty-total-market', 'date' => '2009-01-01',
        'advances' => 1, 'declines' => 0, 'advance_decline_ratio' => 1,
    ]);

    $this->artisan('backtest:bulk-calculate-market-heartbeat')->assertSuccessful();

    expect(MarketHeartbeat::count())->toBe(0);
});
