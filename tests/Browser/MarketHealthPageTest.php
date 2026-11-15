<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

beforeEach(function () {
    $this->heartbeat = [
        'index' => 'nifty-500', 'date' => '2025-01-02',
        'percentage_above_ma_200' => 65,
        'percentage_above_ma_100' => 43.2,
        'percentage_above_ma_50' => 34.2,
        'percentage_above_ma_20' => 29,
        'percentage_of_stocks_with_returns_one_year_above_zero' => 45.2,
        'percentage_of_stocks_with_returns_one_year_above_ten' => 32.6,
        'percentage_of_stocks_with_returns_one_year_above_hundred' => 2,
        'percentage_of_stocks_within_ten_percent_of_ath' => 17.4,
        'percentage_of_stocks_within_twenty_percent_of_ath' => 37.2,
        'percentage_of_stocks_within_thirty_percent_of_ath' => 55.8,
        'advances' => 151, 'declines' => 347, 'advance_decline_ratio' => 0.44,
        'created_at' => now(), 'updated_at' => now(),
    ];
    $rows = [$this->heartbeat];
    for ($session = 0; $session <= 20; $session++) {
        $rows[] = array_merge($this->heartbeat, [
            'date' => CarbonImmutable::parse('2026-07-01')->addWeekdays($session)->toDateString(),
            'percentage_above_ma_200' => 72 - $session,
        ]);
    }
    $rows[] = array_merge($this->heartbeat, [
        'index' => 'nifty-50', 'date' => '2026-07-29', 'advances' => 35, 'declines' => 15,
        'advance_decline_ratio' => 2.33, 'percentage_above_ma_200' => 80,
        'percentage_of_stocks_with_returns_one_year_above_zero' => 61.5,
    ]);
    DB::table('market_heartbeats')->insert($rows);
});

it('shows a dated market brief with usable charts at different screen widths', function (int $width, int $height) {
    $page = visit('/market-health')->resize($width, $height)
        ->assertSee('More stocks fell than rose.')
        ->assertSee('As of 29 Jul 2026')
        ->assertSeeIn('[data-testid="market-advances"]', '151')
        ->assertSeeIn('[data-testid="market-declines"]', '347')
        ->assertSeeIn('[data-testid="market-ratio"]', '0.44×')
        ->assertSeeIn('[data-testid="market-metric-value"]', '52.0%')
        ->assertSeeIn('[data-testid="market-metric-change"]', '−20.0 pp vs 20 sessions ago')
        ->assertVisible('canvas >> nth=0')
        ->assertSee('Across the time frames')
        ->assertSee('Annual returns')
        ->assertSee('Near all-time highs')
        ->keys('[aria-label="Above 200-day average history chart. Use left and right arrow keys to inspect values."]', 'End')
        ->assertSeeIn('[data-testid="chart-inspection"]', '29 Jul 2026 · 52.0%')
        ->keys('[aria-label="Above 200-day average history chart. Use left and right arrow keys to inspect values."]', 'Home')
        ->assertSeeIn('[data-testid="chart-inspection"]', '01 Jul 2026 · 72.0%')
        ->keys('[aria-label="Above 200-day average history chart. Use left and right arrow keys to inspect values."]', 'Escape')
        ->assertSeeIn('[data-testid="chart-inspection"]', 'Move over the chart')
        ->assertNoJavaScriptErrors();

    expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
    expect($page->script('document.querySelectorAll("#market-health-index option").length'))->toBe(14);
})->with(['desktop' => [1440, 1000], 'phone' => [390, 844], 'small phone' => [320, 740]]);

