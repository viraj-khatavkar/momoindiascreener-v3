<?php

use App\Actions\Backtest\CalculateBacktestMetricsAction;
use App\Enums\BacktestStatusEnum;
use App\Models\Backtest;
use App\Models\User;

function createPositionResultsBacktest(bool $withCompletedPositions = true): Backtest
{
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->create([
        'user_id' => $user->id, 'status' => BacktestStatusEnum::Completed, 'initial_capital' => 100000,
    ]);
    foreach (['2013-01-01' => 100, '2021-01-01' => 101] as $date => $nav) {
        $backtest->dailySnapshots()->create([
            'date' => $date, 'nav' => $nav, 'portfolio_value' => 1200, 'cash' => $nav * 1000 - 1200,
            'total_value' => $nav * 1000, 'holdings_count' => 1,
        ]);
    }

    $trades = [
        ['OPEN', '2020-01-01', 'buy', 10, 100],
        ['OPEN', '2020-06-01', 'sell', 2, 110],
    ];
    if ($withCompletedPositions) {
        $trades = [...$trades,
            ['ITC', '2013-01-01', 'buy', 10, 100],
            ['ITC', '2014-01-01', 'sell', 4, 120],
            ['ITC', '2015-01-01', 'sell', 6, 140],
            ['ITC', '2017-01-01', 'buy', 10, 200],
            ['ITC', '2021-01-01', 'sell', 10, 190],
            ['TINY', '2019-01-01', 'buy', 1, 1],
            ['TINY', '2019-02-01', 'sell', 1, 2],
        ];
    }
    foreach ($trades as [$symbol, $date, $type, $quantity, $price]) {
        $backtest->trades()->create([
            'symbol' => $symbol, 'name' => $symbol, 'date' => $date, 'trade_type' => $type, 'quantity' => $quantity,
            'reason' => 'Weight rebalance adjustment', 'price' => $price, 'raw_price' => $price,
            'gross_amount' => $quantity * $price, 'brokerage' => 0, 'stt' => 0, 'transaction_charges' => 0,
            'sebi_charges' => 0, 'gst' => 0, 'stamp_charges' => 0, 'total_charges' => 0, 'net_amount' => $quantity * $price,
        ]);
    }
    createBacktestPriceRow('OPEN', '2021-01-01', ['close_adjusted' => 150]);
    app(CalculateBacktestMetricsAction::class)->execute($backtest);

    return $backtest;
}

it('shows completed position statistics and separate open results on desktop and mobile', function (int $width, int $height) {
    $backtest = createPositionResultsBacktest();
    loginAs($backtest->user->email);

    $page = visit('/backtests/'.$backtest->id)->resize($width, $height)
        ->assertSeeIn('[data-test="position-statistics"]', '66.7%')
        ->assertSeeIn('[data-test="position-statistics"]', '2 winning / 3 completed · 0 breakeven')
        ->assertSeeIn('[data-test="position-statistics"]', '₹161')
        ->assertSeeIn('[data-test="position-statistics"]', '−₹100')
        ->press('Positions')
        ->assertSeeIn('[data-test="final-holdings"]', 'OPEN')
        ->assertSeeIn('[data-test="final-holdings"] th:nth-child(7)', 'Realised P&L')
        ->assertSeeIn('[data-test="final-holdings"] th:nth-child(8)', 'Unrealised P&L')
        ->assertSeeIn('[data-test="final-holdings"]', '₹20')
        ->assertSeeIn('[data-test="final-holdings"]', '₹400')
        ->assertSeeIn('[data-test="top-winners"]', 'ITC')
        ->assertSeeIn('[data-test="top-winners"]', "Jan '13")
        ->assertSeeIn('[data-test="top-winners"]', "Jan '15")
        ->assertSeeIn('[data-test="top-losers"]', 'ITC')
        ->assertSeeIn('[data-test="top-losers"]', "Jan '17")
        ->assertSeeIn('[data-test="top-losers"]', "Jan '21")
        ->assertDontSeeIn('[data-test="top-winners"]', 'OPEN')
        ->assertAriaAttribute('button:has-text("₹ P&L")', 'pressed', 'true');

    expect($page->script('document.querySelector("[data-test=top-winners] li a").textContent.trim()'))->toBe('ITC');

    $page->press('% purchase cost')
        ->assertAriaAttribute('button:has-text("% purchase cost")', 'pressed', 'true')
        ->assertNoJavaScriptErrors();

    expect($page->script('document.querySelector("[data-test=top-winners] li a").textContent.trim()'))->toBe('TINY')
        ->and($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
})->with([[1440, 1000], [390, 844]]);

it('shows no completed statistics while all positions remain open', function () {
    $backtest = createPositionResultsBacktest(false);
    loginAs($backtest->user->email);

    visit('/backtests/'.$backtest->id)
        ->assertSeeIn('[data-test="position-statistics"]', '0 winning / 0 completed · 0 breakeven')
        ->assertDontSeeIn('[data-test="position-statistics"]', '100.0%')
        ->press('Positions')
        ->assertSee('No positions were fully exited during this backtest.')
        ->assertSee('No completed winning positions.')
        ->assertSee('No completed losing positions.')
        ->assertSeeIn('[data-test="final-holdings"]', 'OPEN')
        ->assertNoJavaScriptErrors();
});
