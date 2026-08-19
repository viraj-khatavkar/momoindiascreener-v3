<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class QueueAdminProcessSymbolChangesRequest extends FormRequest
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
            'symbol_changes' => ['required', 'array', 'min:1', 'max:25'],
            'symbol_changes.*.old_symbol' => [
                'bail',
                'required',
                'string',
                'max:50',
                'regex:/\A[A-Z0-9&.\-]+\z/',
                'distinct',
            ],
            'symbol_changes.*.new_symbol' => [
                'bail',
                'required',
                'string',
                'max:50',
                'regex:/\A[A-Z0-9&.\-]+\z/',
                'distinct',
            ],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $symbolChanges = $this->input('symbol_changes');

                if (! is_array($symbolChanges)) {
                    return;
                }

                foreach ($symbolChanges as $index => $symbolChange) {
                    if (! is_array($symbolChange)) {
                        continue;
                    }

                    $oldSymbol = Arr::get($symbolChange, 'old_symbol');
                    $newSymbol = Arr::get($symbolChange, 'new_symbol');

                    if (is_string($oldSymbol) && $oldSymbol !== '' && $oldSymbol === $newSymbol) {
                        $validator->errors()->add(
                            "symbol_changes.{$index}.new_symbol",
                            'The new symbol must be different from the old symbol.',
                        );
                    }
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $symbolChanges = $this->input('symbol_changes');

        if (! is_array($symbolChanges)) {
            return;
        }

        $this->merge([
            'symbol_changes' => collect($symbolChanges)
                ->map(function (mixed $symbolChange): mixed {
                    if (! is_array($symbolChange)) {
                        return $symbolChange;
                    }

                    return [
                        'old_symbol' => Str::of((string) Arr::get($symbolChange, 'old_symbol', ''))
                            ->trim()
                            ->upper()
                            ->toString(),
                        'new_symbol' => Str::of((string) Arr::get($symbolChange, 'new_symbol', ''))
                            ->trim()
                            ->upper()
                            ->toString(),
                    ];
                })
                ->values()
                ->all(),
        ]);
    }
}
