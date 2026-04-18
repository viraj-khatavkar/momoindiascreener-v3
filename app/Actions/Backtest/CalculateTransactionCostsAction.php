<?php

namespace App\Actions\Backtest;

class CalculateTransactionCostsAction
{
    /**
     * @return array{stt: float, transaction_charges: float, sebi_charges: float, gst: float, stamp_charges: float, total_charges: float}
     */
    public function execute(float $grossAmount, string $type): array
    {
        // STT applies only on the sell side for equity delivery trades.
        $stt = ($type === 'sell') ? $grossAmount * 0.001 : 0;
        $transactionCharges = $grossAmount * 0.0000307;
        $sebiCharges = $grossAmount * 0.0000001;
        $gst = ($sebiCharges + $transactionCharges) * 0.18;
        $stampCharges = ($type === 'buy') ? $grossAmount * 0.00015 : 0;
        $totalCharges = $stt + $transactionCharges + $sebiCharges + $gst + $stampCharges;

        return [
            'stt' => round($stt, 2),
            'transaction_charges' => round($transactionCharges, 2),
            'sebi_charges' => round($sebiCharges, 2),
            'gst' => round($gst, 2),
            'stamp_charges' => round($stampCharges, 2),
            'total_charges' => round($totalCharges, 2),
        ];
    }
}
