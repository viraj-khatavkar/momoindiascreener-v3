<?php

namespace App\Actions\Backtest;

use App\Models\Backtest;
use Illuminate\Validation\ValidationException;

class BacktestSortDateAction
{
    public const DEFAULT_START_DATE = '2011-01-05';

    public const SORT_START_DATES = [
        'marketcap' => '2024-03-01',
        'price_to_earnings' => '2024-03-01',
    ];

    public const VALIDATION_MESSAGE = 'The selected ranking factors require a start date on or after :date.';

    /** @param array<string, mixed> $settings */
    public function minimumDate(array $settings): string
    {
        $fields = ['sort_by'];
        if (! empty($settings['apply_factor_two'])) {
            $fields[] = 'factor_two_sort_by';
        }
        if (! empty($settings['apply_factor_three'])) {
            $fields[] = 'factor_three_sort_by';
        }

        $minimumDate = self::DEFAULT_START_DATE;
        foreach ($fields as $field) {
            $sort = $settings[$field] ?? null;
            if (is_string($sort)) {
                $minimumDate = max($minimumDate, self::SORT_START_DATES[$sort] ?? self::DEFAULT_START_DATE);
            }
        }

        return $minimumDate;
    }

    public function validate(Backtest $backtest): void
    {
        $minimumDate = $this->minimumDate($backtest->getAttributes());
        $startDate = $backtest->start_date?->toDateString() ?? self::DEFAULT_START_DATE;
        if ($startDate < $minimumDate) {
            throw ValidationException::withMessages([
                'start_date' => str_replace(':date', $minimumDate, self::VALIDATION_MESSAGE),
            ]);
        }
    }
}
