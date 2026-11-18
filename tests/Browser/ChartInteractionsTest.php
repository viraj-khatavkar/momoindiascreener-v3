<?php

use App\Enums\BacktestStatusEnum;
use App\Models\Backtest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Pest\Browser\Api\AwaitableWebpage;

function assertChartWheelRequiresModifier(AwaitableWebpage $page, string $selector): void
{
    $page->assertVisible($selector.' canvas >> nth=0');
    $selectorJson = json_encode($selector, JSON_THROW_ON_ERROR);
    $results = $page->script(<<<JS
        (async () => {
            const root = document.querySelector($selectorJson);
            const canvas = [...root.querySelectorAll('canvas')].find(el => el.clientWidth > 100 && el.clientHeight > 100);
            const frame = () => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
            const steps = [
                { name: 'page scroll', deltaY: 120 },
                { name: 'trackpad horizontal scroll', deltaX: 120 },
                { name: 'Ctrl zoom', deltaY: -120, ctrlKey: true },
                { name: 'page scroll after Ctrl release', deltaY: 120 },
                { name: 'Command zoom', deltaY: -120, metaKey: true },
                { name: 'page scroll after Command release', deltaY: 120 },
            ];
            const results = [];
            for (const step of steps) {
                await frame();
                const before = canvas.toDataURL();
                const bounds = canvas.getBoundingClientRect();
                const event = new WheelEvent('wheel', {
                    bubbles: true, cancelable: true,
                    clientX: bounds.left + bounds.width / 2,
                    clientY: bounds.top + bounds.height / 2,
                    ...step,
                });
                canvas.dispatchEvent(event);
                await frame();
                results.push({
                    name: step.name,
                    intentional: Boolean(step.ctrlKey || step.metaKey),
                    prevented: event.defaultPrevented,
                    changed: before !== canvas.toDataURL(),
                });
            }
            return results;
        })()
    JS);

    foreach ($results as $result) {
        expect($result['prevented'], $selector.': '.$result['name'])->toBe($result['intentional'])
            ->and($result['changed'], $selector.': '.$result['name'])->toBe($result['intentional']);
    }
    $page->assertNoJavaScriptErrors();
}

it('requires deliberate wheel input for instrument prices and each metric after tab changes', function () {
    for ($day = 0; $day < 260; $day++) {
        createScreenResultRow('WHEEL', 'Wheel Test', CarbonImmutable::parse('2025-01-01')->addWeekdays($day)->toDateString(), [
            'close_adjusted' => 100 + $day + sin($day) * 5,
            'price_to_earnings' => 10 + $day / 10,
            'marketcap' => 1000 + $day * 10,
            'absolute_return_one_year' => 10 + $day / 10,
            'rsi_one_year' => 50 + sin($day) * 20,
        ]);
    }
    $page = visit('/instruments/WHEEL')->resize(1440, 1000);
    $selector = '#price [role="tabpanel"]:not([hidden]) .tv-lightweight-charts';
    assertChartWheelRequiresModifier($page, $selector);

    foreach (['1Y return', 'P/E', 'Market cap', 'RSI', 'Price', 'P/E'] as $label) {
        $page->press('#price [role="tab"]:has-text("'.$label.'")');
        assertChartWheelRequiresModifier($page, $selector);
    }
});

it('keeps the page scroll available on market health and index charts', function () {
    $indices = [];
    $heartbeats = [];
    for ($day = 0; $day < 180; $day++) {
        $date = CarbonImmutable::parse('2025-01-01')->addWeekdays($day)->toDateString();
        $indices[] = [
            'symbol' => 'NIFTY 500', 'slug' => 'nifty-500', 'date' => $date,
            'close' => 10000 + $day * 10, 'price_to_earnings' => 15 + $day / 10,
        ];
        $heartbeats[] = [
            'index' => 'nifty-500', 'date' => $date, 'percentage_above_ma_200' => 50 + sin($day) * 20,
            'advances' => 300, 'declines' => 200, 'advance_decline_ratio' => 1.5,
            'created_at' => now(), 'updated_at' => now(),
        ];
    }
    DB::table('nse_indices')->insert($indices);
    DB::table('market_heartbeats')->insert($heartbeats);

    assertChartWheelRequiresModifier(visit('/market-health')->resize(1440, 1000), '.tv-lightweight-charts');
    assertChartWheelRequiresModifier(visit('/nse-index/nifty-500')->resize(1440, 1000), '.tv-lightweight-charts');
});

it('requires deliberate wheel input for every backtest chart and retains reset controls', function () {
    $user = User::factory()->create(['is_paid' => true]);
    $backtest = Backtest::factory()->for($user)->create(['status' => BacktestStatusEnum::Completed]);
    $rollingReturns = [];
    for ($day = 0; $day < 180; $day++) {
        $date = CarbonImmutable::parse('2025-01-01')->addWeekdays($day)->toDateString();
        $nav = 100 + $day / 10 + sin($day / 10) * 10;
        $backtest->dailySnapshots()->create([
            'date' => $date, 'nav' => $nav, 'portfolio_value' => $nav * 9, 'cash' => $nav,
            'total_value' => $nav * 10, 'holdings_count' => 2,
            'market_cap_allocation' => [
                'large_cap' => 20 + $day % 10, 'mid_cap' => 30 - $day % 10,
                'small_cap' => 30, 'etf' => 10, 'cash' => 10,
            ],
            'market_cap_allocation_calculated_at' => now(),
        ]);
        $rollingReturns[] = ['date' => $date, 'return' => 0.1 + sin($day / 10) / 10];
    }
    $backtest->summaryMetrics()->create([
        'cagr' => 0.1, 'max_drawdown' => -0.1, 'total_trades' => 1, 'total_charges_paid' => 0,
        'final_value' => $nav * 10, 'rolling_returns_one_year' => $rollingReturns,
        'rolling_returns_three_year' => [], 'rolling_returns_five_year' => [], 'stock_performance' => null,
    ]);
    $this->actingAs($user);
    $page = visit('/backtests/'.$backtest->id)->resize(1440, 1000);
    foreach (['#bt-nav', '#bt-drawdown', '#bt-rolling'] as $section) {
        assertChartWheelRequiresModifier($page, $section.' .tv-lightweight-charts');
    }
    $page->press('summary:has-text("Allocation over time")');
    foreach (['#bt-cash', '#bt-market-cap'] as $section) {
        assertChartWheelRequiresModifier($page, $section.' .tv-lightweight-charts');
    }
    $page->script('document.querySelector("#bt-nav canvas").dispatchEvent(new MouseEvent("dblclick", { bubbles: true }))');
    $page->assertSeeIn('[data-test="nav-visible-period"]', '01 Jan 2025')->assertNoJavaScriptErrors();
});
