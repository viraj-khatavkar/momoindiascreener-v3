<?php

namespace App\Enums;

use App\Contracts\ResolveDisplayableValueListForEnum;
use App\Traits\ResolveDisplayableValueListForEnumTrait;

enum ScreenResultColumnEnum: string implements ResolveDisplayableValueListForEnum
{
    use ResolveDisplayableValueListForEnumTrait;

    case SERIES = 'series';
    case MARKETCAP = 'marketcap';
    case PRICE_TO_EARNINGS = 'price_to_earnings';
    case ABSOLUTE_RETURN_ONE_YEAR = 'absolute_return_one_year';
    case ABSOLUTE_RETURN_NINE_MONTHS = 'absolute_return_nine_months';
    case ABSOLUTE_RETURN_SIX_MONTHS = 'absolute_return_six_months';
    case ABSOLUTE_RETURN_THREE_MONTHS = 'absolute_return_three_months';
    case ABSOLUTE_RETURN_ONE_MONTHS = 'absolute_return_one_months';
    case SHARPE_RETURN_ONE_YEAR = 'sharpe_return_one_year';
    case SHARPE_RETURN_NINE_MONTHS = 'sharpe_return_nine_months';
    case SHARPE_RETURN_SIX_MONTHS = 'sharpe_return_six_months';
    case SHARPE_RETURN_THREE_MONTHS = 'sharpe_return_three_months';
    case SHARPE_RETURN_ONE_MONTHS = 'sharpe_return_one_months';
    case RSI_ONE_YEAR = 'rsi_one_year';
    case RSI_NINE_MONTHS = 'rsi_nine_months';
    case RSI_SIX_MONTHS = 'rsi_six_months';
    case RSI_THREE_MONTHS = 'rsi_three_months';
    case RSI_ONE_MONTHS = 'rsi_one_months';
    case BETA = 'beta';
    case VOLATILITY_ONE_YEAR = 'volatility_one_year';
    case HIGH_ONE_YEAR = 'high_one_year';
    case HIGH_ALL_TIME = 'high_all_time';
    case AWAY_FROM_HIGH_ONE_YEAR = 'away_from_high_one_year';
    case AWAY_FROM_HIGH_ALL_TIME = 'away_from_high_all_time';
    case MA_200 = 'ma_200';
    case MA_100 = 'ma_100';
    case MA_50 = 'ma_50';
    case MA_20 = 'ma_20';
    case MEDIAN_VOLUME_ONE_YEAR = 'median_volume_one_year';
    case CLOSE_ADJUSTED = 'close_adjusted';
    case CLOSE_RAW = 'close_raw';
    case CIRCUITS_ONE_YEAR = 'circuits_one_year';
    case CIRCUITS_NINE_MONTHS = 'circuits_nine_months';
    case CIRCUITS_SIX_MONTHS = 'circuits_six_months';
    case CIRCUITS_THREE_MONTHS = 'circuits_three_months';
    case CIRCUITS_ONE_MONTHS = 'circuits_one_months';

