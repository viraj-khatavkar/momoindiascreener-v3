<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum ApplyFiltersOnOptionEnum: string
{
    case ALL = 'all';
    case TOP_DECILE = 'top_decile';
    case TOP_TWO_DECILE = 'top_two_decile';
    case TOP_THREE_DECILE = 'top_three_decile';
    case TOP_FOUR_DECILE = 'top_four_decile';
    case TOP_FIVE_DECILE = 'top_five_decile';
    case TOP_50 = 'top_50';
    case TOP_100 = 'top_100';

    public static function getOptionsForFilters(): array
    {
        return collect(self::cases())
            ->map(function ($record) {
                return [
                    'id' => $record->value,
                    'name' => ucfirst(Str::of($record->value)->replace('_', ' ')->ucfirst()).' stocks of selected index',
                ];
            })
            ->toArray();
    }
}
