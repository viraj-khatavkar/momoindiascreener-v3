<?php

namespace App\Actions\Backtest;

use App\Actions\RankInstrumentsAction;
use App\Enums\ApplyFiltersOnOptionEnum;
use App\Enums\NseIndexEnum;
use App\Models\Backtest;
use App\Models\BacktestNseInstrumentPrice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ApplyBacktestFiltersAction
{
    public function __construct(private RankInstrumentsAction $rankInstruments) {}

    public function execute(Backtest $backtest, $date)
    {
        $query = BacktestNseInstrumentPrice::query()
            ->select($this->requiredColumns($backtest))
            ->where('date', $date)
            ->where($backtest->index->isIndexFieldName(), true)
            ->whereNotNull($backtest->sort_by)
            ->orderBy($backtest->sort_by, $backtest->sort_direction)
            ->orderBy('symbol');

        if ($backtest->apply_filters_on == ApplyFiltersOnOptionEnum::ALL) {
            $query = $this->applyFilters($query, $backtest);
        } else {
            $query = $this->applyFilters($query, $backtest, $this->fetchTopDecileIds($backtest, $date));
        }

        if ($backtest->apply_factor_two || $backtest->apply_factor_three) {
            $results = $this->applyCombinedRanking($query, $backtest);
        } else {
            $results = $query->get();
        }

        $rank = 0;

        return $results->values()->map(function ($instrument) use (&$rank) {
            $instrument->rank = ++$rank;

            return $instrument;
        });
    }

    /**
     * Filters run in SQL; only ranking and execution values need to be hydrated.
     *
     * @return list<string>
     */
    private function requiredColumns(Backtest $backtest): array
    {
        $columns = [
            'id', 'date', 'symbol', 'name', 'series',
            'close_adjusted', 'close_raw', 'volatility_one_year',
            $backtest->sort_by,
        ];

        if ($backtest->apply_factor_two) {
            $columns[] = $backtest->factor_two_sort_by;
        }

        if ($backtest->apply_factor_three) {
            $columns[] = $backtest->factor_three_sort_by;
        }

        return array_values(array_unique($columns));
    }

    protected function applyCombinedRanking(Builder $query, Backtest $backtest): Collection
    {
        if ($backtest->apply_factor_two) {
            $query->whereNotNull($backtest->factor_two_sort_by);
        }

        if ($backtest->apply_factor_three) {
            $query->whereNotNull($backtest->factor_three_sort_by);
        }

        return $this->rankInstruments->execute($query->get(), $backtest);
    }

    protected function fetchTopDecileIds(Backtest $backtest, $date): array
    {
        $constituentCount = $backtest->index->numberOfConstituents()
            ?? BacktestNseInstrumentPrice::query()->where('date', $date)->where('is_nifty_fno', true)->count();
        $query = BacktestNseInstrumentPrice::query()
            ->where('date', $date)
            ->whereNotNull($backtest->sort_by)
            ->where($backtest->index->isIndexFieldName(), true);

        return $query->orderBy($backtest->sort_by, $backtest->sort_direction)
            ->orderBy('symbol')
            ->select('id')
            ->limit(
                match ($backtest->apply_filters_on) {
                    ApplyFiltersOnOptionEnum::TOP_DECILE => 0.1 * $constituentCount,
                    ApplyFiltersOnOptionEnum::TOP_TWO_DECILE => 0.2 * $constituentCount,
                    ApplyFiltersOnOptionEnum::TOP_THREE_DECILE => 0.3 * $constituentCount,
                    ApplyFiltersOnOptionEnum::TOP_FOUR_DECILE => 0.4 * $constituentCount,
                    ApplyFiltersOnOptionEnum::TOP_FIVE_DECILE => 0.5 * $constituentCount,
                    ApplyFiltersOnOptionEnum::TOP_50 => 50,
                    ApplyFiltersOnOptionEnum::TOP_100 => 100,
                }
            )
            ->pluck('id')
            ->toArray();
    }

    protected function applyFilters(Builder $query, Backtest $backtest, $ids = [])
    {
        $query = $this->movingAverageFilters($query, $backtest);
        $query = $this->exponentialMovingAverageFilters($query, $backtest);
        $query = $this->awayFromHighFilters($query, $backtest);
        $query = $this->percentagePositiveDaysFilters($query, $backtest);
        $query = $this->circuitDaysFilters($query, $backtest);
        $query = $this->priceRangeFilters($query, $backtest);

        $query = $this->seriesFilter($query, $backtest);
        $query = $this->ignoreAboveBetaFilter($query, $backtest);
        $query = $this->customFilters($query, $backtest);

        if ($backtest->minimum_return_one_year > -100) {
            $query->where('absolute_return_one_year', '>', $backtest->minimum_return_one_year);
        }

        $query->where('median_volume_one_year', '>=', $backtest->median_volume_one_year);

        if (! empty($ids) || ($backtest->index === NseIndexEnum::NIFTY_FNO && $backtest->apply_filters_on !== ApplyFiltersOnOptionEnum::ALL)) {
            $query = $query->whereIn('id', $ids);
        }

        return $query;
    }

    protected function movingAverageFilters(Builder $query, Backtest $backtest)
    {
        return $query->when($backtest->apply_ma, function (Builder $query) use ($backtest) {
            return $query->when($backtest->above_ma_200, function (Builder $query) {
                return $query->whereColumn('close_adjusted', '>', 'ma_200');
            })->when($backtest->above_ma_100, function (Builder $query) {
                return $query->whereColumn('close_adjusted', '>', 'ma_100');
            })->when($backtest->above_ma_50, function (Builder $query) {
                return $query->whereColumn('close_adjusted', '>', 'ma_50');
            })->when($backtest->above_ma_20, function (Builder $query) {
                return $query->whereColumn('close_adjusted', '>', 'ma_20');
            })->when($backtest->below_ma_200, function (Builder $query) {
                return $query->whereColumn('close_adjusted', '<', 'ma_200');
            })->when($backtest->below_ma_100, function (Builder $query) {
                return $query->whereColumn('close_adjusted', '<', 'ma_100');
            })->when($backtest->below_ma_50, function (Builder $query) {
                return $query->whereColumn('close_adjusted', '<', 'ma_50');
            })->when($backtest->below_ma_20, function (Builder $query) {
                return $query->whereColumn('close_adjusted', '<', 'ma_20');
            });
        });
    }

    protected function exponentialMovingAverageFilters(Builder $query, Backtest $backtest)
    {
        return $query->when($backtest->apply_ema, function (Builder $query) use ($backtest) {
            return $query->when($backtest->above_ema_200, function (Builder $query) {
                return $query->whereColumn('close_adjusted', '>', 'ema_200');
            })->when($backtest->above_ema_100, function (Builder $query) {
                return $query->whereColumn('close_adjusted', '>', 'ema_100');
            })->when($backtest->above_ema_50, function (Builder $query) {
                return $query->whereColumn('close_adjusted', '>', 'ema_50');
            })->when($backtest->above_ema_20, function (Builder $query) {
                return $query->whereColumn('close_adjusted', '>', 'ema_20');
            })->when($backtest->below_ema_200, function (Builder $query) {
                return $query->whereColumn('close_adjusted', '<', 'ema_200');
            })->when($backtest->below_ema_100, function (Builder $query) {
                return $query->whereColumn('close_adjusted', '<', 'ema_100');
            })->when($backtest->below_ema_50, function (Builder $query) {
                return $query->whereColumn('close_adjusted', '<', 'ema_50');
            })->when($backtest->below_ema_20, function (Builder $query) {
                return $query->whereColumn('close_adjusted', '<', 'ema_20');
            });
        });
    }

    protected function awayFromHighFilters(Builder $query, Backtest $backtest)
    {
        return $query->where('away_from_high_one_year', '>', -$backtest->away_from_high_one_year)
            ->where('away_from_high_all_time', '>', -$backtest->away_from_high_all_time);
    }

    protected function percentagePositiveDaysFilters(Builder $query, Backtest $backtest)
    {
        return $query->where('positive_days_percent_one_year', '>=', $backtest->positive_days_percent_one_year)
            ->where('positive_days_percent_nine_months', '>=', $backtest->positive_days_percent_nine_months)
            ->where('positive_days_percent_six_months', '>=', $backtest->positive_days_percent_six_months)
            ->where('positive_days_percent_three_months', '>=', $backtest->positive_days_percent_three_months)
            ->where('positive_days_percent_one_months', '>=', $backtest->positive_days_percent_one_months);
    }

    protected function circuitDaysFilters(Builder $query, Backtest $backtest)
    {
        return $query->where('circuits_one_year', '<=', $backtest->circuits_one_year)
            ->where('circuits_nine_months', '<=', $backtest->circuits_nine_months)
            ->where('circuits_six_months', '<=', $backtest->circuits_six_months)
            ->where('circuits_three_months', '<=', $backtest->circuits_three_months)
            ->where('circuits_one_months', '<=', $backtest->circuits_one_months);
    }

    protected function seriesFilter(Builder $query, Backtest $backtest)
    {
        $series = [];

        if ($backtest->series_eq) {
            $series[] = 'EQ';
        }

        if ($backtest->series_be) {
            $series[] = 'BE';
        }

        if (empty($series)) {
            return $query;
        }

        return $query->whereIn('series', $series);
    }

    protected function priceRangeFilters(Builder $query, Backtest $backtest)
    {
        return $query->where('close_raw', '>=', $backtest->price_from)
            ->where('close_raw', '<=', $backtest->price_to);
    }

    protected function ignoreAboveBetaFilter(Builder $query, Backtest $backtest)
    {
        if ($backtest->ignore_above_beta < 100) {
            return $query->where('beta', '<=', $backtest->ignore_above_beta);
        }

        return $query;
    }

    protected function customFilters(Builder $query, Backtest $backtest)
    {
        return $query->when($backtest->apply_custom_filter_one, function (Builder $query) use ($backtest) {
            return $query->whereRaw("{$backtest->custom_filter_one_value_one} {$backtest->custom_filter_one_operator} {$backtest->custom_filter_one_value_two}");
        })->when($backtest->apply_custom_filter_two, function (Builder $query) use ($backtest) {
            return $query->whereRaw("{$backtest->custom_filter_two_value_one} {$backtest->custom_filter_two_operator} {$backtest->custom_filter_two_value_two}");
        })->when($backtest->apply_custom_filter_three, function (Builder $query) use ($backtest) {
            return $query->whereRaw("{$backtest->custom_filter_three_value_one} {$backtest->custom_filter_three_operator} {$backtest->custom_filter_three_value_two}");
        })->when($backtest->apply_custom_filter_four, function (Builder $query) use ($backtest) {
            return $query->whereRaw("{$backtest->custom_filter_four_value_one} {$backtest->custom_filter_four_operator} {$backtest->custom_filter_four_value_two}");
        })->when($backtest->apply_custom_filter_five, function (Builder $query) use ($backtest) {
            return $query->whereRaw("{$backtest->custom_filter_five_value_one} {$backtest->custom_filter_five_operator} {$backtest->custom_filter_five_value_two}");
        });
    }
}