    public function getDisplayName(): string
    {
        return match ($this) {
            self::SERIES => 'Series',
            self::MARKETCAP => 'Marketcap (in crores)',
            self::PRICE_TO_EARNINGS => 'Price to Earnings',
            self::ABSOLUTE_RETURN_ONE_YEAR => '1Yr Return %',
            self::ABSOLUTE_RETURN_NINE_MONTHS => '9M Return %',
            self::ABSOLUTE_RETURN_SIX_MONTHS => '6M Return %',
            self::ABSOLUTE_RETURN_THREE_MONTHS => '3M Return %',
            self::ABSOLUTE_RETURN_ONE_MONTHS => '1M Return %',
            self::SHARPE_RETURN_ONE_YEAR => '1Yr Sharpe Return %',
            self::SHARPE_RETURN_NINE_MONTHS => '9M Sharpe Return %',
            self::SHARPE_RETURN_SIX_MONTHS => '6M Sharpe Return %',
            self::SHARPE_RETURN_THREE_MONTHS => '3M Sharpe Return %',
            self::SHARPE_RETURN_ONE_MONTHS => '1M Sharpe Return %',
            self::RSI_ONE_YEAR => '1Yr RSI',
            self::RSI_NINE_MONTHS => '9M RSI',
            self::RSI_SIX_MONTHS => '6M RSI',
            self::RSI_THREE_MONTHS => '3M RSI',
            self::RSI_ONE_MONTHS => '1M RSI',
            self::BETA => 'Beta',
            self::VOLATILITY_ONE_YEAR => '1Yr Volatility',
            self::MA_200 => 'MA 200',
            self::MA_100 => 'MA 100',
            self::MA_50 => 'MA 50',
            self::MA_20 => 'MA 20',
            self::HIGH_ONE_YEAR => '1Yr High',
            self::HIGH_ALL_TIME => 'All Time High',
            self::AWAY_FROM_HIGH_ONE_YEAR => 'Away from 1Yr High %',
            self::AWAY_FROM_HIGH_ALL_TIME => 'Away from ATH %',
            self::MEDIAN_VOLUME_ONE_YEAR => 'Median Volume (in ₹ crores)',
            self::CLOSE_ADJUSTED => 'Last Close',
            self::CIRCUITS_ONE_YEAR => '1Yr Circuits',
            self::CIRCUITS_NINE_MONTHS => '9M Circuits',
            self::CIRCUITS_SIX_MONTHS => '6M Circuits',
            self::CIRCUITS_THREE_MONTHS => '3M Circuits',
            self::CIRCUITS_ONE_MONTHS => '1M Circuits',
            self::CLOSE_RAW => 'Unadjusted Close',
        };
    }

    public function getSortOrder(): int
    {
        return match ($this) {
            self::CLOSE_ADJUSTED => 10,
            self::CLOSE_RAW => 11,
            self::MEDIAN_VOLUME_ONE_YEAR => 20,
            self::SERIES => 100,
            self::MARKETCAP => 200,
            self::PRICE_TO_EARNINGS => 300,
            self::ABSOLUTE_RETURN_ONE_YEAR => 400,
            self::ABSOLUTE_RETURN_NINE_MONTHS => 401,
            self::ABSOLUTE_RETURN_SIX_MONTHS => 402,
            self::ABSOLUTE_RETURN_THREE_MONTHS => 403,
            self::ABSOLUTE_RETURN_ONE_MONTHS => 404,
            self::SHARPE_RETURN_ONE_YEAR => 500,
            self::SHARPE_RETURN_NINE_MONTHS => 501,
            self::SHARPE_RETURN_SIX_MONTHS => 502,
            self::SHARPE_RETURN_THREE_MONTHS => 503,
            self::SHARPE_RETURN_ONE_MONTHS => 504,
            self::RSI_ONE_YEAR => 520,
            self::RSI_NINE_MONTHS => 521,
            self::RSI_SIX_MONTHS => 522,
            self::RSI_THREE_MONTHS => 523,
            self::RSI_ONE_MONTHS => 524,
            self::BETA => 600,
            self::VOLATILITY_ONE_YEAR => 550,
            self::MA_200 => 700,
            self::MA_100 => 701,
            self::MA_50 => 702,
            self::MA_20 => 703,
            self::HIGH_ONE_YEAR => 800,
            self::HIGH_ALL_TIME => 810,
            self::AWAY_FROM_HIGH_ONE_YEAR => 801,
            self::AWAY_FROM_HIGH_ALL_TIME => 811,
            self::CIRCUITS_ONE_YEAR => 900,
            self::CIRCUITS_NINE_MONTHS => 901,
            self::CIRCUITS_SIX_MONTHS => 902,
            self::CIRCUITS_THREE_MONTHS => 903,
            self::CIRCUITS_ONE_MONTHS => 904,
            default => 10000,
        };
    }
}
