<?php

namespace App\Console\Commands\Backtest;

use App\Models\BacktestNseCorporateAction;
use App\Models\BacktestNseInstrumentPrice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AdjustDividendActionCommand extends Command
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
    protected $signature = 'backtest:adjust-dividends {--date=} {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Adjusts historic prices for corporate actions with a dividend adjustment factor';

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

        $dividendActions = BacktestNseCorporateAction::query()
            ->where('date', $date)
            ->whereNotNull('dividend_adjustment_factor')
            ->whereNull('dividend_adjustment_applied_at')
            ->get();

        foreach ($dividendActions as $dividendAction) {
            $this->info('Adjusting dividend action for '.$dividendAction->symbol);

            if ($dryRun) {
                continue;
            }

            $factor = (float) $dividendAction->dividend_adjustment_factor;

            BacktestNseInstrumentPrice::query()
                ->where('symbol', $dividendAction->symbol)
                ->where('date', '<', $dividendAction->date)
                ->update(collect(self::ADJUSTED_COLUMNS)->mapWithKeys(
                    fn (string $column) => [$column => DB::raw("{$column} * {$factor}")]
                )->all());

            $dividendAction->dividend_adjustment_applied_at = now();
            $dividendAction->save();
        }
    }
}
