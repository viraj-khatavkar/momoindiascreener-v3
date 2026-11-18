<?php

namespace App\Actions;

use App\Enums\ApplyFiltersOnOptionEnum;
use App\Enums\NseIndexEnum;
use App\Models\BacktestNseInstrumentPrice;
use App\Models\Screen;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ApplyScreenFiltersAction
{
    private const VALUATION_DATA_START_DATE = '2024-03-01';

    /** @var list<string> */
    private array $valuationFilterWarnings = [];

    public function __construct(
        private RankInstrumentsAction $rankInstruments,
        private SelectUniqueEtfsAction $uniqueEtfs,
    ) {}

    /**
     * @return Collection<int, BacktestNseInstrumentPrice>
     */
    public function execute(Screen $screen, CarbonInterface|string $date, bool $all = false): Collection
    {
        $this->valuationFilterWarnings = [];

        if ($screen->apply_historical_date) {
            $date = BacktestNseInstrumentPrice::query()
                ->where('is_nifty_allcap', true)
                ->where('date', '<=', $screen->historical_date)
                ->orderBy('date', 'desc')
                ->limit(1)
                ->first()
                ->date;
        }

        return $this->filterOnDate($screen, $date, $all);
    }

    /** @return Collection<int, BacktestNseInstrumentPrice> */
    public function executeOnDate(Screen $screen, CarbonInterface|string $date, bool $all = false): Collection
    {
        $datedScreen = clone $screen;
        $datedScreen->apply_historical_date = true;

        return $this->filterOnDate($datedScreen, $date, $all);
    }

    /** @return Collection<int, BacktestNseInstrumentPrice> */
    protected function filterOnDate(Screen $screen, CarbonInterface|string $date, bool $all): Collection
    {
        $this->valuationFilterWarnings = [];

        if ($screen->index === NseIndexEnum::NIFTY_FNO && ! BacktestNseInstrumentPrice::query()->where('date', $date)->where('is_nifty_fno', true)->exists()) {
            $dateLabel = $date instanceof CarbonInterface ? $date->toDateString() : $date;
            $this->valuationFilterWarnings[] = "F&O membership is not available for {$dateLabel}. Import the dated F&O file first.";

            return collect();
        }

        $query = BacktestNseInstrumentPrice::query()
            ->where('date', $date)
            ->where($screen->index->isIndexFieldName(), true)
            ->whereNotNull($screen->sort_by)
            ->orderBy($screen->sort_by, $screen->sort_direction)
            ->orderBy('symbol');

        $query = $this->valuationFilters($query, $screen, $date);

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

            if ($screen->index === NseIndexEnum::NIFTY_ETF && $screen->unique_etf_per_index) {
                $query->addSelect('median_volume_one_year');
            }
        }

        if ($screen->apply_factor_two || $screen->apply_factor_three) {
            return $this->applyCombinedRanking($query, $screen);
        }

        return $this->uniqueEtfsPerIndex($query->get(), $screen);
    }

    /**
     * @param  Collection<int, BacktestNseInstrumentPrice>  $instruments
     * @return Collection<int, BacktestNseInstrumentPrice>
     */
    protected function uniqueEtfsPerIndex(Collection $instruments, Screen $screen): Collection
    {
        if ($screen->index !== NseIndexEnum::NIFTY_ETF || ! $screen->unique_etf_per_index || $instruments->isEmpty()) {
            return $instruments;
        }

        return $this->uniqueEtfs->execute($instruments);
    }

    /**
     * @return list<string>
     */
    public function getValuationFilterWarnings(): array
    {
        return $this->valuationFilterWarnings;
    }

    protected function valuationFilters(Builder $query, Screen $screen, CarbonInterface|string $date): Builder
    {
        if ($screen->index === NseIndexEnum::NIFTY_ETF) {
            $this->valuationFilterWarnings[] = 'Market cap data is not available for ETFs. The market cap filter was not applied.';

            if ($screen->apply_pe) {
                $this->valuationFilterWarnings[] = 'P/E data is not available for ETFs. The P/E filter was not applied.';
            }

            return $query;
        }

        $date = $date instanceof CarbonInterface ? $date->toDateString() : $date;
        $hasMarketcap = true;
        $hasPriceToEarnings = true;

        if ($screen->apply_historical_date) {
            $availability = $date >= self::VALUATION_DATA_START_DATE
                ? BacktestNseInstrumentPrice::query()
                    ->where('date', $date)
                    ->selectRaw('COUNT(marketcap) AS marketcap_count, COUNT(price_to_earnings) AS price_to_earnings_count')
                    ->toBase()
                    ->first()
                : null;

            $hasMarketcap = ($availability?->marketcap_count ?? 0) > 0;
            $hasPriceToEarnings = ($availability?->price_to_earnings_count ?? 0) > 0;
        }

        if ($hasMarketcap) {
            $query->whereBetween('marketcap', [$screen->marketcap_from, $screen->marketcap_to]);
        } else {
            $this->valuationFilterWarnings[] = "Market cap data is not available for {$date}. The market cap filter was not applied.";
        }

        if ($screen->apply_pe) {
            if ($hasPriceToEarnings) {
                $query->whereBetween('price_to_earnings', [$screen->price_to_earnings_from, $screen->price_to_earnings_to]);
            } else {
                $this->valuationFilterWarnings[] = "P/E data is not available for {$date}. The P/E filter was not applied.";
            }
        }

        return $query;
    }

    protected function applyCombinedRanking(Builder $query, Screen $screen): Collection
    {
        if ($screen->apply_factor_two) {
            $query->whereNotNull($screen->factor_two_sort_by);
        }

        if ($screen->apply_factor_three) {
            $query->whereNotNull($screen->factor_three_sort_by);
        }

        $instruments = $this->uniqueEtfsPerIndex($query->get(), $screen);

        return $this->rankInstruments->execute($instruments, $screen);
    }

    protected function fetchTopDecileIds(Screen $screen, $date): array
    {
        $constituentCount = $screen->index->numberOfConstituents()
            ?? BacktestNseInstrumentPrice::query()->where('date', $date)->where('is_nifty_fno', true)->count();
        $query = BacktestNseInstrumentPrice::query()
            ->where('date', $date)
            ->whereNotNull($screen->sort_by)
            ->where($screen->index->isIndexFieldName(), true);

        return $query->orderBy($screen->sort_by, $screen->sort_direction)
            ->orderBy('symbol')
            ->select('id')
            ->limit(
                match ($screen->apply_filters_on) {
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

    protected function applyFilters(Builder $query, Screen $screen, $ids = [])
    {
        $query = $this->movingAverageFilters($query, $screen);
        $query = $this->exponentialMovingAverageFilters($query, $screen);
        $query = $this->awayFromHighFilters($query, $screen);
        $query = $this->percentagePositiveDaysFilters($query, $screen);
        $query = $this->circuitDaysFilters($query, $screen);
        $query = $this->priceRangeFilters($query, $screen);

        $query = $this->seriesFilter($query, $screen);
        $query = $this->ignoreAboveBetaFilter($query, $screen);
        $query = $this->customFilters($query, $screen);

        if ($screen->minimum_return_one_year > -100) {
            $query->where('absolute_return_one_year', '>', $screen->minimum_return_one_year);
        }

        $query->where('median_volume_one_year', '>=', $screen->median_volume_one_year);

        if (! empty($ids) || ($screen->index === NseIndexEnum::NIFTY_FNO && $screen->apply_filters_on !== ApplyFiltersOnOptionEnum::ALL)) {
            $query = $query->whereIn('id', $ids);
        }

        return $query;
    }

    protected function movingAverageFilters(Builder $query, Screen $screen): Builder
    {
        return $query->when($screen->apply_ma, function (Builder $query) use ($screen) {
            return $query->when($screen->above_ma_200, function (Builder $query) {
                return $query->whereColumn('close_adjusted', '>', 'ma_200');
            })->when($screen->above_ma_100, function (Builder $query) {
                return $query->whereColumn('close_adjusted', '>', 'ma_100');
            })->when($screen->above_ma_50, function (Builder $query) {
                return $query->whereColumn('close_adjusted', '>', 'ma_50');
            })->when($screen->above_ma_20, function (Builder $query) {
                return $query->whereColumn('close_adjusted', '>', 'ma_20');
            })->when($screen->below_ma_200, function (Builder $query) {
                return $query->whereColumn('close_adjusted', '<', 'ma_200');
            })->when($screen->below_ma_100, function (Builder $query) {
                return $query->whereColumn('close_adjusted', '<', 'ma_100');
            })->when($screen->below_ma_50, function (Builder $query) {
                return $query->whereColumn('close_adjusted', '<', 'ma_50');
            })->when($screen->below_ma_20, function (Builder $query) {
                return $query->whereColumn('close_adjusted', '<', 'ma_20');
            });
        });
    }

    protected function exponentialMovingAverageFilters(Builder $query, Screen $screen): Builder
    {
        return $query->when($screen->apply_ema, function (Builder $query) use ($screen) {
            return $query->when($screen->above_ema_200, function (Builder $query) {
                return $query->whereColumn('close_adjusted', '>', 'ema_200');
            })->when($screen->above_ema_100, function (Builder $query) {
                return $query->whereColumn('close_adjusted', '>', 'ema_100');
            })->when($screen->above_ema_50, function (Builder $query) {
                return $query->whereColumn('close_adjusted', '>', 'ema_50');
            })->when($screen->above_ema_20, function (Builder $query) {
                return $query->whereColumn('close_adjusted', '>', 'ema_20');
            })->when($screen->below_ema_200, function (Builder $query) {
                return $query->whereColumn('close_adjusted', '<', 'ema_200');
            })->when($screen->below_ema_100, function (Builder $query) {
                return $query->whereColumn('close_adjusted', '<', 'ema_100');
            })->when($screen->below_ema_50, function (Builder $query) {
                return $query->whereColumn('close_adjusted', '<', 'ema_50');
            })->when($screen->below_ema_20, function (Builder $query) {
                return $query->whereColumn('close_adjusted', '<', 'ema_20');
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

    protected function seriesFilter(Builder $query, Screen $screen): Builder
    {
        $series = [];

        if ($screen->series_eq) {
            $series[] = 'EQ';
        }

        if ($screen->series_be) {
            $series[] = 'BE';
        }

        return $query->whereIn('series', $series);
    }

    protected function priceRangeFilters(Builder $query, Screen $screen)
    {
        return $query->where('close_raw', '>=', $screen->price_from)
            ->where('close_raw', '<=', $screen->price_to);
    }

    protected function ignoreAboveBetaFilter(Builder $query, Screen $screen)
    {
        if ($screen->ignore_above_beta < 100) {
            return $query->where('beta', '<=', $screen->ignore_above_beta);
        }

        return $query;
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
