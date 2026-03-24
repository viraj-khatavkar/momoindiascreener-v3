<?php

namespace App\Enums;

use App\Contracts\ResolveDisplayableValueListForEnum;
use App\Traits\ResolveDisplayableValueListForEnumTrait;

enum NseFileEnum: string implements ResolveDisplayableValueListForEnum
{
    use ResolveDisplayableValueListForEnumTrait;

    case Bhavcopy = 'bhavcopy';
    case Index = 'index';
    case Etf = 'etf';
    case PriceToEarnings = 'price_to_earnings';
    case Marketcap = 'marketcap';
    case CorporateActions = 'corporate_actions';
}
