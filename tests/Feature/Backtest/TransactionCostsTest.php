<?php

use App\Actions\Backtest\CalculateTransactionCostsAction;
use App\Models\Backtest;

/**
 * @param  array<string, mixed>  $overrides
 */
function costBacktest(array $overrides = []): Backtest
{
    return Backtest::factory()->make($overrides);
}

it('calculates correct buy transaction costs with the default rates', function () {
    $action = new CalculateTransactionCostsAction;
    $result = $action->execute(1000000, 'buy', costBacktest());

    // Brokerage: defaults to zero (discount broker)
    expect($result['brokerage'])->toBe(0.00)
        // STT: zero on buy (delivery equity — STT is sell-side only)
        ->and($result['stt'])->toBe(0.00)
        // NSE transaction charges: 0.00307%
        ->and($result['transaction_charges'])->toBe(30.70)
        ->and($result['sebi_charges'])->toBe(0.10)
        // Stamp: 0.015% on buy only
        ->and($result['stamp_charges'])->toBe(150.00)
        // GST: 18% on (brokerage + SEBI + transaction charges)
        ->and($result['gst'])->toBe(5.54)
        // Total (no STT)
        ->and($result['total_charges'])->toBe(186.34);
});

it('calculates correct sell transaction costs with zero stamp charges', function () {
    $action = new CalculateTransactionCostsAction;
    $result = $action->execute(1000000, 'sell', costBacktest());

    expect($result['stamp_charges'])->toBe(0.00)
        ->and($result['stt'])->toBe(1000.00)
        ->and($result['total_charges'])->toBe(1036.34);
});

it('produces approximately 0.12 percent round trip cost with the default rates', function () {
    $action = new CalculateTransactionCostsAction;
    $backtest = costBacktest();
    $buy = $action->execute(1000000, 'buy', $backtest);
    $sell = $action->execute(1000000, 'sell', $backtest);

    $roundTripPct = ($buy['total_charges'] + $sell['total_charges']) / 1000000 * 100;

    expect($roundTripPct)->toBeGreaterThan(0.12)
        ->and($roundTripPct)->toBeLessThan(0.13);
});

it('scales costs proportionally with amount', function () {
    $action = new CalculateTransactionCostsAction;
    $backtest = costBacktest();

    $small = $action->execute(100000, 'buy', $backtest);
    $large = $action->execute(1000000, 'buy', $backtest);

    // 10x amount should produce approximately 10x costs
    $ratio = $large['total_charges'] / $small['total_charges'];

    expect($ratio)->toBeGreaterThan(9.9)
        ->and($ratio)->toBeLessThan(10.1);
});

it('charges brokerage on both sides and includes it in the gst base', function () {
    $action = new CalculateTransactionCostsAction;
    $backtest = costBacktest(['brokerage_rate' => 0.03]);

    $buy = $action->execute(1000000, 'buy', $backtest);
    $sell = $action->execute(1000000, 'sell', $backtest);

    // Brokerage: 0.03% of 10L = 300 on each side
    expect($buy['brokerage'])->toBe(300.00)
        ->and($sell['brokerage'])->toBe(300.00)
        // GST: 18% on (300 + 30.70 + 0.10)
        ->and($buy['gst'])->toBe(59.54)
        ->and($buy['total_charges'])->toBe(540.34);
});

it('uses the backtest rates instead of the statutory defaults', function () {
    $action = new CalculateTransactionCostsAction;
    $backtest = costBacktest([
        'stt_rate' => 0.2,
        'transaction_charges_rate' => 0.005,
        'sebi_charges_rate' => 0,
        'gst_rate' => 10,
        'stamp_charges_rate' => 0.02,
    ]);

    $buy = $action->execute(1000000, 'buy', $backtest);
    $sell = $action->execute(1000000, 'sell', $backtest);

    expect($sell['stt'])->toBe(2000.00)
        ->and($buy['transaction_charges'])->toBe(50.00)
        ->and($buy['sebi_charges'])->toBe(0.00)
        ->and($buy['gst'])->toBe(5.00)
        ->and($buy['stamp_charges'])->toBe(200.00)
        ->and($buy['total_charges'])->toBe(255.00);
});

it('produces zero charges when every rate is zero', function () {
    $action = new CalculateTransactionCostsAction;
    $backtest = costBacktest([
        'brokerage_rate' => 0,
        'stt_rate' => 0,
        'transaction_charges_rate' => 0,
        'sebi_charges_rate' => 0,
        'gst_rate' => 0,
        'stamp_charges_rate' => 0,
    ]);

    expect($action->execute(1000000, 'buy', $backtest)['total_charges'])->toBe(0.00)
        ->and($action->execute(1000000, 'sell', $backtest)['total_charges'])->toBe(0.00)
        ->and($action->buyCostRate($backtest))->toBe(0.0);
});

it('derives the buy cost rate from the configured rates', function () {
    $action = new CalculateTransactionCostsAction;

    // Defaults: stamp + txn + SEBI + GST on (txn + SEBI) ≈ 0.0186% of gross
    expect($action->buyCostRate(costBacktest()))->toEqualWithDelta(0.000186344, 0.0000001);

    // With 0.5% brokerage the rate grows by brokerage + GST on brokerage
    expect($action->buyCostRate(costBacktest(['brokerage_rate' => 0.5])))
        ->toEqualWithDelta(0.000186344 + 0.005 * 1.18, 0.0000001);
});
