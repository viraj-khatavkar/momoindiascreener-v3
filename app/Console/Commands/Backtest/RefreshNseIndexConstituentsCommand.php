<?php

namespace App\Console\Commands\Backtest;

use App\Models\BacktestNseIndexConstituent;
use App\Models\BacktestNseInstrumentPrice;
use Illuminate\Console\Command;

use function is_null;

class RefreshNseIndexConstituentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backtest:refresh-nse-index-constituents {--date=}';

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
        $this->info('Refreshing index constituents');

        $date = $this->option('date');

        if (is_null($date)) {
            $this->error('Please provide a date');

            return Command::FAILURE;
        }

        BacktestNseIndexConstituent::truncate();

        $indices = [
            'nifty_50',
            'nifty_next_50',
            'nifty_100',
            'nifty_200',
            'nifty_midcap_100',
            'nifty_midcap_150',
            'nifty_500',
            'nifty_smallcap_250',
            'nifty_largemidcap_250',
            'nifty_midsmallcap_400',
        ];

        foreach ($indices as $index) {
            $instruments = BacktestNseInstrumentPrice::query()
                ->where('date', $date)
                ->where('is_'.$index, true)
                ->select('id', 'symbol')
                ->get()
                ->map(fn ($instrument) => [
                    'symbol' => $instrument->symbol,
                    'index' => $index,
                ])
                ->toArray();

            BacktestNseIndexConstituent::insert($instruments);
        }

        foreach ($indices as $index) {
            $this->info($index.': '.BacktestNseIndexConstituent::where('index', $index)->count());
        }
    }
}
