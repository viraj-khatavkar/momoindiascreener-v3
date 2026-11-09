<?php

namespace App\Http\Requests;

use App\Actions\ReadPortfolioCsvAction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class AnalyzeRebalancePortfolioRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
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
            'screen_id' => ['required', 'integer', 'exists:screens,id'],
            'symbols' => ['required', 'array', 'list', 'min:1', 'max:'.ReadPortfolioCsvAction::MAX_SYMBOLS],
            'symbols.*' => ['required', 'string', 'max:50', 'regex:'.ReadPortfolioCsvAction::SYMBOL_PATTERN],
            'worst_rank_held' => ['required', 'integer', 'min:1', 'max:10000'],
            'max_buy_recommendations' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_array($this->input('symbols'))) {
            $this->merge([
                'symbols' => array_map(
                    fn (mixed $symbol): mixed => is_string($symbol) ? Str::upper(trim($symbol)) : $symbol,
                    $this->input('symbols'),
                ),
            ]);
        }
    }
}
