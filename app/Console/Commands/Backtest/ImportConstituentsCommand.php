<?php

namespace App\Console\Commands\Backtest;

use App\Actions\ReadCsvAction;
use App\Models\BacktestNseIndexConstituent;
use App\Models\BacktestNseInstrumentPrice;
use Illuminate\Console\Command;

class ImportConstituentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backtest:import-constituents {--date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imports nse constituents from daily file for backtest tables';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Importing constituents from NSE file...');
        $date = $this->option('date');

        if (is_null($date)) {
            $this->error('Please provide a date');

            return Command::FAILURE;
        }

        $groupedInstruments = $this->fetchConstituents();

        foreach ($groupedInstruments as $universe => $instruments) {
            $this->info($universe.' - '.count($instruments).' instruments found in table');

            BacktestNseInstrumentPrice::query()
                ->where('date', $date)
                ->update(['is_'.$universe => false]);

            BacktestNseInstrumentPrice::query()
                ->where('date', $date)
                ->whereIn('symbol', collect($instruments)->pluck('symbol')->all())
                ->update(['is_'.$universe => true]);
        }

        BacktestNseInstrumentPrice::query()
            ->where('date', $date)
            ->update(['is_nifty_allcap' => false]);

        BacktestNseInstrumentPrice::query()
            ->where('date', $date)
            ->whereIn('series', ['EQ', 'BE'])
            ->where('is_etf', false)
            ->update(['is_nifty_allcap' => true]);

        $this->info(
            'nifty allcap: '.BacktestNseInstrumentPrice::where('is_nifty_allcap', true)->where('date', $date)->count()
        );
        $this->info(
            'nifty 50: '.BacktestNseInstrumentPrice::where('is_nifty_50', true)->where('date', $date)->count()
        );
        $this->info(
            'nifty next 50: '.BacktestNseInstrumentPrice::where('is_nifty_next_50', true)->where('date', $date)
                ->count()
        );
        $this->info(
            'nifty 100: '.BacktestNseInstrumentPrice::where('is_nifty_100', true)->where('date', $date)->count()
        );
        $this->info(
            'nifty 200: '.BacktestNseInstrumentPrice::where('is_nifty_200', true)->where('date', $date)->count()
        );
        $this->info(
            'nifty midcap 100: '.BacktestNseInstrumentPrice::where('is_nifty_midcap_100', true)->where('date', $date)
                ->count()
        );
        $this->info(
            'nifty 500: '.BacktestNseInstrumentPrice::where('is_nifty_500', true)->where('date', $date)->count()
        );
    }

    protected function fetchConstituents(): array
    {
        /** @var ReadCsvAction $readCsvAction */
        $backtestNseIndexConstituents = BacktestNseIndexConstituent::all();

        return [
            'nifty_50' => $backtestNseIndexConstituents->filter(fn ($backtestNseIndexConstituent) => $backtestNseIndexConstituent->index === 'nifty_50')->toArray(),
            'nifty_next_50' => $backtestNseIndexConstituents->filter(fn ($backtestNseIndexConstituent) => $backtestNseIndexConstituent->index === 'nifty_next_50')->toArray(),
            'nifty_100' => $backtestNseIndexConstituents->filter(fn ($backtestNseIndexConstituent) => $backtestNseIndexConstituent->index === 'nifty_100')->toArray(),
            'nifty_200' => $backtestNseIndexConstituents->filter(fn ($backtestNseIndexConstituent) => $backtestNseIndexConstituent->index === 'nifty_200')->toArray(),
            'nifty_midcap_100' => $backtestNseIndexConstituents->filter(fn ($backtestNseIndexConstituent) => $backtestNseIndexConstituent->index === 'nifty_midcap_100')->toArray(),
            'nifty_500' => $backtestNseIndexConstituents->filter(fn ($backtestNseIndexConstituent) => $backtestNseIndexConstituent->index === 'nifty_500')->toArray(),
        ];
    }
}
