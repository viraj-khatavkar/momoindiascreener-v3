<?php

namespace App\Console\Commands\Backtest;

use App\Models\BacktestNseCorporateAction;
use App\Models\BacktestNseInstrumentPrice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AdjustCorporateActionCommand extends Command
{
    /**
     * The price columns rewritten by the adjustment.
     *
     * @var list<string>
     */
    private const ADJUSTED_COLUMNS = [
        'open_adjusted', 'high_adjusted', 'low_adjusted', 'close_adjusted',
        'ma_200', 'ma_100', 'ma_50', 'ma_20',
        'ema_200', 'ema_100', 'ema_50', 'ema_20',
        'high_one_year', 'high_all_time',
    ];

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
    protected $description = 'Adjusts historic prices for corporate actions with a price adjustment factor';

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

        $corporateActions = BacktestNseCorporateAction::query()
            ->where('date', $date)
            ->whereNotNull('price_adjustment_factor')
            ->whereNull('price_adjustment_applied_at')
            ->get();

        foreach ($corporateActions as $corporateAction) {
            $this->info('Adjusting corporate action for '.$corporateAction->symbol);

            if ($dryRun) {
                continue;
            }

            $factor = (float) $corporateAction->price_adjustment_factor;

            BacktestNseInstrumentPrice::query()
                ->where('symbol', $corporateAction->symbol)
                ->where('date', '<', $corporateAction->date)
                ->update(collect(self::ADJUSTED_COLUMNS)->mapWithKeys(
                    fn (string $column) => [$column => DB::raw("{$column} / {$factor}")]
                )->all());

            $corporateAction->price_adjustment_applied_at = now();
            $corporateAction->save();
        }
    }
}
