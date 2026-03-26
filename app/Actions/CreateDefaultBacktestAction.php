<?php

namespace App\Actions;

use App\Enums\ApplyFiltersOnOptionEnum;
use App\Enums\BacktestCashCallEnum;
use App\Enums\BacktestRebalanceFrequencyEnum;
use App\Enums\BacktestWeightageEnum;
use App\Enums\CustomFilterComparatorOptionEnum;
use App\Enums\CustomFilterValueOptionEnum;
use App\Enums\NseIndexEnum;
use App\Enums\ScreenSortByOptionEnum;
use App\Models\Backtest;
use App\Models\User;

class CreateDefaultBacktestAction
{
    public function execute(string $name, User $user): Backtest
    {
        return $user->backtests()->create([
            'name' => $name,

            // Backtest-specific defaults
            'max_stocks_to_hold' => 30,
            'worst_rank_held' => 100,
            'rebalance_frequency' => BacktestRebalanceFrequencyEnum::Monthly->value,
            'rebalance_day' => 1,
            'weightage' => BacktestWeightageEnum::EqualWeight->value,
            'cash_call' => BacktestCashCallEnum::NoCashCall->value,
            'cash_call_index' => 'nifty-50',
            'cash_return_rate' => 6.00,
            'initial_capital' => 5000000.00,

            // Screen filter defaults
            'index' => NseIndexEnum::NIFTY_ALLCAP->value,
            'sort_by' => ScreenSortByOptionEnum::sharpe_return_one_year->value,
            'sort_direction' => 'desc',
            'median_volume_one_year' => 10000000,
            'minimum_return_one_year' => 6.5,
            'apply_ma' => false,
            'above_ma_200' => false,
            'above_ma_100' => false,
            'above_ma_50' => false,
            'above_ma_20' => false,
            'below_ma_200' => false,
            'below_ma_100' => false,
            'below_ma_50' => false,
            'below_ma_20' => false,
            'apply_ema' => false,
            'above_ema_200' => false,
            'above_ema_100' => false,
            'above_ema_50' => false,
            'above_ema_20' => false,
            'below_ema_200' => false,
            'below_ema_100' => false,
            'below_ema_50' => false,
            'below_ema_20' => false,
            'away_from_high_one_year' => 100,
            'away_from_high_all_time' => 100,
            'positive_days_percent_one_year' => 0,
            'positive_days_percent_nine_months' => 0,
            'positive_days_percent_six_months' => 0,
            'positive_days_percent_three_months' => 0,
            'positive_days_percent_one_months' => 0,
            'circuits_one_year' => 300,
            'circuits_nine_months' => 300,
            'circuits_six_months' => 300,
            'circuits_three_months' => 300,
            'circuits_one_months' => 300,
            'marketcap_from' => 0,
            'marketcap_to' => 100000000,
            'apply_pe' => false,
            'price_to_earnings_from' => 0,
            'price_to_earnings_to' => 10000,
            'series_eq' => true,
            'series_be' => true,
            'ignore_above_beta' => 100,
            'price_from' => 0,
            'price_to' => 10000000,
            'apply_factor_two' => false,
            'factor_two_sort_by' => ScreenSortByOptionEnum::volatility_one_year,
            'factor_two_sort_direction' => 'asc',
            'apply_factor_three' => false,
            'factor_three_sort_by' => ScreenSortByOptionEnum::beta,
            'factor_three_sort_direction' => 'asc',
            'apply_filters_on' => ApplyFiltersOnOptionEnum::ALL->value,
            'apply_custom_filter_one' => false,
            'custom_filter_one_value_one' => CustomFilterValueOptionEnum::ma_50->value,
            'custom_filter_one_operator' => CustomFilterComparatorOptionEnum::GreaterThanEqualTo->value,
            'custom_filter_one_value_two' => CustomFilterValueOptionEnum::ma_200,
            'apply_custom_filter_two' => false,
            'custom_filter_two_value_one' => CustomFilterValueOptionEnum::ma_50->value,
            'custom_filter_two_operator' => CustomFilterComparatorOptionEnum::GreaterThanEqualTo->value,
            'custom_filter_two_value_two' => CustomFilterValueOptionEnum::ma_200,
            'apply_custom_filter_three' => false,
            'custom_filter_three_value_one' => CustomFilterValueOptionEnum::ma_50->value,
            'custom_filter_three_operator' => CustomFilterComparatorOptionEnum::GreaterThanEqualTo->value,
            'custom_filter_three_value_two' => CustomFilterValueOptionEnum::ma_200,
            'apply_custom_filter_four' => false,
            'custom_filter_four_value_one' => CustomFilterValueOptionEnum::ma_50->value,
            'custom_filter_four_operator' => CustomFilterComparatorOptionEnum::GreaterThanEqualTo->value,
            'custom_filter_four_value_two' => CustomFilterValueOptionEnum::ma_200,
            'apply_custom_filter_five' => false,
            'custom_filter_five_value_one' => CustomFilterValueOptionEnum::ma_50->value,
            'custom_filter_five_operator' => CustomFilterComparatorOptionEnum::GreaterThanEqualTo->value,
            'custom_filter_five_value_two' => CustomFilterValueOptionEnum::ma_200,
        ]);
    }
}
