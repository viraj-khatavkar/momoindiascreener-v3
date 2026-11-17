<?php

namespace App\Http\Requests;

use App\Actions\Backtest\LoadBacktestTradeLogAction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ShowBacktestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $ability = $this->routeIs('public-backtests.show') ? 'viewPublic' : 'view';

        abort_unless(Gate::allows($ability, $this->route('backtest')), 404);

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'trade_search' => ['nullable', 'string', 'max:100'],
            'trade_type' => ['nullable', Rule::in(['all', 'buy', 'sell'])],
            'trade_reason' => ['nullable', Rule::in(LoadBacktestTradeLogAction::REASON_CATEGORIES)],
            'trade_sort' => ['nullable', Rule::in(['asc', 'desc'])],
            'trade_year' => ['nullable', 'integer', 'between:1900,9999'],
            'trade_symbol' => ['nullable', 'required_with:trade_from,trade_through', 'string', 'max:100'],
            'trade_from' => ['nullable', 'required_with:trade_symbol,trade_through', 'date_format:Y-m-d'],
            'trade_through' => ['nullable', 'required_with:trade_symbol,trade_from', 'date_format:Y-m-d', 'after_or_equal:trade_from'],
            'trades_page' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
        ];
    }

    /**
     * @return array{search: string, type: string, reason: ?string, sort: string, year: ?int, symbol: ?string, from: ?string, through: ?string}
     */
    public function filters(): array
    {
        $data = $this->validated();

        return [
            'search' => $data['trade_search'] ?? '',
            'type' => $data['trade_type'] ?? 'all',
            'reason' => $data['trade_reason'] ?? null,
            'sort' => $data['trade_sort'] ?? 'desc',
            'year' => isset($data['trade_year']) ? (int) $data['trade_year'] : null,
            'symbol' => $data['trade_symbol'] ?? null,
            'from' => $data['trade_from'] ?? null,
            'through' => $data['trade_through'] ?? null,
        ];
    }
}
