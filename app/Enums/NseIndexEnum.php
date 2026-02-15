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
    case NIFTY_500 = 'nifty_500';
    case NIFTY_SMALLCAP_250 = 'nifty_smallcap_250';
    case NIFTY_ALLCAP = 'nifty_allcap';
    case NIFTY_ETF = 'etf';

    public static function getOptionsForFilters(): array
    {
        return collect(self::cases())
            ->reject(fn ($record) => $record->value == self::NIFTY_ALLCAP->value)
            ->reject(fn ($record) => $record->value == self::NIFTY_ETF->value)
            ->map(function ($record) {
                return [
                    'id' => $record->value,
                    'name' => ucfirst(Str::of($record->value)->replace('_', ' ')->upper()),
                ];
            })
            ->push(['id' => 'nifty_allcap', 'name' => 'All NSE Listed Stocks'])
            ->push(['id' => 'etf', 'name' => 'All NSE Listed ETFs'])
            ->toArray();
    }

    public function numberOfConstituents(): int
    {
        return match ($this) {
            self::NIFTY_50 => 50,
            self::NIFTY_NEXT_50 => 50,
            self::NIFTY_100 => 100,
            self::NIFTY_200 => 200,
            self::NIFTY_MIDCAP_100 => 100,
            self::NIFTY_500 => 500,
            self::NIFTY_SMALLCAP_250 => 250,
            self::NIFTY_ALLCAP => 2000,
            self::NIFTY_ETF => 220,
        };
    }

    public function isIndexFieldName(): string
    {
        return 'is_'.$this->value;
    }
}
