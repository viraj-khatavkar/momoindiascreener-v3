<?php

namespace App\Actions\Backtest;

use App\Actions\ResolveNseFnoSymbolsAction;
use App\Models\BacktestNseIndexConstituent;
use App\Models\BacktestNseInstrumentPrice;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportNseFnoMembershipAction
{
    public function __construct(private ResolveNseFnoSymbolsAction $resolveSymbols) {}

    /**
     * @param  list<string>  $sourceSymbols
     * @return array{mappings: array<string, string>, missing: list<string>}
     */
    public function execute(string $date, array $sourceSymbols, ?string $changesPath = null, bool $dryRun = false): array
    {
        $prices = BacktestNseInstrumentPrice::query()->where('date', $date)->pluck('symbol')->all();
        if ($prices === []) {
            throw new RuntimeException("Import equity prices for {$date} before importing F&O membership.");
        }
        $result = $this->resolveSymbols->execute($sourceSymbols, $date, $prices, $changesPath);
        if ($result['missing'] !== []) {
            throw new RuntimeException("F&O symbols have no matching price row for {$date}: ".implode(', ', $result['missing']).'. No membership was changed.');
        }
        if ($result['mappings'] === []) {
            throw new RuntimeException("No F&O symbols match equity prices for {$date}. No membership was changed.");
        }
        if ($dryRun) {
            return $result;
        }
        DB::transaction(function () use ($date, $result): void {
            BacktestNseInstrumentPrice::query()->where('date', $date)->where('is_nifty_fno', true)
                ->whereNotIn('symbol', array_values($result['mappings']))->update(['is_nifty_fno' => false]);
            BacktestNseInstrumentPrice::query()->where('date', $date)->where('is_nifty_fno', false)
                ->whereIn('symbol', array_values($result['mappings']))->update(['is_nifty_fno' => true]);

            $latestDate = BacktestNseInstrumentPrice::query()->orderByDesc('date')->toBase()->value('date');
            if ($latestDate === $date) {
                BacktestNseIndexConstituent::query()->where('index', 'nifty_fno')->delete();
                BacktestNseIndexConstituent::query()->insert(array_map(
                    fn (string $symbol): array => ['index' => 'nifty_fno', 'symbol' => $symbol],
                    BacktestNseInstrumentPrice::query()->where('date', $date)->where('is_nifty_fno', true)->pluck('symbol')->all(),
                ));
            }
        });

        return $result;
    }
}
