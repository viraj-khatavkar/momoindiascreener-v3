<?php

use App\Models\NseIndex;
use Carbon\CarbonImmutable;

it('matches beta to benchmark returns over the same trade intervals', function (bool $missingBenchmark) {
    $date = CarbonImmutable::parse('2024-12-31');
    $previousStockClose = 10000.0;
    $indexRows = [];
    $prices = [];
    for ($day = 0; $date->year < 2026; $day++, $date = $date->addWeekday()) {
        $close = 10000 + $day * 3 + ($day % 7) * 20;
        if (! $missingBenchmark || $day !== 100) {
            $indexRows[] = [
                'symbol' => 'Nifty 50', 'slug' => 'nifty-50', 'date' => $date->toDateString(),
                'open' => $close, 'high' => $close, 'low' => $close, 'close' => $close,
                'points_change' => 0, 'percentage_change' => 0, 'volume' => 0, 'turnover' => 0,
            ];
        }
        if ($day === 75) {
            continue;
        }
        $prices[] = createBacktestPriceRow('TRUST', $date->toDateString(), [
            'series' => 'IV', 'variance_one_year' => 1,
            't_percent_raw' => 2 * ($close / $previousStockClose - 1),
        ]);
        $previousStockClose = $close;
    }
    NseIndex::insert($indexRows);
    $last = $prices[array_key_last($prices)];
    $this->artisan('backtest:calculate-covariance', ['--symbol' => 'TRUST', '--date' => $last->date->toDateString()])->assertSuccessful();
    if ($missingBenchmark) {
        expect($last->fresh()->beta)->toBeNull()->and($last->fresh()->covariance)->toBeNull();
    } else {
        expect((float) $last->fresh()->beta)->toEqualWithDelta(2, 0.00001);
    }
})->with([false, true]);
