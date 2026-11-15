<?php

namespace App\Console\Commands\Backtest;

use App\Models\BacktestNseInstrumentPrice;
use App\Models\MarketHeartbeat;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use stdClass;

class CalculateMarketHeartbeatCommand extends Command
{
    protected $signature = 'backtest:calculate-market-heartbeat {--date=} {--index= : Calculate only the selected index}';

    protected $description = 'Calculate market breadth from the stored index membership and available metrics for a date';

    public function handle(): int
    {
        $date = $this->option('date');

        if ($date === null) {
            $this->error('Please provide a date');

            return self::FAILURE;
        }

        if (Validator::make(['date' => $date], ['date' => ['required', 'date_format:Y-m-d']])->fails()) {
            $this->error('Please provide a valid date in YYYY-MM-DD format.');

            return self::FAILURE;
        }

        $indices = [
            'nifty-50',
            'nifty-next-50',
            'nifty-100',
            'nifty-200',
            'nifty-midcap-100',
            'nifty-midcap-150',
            'nifty-500',
            'nifty-total-market',
            'nifty-smallcap-250',
            'nifty-microcap-250',
            'nifty-largemidcap-250',
            'nifty-midsmallcap-400',
            'nifty-allcap',
            'nifty-fno',
        ];
        if ($this->option('index') !== null) {
            if (! in_array($this->option('index'), $indices, true)) {
                $this->error('Unknown market heartbeat index.');

                return self::FAILURE;
            }
            $indices = [$this->option('index')];
        }
        $membershipColumns = array_map(fn (string $index): string => 'is_'.Str::replace('-', '_', $index), $indices);

        $prices = BacktestNseInstrumentPrice::query()
            ->where('date', $date)
            ->toBase()
            ->get([
                ...$membershipColumns,
                'ma_200', 'ma_100', 'ma_50', 'ma_20', 'away_from_high_all_time',
                'close_adjusted', 'absolute_return_one_year', 't_percent',
            ]);

        foreach ($indices as $position => $index) {
            $members = $prices->where($membershipColumns[$position], true);

            if ($members->isEmpty()) {
                continue;
            }

            $values = [];
            foreach ([200, 100, 50, 20] as $period) {
                $column = 'ma_'.$period;
                $values['percentage_above_'.$column] = $this->percentageMatching(
                    $members, $column, fn (stdClass $price): bool => $price->close_adjusted > $price->{$column},
                );
            }
            foreach (['zero' => 0, 'ten' => 10, 'hundred' => 100] as $label => $threshold) {
                $values['percentage_of_stocks_with_returns_one_year_above_'.$label] = $this->percentageMatching(
                    $members, 'absolute_return_one_year', fn (stdClass $price): bool => $price->absolute_return_one_year > $threshold,
                );
            }
            foreach (['ten' => -10, 'twenty' => -20, 'thirty' => -30] as $label => $threshold) {
                $values['percentage_of_stocks_within_'.$label.'_percent_of_ath'] = $this->percentageMatching(
                    $members, 'away_from_high_all_time', fn (stdClass $price): bool => $price->away_from_high_all_time > $threshold,
                );
            }

            $advances = $members->where('t_percent', '>', 0)->count();
            $declines = $members->where('t_percent', '<', 0)->count();

            MarketHeartbeat::updateOrInsert(['index' => $index, 'date' => $date], [
                ...$values,
                'advances' => $advances,
                'declines' => $declines,
                'advance_decline_ratio' => $advances / ($declines > 0 ? $declines : 1),
            ]);
        }

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, stdClass>  $members
     * @param  Closure(stdClass): bool  $matches
     */
    private function percentageMatching(Collection $members, string $column, Closure $matches): ?float
    {
        $available = $members->whereNotNull($column);

        return $available->isEmpty() ? null : $available->filter($matches)->count() / $available->count() * 100;
    }
}
