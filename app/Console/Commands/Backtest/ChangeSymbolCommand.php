<?php

namespace App\Console\Commands\Backtest;

use App\Models\BacktestNseCorporateAction;
use App\Models\BacktestNseIndexConstituent;
use App\Models\BacktestNseInstrument;
use App\Models\BacktestNseInstrumentPrice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ChangeSymbolCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backtest:change-symbol {--old-symbol=} {--new-symbol=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Changes an NSE symbol across backtest price, corporate action, index constituent, and instrument master tables';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Changing symbol for NSE instruments...');
        $oldSymbol = $this->option('old-symbol');
        $newSymbol = $this->option('new-symbol');

        if (is_null($oldSymbol)) {
            $this->error('Please provide an old symbol');

            return Command::FAILURE;
        }

        if (is_null($newSymbol)) {
            $this->error('Please provide a new symbol');

            return Command::FAILURE;
        }

        [$priceRowsUpdated, $corporateActionRowsUpdated, $constituentRowsUpdated, $instrumentRowsUpdated] = DB::transaction(function () use ($oldSymbol, $newSymbol): array {
            $priceRowsUpdated = BacktestNseInstrumentPrice::query()
                ->where('symbol', $oldSymbol)
                ->update(['symbol' => $newSymbol]);

            $corporateActionRowsUpdated = BacktestNseCorporateAction::query()
                ->where('symbol', $oldSymbol)
                ->update(['symbol' => $newSymbol]);

            $constituentRowsUpdated = $this->updateIndexConstituentSymbol($oldSymbol, $newSymbol);

            $instrumentRowsUpdated = $this->updateInstrumentSymbol($oldSymbol, $newSymbol);

            return [$priceRowsUpdated, $corporateActionRowsUpdated, $constituentRowsUpdated, $instrumentRowsUpdated];
        });

        $this->info($priceRowsUpdated.' price rows updated.');
        $this->info($corporateActionRowsUpdated.' corporate action rows updated.');
        $this->info($constituentRowsUpdated.' index constituent rows updated.');
        $this->info($instrumentRowsUpdated.' instrument rows updated.');

        return Command::SUCCESS;
    }

    /**
     * Renames the symbol on every index constituent row, dropping the old rows
     * for indexes where the new symbol is already a constituent so the
     * (index, symbol) unique constraint is not violated.
     */
    private function updateIndexConstituentSymbol(string $oldSymbol, string $newSymbol): int
    {
        $indexesAlreadyHoldingNewSymbol = BacktestNseIndexConstituent::query()
            ->where('symbol', $newSymbol)
            ->pluck('index');

        if ($indexesAlreadyHoldingNewSymbol->isNotEmpty()) {
            BacktestNseIndexConstituent::query()
                ->where('symbol', $oldSymbol)
                ->whereIn('index', $indexesAlreadyHoldingNewSymbol)
                ->delete();
        }

        return BacktestNseIndexConstituent::query()
            ->where('symbol', $oldSymbol)
            ->update(['symbol' => $newSymbol]);
    }

    private function updateInstrumentSymbol(string $oldSymbol, string $newSymbol): int
    {
        $oldInstrument = BacktestNseInstrument::query()
            ->where('symbol', $oldSymbol)
            ->first(['id', 'name', 'etf_index', 'market_index_alias_id', 'etf_index_source_date']);

        if (! $oldInstrument) {
            return 0;
        }

        $newInstrument = BacktestNseInstrument::query()
            ->where('symbol', $newSymbol)
            ->first(['id', 'name', 'etf_index', 'market_index_alias_id', 'etf_index_source_date']);

        if (! $newInstrument) {
            return BacktestNseInstrument::query()
                ->whereKey($oldInstrument->id)
                ->update(['symbol' => $newSymbol]);
        }

        $updates = [];

        if (blank($newInstrument->name) && filled($oldInstrument->name)) {
            $updates['name'] = $oldInstrument->name;
        }

        if ($oldInstrument->market_index_alias_id && (
            ! $newInstrument->etf_index_source_date
            || $oldInstrument->etf_index_source_date?->isAfter($newInstrument->etf_index_source_date)
        )) {
            $updates['etf_index'] = $oldInstrument->etf_index;
            $updates['market_index_alias_id'] = $oldInstrument->market_index_alias_id;
            $updates['etf_index_source_date'] = $oldInstrument->etf_index_source_date;
        } elseif (
            ! $newInstrument->market_index_alias_id
            && blank($newInstrument->etf_index)
            && filled($oldInstrument->etf_index)
        ) {
            $updates['etf_index'] = $oldInstrument->etf_index;
        }

        if ($updates !== []) {
            BacktestNseInstrument::query()
                ->whereKey($newInstrument->id)
                ->update($updates);
        }

        BacktestNseInstrument::query()
            ->whereKey($oldInstrument->id)
            ->delete();

        return 1;
    }
}