it('opens every metric with the correct unit and preserves the chart period', function () {
    $page = visit('/market-health')
        ->press('button[aria-label="Show all available history"]')
        ->assertSeeIn('[data-testid="market-chart-range"]', '02 Jan 2025');

    foreach ([
        'percentage_above_ma_200' => '52.0%',
        'percentage_above_ma_100' => '43.2%',
        'percentage_above_ma_50' => '34.2%',
        'percentage_above_ma_20' => '29.0%',
        'percentage_of_stocks_with_returns_one_year_above_zero' => '45.2%',
        'percentage_of_stocks_with_returns_one_year_above_ten' => '32.6%',
        'percentage_of_stocks_with_returns_one_year_above_hundred' => '2.0%',
        'percentage_of_stocks_within_ten_percent_of_ath' => '17.4%',
        'percentage_of_stocks_within_twenty_percent_of_ath' => '37.2%',
        'percentage_of_stocks_within_thirty_percent_of_ath' => '55.8%',
        'advance_decline_ratio' => '0.44×', 'advances' => '151', 'declines' => '347',
    ] as $metric => $value) {
        $page->select('#market-health-metric', $metric)
            ->assertSeeIn('[data-testid="market-metric-value"]', $value)
            ->assertAriaAttribute('button[aria-label="Show all available history"]', 'pressed', 'true')
            ->assertVisible('canvas >> nth=0');
    }

    $page->press('[data-metric="percentage_above_ma_20"]')
        ->assertSelected('#market-health-metric', 'percentage_above_ma_20')
        ->press('[data-metric="percentage_of_stocks_with_returns_one_year_above_zero"]')
        ->assertSelected('#market-health-metric', 'percentage_of_stocks_with_returns_one_year_above_zero')
        ->press('button[aria-label="Show 1 month"]')
        ->assertSeeIn('[data-testid="market-chart-range"]', '01 Jul 2026')
        ->select('#market-health-index', 'nifty-50')
        ->assertPathIs('/market-health/nifty-50')
        ->assertSee('More stocks rose than fell.')
        ->assertSeeIn('[data-testid="market-metric-value"]', '61.5%')
        ->assertSelected('#market-health-metric', 'percentage_of_stocks_with_returns_one_year_above_zero')
        ->assertAriaAttribute('button[aria-label="Show 1 month"]', 'pressed', 'true')
        ->assertVisible('canvas >> nth=0')
        ->press('button[aria-label="Show advance / decline ratio history"]')
        ->assertSeeIn('[data-testid="market-metric-value"]', '2.33×')
        ->assertNoJavaScriptErrors();
});

it('can return from an index with no history without losing the chart selection', function () {
    visit('/market-health')
        ->select('#market-health-metric', 'advance_decline_ratio')
        ->press('button[aria-label="Show 3 months"]')
        ->select('#market-health-index', 'nifty-allcap')
        ->assertPathIs('/market-health/nifty-allcap')
        ->assertSee('No market health history is available for Nifty Allcap.')
        ->assertSee('No values are available for this metric and period.')
        ->assertSeeIn('[data-testid="market-metric-value"]', '—')
        ->assertSeeIn('[data-testid="market-advances"]', '—')
        ->assertNotPresent('canvas')
        ->select('#market-health-index', 'nifty-fno')
        ->assertPathIs('/market-health/nifty-fno')
        ->assertSee('No market health history is available for NSE F&O Stocks.')
        ->select('#market-health-index', 'nifty-500')
        ->assertSeeIn('[data-testid="market-metric-value"]', '0.44×')
        ->assertAriaAttribute('button[aria-label="Show 3 months"]', 'pressed', 'true')
        ->assertVisible('canvas >> nth=0')
        ->assertNoJavaScriptErrors();
});

it('keeps missing values separate from valid zero values', function () {
    $heartbeat = array_merge($this->heartbeat, [
        'percentage_above_ma_200' => null, 'percentage_above_ma_20' => 0,
        'advances' => 0, 'advance_decline_ratio' => 0,
    ]);
    Route::middleware('web')->get('/test-market-health-missing-values', fn (): Response => Inertia::render('MarketHealth', [
        'heartbeats' => [$heartbeat], 'index' => 'nifty-500', 'indexName' => 'Nifty 500',
        'availableIndices' => ['nifty-500'],
    ]));

    visit('/test-market-health-missing-values')
        ->assertSeeIn('[data-testid="market-metric-value"]', '—')
        ->assertSeeIn('[data-testid="market-advances"]', '0')
        ->assertSeeIn('[data-testid="market-ratio"]', '0.00×')
        ->assertSee('The 200-day measure is not available for this session.')
        ->assertSee('No values are available for this metric and period.')
        ->press('[data-metric="percentage_above_ma_20"]')
        ->assertSeeIn('[data-testid="market-metric-value"]', '0.0%')
        ->assertVisible('canvas >> nth=0')
        ->keys('[aria-label="Above 20-day average history chart. Use left and right arrow keys to inspect values."]', 'End')
        ->assertSeeIn('[data-testid="chart-inspection"]', '02 Jan 2025 · 0.0%')
        ->assertNoJavaScriptErrors();
});
