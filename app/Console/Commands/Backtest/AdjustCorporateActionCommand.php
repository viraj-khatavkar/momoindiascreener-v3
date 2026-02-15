<?php

namespace App\Console\Commands\Backtest;

use App\Models\BacktestNseInstrumentPrice;
use Illuminate\Console\Command;

class AdjustCorporateActionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backtest:adjust-corporate-action {--date=} {--dry-run}';

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
        $this->info('Adjusting bonus, splits, demergers...');
        $date = $this->option('date');
        $dryRun = $this->option('dry-run');

        if (is_null($date)) {
            $this->error('Please provide a date');

            return Command::FAILURE;
        }

        $corporateActions = BacktestNseInstrumentPrice::query()
            ->where('date', $date)
            ->whereNotNull('price_adjustment_factor')
            ->get();

        foreach ($corporateActions as $corporateAction) {
            $this->info('Adjusting corporate action for '.$corporateAction->symbol);

            if ($dryRun) {
                continue;
            }

            $backtestNseInstrumentPrices = BacktestNseInstrumentPrice::query()
                ->where('symbol', $corporateAction->symbol)
                ->where('date', '<', $corporateAction->date)
                ->orderBy('date', 'desc')
                ->get();

            foreach ($backtestNseInstrumentPrices as $backtestNseInstrumentPrice) {
                $backtestNseInstrumentPrice->open_adjusted = $backtestNseInstrumentPrice->open_adjusted / $corporateAction->price_adjustment_factor;
                $backtestNseInstrumentPrice->high_adjusted = $backtestNseInstrumentPrice->high_adjusted / $corporateAction->price_adjustment_factor;
                $backtestNseInstrumentPrice->low_adjusted = $backtestNseInstrumentPrice->low_adjusted / $corporateAction->price_adjustment_factor;
                $backtestNseInstrumentPrice->close_adjusted = $backtestNseInstrumentPrice->close_adjusted / $corporateAction->price_adjustment_factor;
                $backtestNseInstrumentPrice->ma_200 = $backtestNseInstrumentPrice->ma_200 / $corporateAction->price_adjustment_factor;
                $backtestNseInstrumentPrice->ma_100 = $backtestNseInstrumentPrice->ma_100 / $corporateAction->price_adjustment_factor;
                $backtestNseInstrumentPrice->ma_50 = $backtestNseInstrumentPrice->ma_50 / $corporateAction->price_adjustment_factor;
                $backtestNseInstrumentPrice->ma_20 = $backtestNseInstrumentPrice->ma_20 / $corporateAction->price_adjustment_factor;
                $backtestNseInstrumentPrice->ema_200 = $backtestNseInstrumentPrice->ema_200 / $corporateAction->price_adjustment_factor;
                $backtestNseInstrumentPrice->ema_100 = $backtestNseInstrumentPrice->ema_100 / $corporateAction->price_adjustment_factor;
                $backtestNseInstrumentPrice->ema_50 = $backtestNseInstrumentPrice->ema_50 / $corporateAction->price_adjustment_factor;
                $backtestNseInstrumentPrice->ema_20 = $backtestNseInstrumentPrice->ema_20 / $corporateAction->price_adjustment_factor;
                $backtestNseInstrumentPrice->high_one_year = $backtestNseInstrumentPrice->high_one_year / $corporateAction->price_adjustment_factor;
                $backtestNseInstrumentPrice->high_all_time = $backtestNseInstrumentPrice->high_all_time / $corporateAction->price_adjustment_factor;
                $backtestNseInstrumentPrice->save();
            }
        }
    }
}
