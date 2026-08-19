<?php

namespace App\Http\Requests;

use App\Actions\AdminProcess\BuildDailyProcessStepsAction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Validator;

class StoreAdminProcessRunRequest extends FormRequest
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
            'date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('date')) {
                    return;
                }

                $date = (string) $this->input('date');
                $missingFiles = collect(app(BuildDailyProcessStepsAction::class)->requiredFiles())
                    ->reject(fn (array $file): bool => Storage::disk('local')->exists(
                        "uploads/{$date}/{$file['key']}.csv",
                    ))
                    ->pluck('name');

                if ($missingFiles->isNotEmpty()) {
                    $validator->errors()->add(
                        'files',
                        'Upload these files before you create the run: '.$missingFiles->join(', ').'.',
                    );
                }
            },
        ];
    }
}
