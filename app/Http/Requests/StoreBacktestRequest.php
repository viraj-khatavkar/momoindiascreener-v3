<?php

namespace App\Http\Requests;

use App\Enums\BacktestCashCallEnum;
use App\Enums\BacktestRebalanceFrequencyEnum;
use App\Enums\BacktestWeightageEnum;
use App\Enums\CustomFilterComparatorOptionEnum;
use App\Enums\CustomFilterValueOptionEnum;
use App\Enums\ScreenSortByOptionEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBacktestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Backtest-specific
            'name' => ['required', 'string', 'max:250'],
            'max_stocks_to_hold' => ['required', 'integer', 'min:1', 'max:100'],
            'worst_rank_held' => ['required', 'integer', 'min:1', 'max:500'],
            'rebalance_frequency' => ['required', Rule::enum(BacktestRebalanceFrequencyEnum::class)],
            'rebalance_day' => ['required', 'integer', 'min:1', 'max:28'],
            'weightage' => ['required', Rule::enum(BacktestWeightageEnum::class)],
            'cash_call' => ['required', Rule::enum(BacktestCashCallEnum::class)],
            'cash_call_index' => ['required_if:cash_call,full_cash_below_index_dma,only_exits_below_index_dma,allocate_to_gold_below_index_dma,only_exits_allocate_to_gold_below_index_dma', 'nullable', 'string', Rule::in(['nifty-50'])],
            'cash_return_rate' => ['required', 'numeric', 'between:0,20'],

            // Screen filter fields
            'index' => ['required'],
            'sort_by' => ['required', Rule::enum(ScreenSortByOptionEnum::class)],
            'sort_direction' => ['required', Rule::in(['asc', 'desc'])],
            'median_volume_one_year' => ['required', 'numeric', 'between:0,1000000000'],
            'minimum_return_one_year' => ['required', 'numeric', 'between:-100,100'],
            'apply_ma' => ['required', 'boolean'],
            'above_ma_200' => ['required', 'boolean'],
            'above_ma_100' => ['required', 'boolean'],
            'above_ma_50' => ['required', 'boolean'],
            'above_ma_20' => ['required', 'boolean'],
            'below_ma_200' => ['required', 'boolean'],
            'below_ma_100' => ['required', 'boolean'],
            'below_ma_50' => ['required', 'boolean'],
            'below_ma_20' => ['required', 'boolean'],
            'apply_ema' => ['required', 'boolean'],
            'above_ema_200' => ['required', 'boolean'],
            'above_ema_100' => ['required', 'boolean'],
            'above_ema_50' => ['required', 'boolean'],
            'above_ema_20' => ['required', 'boolean'],
            'below_ema_200' => ['required', 'boolean'],
            'below_ema_100' => ['required', 'boolean'],
            'below_ema_50' => ['required', 'boolean'],
            'below_ema_20' => ['required', 'boolean'],
            'away_from_high_one_year' => ['required', 'numeric', 'between:0,100'],
            'away_from_high_all_time' => ['required', 'numeric', 'between:0,100'],
            'positive_days_percent_one_year' => ['required', 'numeric', 'between:0,100'],
            'positive_days_percent_nine_months' => ['required', 'numeric', 'between:0,100'],
            'positive_days_percent_six_months' => ['required', 'numeric', 'between:0,100'],
            'positive_days_percent_three_months' => ['required', 'numeric', 'between:0,100'],
            'positive_days_percent_one_months' => ['required', 'numeric', 'between:0,100'],
            'circuits_one_year' => ['required', 'numeric', 'between:0,300'],
            'circuits_nine_months' => ['required', 'numeric', 'between:0,300'],
            'circuits_six_months' => ['required', 'numeric', 'between:0,300'],
            'circuits_three_months' => ['required', 'numeric', 'between:0,300'],
            'circuits_one_months' => ['required', 'numeric', 'between:0,300'],
            'marketcap_from' => ['required', 'numeric', 'between:0,100000000'],
            'marketcap_to' => ['required', 'numeric', 'between:0,100000000'],
            'apply_pe' => ['required', 'boolean'],
            'price_to_earnings_from' => ['required', 'numeric', 'between:0,10000'],
            'price_to_earnings_to' => ['required', 'numeric', 'between:0,10000'],
            'series_eq' => ['required', 'boolean'],
            'series_be' => ['required', 'boolean'],
            'price_from' => ['required', 'numeric', 'between:0,10000000'],
            'price_to' => ['required', 'numeric', 'between:0,10000000'],
            'apply_factor_two' => ['required', 'boolean'],
            'apply_factor_three' => ['required', 'boolean'],
            'factor_two_sort_by' => ['required', Rule::enum(ScreenSortByOptionEnum::class)],
            'factor_two_sort_direction' => ['required', Rule::in(['asc', 'desc'])],
            'factor_three_sort_by' => ['required', Rule::enum(ScreenSortByOptionEnum::class)],
            'factor_three_sort_direction' => ['required', Rule::in(['asc', 'desc'])],
            'apply_filters_on' => ['required'],
            'apply_custom_filter_one' => ['required', 'boolean'],
            'custom_filter_one_value_one' => ['required', Rule::enum(CustomFilterValueOptionEnum::class)],
            'custom_filter_one_operator' => ['required', Rule::enum(CustomFilterComparatorOptionEnum::class)],
            'custom_filter_one_value_two' => ['required', Rule::enum(CustomFilterValueOptionEnum::class)],
            'apply_custom_filter_two' => ['required', 'boolean'],
            'custom_filter_two_value_one' => ['required', Rule::enum(CustomFilterValueOptionEnum::class)],
            'custom_filter_two_operator' => ['required', Rule::enum(CustomFilterComparatorOptionEnum::class)],
            'custom_filter_two_value_two' => ['required', Rule::enum(CustomFilterValueOptionEnum::class)],
            'apply_custom_filter_three' => ['required', 'boolean'],
            'custom_filter_three_value_one' => ['required', Rule::enum(CustomFilterValueOptionEnum::class)],
            'custom_filter_three_operator' => ['required', Rule::enum(CustomFilterComparatorOptionEnum::class)],
            'custom_filter_three_value_two' => ['required', Rule::enum(CustomFilterValueOptionEnum::class)],
            'apply_custom_filter_four' => ['required', 'boolean'],
            'custom_filter_four_value_one' => ['required', Rule::enum(CustomFilterValueOptionEnum::class)],
            'custom_filter_four_operator' => ['required', Rule::enum(CustomFilterComparatorOptionEnum::class)],
            'custom_filter_four_value_two' => ['required', Rule::enum(CustomFilterValueOptionEnum::class)],
            'apply_custom_filter_five' => ['required', 'boolean'],
            'custom_filter_five_value_one' => ['required', Rule::enum(CustomFilterValueOptionEnum::class)],
            'custom_filter_five_operator' => ['required', Rule::enum(CustomFilterComparatorOptionEnum::class)],
            'custom_filter_five_value_two' => ['required', Rule::enum(CustomFilterValueOptionEnum::class)],
        ];
    }
}
