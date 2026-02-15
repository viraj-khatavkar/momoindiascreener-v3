<?php

namespace App\Console\Commands\Backtest;

use App\Models\BacktestNseInstrumentPrice;
use Illuminate\Console\Command;

class AdjustDividendActionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backtest:adjust-dividends {--date=} {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description ';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Adjusting dividends...');
        $date = $this->option('date');
        $dryRun = $this->option('dry-run');

        if (is_null($date)) {
            $this->error('Please provide a date');

            return Command::FAILURE;
        }

        $dividendActions = BacktestNseInstrumentPrice::query()
            ->where('date', $date)
            ->whereNotNull('dividend_adjustment_factor')
            ->get();

        foreach ($dividendActions as $dividendAction) {
            $this->info('Adjusting dividend action for '.$dividendAction->symbol);

            if ($dryRun) {
                continue;
            }

            $backtestNseInstrumentPrices = BacktestNseInstrumentPrice::query()
                ->where('symbol', $dividendAction->symbol)
                ->where('date', '<', $dividendAction->date)
                ->orderBy('date', 'desc')
                ->get();

            foreach ($backtestNseInstrumentPrices as $backtestNseInstrumentPrice) {
                $backtestNseInstrumentPrice->open_adjusted = $backtestNseInstrumentPrice->open_adjusted * $dividendAction->dividend_adjustment_factor;
                $backtestNseInstrumentPrice->high_adjusted = $backtestNseInstrumentPrice->high_adjusted * $dividendAction->dividend_adjustment_factor;
                $backtestNseInstrumentPrice->low_adjusted = $backtestNseInstrumentPrice->low_adjusted * $dividendAction->dividend_adjustment_factor;
                $backtestNseInstrumentPrice->close_adjusted = $backtestNseInstrumentPrice->close_adjusted * $dividendAction->dividend_adjustment_factor;
                $backtestNseInstrumentPrice->ma_200 = $backtestNseInstrumentPrice->ma_200 * $dividendAction->dividend_adjustment_factor;
                $backtestNseInstrumentPrice->ma_100 = $backtestNseInstrumentPrice->ma_100 * $dividendAction->dividend_adjustment_factor;
                $backtestNseInstrumentPrice->ma_50 = $backtestNseInstrumentPrice->ma_50 * $dividendAction->dividend_adjustment_factor;
                $backtestNseInstrumentPrice->ma_20 = $backtestNseInstrumentPrice->ma_20 * $dividendAction->dividend_adjustment_factor;
                $backtestNseInstrumentPrice->ema_200 = $backtestNseInstrumentPrice->ema_200 * $dividendAction->dividend_adjustment_factor;
                $backtestNseInstrumentPrice->ema_100 = $backtestNseInstrumentPrice->ema_100 * $dividendAction->dividend_adjustment_factor;
                $backtestNseInstrumentPrice->ema_50 = $backtestNseInstrumentPrice->ema_50 * $dividendAction->dividend_adjustment_factor;
                $backtestNseInstrumentPrice->ema_20 = $backtestNseInstrumentPrice->ema_20 * $dividendAction->dividend_adjustment_factor;
                $backtestNseInstrumentPrice->high_one_year = $backtestNseInstrumentPrice->high_one_year * $dividendAction->dividend_adjustment_factor;
                $backtestNseInstrumentPrice->high_all_time = $backtestNseInstrumentPrice->high_all_time * $dividendAction->dividend_adjustment_factor;
                $backtestNseInstrumentPrice->save();
            }
        }
    }
}
