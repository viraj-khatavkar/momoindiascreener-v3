<?php

use App\Actions\Backtest\CalculateTransactionCostsAction;

it('calculates correct buy transaction costs', function () {
    $action = new CalculateTransactionCostsAction();
    $result = $action->execute(1000000, 'buy');

    // STT: 0.1% on buy
    expect($result['stt'])->toBe(1000.00)
        // NSE transaction charges: 0.00307%
        ->and($result['transaction_charges'])->toBe(30.70)
        // SEBI: Rs 10 per crore
        ->and($result['sebi_charges'])->toBe(0.10)
        // Stamp: 0.015% on buy only
        ->and($result['stamp_charges'])->toBe(150.00)
        // GST: 18% on (SEBI + transaction charges)
        ->and($result['gst'])->toBe(5.54)
        // Total
        ->and($result['total_charges'])->toBe(1186.34);
});

it('calculates correct sell transaction costs with zero stamp charges', function () {
    $action = new CalculateTransactionCostsAction();
    $result = $action->execute(1000000, 'sell');

    expect($result['stamp_charges'])->toBe(0.00)
        ->and($result['stt'])->toBe(1000.00)
        ->and($result['total_charges'])->toBe(1036.34);
});

it('produces approximately 0.22 percent round trip cost', function () {
    $action = new CalculateTransactionCostsAction();
    $buy = $action->execute(1000000, 'buy');
    $sell = $action->execute(1000000, 'sell');

    $roundTripPct = ($buy['total_charges'] + $sell['total_charges']) / 1000000 * 100;

    expect($roundTripPct)->toBeGreaterThan(0.22)
        ->and($roundTripPct)->toBeLessThan(0.23);
});

it('scales costs proportionally with amount', function () {
    $action = new CalculateTransactionCostsAction();

    $small = $action->execute(100000, 'buy');
    $large = $action->execute(1000000, 'buy');

    // 10x amount should produce approximately 10x costs
    $ratio = $large['total_charges'] / $small['total_charges'];

    expect($ratio)->toBeGreaterThan(9.9)
        ->and($ratio)->toBeLessThan(10.1);
});
