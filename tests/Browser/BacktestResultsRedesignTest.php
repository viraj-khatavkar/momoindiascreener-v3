<?php

use App\Actions\Backtest\CalculateBacktestMetricsAction;
use App\Enums\BacktestStatusEnum;
use App\Models\Backtest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->member = User::factory()->create(['is_paid' => true]);
    $this->backtest = Backtest::factory()->for($this->member)->create([
        'name' => 'Momentum portfolio', 'status' => BacktestStatusEnum::Completed,
        'start_date' => '2011-03-01',
    ]);
    $snapshots = [];
    $indices = [];
    $i = 0;
    for ($date = CarbonImmutable::parse('2011-03-01'); $date->lte('2026-11-02'); $date = $date->addDay()) {
        if ($date->isWeekend()) {
            continue;
        }
        $nav = 100 * exp($i * 0.0009);
        if ($i >= 1800 && $i < 2100) {
            $nav *= 0.7 + ($i - 1800) * 0.001;
        }
        $snapshots[] = [
            'backtest_id' => $this->backtest->id, 'date' => $date->toDateString(), 'nav' => $nav,
            'total_value' => $nav * 50000, 'portfolio_value' => $nav * 49000, 'cash' => $nav * 1000, 'holdings_count' => 10,
        ];
        foreach (['nifty-50' => 0.0004, 'nifty-500' => 0.0005, 'nifty-100' => 0.00045] as $slug => $rate) {
            if ($slug === 'nifty-100' && $date->lt('2016-01-01')) {
                continue;
            }
            $indices[] = ['symbol' => $slug, 'slug' => $slug, 'date' => $date->toDateString(), 'close' => 10000 * exp($i * $rate)];
        }
        $i++;
    }
    foreach (array_chunk($snapshots, 500) as $chunk) {
        DB::table('backtest_daily_snapshots')->insert($chunk);
    }
    foreach (array_chunk($indices, 500) as $chunk) {
        DB::table('nse_indices')->insert($chunk);
    }

    foreach ([
        ['ITC', '2013-01-01', 'buy', 10, 1000],
        ['ITC', '2014-01-01', 'sell', 4, 1200],
        ['ITC', '2014-06-02', 'buy', 2, 1500],
        ['ITC', '2015-01-01', 'sell', 8, 1500],
        ['ITC', '2017-01-02', 'buy', 10, 2000],
        ['ITC', '2021-01-01', 'sell', 10, 1800],
        ['OPEN', '2025-01-01', 'buy', 10, 1000],
    ] as [$symbol, $date, $type, $quantity, $price]) {
        $this->backtest->trades()->create([
            'symbol' => $symbol, 'name' => $symbol, 'date' => $date, 'trade_type' => $type,
            'reason' => $type === 'buy' ? 'New entry' : 'Rank exceeded exit threshold',
            'quantity' => $quantity, 'price' => $price, 'raw_price' => $price,
            'gross_amount' => $quantity * $price, 'net_amount' => $quantity * $price,
            'brokerage' => 0, 'stt' => 0, 'transaction_charges' => 0, 'sebi_charges' => 0,
            'gst' => 0, 'stamp_charges' => 0, 'total_charges' => 0,
        ]);
    }
    app(CalculateBacktestMetricsAction::class)->execute($this->backtest);
});

