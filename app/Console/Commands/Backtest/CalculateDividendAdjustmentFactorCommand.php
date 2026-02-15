<?php

namespace App\Console\Commands\Backtest;

use App\Models\BacktestNseInstrumentPrice;
use Illuminate\Console\Command;

class CalculateDividendAdjustmentFactorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backtest:calculate-dividend-adjustment-factor {--date=} {--dry-run}';

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
        $this->info('Calculating dividend adjustment factor...');
        $date = $this->option('date');
        $dryRun = $this->option('dry-run');

        if (is_null($date)) {
            $this->error('Please provide a date');

            return Command::FAILURE;
        }

        $dividends = BacktestNseInstrumentPrice::query()
            ->where('date', $date)
            ->whereNotNull('dividend')
            ->get();

        foreach ($dividends as $dividend) {
            $this->info('Calculating dividend adjustment factor for '.$dividend->symbol);

            if ($dryRun) {
                continue;
            }

            $oneDayBeforePrice = BacktestNseInstrumentPrice::query()
                ->where('symbol', $dividend->symbol)
                ->where('date', '<', $dividend->date)
                ->orderBy('date', 'desc')
                ->first();

            $dividend->dividend_adjustment_factor = ($oneDayBeforePrice->close_adjusted - $dividend->dividend) / $oneDayBeforePrice->close_adjusted;
            $dividend->save();
        }
    }
}
