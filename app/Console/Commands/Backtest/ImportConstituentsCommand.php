<?php

namespace App\Console\Commands\Backtest;

use App\Models\BacktestNseInstrumentPrice;
use App\NseIndexMembership;
use Illuminate\Console\Command;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
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

        $prices = BacktestNseInstrumentPrice::query()->where('date', $date)->toBase();
        if (! $prices->exists()) {
            $this->error('No prices found for '.$date);

            return Command::FAILURE;
        }

        $indices = NseIndexMembership::forDailyImport($date);
        $this->info('Marking index membership from the constituent table for '.$date.'...');

        [$memberships, $allcapCount] = DB::transaction(function () use ($indices, $date, $prices): array {
            $this->updateMemberships($date, $indices);

            $memberships = DB::table('backtest_nse_index_constituents as members')
                ->leftJoin('backtest_nse_instrument_prices as prices', function (JoinClause $join) use ($date): void {
                    $join->on('prices.symbol', '=', 'members.symbol')->where('prices.date', $date);
                })
                ->whereIn('members.index', $indices->pluck('value'))
                ->get(['members.index', 'members.symbol', 'prices.id as price_id'])
                ->groupBy('index');

            return [$memberships, $prices->where('is_nifty_allcap', true)->count()];
        });

        foreach ($indices as $index) {
            $members = $memberships->get($index->value, collect());
            $missing = $members->whereNull('price_id')->pluck('symbol');
            $this->info(Str::lower($index->label()).': '.($members->count() - $missing->count()).' marked; '.$members->count().' in constituent table');
            if ($missing->isNotEmpty()) {
                $this->warn($index->label().' — no price for: '.$missing->implode(', '));
            }
        }
        $this->info('nifty allcap: '.$allcapCount);

        return Command::SUCCESS;
    }

    /** @param Collection<int, NseIndexMembership> $indices */
    private function updateMemberships(string $date, Collection $indices): void
    {
        $grammar = DB::connection()->getQueryGrammar();
        $members = $grammar->wrapTable('backtest_nse_index_constituents');
        $indexKey = $grammar->wrap('index');

        // Each subquery uses the (index, symbol) key. Write the day's price rows once.
        $assignments = [];
        foreach ($indices as $index) {
            $column = $grammar->wrap($index->isIndexFieldName());
            $assignments[] = "$column = symbol in (select symbol from $members where $indexKey = ?)";
        }
        $assignments[] = "is_nifty_allcap = case when series in ('EQ', 'BE', 'RR', 'IV') and is_etf = false then true else false end";

        $prices = $grammar->wrapTable('backtest_nse_instrument_prices');
        DB::update("update $prices set ".implode(', ', $assignments).' where '.$grammar->wrap('date').' = ?', [
            ...$indices->pluck('value'), $date,
        ]);
    }
}
