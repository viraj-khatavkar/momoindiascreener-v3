<?php

namespace App\Console\Commands\Backtest;

use App\Models\BacktestNseInstrumentPrice;
use Illuminate\Console\Command;

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
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
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

        BacktestNseInstrumentPrice::query()
            ->where('symbol', $oldSymbol)
            ->update(['symbol' => $newSymbol]);

        return Command::SUCCESS;
    }
}
