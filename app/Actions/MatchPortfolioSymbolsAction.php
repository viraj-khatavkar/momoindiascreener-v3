<?php

namespace App\Actions;

use App\Models\BacktestNseInstrumentPrice;
use Illuminate\Validation\ValidationException;

class MatchPortfolioSymbolsAction
{
    /**
     * @param  list<string>  $symbols
     * @return array{matched: list<string>, unmatched: list<string>, total: int, duplicates: int, date: string}
     */
    public function execute(array $symbols, string $errorField = 'symbols'): array
    {
        $date = BacktestNseInstrumentPrice::query()->max('date');

        if ($date === null) {
            throw ValidationException::withMessages([$errorField => 'No market data is available. Try again after prices are imported.']);
        }

        $uniqueSymbols = array_values(array_unique($symbols));
        $availableSymbols = BacktestNseInstrumentPrice::query()
            ->where('date', $date)
            ->whereIn('symbol', $uniqueSymbols)
            ->pluck('symbol')
            ->all();

        return [
            'matched' => array_values(array_intersect($uniqueSymbols, $availableSymbols)),
            'unmatched' => array_values(array_diff($uniqueSymbols, $availableSymbols)),
            'total' => count($uniqueSymbols),
            'duplicates' => count($symbols) - count($uniqueSymbols),
            'date' => $date,
        ];
    }
}
