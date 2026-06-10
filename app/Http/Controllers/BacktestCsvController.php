<?php

namespace App\Http\Controllers;

use App\Models\Backtest;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BacktestCsvController extends Controller
{
    public function __invoke(Request $request, Backtest $backtest, string $type): StreamedResponse
    {
        if ($request->user()->cannot('view', $backtest)) {
            abort(404);
        }

        abort_unless(in_array($type, ['trades', 'nav']), 404);

        $filename = str($backtest->name)->replace(' ', '_').'_'.$type.'.csv';

        return response()->streamDownload(
            fn () => $type === 'trades' ? $this->writeTrades($backtest) : $this->writeNav($backtest),
            $filename,
            ['Content-Type' => 'text/csv'],
        );
    }

    protected function writeTrades(Backtest $backtest): void
    {
        $out = fopen('php://output', 'w');

        fputcsv($out, [
            'date', 'symbol', 'name', 'type', 'reason', 'quantity', 'raw_price', 'adjusted_price',
            'gross_amount', 'stt', 'transaction_charges', 'sebi_charges', 'gst', 'stamp_charges',
            'total_charges', 'net_amount',
        ], escape: '');

        foreach ($backtest->trades()->orderBy('date')->orderBy('id')->lazy() as $trade) {
            fputcsv($out, [
                $trade->date->format('Y-m-d'),
                $trade->symbol,
                $trade->name,
                $trade->trade_type,
                $trade->reason,
                $trade->quantity,
                $trade->raw_price,
                $trade->price,
                $trade->gross_amount,
                $trade->stt,
                $trade->transaction_charges,
                $trade->sebi_charges,
                $trade->gst,
                $trade->stamp_charges,
                $trade->total_charges,
                $trade->net_amount,
            ], escape: '');
        }

        fclose($out);
    }

    protected function writeNav(Backtest $backtest): void
    {
        $out = fopen('php://output', 'w');

        fputcsv($out, ['date', 'nav', 'portfolio_value', 'cash', 'total_value', 'holdings_count'], escape: '');

        foreach ($backtest->dailySnapshots()->orderBy('date')->lazy() as $snapshot) {
            fputcsv($out, [
                $snapshot->date->format('Y-m-d'),
                $snapshot->nav,
                $snapshot->portfolio_value,
                $snapshot->cash,
                $snapshot->total_value,
                $snapshot->holdings_count,
            ], escape: '');
        }

        fclose($out);
    }
}