it('shows the complete history and consistent comparisons on desktop and mobile', function (int $width, int $height) {
    loginAs($this->member->email);
    $page = visit('/backtests/'.$this->backtest->id)->resize($width, $height)
        ->assertSee('Performance overview')
        ->assertSeeIn('[data-test="save-and-run"]', 'Re-run backtest')
        ->assertSeeIn('[data-test="nav-visible-period"]', '01 Mar 2011')
        ->assertSeeIn('[data-test="nav-visible-period"]', '02 Nov 2026')
        ->assertSeeIn('[data-test="benchmark-comparison"]', 'Nifty 50')
        ->assertSeeIn('[data-test="benchmark-comparison"]', 'pp')
        ->assertSeeIn('[data-test="performance-metrics"] > div:nth-child(3)', '%')
        ->assertAttributeContains('[data-test="performance-metrics"] > div:nth-child(1)', 'class', 'bg-emerald-50')
        ->assertAttributeContains('[data-test="performance-metrics"] > div:nth-child(2)', 'class', 'bg-red-50')
        ->assertAttributeContains('[data-test="performance-metrics"] > div:nth-child(3)', 'class', 'bg-amber-50')
        ->assertAttributeContains('[data-test="performance-metrics"] > div:nth-child(4)', 'class', 'bg-violet-50')
        ->assertAttributeMissing('#bt-overview [data-test="risk-statistics"]', 'open')
        ->assertDontSeeIn('#bt-nav', 'CAGR difference')
        ->assertNoJavaScriptErrors();
    expect($page->script('Array.from(document.querySelectorAll("[data-test=performance-metrics] dt"), el => el.innerText)'))
        ->toBe(['CAGR', 'Maximum drawdown', 'Annualised volatility', 'Sharpe ratio']);
    expect($page->script('new Set(Array.from(document.querySelectorAll("[data-test=performance-metrics] dd:first-of-type"), el => getComputedStyle(el).color)).size'))
        ->toBe(4);
    expect($page->script('getComputedStyle(document.querySelector("[data-test=performance-metrics]")).gridTemplateColumns.split(" ").length'))
        ->toBe($width === 1440 ? 4 : 2);
    expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
    $page->screenshot(false, 'backtest-overview-'.$width);

    $originalDelta = $page->script('document.querySelector("[data-test=cagr-difference]").textContent.trim()');
    $page->select('#result-benchmark', 'nifty-500')
        ->assertQueryStringHas('benchmark', 'nifty-500')
        ->refresh()
        ->assertSelected('#result-benchmark', 'nifty-500')
        ->assertSeeIn('[data-test="benchmark-comparison"]', 'Nifty 500')
        ->assertSeeIn('[data-test="performance-metrics"] > div:nth-child(2)', 'Nifty 500');
    expect($page->script('document.querySelector("[data-test=cagr-difference]").textContent.trim()'))->not->toBe($originalDelta);

    $page->press('Monthly returns')->assertSee('Partial period')->assertSee('YTD');
    $monthSelector = $width < 1024
        ? '[data-test="mobile-monthly-returns"] article:first-child button[aria-label^="Nov 2026"]'
        : '#bt-monthly tbody tr:first-child button[aria-label^="Nov 2026"]';
    $page->press($monthSelector)->assertSeeIn('#bt-monthly [role="status"]:visible', 'Nifty 500');
    $page->screenshot(false, 'backtest-monthly-'.$width);
    if ($width < 1024) {
        expect($page->script('document.querySelectorAll("[data-test=mobile-monthly-returns] article").length'))->toBe(16);
    }
    expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();

    $page->press('#bt-drawdown tbody tr:first-child button')->assertDontSeeIn('[data-test="nav-visible-period"]', '01 Mar 2011');
    $window = $page->script('document.querySelector("[data-test=nav-visible-period]").innerText');
    [$from, $to] = explode(' to ', str_replace('Showing ', '', $window));
    $firstNav = $this->backtest->dailySnapshots()->whereDate('date', CarbonImmutable::parse($from))->value('nav');
    $lastNav = $this->backtest->dailySnapshots()->whereDate('date', CarbonImmutable::parse($to))->value('nav');
    $lastVisibleNav = rtrim(rtrim(number_format($lastNav, 2), '0'), '.');
    $windowReturn = sprintf('%+.2f%%', ($lastNav / $firstNav - 1) * 100);
    $page->press('#bt-nav button:has-text("Lin")')
        ->assertSeeIn('[data-test="nav-legend"]', $to)
        ->assertSeeIn('[data-test="nav-legend"]', $lastVisibleNav)
        ->press('#bt-nav button:text-is("%")')
        ->assertSeeIn('[data-test="nav-legend"]', $windowReturn)
        ->press('#bt-nav button:has-text("Log")');
    $page->press('Max')->assertSeeIn('[data-test="nav-visible-period"]', '01 Mar 2011');
    $page->select('#result-benchmark', 'nifty-100')
        ->assertSeeIn('#bt-overview', 'A CAGR difference is unavailable because the periods differ.')
        ->assertSeeIn('[data-test="benchmark-comparison"]', 'Nifty 100')
        ->assertNotPresent('[data-test="cagr-difference"]');
    $partialHistory = DB::table('nse_indices')->where('slug', 'nifty-100');
    $firstClose = (clone $partialHistory)->orderBy('date')->value('close');
    $lastClose = $partialHistory->orderByDesc('date')->value('close');
    $page->press('#bt-nav button:text-is("%")')
        ->assertSeeIn('[data-test="nav-legend"]', sprintf('%+.2f%%', ($lastClose / $firstClose - 1) * 100))
        ->press('#bt-nav button:has-text("Log")');
    $page->select('#result-benchmark', '')
        ->assertQueryStringHas('benchmark', 'none')
        ->refresh()
        ->assertSelected('#result-benchmark', '')
        ->assertNotPresent('[data-test="benchmark-comparison"]')
        ->assertDontSeeIn('[data-test="performance-metrics"]', 'Nifty 100')
        ->resize($width === 1440 ? 390 : 1440, $height)
        ->assertSeeIn('[data-test="nav-visible-period"]', '01 Mar 2011')
        ->assertSeeIn('[data-test="nav-visible-period"]', '02 Nov 2026')
        ->press('[role="tab"]:has-text("Settings")')
        ->assertSeeIn('[data-test="save-and-run"]', 'Save and Run')
        ->assertQueryStringHas('benchmark', 'none')
        ->press('[role="tab"]:has-text("Results")')
        ->assertSeeIn('[data-test="save-and-run"]', 'Re-run backtest')
        ->assertNoJavaScriptErrors();
})->with([[1440, 1000], [390, 844]]);

it('opens the selected holding period and can restore the full trade log', function () {
    loginAs($this->member->email);
    $page = visit('/backtests/'.$this->backtest->id)->resize(390, 844)
        ->press('button[aria-label="View trades for ITC from 01 Jan 2013"]')
        ->assertSee('Loaded 4 of 4 matching trades.')
        ->assertSeeIn('#bt-trades', '4 matching trades of 7')
        ->assertSee('Transactions within this holding period')
        ->press('Expand loaded')
        ->assertSeeIn('#bt-trades strong', 'ITC')
        ->assertDontSeeIn('#bt-trades', 'OPEN');
    expect($page->script('document.querySelectorAll("#bt-trades tbody tr").length'))->toBe(4);
    expect($page->script('Array.from(document.querySelectorAll("#bt-trades button"), b => b.textContent.trim()).filter(t => /^\d{4}$/.test(t))'))->toBe(['2015', '2014', '2013']);
    $page->screenshot(false, 'backtest-position-trades-mobile');
    expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();

    $page->press('button[aria-label="View trades for ITC from 02 Jan 2017"]')
        ->assertSee('Loaded 2 of 2 matching trades.')
        ->press('Clear position filter')
        ->assertSee('Loaded 7 of 7 matching trades.')
        ->press('button[aria-label="View trades for OPEN"]')
        ->assertSee('Loaded 1 of 1 matching trades.')
        ->assertNoJavaScriptErrors();

    expect($this->backtest->refresh()->trades()->count())->toBe(7);
});
