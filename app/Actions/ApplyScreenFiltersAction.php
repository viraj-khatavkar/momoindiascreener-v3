<?php

namespace App\Actions;

use App\Enums\ApplyFiltersOnOptionEnum;
use App\Models\BacktestNseInstrumentPrice;
use App\Models\Screen;
use Illuminate\Database\Eloquent\Builder;

class ApplyScreenFiltersAction
{
    public function execute(Screen $screen, $date, $all = false)
    {
        if ($screen->apply_historical_date) {
            $date = BacktestNseInstrumentPrice::query()
                ->where('is_nifty_allcap', true)
                ->where('date', '<=', $screen->historical_date)
                ->orderBy('date', 'desc')
                ->limit(1)
                ->first()
                ->date;
        }

        $query = BacktestNseInstrumentPrice::query()
            ->where('date', $date)
            ->where($screen->index->isIndexFieldName(), true)
            ->whereNotNull($screen->sort_by)
            ->orderBy($screen->sort_by, $screen->sort_direction);

        if ($screen->apply_filters_on == ApplyFiltersOnOptionEnum::ALL) {
            $query = $this->applyFilters($query, $screen);
        } else {
            $query = $this->applyFilters($query, $screen, $this->fetchTopDecileIds($screen, $date));
        }

        if (! $all) {
            $query->select(
                array_merge(
                    [
                        'id',
                        'symbol',
                        'name',
                        'close_adjusted',
                        'close_raw',
                        'date',
                        $screen->sort_by,
                        $screen->factor_two_sort_by,
                        $screen->factor_three_sort_by,
                    ],
                    $screen->columns
                )
            );
        }

        if ($screen->apply_factor_two || $screen->apply_factor_three) {
            return $this->applyCombinedRanking($query, $screen);
        }

        return $query->get();
    }

    protected function applyCombinedRanking(Builder $query, Screen $screen)
    {
        if ($screen->apply_factor_two) {
            $query->whereNotNull($screen->factor_two_sort_by);
        }

        if ($screen->apply_factor_three) {
            $query->whereNotNull($screen->factor_three_sort_by);
        }

        $instruments = $query->get();

        $rank = 0;
        $instruments = $instruments->when($screen->sort_direction == 'asc', function ($query) use ($screen) {
            return $query->sortBy($screen->sort_by);
        })->when($screen->sort_direction == 'desc', function ($query) use ($screen) {
            return $query->sortByDesc($screen->sort_by);
        })->map(function ($instrument) use (&$rank) {
            $instrument->factor_one_rank = $rank = $rank + 1;

            return $instrument;
        });

        $rank = 0;
        if ($screen->apply_factor_two) {
            $instruments = $instruments->when($screen->factor_two_sort_direction == 'asc', function ($query) use ($screen) {
                return $query->sortBy($screen->factor_two_sort_by);
            })->when($screen->factor_two_sort_direction == 'desc', function ($query) use ($screen) {
                return $query->sortByDesc($screen->factor_two_sort_by);
            })->map(function ($instrument) use (&$rank) {
                $instrument->factor_two_rank = $rank = $rank + 1;

                return $instrument;
            });
        }

        $rank = 0;
        if ($screen->apply_factor_three) {
            $instruments = $instruments->when($screen->factor_three_sort_direction == 'asc', function ($query) use ($screen) {
                return $query->sortBy($screen->factor_three_sort_by);
            })->when($screen->factor_three_sort_direction == 'desc', function ($query) use ($screen) {
                return $query->sortByDesc($screen->factor_three_sort_by);
            })->map(function ($instrument) use (&$rank) {
                $instrument->factor_three_rank = $rank = $rank + 1;

                return $instrument;
            });
        }

        return $instruments->sortBy(function ($instrument) {
            return $instrument->factor_one_rank + $instrument->factor_two_rank + $instrument->factor_three_rank;
        });
    }

    protected function fetchTopDecileIds(Screen $screen, $date): array
    {
        $query = BacktestNseInstrumentPrice::query()
            ->where('date', $date)
            ->whereNotNull($screen->sort_by)
            ->where($screen->index->isIndexFieldName(), true);

        return $query->orderBy($screen->sort_by, $screen->sort_direction)
            ->select('id')
            ->limit(
                match ($screen->apply_filters_on) {
                    ApplyFiltersOnOptionEnum::TOP_DECILE => 0.1 * $screen->index->numberOfConstituents(),
                    ApplyFiltersOnOptionEnum::TOP_TWO_DECILE => 0.2 * $screen->index->numberOfConstituents(),
                    ApplyFiltersOnOptionEnum::TOP_THREE_DECILE => 0.3 * $screen->index->numberOfConstituents(),
                    ApplyFiltersOnOptionEnum::TOP_FOUR_DECILE => 0.4 * $screen->index->numberOfConstituents(),
                    ApplyFiltersOnOptionEnum::TOP_FIVE_DECILE => 0.5 * $screen->index->numberOfConstituents(),
                    ApplyFiltersOnOptionEnum::TOP_50 => 50,
                    ApplyFiltersOnOptionEnum::TOP_100 => 100,
                }
            )
            ->pluck('id')
            ->toArray();
    }

