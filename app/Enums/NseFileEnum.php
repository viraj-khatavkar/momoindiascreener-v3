<?php

namespace App\Enums;

use App\Contracts\ResolveDisplayableValueListForEnum;
use App\Traits\ResolveDisplayableValueListForEnumTrait;
use Illuminate\Support\Str;

enum NseFileEnum: string implements ResolveDisplayableValueListForEnum
{
    use ResolveDisplayableValueListForEnumTrait;

    case Bhavcopy = 'bhavcopy';
    case Fno = 'fno';
    case Index = 'index';
    case Etf = 'etf';
    case PriceToEarnings = 'price_to_earnings';
    case Marketcap = 'marketcap';
    case CorporateActions = 'corporate_actions';

    public static function resolveDisplayableValueList(): array
    {
        return collect(self::cases())->map(fn (self $file): array => [
            'id' => $file->value,
            'name' => $file === self::Fno ? 'F&O membership' : ucwords(Str::replace('_', ' ', $file->value)),
        ])->all();
    }
}
