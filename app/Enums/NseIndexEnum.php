<?php

namespace App\Enums;

use App\Contracts\ResolveDisplayableValueListForEnum;
use App\Traits\ResolveDisplayableValueListForEnumTrait;
use Illuminate\Support\Str;

enum NseIndexEnum: string implements ResolveDisplayableValueListForEnum
{
    use ResolveDisplayableValueListForEnumTrait;

    case NIFTY_50 = 'nifty_50';
    case NIFTY_NEXT_50 = 'nifty_next_50';
    case NIFTY_100 = 'nifty_100';
    case NIFTY_200 = 'nifty_200';
    case NIFTY_MIDCAP_100 = 'nifty_midcap_100';
    case NIFTY_MIDCAP_150 = 'nifty_midcap_150';
    case NIFTY_500 = 'nifty_500';
    case NIFTY_TOTAL_MARKET = 'nifty_total_market';
    case NIFTY_1000 = 'nifty_1000';
    case NIFTY_SMALLCAP_250 = 'nifty_smallcap_250';
    case NIFTY_MICROCAP_250 = 'nifty_microcap_250';
    case NIFTY_LARGEMIDCAP_250 = 'nifty_largemidcap_250';
    case NIFTY_MIDSMALLCAP_400 = 'nifty_midsmallcap_400';
    case NIFTY_ALLCAP = 'nifty_allcap';
    case NIFTY_FNO = 'nifty_fno';
    case NIFTY_ETF = 'etf';

    /** Special sessions excluded by the user because stock prices are unavailable. */
    public const FNO_IGNORED_DATES = ['2024-03-02'];

    public function slug(): string
    {
        return str_replace('_', '-', $this->value);
    }

    public function label(): string
    {
        return match ($this) {
            self::NIFTY_50 => 'Nifty 50',
            self::NIFTY_NEXT_50 => 'Nifty Next 50',
            self::NIFTY_100 => 'Nifty 100',
            self::NIFTY_200 => 'Nifty 200',
            self::NIFTY_MIDCAP_100 => 'Nifty Midcap 100',
            self::NIFTY_MIDCAP_150 => 'Nifty Midcap 150',
            self::NIFTY_500 => 'Nifty 500',
            self::NIFTY_TOTAL_MARKET => 'Nifty Total Market',
            self::NIFTY_1000 => 'Nifty 1000',
            self::NIFTY_SMALLCAP_250 => 'Nifty Smallcap 250',
            self::NIFTY_MICROCAP_250 => 'Nifty Microcap 250',
            self::NIFTY_LARGEMIDCAP_250 => 'Nifty LargeMidcap 250',
            self::NIFTY_MIDSMALLCAP_400 => 'Nifty MidSmallcap 400',
            self::NIFTY_ALLCAP => 'Nifty Allcap',
            self::NIFTY_FNO => 'NSE F&O Stocks',
            self::NIFTY_ETF => 'NSE ETFs',
        };
    }

    public static function getOptionsForFilters(): array
    {
        return collect(self::cases())
            ->reject(fn ($record) => $record->value == self::NIFTY_ALLCAP->value)
            ->reject(fn ($record) => $record->value == self::NIFTY_ETF->value)
            ->map(function ($record) {
                return [
                    'id' => $record->value,
                    'name' => $record === self::NIFTY_FNO ? 'NSE F&O Stocks' : ucfirst(Str::of($record->value)->replace('_', ' ')->upper()),
                ];
            })
            ->push(['id' => 'nifty_allcap', 'name' => 'All NSE Listed Stocks'])
            ->push(['id' => 'etf', 'name' => 'All NSE Listed ETFs'])
            ->toArray();
    }

    public function numberOfConstituents(): ?int
    {
        return match ($this) {
            self::NIFTY_50 => 50,
            self::NIFTY_NEXT_50 => 50,
            self::NIFTY_100 => 100,
            self::NIFTY_200 => 200,
            self::NIFTY_MIDCAP_100 => 100,
            self::NIFTY_MIDCAP_150 => 150,
            self::NIFTY_500 => 500,
            self::NIFTY_TOTAL_MARKET => 750,
            self::NIFTY_1000 => 1000,
            self::NIFTY_SMALLCAP_250 => 250,
            self::NIFTY_MICROCAP_250 => 250,
            self::NIFTY_LARGEMIDCAP_250 => 250,
            self::NIFTY_MIDSMALLCAP_400 => 400,
            self::NIFTY_ALLCAP => 2000,
            self::NIFTY_ETF => 220,
            self::NIFTY_FNO => null,
        };
    }

    public function isIndexFieldName(): string
    {
        return 'is_'.$this->value;
    }
}
