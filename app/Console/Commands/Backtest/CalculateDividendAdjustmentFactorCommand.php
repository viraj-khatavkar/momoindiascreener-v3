<?php

namespace App\Console\Commands\Backtest;

use App\Models\BacktestNseCorporateAction;
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
    protected $description = 'Calculates the dividend adjustment factor for corporate actions with a dividend';

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

        $dividendActions = BacktestNseCorporateAction::query()
            ->where('date', $date)
            ->whereNotNull('dividend')
            ->get();

        foreach ($dividendActions as $dividendAction) {
            $this->info('Calculating dividend adjustment factor for '.$dividendAction->symbol);

            if ($dryRun) {
                continue;
            }

            if (! is_null($dividendAction->dividend_adjustment_applied_at)) {
                $this->warn('Dividend adjustment already applied for '.$dividendAction->symbol.', skipping');

                continue;
            }

            $oneDayBeforePrice = BacktestNseInstrumentPrice::query()
                ->where('symbol', $dividendAction->symbol)
                ->where('date', '<', $dividendAction->date)
                ->orderBy('date', 'desc')
                ->first();

            if (is_null($oneDayBeforePrice)) {
                $this->warn('No earlier price record for '.$dividendAction->symbol.', skipping');

                continue;
            }

            $dividendAction->dividend_adjustment_factor = ($oneDayBeforePrice->close_adjusted - $dividendAction->dividend) / $oneDayBeforePrice->close_adjusted;
            $dividendAction->save();
        }
    }
}
