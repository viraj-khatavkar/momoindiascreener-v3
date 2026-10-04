<?php

namespace App\Http\Requests;

use App\Actions\Backtest\LoadBacktestTradeLogAction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShowBacktestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        abort_unless($this->user()->can('view', $this->route('backtest')), 404);

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
            'trades_page' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
        ];
    }

    /**
     * @return array{search: string, type: string, reason: ?string, sort: string, year: ?int}
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
        ];
    }
}
