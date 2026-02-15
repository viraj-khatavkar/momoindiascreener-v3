<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum ScreenSortByOptionEnum: string
{
    case absolute_return_one_year = 'absolute_return_one_year';
    case absolute_return_nine_months = 'absolute_return_nine_months';
    case absolute_return_six_months = 'absolute_return_six_months';
    case absolute_return_three_months = 'absolute_return_three_months';
    case absolute_return_one_months = 'absolute_return_one_months';
    case average_absolute_return_twelve_nine_six_three_one_months = 'average_absolute_return_twelve_nine_six_three_one_months';
    case average_absolute_return_twelve_nine_six_three_months = 'average_absolute_return_twelve_nine_six_three_months';
    case average_absolute_return_twelve_nine_six_months = 'average_absolute_return_twelve_nine_six_months';
    case average_absolute_return_twelve_nine_months = 'average_absolute_return_twelve_nine_months';
    case average_absolute_return_twelve_six_three_one_months = 'average_absolute_return_twelve_six_three_one_months';
    case average_absolute_return_twelve_six_three_months = 'average_absolute_return_twelve_six_three_months';
    case average_absolute_return_twelve_six_months = 'average_absolute_return_twelve_six_months';
    case average_absolute_return_twelve_three_one_months = 'average_absolute_return_twelve_three_one_months';
    case average_absolute_return_twelve_three_months = 'average_absolute_return_twelve_three_months';
    case average_absolute_return_twelve_nine_three_one_months = 'average_absolute_return_twelve_nine_three_one_months';
    case average_absolute_return_twelve_nine_three_months = 'average_absolute_return_twelve_nine_three_months';
    case sharpe_return_one_year = 'sharpe_return_one_year';
    case sharpe_return_nine_months = 'sharpe_return_nine_months';
    case sharpe_return_six_months = 'sharpe_return_six_months';
    case sharpe_return_three_months = 'sharpe_return_three_months';
    case sharpe_return_one_months = 'sharpe_return_one_months';
    case average_sharpe_return_twelve_nine_six_three_one_months = 'average_sharpe_return_twelve_nine_six_three_one_months';
    case average_sharpe_return_twelve_nine_six_three_months = 'average_sharpe_return_twelve_nine_six_three_months';
    case average_sharpe_return_twelve_nine_six_months = 'average_sharpe_return_twelve_nine_six_months';
    case average_sharpe_return_twelve_nine_months = 'average_sharpe_return_twelve_nine_months';
    case average_sharpe_return_twelve_six_three_one_months = 'average_sharpe_return_twelve_six_three_one_months';
    case average_sharpe_return_twelve_six_three_months = 'average_sharpe_return_twelve_six_three_months';
    case average_sharpe_return_twelve_six_months = 'average_sharpe_return_twelve_six_months';
    case average_sharpe_return_twelve_three_one_months = 'average_sharpe_return_twelve_three_one_months';
    case average_sharpe_return_twelve_three_months = 'average_sharpe_return_twelve_three_months';
    case average_sharpe_return_twelve_nine_three_one_months = 'average_sharpe_return_twelve_nine_three_one_months';
    case average_sharpe_return_twelve_nine_three_months = 'average_sharpe_return_twelve_nine_three_months';
    case rsi_one_year = 'rsi_one_year';
    case rsi_nine_months = 'rsi_nine_months';
    case rsi_six_months = 'rsi_six_months';
    case rsi_three_months = 'rsi_three_months';
    case rsi_one_months = 'rsi_one_months';
    case average_rsi_twelve_nine_six_three_one_months = 'average_rsi_twelve_nine_six_three_one_months';
    case average_rsi_twelve_nine_six_three_months = 'average_rsi_twelve_nine_six_three_months';
    case average_rsi_twelve_nine_six_months = 'average_rsi_twelve_nine_six_months';
    case average_rsi_twelve_nine_months = 'average_rsi_twelve_nine_months';
    case average_rsi_twelve_six_three_one_months = 'average_rsi_twelve_six_three_one_months';
    case average_rsi_twelve_six_three_months = 'average_rsi_twelve_six_three_months';
    case average_rsi_twelve_six_months = 'average_rsi_twelve_six_months';
    case average_rsi_twelve_three_one_months = 'average_rsi_twelve_three_one_months';
    case average_rsi_twelve_three_months = 'average_rsi_twelve_three_months';
    case average_rsi_twelve_nine_three_one_months = 'average_rsi_twelve_nine_three_one_months';
    case average_rsi_twelve_nine_three_months = 'average_rsi_twelve_nine_three_months';
    case absolute_divide_beta_return_one_year = 'absolute_divide_beta_return_one_year';
    case sharpe_divide_beta_return_one_year = 'sharpe_divide_beta_return_one_year';
    case average_sharpe_divide_beta_return_twelve_nine_six_three_months = 'average_sharpe_divide_beta_return_twelve_nine_six_three_months';
    case average_sharpe_divide_beta_return_twelve_six_three_months = 'average_sharpe_divide_beta_return_twelve_six_three_months';
    case average_sharpe_divide_beta_return_twelve_six_months = 'average_sharpe_divide_beta_return_twelve_six_months';
    case return_twelve_minus_one_months = 'return_twelve_minus_one_months';
    case return_twelve_minus_two_months = 'return_twelve_minus_two_months';
    case volatility_one_year = 'volatility_one_year';
    case beta = 'beta';
    case price_to_earnings = 'price_to_earnings';
    case marketcap = 'marketcap';
    case close_adjusted = 'close_adjusted';
    case close_raw = 'close_raw';
    case away_from_high_all_time = 'away_from_high_all_time';
    case away_from_high_one_year = 'away_from_high_one_year';

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
