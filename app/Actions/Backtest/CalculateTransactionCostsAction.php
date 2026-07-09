<?php

namespace App\Actions\Backtest;

use App\Models\Backtest;

class CalculateTransactionCostsAction
{
    /**
     * @return array{brokerage: float, stt: float, transaction_charges: float, sebi_charges: float, gst: float, stamp_charges: float, total_charges: float}
     */
    public function execute(float $grossAmount, string $type, Backtest $backtest): array
    {
        $brokerage = $grossAmount * ((float) $backtest->brokerage_rate / 100);
        // STT applies only on the sell side for equity delivery trades.
        $stt = ($type === 'sell') ? $grossAmount * ((float) $backtest->stt_rate / 100) : 0;
        $transactionCharges = $grossAmount * ((float) $backtest->transaction_charges_rate / 100);
        $sebiCharges = $grossAmount * ((float) $backtest->sebi_charges_rate / 100);
        $gst = ($brokerage + $sebiCharges + $transactionCharges) * ((float) $backtest->gst_rate / 100);
        $stampCharges = ($type === 'buy') ? $grossAmount * ((float) $backtest->stamp_charges_rate / 100) : 0;
        $totalCharges = $brokerage + $stt + $transactionCharges + $sebiCharges + $gst + $stampCharges;

        return [
            'brokerage' => round($brokerage, 2),
            'stt' => round($stt, 2),
            'transaction_charges' => round($transactionCharges, 2),
            'sebi_charges' => round($sebiCharges, 2),
            'gst' => round($gst, 2),
            'stamp_charges' => round($stampCharges, 2),
            'total_charges' => round($totalCharges, 2),
        ];
    }

    /**
     * Buy-side charges as a fraction of gross amount (no STT on buys for
     * delivery), used to size orders so cost-inclusive spend fits a budget.
     */
    public function buyCostRate(Backtest $backtest): float
    {
        $brokerage = (float) $backtest->brokerage_rate / 100;
        $transactionCharges = (float) $backtest->transaction_charges_rate / 100;
        $sebiCharges = (float) $backtest->sebi_charges_rate / 100;
        $gst = (float) $backtest->gst_rate / 100;
        $stampCharges = (float) $backtest->stamp_charges_rate / 100;

        return $brokerage + $stampCharges + $transactionCharges + $sebiCharges
            + $gst * ($brokerage + $transactionCharges + $sebiCharges);
    }
}
