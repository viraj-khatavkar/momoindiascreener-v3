<?php

namespace App\Http\Requests;

use App\Actions\QueryNseIndexConstituentsAction;
use App\NseIndexMembership;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NseIndexConstituentsRequest extends FormRequest
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
            'date' => ['nullable', 'date_format:Y-m-d'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(QueryNseIndexConstituentsAction::SORT_COLUMNS)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function index(): NseIndexMembership
    {
        $slug = (string) $this->route('slug');
        $index = NseIndexMembership::fromSlug($slug);
        abort_unless($index !== null && $index->slug() === $slug, 404);

        return $index;
    }

    /** @return array{date: ?string, search: string, sort: string, direction: string} */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'date' => $validated['date'] ?? null,
            'search' => trim($validated['search'] ?? ''),
            'sort' => $validated['sort'] ?? 'marketcap',
            'direction' => $validated['direction'] ?? 'desc',
        ];
    }
}
