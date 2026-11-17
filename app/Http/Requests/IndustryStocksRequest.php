<?php

namespace App\Http\Requests;

use App\Actions\QueryIndustryStocksAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndustryStocksRequest extends FormRequest
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
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(QueryIndustryStocksAction::SORT_COLUMNS)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array{search: string, sort: string, direction: string} */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'search' => trim($validated['search'] ?? ''),
            'sort' => $validated['sort'] ?? 'marketcap',
            'direction' => $validated['direction'] ?? 'desc',
        ];
    }
}