    protected function applyFilters(Builder $query, Screen $screen, $ids = [])
    {
        $query = $this->movingAverageFilters($query, $screen);
        $query = $this->awayFromHighFilters($query, $screen);
        $query = $this->percentagePositiveDaysFilters($query, $screen);
        $query = $this->circuitDaysFilters($query, $screen);
        $query = $this->priceRangeFilters($query, $screen);

        $query = $this->seriesFilter($query, $screen);
        $query = $this->customFilters($query, $screen);

        if ($screen->minimum_return_one_year > -100) {
            $query->where('absolute_return_one_year', '>', $screen->minimum_return_one_year);
        }

        $query->where('median_volume_one_year', '>=', $screen->median_volume_one_year);

        if (! empty($ids)) {
            $query = $query->whereIn('id', $ids);
        }

        return $query;
    }

    protected function movingAverageFilters(Builder $query, Screen $screen)
    {
        return $query->when($screen->apply_ma, function (Builder $query) use ($screen) {
            return $query->when($screen->above_ma_200, function (Builder $query) {
                return $query->whereRaw('close_raw > ma_200');
            })->when($screen->above_ma_100, function (Builder $query) {
                return $query->whereRaw('close_raw > ma_100');
            })->when($screen->above_ma_50, function (Builder $query) {
                return $query->whereRaw('close_raw > ma_50');
            })->when($screen->above_ma_20, function (Builder $query) {
                return $query->whereRaw('close_raw > ma_20');
            })->when($screen->below_ma_200, function (Builder $query) {
                return $query->whereRaw('close_raw < ma_200');
            })->when($screen->below_ma_100, function (Builder $query) {
                return $query->whereRaw('close_raw < ma_100');
            })->when($screen->below_ma_50, function (Builder $query) {
                return $query->whereRaw('close_raw < ma_50');
            })->when($screen->below_ma_20, function (Builder $query) {
                return $query->whereRaw('close_raw < ma_20');
            });
        });
    }

    protected function awayFromHighFilters(Builder $query, Screen $screen)
    {
        return $query->where('away_from_high_one_year', '>', -$screen->away_from_high_one_year)
            ->where('away_from_high_all_time', '>', -$screen->away_from_high_all_time);
    }

    protected function percentagePositiveDaysFilters(Builder $query, Screen $screen)
    {
        return $query->where('positive_days_percent_one_year', '>=', $screen->positive_days_percent_one_year)
            ->where('positive_days_percent_nine_months', '>=', $screen->positive_days_percent_nine_months)
            ->where('positive_days_percent_six_months', '>=', $screen->positive_days_percent_six_months)
            ->where('positive_days_percent_three_months', '>=', $screen->positive_days_percent_three_months)
            ->where('positive_days_percent_one_months', '>=', $screen->positive_days_percent_one_months);
    }

    protected function circuitDaysFilters(Builder $query, Screen $screen)
    {
        return $query->where('circuits_one_year', '<=', $screen->circuits_one_year)
            ->where('circuits_nine_months', '<=', $screen->circuits_nine_months)
            ->where('circuits_six_months', '<=', $screen->circuits_six_months)
            ->where('circuits_three_months', '<=', $screen->circuits_three_months)
            ->where('circuits_one_months', '<=', $screen->circuits_one_months);
    }

    protected function seriesFilter(Builder $query, Screen $screen)
    {
        $series = [];

        if ($screen->series_eq) {
            $series[] = 'EQ';
        }

        if ($screen->series_be) {
            $series[] = 'BE';
        }

        if (empty($series)) {
            return $query;
        }

        return $query->whereIn('series', $series);
    }

    protected function priceRangeFilters(Builder $query, Screen $screen)
    {
        return $query->where('close_raw', '>=', $screen->price_from)
            ->where('close_raw', '<=', $screen->price_to);
    }

    protected function customFilters(Builder $query, Screen $screen)
    {
        return $query->when($screen->apply_custom_filter_one, function (Builder $query) use ($screen) {
            return $query->whereRaw("{$screen->custom_filter_one_value_one} {$screen->custom_filter_one_operator} {$screen->custom_filter_one_value_two}");
        })->when($screen->apply_custom_filter_two, function (Builder $query) use ($screen) {
            return $query->whereRaw("{$screen->custom_filter_two_value_one} {$screen->custom_filter_two_operator} {$screen->custom_filter_two_value_two}");
        })->when($screen->apply_custom_filter_three, function (Builder $query) use ($screen) {
            return $query->whereRaw("{$screen->custom_filter_three_value_one} {$screen->custom_filter_three_operator} {$screen->custom_filter_three_value_two}");
        })->when($screen->apply_custom_filter_four, function (Builder $query) use ($screen) {
            return $query->whereRaw("{$screen->custom_filter_four_value_one} {$screen->custom_filter_four_operator} {$screen->custom_filter_four_value_two}");
        })->when($screen->apply_custom_filter_five, function (Builder $query) use ($screen) {
            return $query->whereRaw("{$screen->custom_filter_five_value_one} {$screen->custom_filter_five_operator} {$screen->custom_filter_five_value_two}");
        });
    }
}
