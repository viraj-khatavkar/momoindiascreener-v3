<?php

namespace App\Http\Requests;

use App\Models\ScreenExport;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreScreenExportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->is_paid === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'screen_id' => ['required', 'integer'],
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2010-01-04', 'before_or_equal:today'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before_or_equal:today'],
        ];
    }

    /** @return list<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['start_date', 'end_date'])) {
                return;
            }

            $start = CarbonImmutable::parse($this->string('start_date')->toString());
            $end = CarbonImmutable::parse($this->string('end_date')->toString());
            if ($start->diffInDays($end) >= ScreenExport::MAX_DAYS) {
                $validator->errors()->add('end_date', 'Select no more than 30 calendar days, including the start and end dates.');
            }
        }];
    }
}
