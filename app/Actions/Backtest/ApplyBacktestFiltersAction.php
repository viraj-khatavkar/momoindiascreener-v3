<?php

namespace App\Actions\Backtest;

use App\Enums\ApplyFiltersOnOptionEnum;
use App\Models\Backtest;
use App\Models\BacktestNseInstrumentPrice;
use Illuminate\Database\Eloquent\Builder;

class ApplyBacktestFiltersAction
{
    public function execute(Backtest $backtest, $date)
    {
        $query = BacktestNseInstrumentPrice::query()
            ->where('date', $date)
            ->where($backtest->index->isIndexFieldName(), true)
            ->whereNotNull($backtest->sort_by)
            ->orderBy($backtest->sort_by, $backtest->sort_direction);

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

    protected function applyCombinedRanking(Builder $query, Backtest $backtest)
    {
        if ($backtest->apply_factor_two) {
            $query->whereNotNull($backtest->factor_two_sort_by);
        }

        if ($backtest->apply_factor_three) {
            $query->whereNotNull($backtest->factor_three_sort_by);
        }

        $instruments = $query->get();

        $rank = 0;
        $instruments = $instruments->when($backtest->sort_direction == 'asc', function ($query) use ($backtest) {
            return $query->sortBy($backtest->sort_by);
        })->when($backtest->sort_direction == 'desc', function ($query) use ($backtest) {
            return $query->sortByDesc($backtest->sort_by);
        })->map(function ($instrument) use (&$rank) {
            $instrument->factor_one_rank = $rank = $rank + 1;

            return $instrument;
        });

        $rank = 0;
        if ($backtest->apply_factor_two) {
            $instruments = $instruments->when($backtest->factor_two_sort_direction == 'asc', function ($query) use ($backtest) {
                return $query->sortBy($backtest->factor_two_sort_by);
            })->when($backtest->factor_two_sort_direction == 'desc', function ($query) use ($backtest) {
                return $query->sortByDesc($backtest->factor_two_sort_by);
            })->map(function ($instrument) use (&$rank) {
                $instrument->factor_two_rank = $rank = $rank + 1;

                return $instrument;
            });
        }

        $rank = 0;
        if ($backtest->apply_factor_three) {
            $instruments = $instruments->when($backtest->factor_three_sort_direction == 'asc', function ($query) use ($backtest) {
                return $query->sortBy($backtest->factor_three_sort_by);
            })->when($backtest->factor_three_sort_direction == 'desc', function ($query) use ($backtest) {
                return $query->sortByDesc($backtest->factor_three_sort_by);
            })->map(function ($instrument) use (&$rank) {
                $instrument->factor_three_rank = $rank = $rank + 1;

                return $instrument;
            });
        }

        return $instruments->sortBy(function ($instrument) {
            return $instrument->factor_one_rank + $instrument->factor_two_rank + $instrument->factor_three_rank;
        });
    }

    protected function fetchTopDecileIds(Backtest $backtest, $date): array
    {
        $query = BacktestNseInstrumentPrice::query()
            ->where('date', $date)
            ->whereNotNull($backtest->sort_by)
            ->where($backtest->index->isIndexFieldName(), true);

        return $query->orderBy($backtest->sort_by, $backtest->sort_direction)
            ->select('id')
            ->limit(
                match ($backtest->apply_filters_on) {
                    ApplyFiltersOnOptionEnum::TOP_DECILE => 0.1 * $backtest->index->numberOfConstituents(),
                    ApplyFiltersOnOptionEnum::TOP_TWO_DECILE => 0.2 * $backtest->index->numberOfConstituents(),
                    ApplyFiltersOnOptionEnum::TOP_THREE_DECILE => 0.3 * $backtest->index->numberOfConstituents(),
                    ApplyFiltersOnOptionEnum::TOP_FOUR_DECILE => 0.4 * $backtest->index->numberOfConstituents(),
                    ApplyFiltersOnOptionEnum::TOP_FIVE_DECILE => 0.5 * $backtest->index->numberOfConstituents(),
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

        if (! empty($ids)) {
            $query = $query->whereIn('id', $ids);
        }

        return $query;
    }

    protected function movingAverageFilters(Builder $query, Backtest $backtest)
    {
        return $query->when($backtest->apply_ma, function (Builder $query) use ($backtest) {
            return $query->when($backtest->above_ma_200, function (Builder $query) {
                return $query->whereRaw('close_raw > ma_200');
            })->when($backtest->above_ma_100, function (Builder $query) {
                return $query->whereRaw('close_raw > ma_100');
            })->when($backtest->above_ma_50, function (Builder $query) {
                return $query->whereRaw('close_raw > ma_50');
            })->when($backtest->above_ma_20, function (Builder $query) {
                return $query->whereRaw('close_raw > ma_20');
            })->when($backtest->below_ma_200, function (Builder $query) {
                return $query->whereRaw('close_raw < ma_200');
            })->when($backtest->below_ma_100, function (Builder $query) {
                return $query->whereRaw('close_raw < ma_100');
            })->when($backtest->below_ma_50, function (Builder $query) {
                return $query->whereRaw('close_raw < ma_50');
            })->when($backtest->below_ma_20, function (Builder $query) {
                return $query->whereRaw('close_raw < ma_20');
            });
        });
    }

    protected function exponentialMovingAverageFilters(Builder $query, Backtest $backtest)
    {
        return $query->when($backtest->apply_ema, function (Builder $query) use ($backtest) {
            return $query->when($backtest->above_ema_200, function (Builder $query) {
                return $query->whereRaw('close_raw > ema_200');
            })->when($backtest->above_ema_100, function (Builder $query) {
                return $query->whereRaw('close_raw > ema_100');
            })->when($backtest->above_ema_50, function (Builder $query) {
                return $query->whereRaw('close_raw > ema_50');
            })->when($backtest->above_ema_20, function (Builder $query) {
                return $query->whereRaw('close_raw > ema_20');
            })->when($backtest->below_ema_200, function (Builder $query) {
                return $query->whereRaw('close_raw < ema_200');
            })->when($backtest->below_ema_100, function (Builder $query) {
                return $query->whereRaw('close_raw < ema_100');
            })->when($backtest->below_ema_50, function (Builder $query) {
                return $query->whereRaw('close_raw < ema_50');
            })->when($backtest->below_ema_20, function (Builder $query) {
                return $query->whereRaw('close_raw < ema_20');
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
