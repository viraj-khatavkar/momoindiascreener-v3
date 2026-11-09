<?php

namespace App\Http\Requests;

use App\Models\BacktestNseInstrumentPrice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePortfolioHoldingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('portfolio'));
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'symbol' => ['required', 'string', 'max:50', 'regex:/\A[A-Z0-9][A-Z0-9&._-]*\z/', Rule::exists(BacktestNseInstrumentPrice::class, 'symbol')],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000000'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:1000000000'],
            'date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('symbol'))) {
            $this->merge(['symbol' => $this->string('symbol')->trim()->upper()->toString()]);
        }
    }
}
