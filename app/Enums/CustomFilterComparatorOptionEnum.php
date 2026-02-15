<?php

namespace App\Enums;

use App\Contracts\ResolveDisplayableValueListForEnum;
use App\Traits\ResolveDisplayableValueListForEnumTrait;

enum CustomFilterComparatorOptionEnum: string implements ResolveDisplayableValueListForEnum
{
    use ResolveDisplayableValueListForEnumTrait;

    case GreaterThanEqualTo = '>=';
    case LessThanEqualTo = '<=';
    case EqualTo = '=';
}
