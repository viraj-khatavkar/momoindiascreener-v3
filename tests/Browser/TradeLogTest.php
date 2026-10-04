<?php

use App\Enums\BacktestStatusEnum;
use App\Models\Backtest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

it('loads the next trade page on scroll and searches trades outside loaded pages', function (int $width, int $height) {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create(['user_id' => $user->id, 'status' => BacktestStatusEnum::Completed]);
    $backtest->summaryMetrics()->create([
        'cagr' => 0, 'max_drawdown' => 0, 'total_trades' => 225, 'total_charges_paid' => 225,
        'final_value' => 1000, 'rolling_returns_one_year' => [], 'rolling_returns_three_year' => [],
        'rolling_returns_five_year' => [], 'stock_performance' => null,
    ]);
    $trades = [];
    for ($index = 0; $index < 225; $index++) {
        $trades[] = [
            'backtest_id' => $backtest->id, 'symbol' => $index === 0 ? 'EARLYONLY' : 'STOCK'.$index,
            'name' => 'Company '.$index, 'trade_type' => $index % 2 === 0 ? 'buy' : 'sell',
            'reason' => $index % 2 === 0 ? 'New entry' : 'Rank exceeded exit threshold',
            'date' => Carbon::parse('2019-12-01')->addDays($index)->toDateString(),
            'quantity' => 1, 'price' => 100, 'raw_price' => 100, 'gross_amount' => 100,
            'brokerage' => 0, 'stt' => 0, 'transaction_charges' => 0, 'sebi_charges' => 0,
            'gst' => 0, 'stamp_charges' => 0, 'total_charges' => 1, 'net_amount' => 101,
        ];
    }
    DB::table('backtest_trades')->insert($trades);
    loginAs($user->email);

    $page = visit('/backtests/'.$backtest->id)->resize($width, $height)
        ->press('Trades')
        ->assertSee('Loaded 100 of 225 matching trades.')
        ->assertSee('Buys (113)')
        ->assertSee('Sells (112)');

    $page->script('Array.from(document.querySelectorAll("#bt-trades button")).find(button => button.textContent.trim() === "Load more trades").scrollIntoView()');
    $page->assertSee('Loaded 200 of 225 matching trades.')
        ->fill('input[aria-label="Search trades by symbol"]', 'EARLYONLY')
        ->assertSee('Loaded 1 of 1 matching trades.')
        ->assertSeeIn('#bt-trades', 'EARLYONLY')
        ->assertSee('All matching trades are loaded.')
        ->fill('input[aria-label="Search trades by symbol"]', '')
        ->assertSee('Loaded 100 of 225 matching trades.')
        ->press('#bt-trades button:has-text("Sells (112)")')
        ->assertSee('Loaded 100 of 112 matching trades.')
        ->press('#bt-trades button[aria-pressed]:has-text("2019")')
        ->assertSee('Loaded 15 of 15 matching trades.')
        ->press('#bt-trades button:has-text("All (31)")')
        ->assertSee('Loaded 31 of 31 matching trades.')
        ->press('#bt-trades button:has-text("Newest first")')
        ->assertSee('Oldest first')
        ->assertSee('Loaded 31 of 31 matching trades.')
        ->assertNoJavaScriptErrors();

    expect($page->script('document.querySelector("#bt-trades [id^=tl-]").id'))->toBe('tl-2019-12-01');
})->with([[1440, 1000], [390, 844]]);
