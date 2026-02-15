<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum CustomFilterValueOptionEnum: string
{
    case absolute_return_one_year = 'absolute_return_one_year';
    case volatility_one_year = 'volatility_one_year';
    case volatility_nine_months = 'volatility_nine_months';
    case volatility_six_months = 'volatility_six_months';
    case volatility_three_months = 'volatility_three_months';
    case volatility_one_months = 'volatility_one_months';
    case beta = 'beta';
    case close_adjusted = 'close_adjusted';
    case close_raw = 'close_raw';
    case away_from_high_all_time = 'away_from_high_all_time';
    case away_from_high_one_year = 'away_from_high_one_year';
    case ma_200 = 'ma_200';
    case ma_100 = 'ma_100';
    case ma_50 = 'ma_50';
    case ma_20 = 'ma_20';
    case volume_day = 'volume_day';
    case volume_one_year_average = 'volume_one_year_average';
    case volume_nine_months_average = 'volume_nine_months_average';
    case volume_six_months_average = 'volume_six_months_average';
    case volume_three_months_average = 'volume_three_months_average';
    case volume_one_months_average = 'volume_one_months_average';
    case volume_week_average = 'volume_week_average';

    public static function getOptionsForFilters(): array
    {
        return collect(self::cases())
            ->map(function ($record) {
                return [
                    'id' => $record->value,
                    'name' => Str::of($record->value)
                        ->replace('twelve', '12')
                        ->replace('nine', '9')
                        ->replace('six', '6')
                        ->replace('three', '3')
                        ->replace('one', '1')
                        ->replace('_', ' ')
                        ->ucfirst(),
                ];
            })
            ->toArray();
    }
}
