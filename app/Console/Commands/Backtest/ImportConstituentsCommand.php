<?php

namespace App\Console\Commands\Backtest;

use App\Models\BacktestNseIndexConstituent;
use App\Models\BacktestNseInstrumentPrice;
use App\NseIndexMembership;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ImportConstituentsCommand extends Command
{
    protected $signature = 'backtest:import-constituents {--date=}';

    protected $description = 'Mark dated index membership from the maintained constituent table';

    public function handle(): int
    {
        $date = $this->option('date');
        if ($date === null) {
            $this->error('Please provide a date');

            return Command::FAILURE;
        }
        if (Validator::make(['date' => $date], ['date' => ['required', 'date_format:Y-m-d']])->fails()) {
            $this->error('Please provide a valid date in YYYY-MM-DD format');

            return Command::FAILURE;
        }

        $prices = BacktestNseInstrumentPrice::query()->where('date', $date)->pluck('symbol');
        if ($prices->isEmpty()) {
            $this->error('No prices found for '.$date);

            return Command::FAILURE;
        }

        $indices = NseIndexMembership::forDailyImport($date);
        $memberships = BacktestNseIndexConstituent::query()
            ->whereIn('index', $indices->pluck('value'))
            ->get(['index', 'symbol'])->groupBy('index');
        $this->info('Marking index membership from the constituent table for '.$date.'...');

        DB::transaction(function () use ($indices, $memberships, $date, $prices): void {
            foreach ($indices as $index) {
                $symbols = $memberships->get($index->value, collect())->pluck('symbol')->unique()->values();
                BacktestNseInstrumentPrice::query()->where('date', $date)
                    ->update([$index->isIndexFieldName() => false]);
                BacktestNseInstrumentPrice::query()->where('date', $date)->whereIn('symbol', $symbols)
                    ->update([$index->isIndexFieldName() => true]);

                $missing = $symbols->diff($prices);
                $this->info(Str::lower($index->label()).': '.($symbols->count() - $missing->count()).' marked; '.$symbols->count().' in constituent table');
                if ($missing->isNotEmpty()) {
                    $this->warn($index->label().' — no price for: '.$missing->implode(', '));
                }
            }

            BacktestNseInstrumentPrice::query()->where('date', $date)
                ->update(['is_nifty_allcap' => false]);
            BacktestNseInstrumentPrice::query()->where('date', $date)
                ->whereIn('series', ['EQ', 'BE', 'RR', 'IV'])->where('is_etf', false)
                ->update(['is_nifty_allcap' => true]);
        });

        $this->info('nifty allcap: '.BacktestNseInstrumentPrice::query()->where('date', $date)->where('is_nifty_allcap', true)->count());

        return Command::SUCCESS;
    }
}
