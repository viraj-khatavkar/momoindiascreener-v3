<?php

namespace App\Http\Requests;

use App\Actions\NormalizeMarketIndexAction;
use App\Actions\ResolveMarketIndexAliasAction;
use App\Models\MarketIndex;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMarketIndexAliasRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['link', 'create', 'ignore'])],
            'market_index_id' => [
                Rule::excludeIf($this->input('action') !== 'link'),
                'required',
                'integer',
                Rule::exists(MarketIndex::class, 'id')->where('is_active', true),
            ],
            'name' => [
                Rule::excludeIf($this->input('action') !== 'create'),
                'required',
                'string',
                'max:255',
                Rule::unique(MarketIndex::class, 'name'),
            ],
            'slug' => [
                Rule::excludeIf($this->input('action') !== 'create'),
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique(MarketIndex::class, 'slug'),
            ],
            'provider' => [
                Rule::excludeIf($this->input('action') !== 'create'),
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    /** @return array<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('action') !== 'create' || $validator->errors()->isNotEmpty()) {
                return;
            }

            $resolver = new ResolveMarketIndexAliasAction(new NormalizeMarketIndexAction);
            $existing = $resolver->findMarketIndex((string) $this->input('slug'))
                ?? $resolver->findMarketIndex((string) $this->input('name'));

            if ($existing) {
                $validator->errors()->add('slug', 'This underlying already exists as '.$existing->slug.'. Link to that index.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('action') !== 'create') {
            return;
        }

        $normalizer = new NormalizeMarketIndexAction;
        $slug = $normalizer->execute((string) $this->input('slug'));

        $this->merge([
            'name' => $normalizer->name($normalizer->execute((string) $this->input('name'))),
            'slug' => $normalizer->isGeneric($slug) ? '' : $slug,
            'provider' => filled($this->input('provider'))
                ? str((string) $this->input('provider'))->squish()->toString()
                : null,
        ]);
    }
}
