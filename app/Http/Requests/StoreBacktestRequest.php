<?php

namespace App\Http\Requests;

use App\Actions\Backtest\BacktestStartDateAction;
use App\Enums\ApplyFiltersOnOptionEnum;
use App\Enums\BacktestCashCallEnum;
use App\Enums\BacktestCashCallSourceEnum;
use App\Enums\BacktestRebalanceFrequencyEnum;
use App\Enums\BacktestStopLossProceedsEnum;
use App\Enums\BacktestWeightageEnum;
use App\Enums\CustomFilterComparatorOptionEnum;
use App\Enums\CustomFilterValueOptionEnum;
use App\Enums\NseIndexEnum;
use App\Enums\ScreenSortByOptionEnum;
use App\Models\Backtest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBacktestRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $cashCallValue = $this->input('cash_call');
        $cashCall = is_string($cashCallValue) ? BacktestCashCallEnum::tryFrom($cashCallValue) : null;
        $source = $cashCall?->usesIndexDma()
            ? $this->input('cash_call_source', BacktestCashCallSourceEnum::Index->value)
            : BacktestCashCallSourceEnum::Index->value;

        $this->merge([
            'apply_marketcap' => $this->input('apply_marketcap', $this->route('backtest')?->apply_marketcap ?? false),
            'cash_call_source' => $source,
            'cash_call_backtest_id' => $source === BacktestCashCallSourceEnum::Backtest->value
                ? $this->input('cash_call_backtest_id') : null,
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cash_call_backtest_id.required_if' => 'Select a source backtest.',
            'cash_call_backtest_id.exists' => 'Select another backtest that you own with No cash call and no source backtest.',
            'start_date.after_or_equal' => BacktestStartDateAction::VALIDATION_MESSAGE,
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (app(BacktestStartDateAction::class)->unavailableSettingsErrors($this->all()) as $field => $message) {
                $validator->errors()->add($field, $message);
            }
        }];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $frequency = $this->input('rebalance_frequency');
        $monthInterval = is_string($frequency) ? BacktestRebalanceFrequencyEnum::tryFrom($frequency)?->monthInterval() : null;
        $minimumStartDate = app(BacktestStartDateAction::class)->minimumDate($this->only([
            'index', 'apply_marketcap', 'apply_pe', 'sort_by', 'apply_factor_two', 'factor_two_sort_by', 'apply_factor_three', 'factor_three_sort_by',
        ]));

        return [
            // Backtest-specific
            'name' => ['required', 'string', 'max:250'],
            'max_stocks_to_hold' => ['required', 'integer', 'min:1', 'max:100'],
            'worst_rank_held' => ['required', 'integer', 'min:1', 'max:500'],
            'apply_hold_above_dma' => ['required', 'boolean'],
            'hold_above_dma_period' => ['required_if:apply_hold_above_dma,true', 'nullable', 'integer', 'in:20,50,100,200'],
            'execute_next_trading_day' => ['required', 'boolean'],
            'skip_circuit_trades' => ['required', 'boolean'],
            'exit_before_demerger' => ['required', 'boolean'],
            'exit_on_be_series' => ['required', 'boolean'],
            'apply_stop_loss' => ['required', 'boolean'],
            'stop_loss_percentage' => ['exclude_unless:apply_stop_loss,true', 'required', 'numeric', 'between:0.01,99.99', 'decimal:0,2'],
            'trail_stop_loss' => ['exclude_unless:apply_stop_loss,true', 'required', 'boolean'],
            'stop_loss_proceeds' => ['exclude_unless:apply_stop_loss,true', 'required', Rule::enum(BacktestStopLossProceedsEnum::class)],
            'rebalance_frequency' => ['required', Rule::enum(BacktestRebalanceFrequencyEnum::class)],
            'rebalance_day' => ['required', 'integer', 'min:1', $monthInterval === null ? 'max:5' : 'max:28'],
            'rebalance_month' => [Rule::excludeIf($monthInterval <= 1), 'required', 'integer', 'between:1,12'],
            'weightage' => ['required', Rule::enum(BacktestWeightageEnum::class)],
            'cash_call' => ['required', Rule::enum(BacktestCashCallEnum::class)],
            'cash_call_source' => ['required', Rule::enum(BacktestCashCallSourceEnum::class)],
            'cash_call_backtest_id' => [
                'nullable', 'required_if:cash_call_source,backtest', 'integer',
                Rule::exists(Backtest::class, 'id')->where(fn ($query) => $query
                    ->where('user_id', $this->user()->id)
                    ->where('id', '!=', $this->route('backtest')?->getKey() ?? 0)
                    ->where('cash_call', BacktestCashCallEnum::NoCashCall->value)
                    ->where('cash_call_source', BacktestCashCallSourceEnum::Index->value)
                    ->whereNull('cash_call_backtest_id')),
            ],
            'cash_call_index' => ['exclude_if:cash_call,no_cash_call,cash_call_if_not_enough_stocks', 'exclude_if:cash_call_source,backtest', 'required', 'string', Rule::in(['nifty-50', 'nifty-100', 'nifty-500', 'nifty200-momentum-30', 'nifty500-momentum-50'])],
            'cash_call_dma_period' => ['exclude_if:cash_call,no_cash_call,cash_call_if_not_enough_stocks', 'required', 'integer', 'in:20,50,100,200'],
            'cash_call_gold_dma_period' => ['exclude_unless:cash_call,only_exits_allocate_to_gold_above_dma_below_index_dma', 'required', 'integer', 'in:20,50,100,200'],
            'start_date' => ['required', 'date', 'after_or_equal:'.$minimumStartDate],
            'cash_return_rate' => ['required', 'numeric', 'between:0,20'],
            'brokerage_rate' => ['required', 'numeric', 'between:0,5'],
            'stt_rate' => ['required', 'numeric', 'between:0,5'],
            'transaction_charges_rate' => ['required', 'numeric', 'between:0,5'],
            'sebi_charges_rate' => ['required', 'numeric', 'between:0,5'],
            'gst_rate' => ['required', 'numeric', 'between:0,100'],
            'stamp_charges_rate' => ['required', 'numeric', 'between:0,5'],

            // Screen filter fields
            'index' => ['required', Rule::enum(NseIndexEnum::class)],
            'unique_etf_per_index' => ['sometimes', 'boolean'],
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
            'apply_marketcap' => ['sometimes', 'boolean'],
            'marketcap_from' => ['required', 'numeric', 'between:0,100000000'],
            'marketcap_to' => ['required', 'numeric', 'between:0,100000000', Rule::when($this->boolean('apply_marketcap'), 'gte:marketcap_from')],
            'apply_pe' => ['required', 'boolean'],
            'price_to_earnings_from' => ['required', 'numeric', 'between:0,10000'],
            'price_to_earnings_to' => ['required', 'numeric', 'between:0,10000', Rule::when($this->boolean('apply_pe'), 'gte:price_to_earnings_from')],
            'series_eq' => ['required', 'boolean'],
            'series_be' => ['required', 'boolean'],
            'ignore_above_beta' => ['required', 'numeric', 'between:0,100'],
            'price_from' => ['required', 'numeric', 'between:0,10000000'],
            'price_to' => ['required', 'numeric', 'between:0,10000000'],
            'apply_factor_two' => ['required', 'boolean'],
            'apply_factor_three' => ['required', 'boolean'],
            'factor_two_sort_by' => ['required', Rule::enum(ScreenSortByOptionEnum::class)],
            'factor_two_sort_direction' => ['required', Rule::in(['asc', 'desc'])],
            'factor_three_sort_by' => ['required', Rule::enum(ScreenSortByOptionEnum::class)],
            'factor_three_sort_direction' => ['required', Rule::in(['asc', 'desc'])],
            'apply_filters_on' => ['required', Rule::enum(ApplyFiltersOnOptionEnum::class)],
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
