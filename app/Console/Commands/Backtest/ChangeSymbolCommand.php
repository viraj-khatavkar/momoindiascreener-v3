<?php

namespace App\Console\Commands\Backtest;

use App\Models\BacktestNseCorporateAction;
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
    protected $description = 'Changes an NSE symbol across backtest price, corporate action, and instrument master tables';

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

        [$priceRowsUpdated, $corporateActionRowsUpdated, $instrumentRowsUpdated] = DB::transaction(function () use ($oldSymbol, $newSymbol): array {
            $priceRowsUpdated = BacktestNseInstrumentPrice::query()
                ->where('symbol', $oldSymbol)
                ->update(['symbol' => $newSymbol]);

            $corporateActionRowsUpdated = BacktestNseCorporateAction::query()
                ->where('symbol', $oldSymbol)
                ->update(['symbol' => $newSymbol]);

            $instrumentRowsUpdated = $this->updateInstrumentSymbol($oldSymbol, $newSymbol);

            return [$priceRowsUpdated, $corporateActionRowsUpdated, $instrumentRowsUpdated];
        });

        $this->info($priceRowsUpdated.' price rows updated.');
        $this->info($corporateActionRowsUpdated.' corporate action rows updated.');
        $this->info($instrumentRowsUpdated.' instrument rows updated.');

        return Command::SUCCESS;
    }

    private function updateInstrumentSymbol(string $oldSymbol, string $newSymbol): int
    {
        $oldInstrument = BacktestNseInstrument::query()
            ->where('symbol', $oldSymbol)
            ->first(['id', 'name', 'etf_index']);

        if (! $oldInstrument) {
            return 0;
        }

        $newInstrument = BacktestNseInstrument::query()
            ->where('symbol', $newSymbol)
            ->first(['id', 'name', 'etf_index']);

        if (! $newInstrument) {
            return BacktestNseInstrument::query()
                ->whereKey($oldInstrument->id)
                ->update(['symbol' => $newSymbol]);
        }

        $updates = [];

        if (blank($newInstrument->name) && filled($oldInstrument->name)) {
            $updates['name'] = $oldInstrument->name;
        }

        if (blank($newInstrument->etf_index) && filled($oldInstrument->etf_index)) {
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
