<?php

use App\Enums\BacktestStatusEnum;
use App\Models\Backtest;
use App\Models\User;
use Illuminate\Support\Carbon;

it('shows daily market cap allocation and supports date selection on desktop and mobile', function (int $width, int $height) {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id, 'status' => BacktestStatusEnum::Completed]);
    $backtest->summaryMetrics()->create([
        'cagr' => 0.1, 'max_drawdown' => -0.1, 'total_trades' => 1, 'total_charges_paid' => 0,
        'final_value' => 1000, 'rolling_returns_one_year' => [], 'rolling_returns_three_year' => [],
        'rolling_returns_five_year' => [], 'stock_performance' => [],
    ]);
    for ($index = 0; $index < 280; $index++) {
        $backtest->dailySnapshots()->create([
            'date' => Carbon::parse('2020-01-01')->addDays($index)->toDateString(),
            'nav' => 100, 'portfolio_value' => 900, 'cash' => 100, 'total_value' => 1000, 'holdings_count' => 4,
            'market_cap_allocation' => [
                'large_cap' => 20 + $index % 10, 'mid_cap' => 30 - $index % 10,
                'small_cap' => 30, 'etf' => 10, 'cash' => 10,
            ],
            'market_cap_allocation_calculated_at' => now(),
        ]);
    }

    loginAs($user->email);

    $page = visit('/backtests/'.$backtest->id)
        ->resize($width, $height)
        ->press('Market caps')
        ->assertSee('Market Cap Allocation Over Time')
        ->assertSeeIn('#bt-market-cap', '29.0%')
        ->assertSeeIn('#bt-market-cap', '21.0%')
        ->assertSeeIn('#bt-market-cap dt', 'ETFs')
        ->assertSeeIn('#bt-market-cap dt', 'Cash')
        ->assertSeeIn('#bt-market-cap', 'Data from 01 Jan 2020')
        ->assertVisible('#bt-market-cap canvas >> nth=0')
        ->keys('#allocation-date', 'Home')
        ->assertValue('#allocation-date', '0')
        ->assertSeeIn('#bt-market-cap', 'Selected: 01 Jan 2020')
        ->assertSeeIn('#bt-market-cap', '20.0%')
        ->keys('#allocation-date', 'ArrowRight')
        ->assertValue('#allocation-date', '1')
        ->assertSeeIn('#bt-market-cap', 'Selected: 02 Jan 2020')
        ->press('#bt-market-cap button:has-text("1Y")')
        ->assertAriaAttribute('#bt-market-cap button:has-text("1Y")', 'pressed', 'true')
        ->press('#bt-market-cap button:has-text("All")')
        ->assertAriaAttribute('#bt-market-cap button:has-text("All")', 'pressed', 'true')
        ->assertNoJavaScriptErrors();

    expect($page->script('document.querySelector("#bt-market-cap").scrollWidth <= document.querySelector("#bt-market-cap").clientWidth'))->toBeTrue();

})->with([[1440, 1000], [390, 844]]);

it('explains when the backtest has no market cap coverage', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id, 'status' => BacktestStatusEnum::Completed]);
    $backtest->summaryMetrics()->create([
        'cagr' => 0, 'max_drawdown' => 0, 'total_trades' => 0, 'total_charges_paid' => 0,
        'final_value' => 1000, 'rolling_returns_one_year' => [], 'rolling_returns_three_year' => [],
        'rolling_returns_five_year' => [], 'stock_performance' => [],
    ]);

    loginAs($user->email);

    visit('/backtests/'.$backtest->id)
        ->press('Market caps')
        ->assertSee('No allocation data is available for this backtest period.')
        ->assertMissing('#allocation-date')
        ->assertNoJavaScriptErrors();
});
